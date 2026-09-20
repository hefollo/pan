<?php
/**
 * 程序更新检查
 *
 * 从 GitHub 上取仓库 main 分支的版本号和提交列表，跟本地装的版本比一比：
 * 后台首页「版本信息」里显示有没有新版本，「程序更新日志」页面列出最近改了什么。
 *
 * 为什么不沿用原版那种做法：
 * 原版后台首页有一段 JSONP 去 auth.cccyun.cc 拉版本检查，返回值本身就是 JavaScript，
 * 会在后台域下执行，结果还被 .html() 直接插进 DOM —— 等于把后台的完全控制权交给
 * 对方域名（及其服务器、DNS、传输链路），那段代码早就删掉了。
 * 这里全部走服务端：PHP 取 JSON，解析出需要的几个字段，输出时一律转义或走 text()，
 * 对方就算返回一段恶意 HTML，也只会原样显示成文字。
 */
if(!defined('SYSTEM_ROOT'))exit();

//要检查的仓库，格式是 用户名/仓库名。自己 fork 之后改这里
if(!defined('UPDATE_REPO'))define('UPDATE_REPO', 'hefollo/pan');
if(!defined('UPDATE_BRANCH'))define('UPDATE_BRANCH', 'main');
//两次真实请求之间至少隔多久（秒）。GitHub 未登录时每个 IP 每小时只有 60 次额度
define('UPDATE_CACHE_TTL', 1800);
//上一次查失败时的重试间隔：失败多半是服务器连不上 GitHub，隔短一点好恢复
define('UPDATE_FAIL_TTL', 300);
//手动点「重新检查」的最小间隔，避免有人按着刷把额度打光
define('UPDATE_FORCE_MIN', 60);
//一次取多少条提交。整份缓存要塞进 pre_config.v（TEXT，最大 65535 字节），别取太多
define('UPDATE_COMMIT_LIMIT', 20);

function update_repo_url(){
	return 'https://github.com/'.UPDATE_REPO;
}

function update_commits_url(){
	return 'https://github.com/'.UPDATE_REPO.'/commits/'.UPDATE_BRANCH.'/';
}

function update_commit_url($sha){
	return 'https://github.com/'.UPDATE_REPO.'/commit/'.$sha;
}

/**
 * 取一个 URL 的内容。成功返回响应体，失败返回 false 并把原因写进 $err。
 *
 * 没有复用 get_curl()：这里要拿到 HTTP 状态码才能区分「接口限流」和「真的连不上」，
 * 而且 GitHub 不带 User-Agent 会直接 403，超时也要比默认值更短一点。
 */
