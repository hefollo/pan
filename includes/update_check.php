<?php
/**
 * 程序更新检查
 *
 * 从更新源上取仓库 main 分支的版本号和提交列表，跟本地装的版本比一比：
 * 后台首页「版本信息」里显示有没有新版本，「程序更新日志」页面列出最近改了什么。
 *
 * 更新源有两个，都写死在本文件里，站长在「程序更新日志」页用下拉框选（DEC-20261005-002）：
 *   github  GitHub 上的仓库（默认）；
 *   gitea   一个自动同步该仓库的 Gitea 镜像，给连不上 GitHub 的国内服务器用。
 * 版本号、提交列表、在线更新下载的 zip 都取自选中的那一个源，不混着用。
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
/*
 * 备用更新源：一个自动同步上面那个仓库的 Gitea 镜像。
 * 只填站点地址（https 开头，结尾不带斜杠），仓库名和分支沿用上面两个常量。
 * 自己 fork 的话改成自己的 Gitea；没有就设成空串，页面上不再出现「更新源」下拉框。
 * 更新源只能是这里写死的这几个：要下载回来执行的代码，不能让人在页面上自填地址。
 */
if(!defined('UPDATE_MIRROR_BASE'))define('UPDATE_MIRROR_BASE', 'https://gitea.hefollo.com');
//两次真实请求之间至少隔多久（秒）。GitHub 未登录时每个 IP 每小时只有 60 次额度
define('UPDATE_CACHE_TTL', 1800);
//上一次查失败时的重试间隔：失败多半是服务器连不上 GitHub，隔短一点好恢复
define('UPDATE_FAIL_TTL', 300);
//手动点「重新检查」的最小间隔，避免有人按着刷把额度打光
define('UPDATE_FORCE_MIN', 60);
//一次取多少条提交。整份缓存要塞进 pre_config.v（TEXT，最大 65535 字节），别取太多
define('UPDATE_COMMIT_LIMIT', 20);

/**
 * 可选的更新源：键 => [name 提示里用的短名, label 下拉框里的文字, host 域名]。
 * 备用源的地址不合规（不是 https、带了奇怪的字符）就当它不存在。
 */
function update_sources(){
	static $list = null;
	if($list !== null)return $list;
	$list = ['github' => ['name'=>'GitHub', 'label'=>'GitHub', 'host'=>'github.com']];
	$base = rtrim(trim((string)UPDATE_MIRROR_BASE), '/');
	if(preg_match('#^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[A-Za-z0-9._~-]+)*$#', $base)){
		$host = (string)parse_url($base, PHP_URL_HOST);
		$list['gitea'] = ['name'=>'备用源', 'label'=>'备用源（'.$host.'）', 'host'=>$host, 'base'=>$base];
	}
	return $list;
}

/**
 * 站长当前选的更新源，存在 pre_config.update_source。
 * 没选过、或者存的值不在清单里（比如备用源后来被关掉了），一律按 GitHub。
 */
function update_source(){
	global $conf;
	$src = isset($conf['update_source']) ? (string)$conf['update_source'] : '';
	$list = update_sources();
	return isset($list[$src]) ? $src : 'github';
}

function update_source_name($src = null){
	$list = update_sources();
	if($src === null || !isset($list[$src]))$src = update_source();
	return $list[$src]['name'];
}

function update_mirror_base(){
	$list = update_sources();
	return isset($list['gitea']) ? $list['gitea']['base'] : '';
}

function update_repo_url(){
	if(update_source() === 'gitea')return update_mirror_base().'/'.UPDATE_REPO;
	return 'https://github.com/'.UPDATE_REPO;
}

function update_commits_url(){
	if(update_source() === 'gitea')return update_mirror_base().'/'.UPDATE_REPO.'/commits/branch/'.UPDATE_BRANCH;
	return 'https://github.com/'.UPDATE_REPO.'/commits/'.UPDATE_BRANCH.'/';
}

function update_commit_url($sha){
	if(update_source() === 'gitea')return update_mirror_base().'/'.UPDATE_REPO.'/commit/'.$sha;
	return 'https://github.com/'.UPDATE_REPO.'/commit/'.$sha;
}

/**
 * 当前更新源上要访问的地址：
 *   common   仓库里 includes/common.php 的原始内容（读版本号用），按顺序试
 *   contents 同一个文件的 contents 接口（返回 base64），上面都不通时兜底
 *   commits  提交列表接口
 * Gitea 的接口是照着 GitHub 做的，返回的字段形状一样，所以解析的代码两边共用。
 */
function update_api_urls(){
	if(update_source() === 'gitea'){
		$base = update_mirror_base();
		$api = $base.'/api/v1/repos/'.UPDATE_REPO;
		return [
			'common'   => $base.'/'.UPDATE_REPO.'/raw/branch/'.UPDATE_BRANCH.'/includes/common.php',
			'contents' => $api.'/contents/includes/common.php?ref='.UPDATE_BRANCH,
			//后三个参数让 Gitea 不去算每个提交的改动统计和签名，列表回得快也小得多
			'commits'  => $api.'/commits?sha='.UPDATE_BRANCH.'&limit='.UPDATE_COMMIT_LIMIT.'&stat=false&verification=false&files=false',
		];
	}
	return [
		'common'   => 'https://raw.githubusercontent.com/'.UPDATE_REPO.'/'.UPDATE_BRANCH.'/includes/common.php',
		'contents' => 'https://api.github.com/repos/'.UPDATE_REPO.'/contents/includes/common.php?ref='.UPDATE_BRANCH,
		'commits'  => 'https://api.github.com/repos/'.UPDATE_REPO.'/commits?sha='.UPDATE_BRANCH.'&per_page='.UPDATE_COMMIT_LIMIT,
	];
}

/**
 * 在线更新下载某个提交的 zip 用的地址，按顺序试（online_update.php 的 oupd_download 用）。
 * 两个源打出来的包只有最外层目录名不同，里面的文件逐个一致。
 */
function update_zip_urls($sha){
	if(update_source() === 'gitea'){
		$base = update_mirror_base();
		return [
			$base.'/'.UPDATE_REPO.'/archive/'.$sha.'.zip',
			$base.'/api/v1/repos/'.UPDATE_REPO.'/archive/'.$sha.'.zip',
		];
	}
	return [
		'https://codeload.github.com/'.UPDATE_REPO.'/zip/'.$sha,
		'https://api.github.com/repos/'.UPDATE_REPO.'/zipball/'.$sha,
	];
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
	$is_github = update_source() === 'github';
	//嵌进中文提示里用：英文名两边留空格，中文名不留
	$name = $is_github ? ' GitHub ' : update_source_name();
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
		curl_setopt($ch, CURLOPT_HTTPHEADER, $is_github ? ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'] : ['Accept: application/json, text/plain, */*']);
		$body = curl_exec($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$cerr = curl_error($ch);
		curl_close($ch);
		if($body === false || $body === ''){
			$err = $cerr ? ('连接'.$name.'失败：'.$cerr) : '连接'.$name.'失败，没有取到内容';
			return false;
		}
		if($code == 403 || $code == 429){
			$err = $is_github
				? 'GitHub 接口限流（HTTP '.$code.'）。未登录时每个 IP 每小时只有 60 次，过一会儿再试'
				: $name.'拒绝了请求（HTTP '.$code.'），可能是被限流或被防火墙拦下，过一会儿再试';
			return false;
		}
		if($code != 200){
			$err = ltrim($name).'返回 HTTP '.$code;
			return false;
		}
		return $body;
	}
	if(ini_get('allow_url_fopen')){
		$ctx = stream_context_create(['http'=>[
			'method' => 'GET',
			'timeout' => 8,
			'header' => 'User-Agent: '.$ua."\r\n".'Accept: '.($is_github ? 'application/vnd.github+json' : 'application/json, text/plain, */*')."\r\n",
		], 'ssl'=>['verify_peer'=>false, 'verify_peer_name'=>false]]);
		$body = @file_get_contents($url, false, $ctx);
		if($body === false){
			$err = '连接'.$name.'失败（file_get_contents）';
			return false;
		}
		return $body;
	}
	$err = '服务器既没有 curl 扩展，也关闭了 allow_url_fopen，无法访问'.rtrim($name);
	return false;
}

/**
 * 两个源各存各的缓存：GitHub 沿用原来的 update_cache，备用源是 update_cache_gitea。
 * 这样来回切换更新源时，缓存期内不会重新去请求，也不会拿这个源的提交列表去配那个源的包。
 * 这些行都是几十 KB，getAllSetting() 里按前缀跳过，不进 $conf。
 */
function update_cache_key($src = null){
	if($src === null)$src = update_source();
	return $src === 'github' ? 'update_cache' : 'update_cache_'.$src;
}

function update_cache_read(){
	$raw = getSetting(update_cache_key());
	if(!$raw)return null;
	$data = json_decode($raw, true);
	return is_array($data) ? $data : null;
}

/**
 * 清掉所有源的缓存。在线更新、还原之后站点装的提交变了，下次打开页面要重新查。
 */
function update_cache_clear(){
	foreach(array_keys(update_sources()) as $src)saveSetting(update_cache_key($src), '');
}