function update_http_get($url, &$err = null){
	$err = '';
	$ua = 'pan-update-check/'.VERSION;
	if(function_exists('curl_init')){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		//超时压得短一点：raw 和 api 两个域名要串着试，最坏情况是三次请求叠加
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
		curl_setopt($ch, CURLOPT_TIMEOUT, 8);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
		curl_setopt($ch, CURLOPT_USERAGENT, $ua);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28']);
		$body = curl_exec($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$cerr = curl_error($ch);
		curl_close($ch);
		if($body === false || $body === ''){
			$err = $cerr ? ('连接 GitHub 失败：'.$cerr) : '连接 GitHub 失败，没有取到内容';
			return false;
		}
		if($code == 403 || $code == 429){
			$err = 'GitHub 接口限流（HTTP '.$code.'）。未登录时每个 IP 每小时只有 60 次，过一会儿再试';
			return false;
		}
		if($code != 200){
			$err = 'GitHub 返回 HTTP '.$code;
			return false;
		}
		return $body;
	}
	if(ini_get('allow_url_fopen')){
		$ctx = stream_context_create(['http'=>[
			'method' => 'GET',
			'timeout' => 8,
			'header' => 'User-Agent: '.$ua."\r\n".'Accept: application/vnd.github+json'."\r\n",
		], 'ssl'=>['verify_peer'=>false, 'verify_peer_name'=>false]]);
		$body = @file_get_contents($url, false, $ctx);
		if($body === false){
			$err = '连接 GitHub 失败（file_get_contents）';
			return false;
		}
		return $body;
	}
	$err = '服务器既没有 curl 扩展，也关闭了 allow_url_fopen，无法访问 GitHub';
	return false;
}

function update_cache_read(){
	$raw = getSetting('update_cache');
	if(!$raw)return null;
	$data = json_decode($raw, true);
	return is_array($data) ? $data : null;
}

function update_cache_write($data){
	$json = json_encode($data, JSON_UNESCAPED_UNICODE);
	/*
	 * pre_config.v 是 TEXT，最大 65535 字节。MySQL 非严格模式下超长是**静默截断**，
	 * 截断后的 JSON 解析不出来，缓存就等于永远失效 —— 每打开一次后台都去请求 GitHub，
	 * 很快撞上每小时 60 次的限流。所以超了先丢正文，再不行就少留几条。
	 */
	if(strlen($json) > 48000 && !empty($data['commits'])){
		foreach($data['commits'] as $i => $c)$data['commits'][$i]['body'] = '';
		$json = json_encode($data, JSON_UNESCAPED_UNICODE);
	}
	if(strlen($json) > 48000 && !empty($data['commits'])){
		$data['commits'] = array_slice($data['commits'], 0, 10);
		$json = json_encode($data, JSON_UNESCAPED_UNICODE);
	}
	saveSetting('update_cache', $json);
}

function update_cut($text, $len){
	$text = trim((string)$text);
	//截断了就补个省略号，否则页面上看着像提交说明写了一半
	if(function_exists('mb_substr')){
		if(mb_strlen($text, 'UTF-8') <= $len)return $text;
		return rtrim(mb_substr($text, 0, $len, 'UTF-8')).'……';
	}
	if(strlen($text) <= $len * 3)return $text;
	return rtrim(substr($text, 0, $len * 3)).'……';
}

/**
 * 取仓库里 includes/common.php 的内容，用来读版本号。
 *
 * 先走 raw.githubusercontent.com：它不吃 API 的每小时 60 次额度。
 * 实测遇到过 raw 这个域名单独超时、而 api.github.com 正常的情况，
 * 所以 raw 失败时再用 contents 接口兜一次底，两个域名只要通一个就行。
 */
function update_fetch_common(&$err = null){
	$raw = update_http_get('https://raw.githubusercontent.com/'.UPDATE_REPO.'/'.UPDATE_BRANCH.'/includes/common.php', $err);
	if($raw !== false)return $raw;
	$body = update_http_get('https://api.github.com/repos/'.UPDATE_REPO.'/contents/includes/common.php?ref='.UPDATE_BRANCH, $err2);
	if($body === false)return false;
	$j = json_decode($body, true);
	if(isset($j['content']) && isset($j['encoding']) && $j['encoding'] === 'base64'){
		$decoded = base64_decode(str_replace(["\n", "\r"], '', $j['content']));
		if($decoded !== false && $decoded !== ''){
			$err = '';
			return $decoded;
		}
	}
	return false;
}

/**
 * 真去 GitHub 查一次，结果（含失败原因）写进缓存。
 * $force=true 表示用户手动点了「重新检查」，仍然受 UPDATE_FORCE_MIN 保护。
 */
function update_check($force = false){
	$cache = update_cache_read();
	$now = time();
	$age = ($cache && isset($cache['time'])) ? ($now - intval($cache['time'])) : null;
	if($age !== null && $age >= 0){
		//查全了的缓存管半小时；失败或只查到一半的只管 5 分钟，网络恢复后能早点自己好
		$ttl = (!empty($cache['ok']) && empty($cache['partial'])) ? UPDATE_CACHE_TTL : UPDATE_FAIL_TTL;
		if($force && $age < UPDATE_FORCE_MIN)return $cache;
		if(!$force && $age < $ttl)return $cache;
	}

	$data = ['time'=>$now, 'ok'=>false, 'partial'=>false, 'error'=>'', 'version_error'=>'',
		'version'=>'', 'db_version'=>'', 'commits'=>[]];

	//① 版本号：直接读仓库里的 includes/common.php，不依赖 release 或 tag
	$raw = update_fetch_common($err1);
	if($raw !== false){
		if(preg_match('/define\(\s*[\'"]VERSION[\'"]\s*,\s*[\'"]([0-9]{1,10})[\'"]/', $raw, $m))$data['version'] = $m[1];
		if(preg_match('/define\(\s*[\'"]DB_VERSION[\'"]\s*,\s*[\'"]([0-9]{1,10})[\'"]/', $raw, $m))$data['db_version'] = $m[1];
	}

	//② 提交列表
	$json = update_http_get('https://api.github.com/repos/'.UPDATE_REPO.'/commits?sha='.UPDATE_BRANCH.'&per_page='.UPDATE_COMMIT_LIMIT, $err2);
	if($json !== false){
		$list = json_decode($json, true);
		if(is_array($list)){
			foreach($list as $c){
				//sha 只认 40 位十六进制：后面要靠它拼 GitHub 链接，不能让接口返回的内容进 URL
				if(!is_array($c) || empty($c['sha']) || !preg_match('/^[0-9a-f]{40}$/', $c['sha']))continue;
				$msg = isset($c['commit']['message']) ? str_replace("\r\n", "\n", (string)$c['commit']['message']) : '';
				$lines = explode("\n", $msg);
				$title = array_shift($lines);
				$data['commits'][] = [
					'sha'    => $c['sha'],
					'title'  => update_cut($title, 200),
					'body'   => update_cut(implode("\n", $lines), 240),
					'author' => update_cut(isset($c['commit']['author']['name']) ? $c['commit']['author']['name'] : '', 40),
					'date'   => isset($c['commit']['author']['date']) ? strtotime($c['commit']['author']['date']) : 0,
				];
			}
		}
	}

	$data['version_error'] = $err1 ? $err1 : '';
	if($data['commits'] || $data['version'] !== ''){
		$data['ok'] = true;
		//只查到一半也算能用：提交列表在、版本号没读到时，页面照样列出改了什么，
		//只是不下「有没有新版本」的结论，并且很快就会自己再试一次
		$data['partial'] = ($data['version'] === '' || !$data['commits']);
	}else{
		//两个请求都没成，报先失败的那个原因就够了
		$data['error'] = $err1 ? $err1 : ($err2 ? $err2 : '没有取到任何内容');
	}
	update_cache_write($data);
	return $data;
}

/**
 * 给页面用的完整状态：本地版本、仓库版本、结论文案。
 *
 * state 的取值：
 *   new      仓库版本号比本地高，有新版本
 *   latest   版本号一致
 *   ahead    本地比仓库还新（一般是本地改了还没推上去）
 *   unknown  提交列表取到了，版本号没取到，比不出结论
 *   error    什么都没查到
 */
function update_status($force = false){
	$d = update_check($force);
	$local_v  = intval(VERSION);
	$local_db = intval(DB_VERSION);
	$remote_v  = isset($d['version']) ? intval($d['version']) : 0;
	$remote_db = isset($d['db_version']) ? intval($d['db_version']) : 0;

	$d['local_version']  = $local_v;
	$d['local_db']       = $local_db;
	$d['remote_version'] = $remote_v;
	$d['remote_db']      = $remote_db;
	$d['checked_text']   = !empty($d['time']) ? date('Y-m-d H:i', intval($d['time'])) : '';
	$d['need_db_update'] = ($remote_db > 0 && $remote_db > $local_db);
	$d['commit_count']   = isset($d['commits']) ? count($d['commits']) : 0;

	if(empty($d['ok'])){
		$d['state'] = 'error';
		$d['text']  = '检查失败';
		if(empty($d['error']))$d['error'] = '没有取到任何内容';
	}elseif($remote_v <= 0){
		//提交列表取到了但版本号没读到：仍然把提交列出来，只是不下结论
		$d['state'] = 'unknown';
		$d['text']  = '版本号没取到';
		if(empty($d['error']))$d['error'] = !empty($d['version_error']) ? $d['version_error'] : '仓库的 includes/common.php 里没读到版本号';
	}elseif($remote_v > $local_v || $d['need_db_update']){
		$d['state'] = 'new';
		$d['text']  = '发现新版本 '.$remote_v;
	}elseif($remote_v < $local_v){
		$d['state'] = 'ahead';
		$d['text']  = '本地版本比仓库新';
	}else{
		$d['state'] = 'latest';
		$d['text']  = '已是最新版本';
	}
	return $d;
}

/**
 * 「3 小时前」这种相对时间，列表里比绝对时间好读
 */
function update_time_ago($ts){
	$ts = intval($ts);
	if($ts <= 0)return '';
	$diff = time() - $ts;
	if($diff < 0)return date('Y-m-d H:i', $ts);
	if($diff < 60)return '刚刚';
	if($diff < 3600)return floor($diff / 60).' 分钟前';
	if($diff < 86400)return floor($diff / 3600).' 小时前';
	if($diff < 2592000)return floor($diff / 86400).' 天前';
	return date('Y-m-d', $ts);
}