function update_cache_write($data){
	$json = json_encode($data, JSON_UNESCAPED_UNICODE);
	/*
	 * pre_config.v 是 TEXT，最大 65535 字节。MySQL 非严格模式下超长是**静默截断**，
	 * 截断后的 JSON 解析不出来，缓存就等于永远失效 —— 每打开一次后台都去请求更新源，
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
	saveSetting(update_cache_key(), $json);
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
 * GitHub 先走 raw.githubusercontent.com：它不吃 API 的每小时 60 次额度。
 * 实测遇到过 raw 这个域名单独超时、而 api.github.com 正常的情况，
 * 所以 raw 失败时再用 contents 接口兜一次底，两个域名只要通一个就行。
 * 备用源照同样的顺序走（原始文件 → contents 接口），只是都在同一个域名上。
 */
function update_fetch_common(&$err = null){
	$urls = update_api_urls();
	$raw = update_http_get($urls['common'], $err);
	if($raw !== false)return $raw;
	$body = update_http_get($urls['contents'], $err2);
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
 * 真去当前更新源查一次，结果（含失败原因）写进这个源自己的缓存。
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

	$data = ['time'=>$now, 'source'=>update_source(), 'ok'=>false, 'partial'=>false, 'error'=>'', 'version_error'=>'',
		'version'=>'', 'db_version'=>'', 'commits'=>[]];
	$urls = update_api_urls();

	//① 版本号：直接读仓库里的 includes/common.php，不依赖 release 或 tag
	$raw = update_fetch_common($err1);
	if($raw !== false){
		if(preg_match('/define\(\s*[\'"]VERSION[\'"]\s*,\s*[\'"]([0-9]{1,10})[\'"]/', $raw, $m))$data['version'] = $m[1];
		if(preg_match('/define\(\s*[\'"]DB_VERSION[\'"]\s*,\s*[\'"]([0-9]{1,10})[\'"]/', $raw, $m))$data['db_version'] = $m[1];
	}

	//② 提交列表
	$json = update_http_get($urls['commits'], $err2);
	if($json !== false){
		$list = json_decode($json, true);
		if(is_array($list)){
			//有的接口不认条数参数会多给，这里自己再截一次，免得缓存超长
			$list = array_slice($list, 0, UPDATE_COMMIT_LIMIT);
			foreach($list as $c){
				//sha 只认 40 位十六进制：后面要靠它拼提交链接和下载地址，不能让接口返回的内容进 URL
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
 * 当前更新源是不是比站点还旧：站点装的那个提交不在它的提交列表里，
 * 而且它最新的提交比站点装的那个提交还早。
 *
 * 备用源是定时从 GitHub 同步的，会晚几个小时，这本身没关系；
 * 但这时拿它「更新」等于把站点改回旧代码，所以页面要提示、在线更新要拒绝。
 * 站点落后太多（装的提交已经掉出最近这些条）时，源的最新提交一定比站点的新，不会误判。
 */
function update_source_behind($commits){
	$installed = (string)getSetting('update_installed_sha');
	if(!preg_match('/^[0-9a-f]{40}$/', $installed) || empty($commits) || !is_array($commits))return false;
	foreach($commits as $c){
		if(isset($c['sha']) && $c['sha'] === $installed)return false;
	}
	$newest = isset($commits[0]['date']) ? intval($commits[0]['date']) : 0;
	if($newest <= 0)return false;
	//在线更新时记下的那个提交的提交时间（online_update.php）
	$mine = intval(getSetting('update_installed_date'));
	if($mine <= 0){
		//这个记录是后加的，之前就在线更新过的站点没有，退一步用那次更新的时间：它一定晚于装上的那个提交
		$last = json_decode((string)getSetting('update_last'), true);
		if(is_array($last) && isset($last['sha']) && $last['sha'] === $installed && !empty($last['time']))$mine = intval($last['time']);
	}
	return $mine > 0 && $mine > $newest;
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
	$d['source']         = update_source();
	$d['source_name']    = update_source_name();

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

	/*
	 * 用在线更新装过的站点记着自己装的是哪个提交（update_installed_sha，见 online_update.php），
	 * 这比 VERSION 准：只改 PHP 的提交不提 VERSION，光看版本号会一直显示「已是最新」。
	 * 能在提交列表里找到这个提交时，就按「落后几个提交」下结论；找不到（太旧或手工传过包）仍按版本号。
	 * 「本地比仓库新」不在这里改判：那说明站点上有仓库里没有的改动，更要提醒。
	 */
	$d['installed_sha'] = '';
	$d['behind'] = -1;
	$installed = (string)getSetting('update_installed_sha');
	if(preg_match('/^[0-9a-f]{40}$/', $installed)){
		$d['installed_sha'] = $installed;
		if(in_array($d['state'], ['latest', 'new', 'unknown'], true) && !empty($d['commits'])){
			foreach($d['commits'] as $i => $c){
				if(isset($c['sha']) && $c['sha'] === $installed){
					$d['behind'] = $i;
					break;
				}
			}
			if($d['behind'] === 0 && !$d['need_db_update']){
				$d['state'] = 'latest';
				$d['text']  = '已是最新提交';
			}elseif($d['behind'] > 0){
				$d['state'] = 'new';
				$d['text']  = '落后 '.$d['behind'].' 个提交';
			}
		}
	}

	//更新源比站点还旧（备用源还没同步过来）：不是有新版本，也不是已经最新，单独说清楚
	$d['source_behind'] = ($d['state'] !== 'error' && update_source_behind(isset($d['commits']) ? $d['commits'] : []));
	if($d['source_behind']){
		$d['state'] = 'ahead';
		$d['text']  = '更新源还没同步到当前提交';
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
