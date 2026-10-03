<?php
/**
 * 后台在线更新（DEC-20261003-001）
 *
 * 做法：从 GitHub 下载仓库某一个提交的 zip，校验后按排除清单覆盖到站点目录。
 * 服务器上不需要装 git，也不需要 .git 目录；仓库地址沿用 update_check.php 里写死的
 * UPDATE_REPO / UPDATE_BRANCH，页面上不能自填下载地址。
 *
 * 流程分两步，中间给站长看一眼再确认：
 *   ① 预检 oupd_prepare()：下载 → 检查 zip → 和站点现有文件逐个比对，列出会改哪些文件。
 *      下载好的 zip 留在 data/update_backup/ 里，半小时内正式更新直接复用，不再下第二遍。
 *   ② 更新 oupd_apply()：先把要被覆盖的旧文件打成备份 zip，再逐个写入；
 *      中途任何一个文件写失败，就把本次已经写过的文件全部还原，不留半新半旧的站。
 * 备份保留最近几份，后台可以一键还原（oupd_restore）。
 *
 * 和全量包保持同一套口径（DEC-20260903-001）：
 *   - config.php、README、tools/、tests/ 这些不进站点的东西一律跳过；
 *   - 仓库里删掉的文件，在线更新也不会去删（全量包覆盖同样不会删）；
 *   - includes/vendor/ 不在仓库里，在线更新不碰它，composer.json 变了会提示手动补。
 */
if(!defined('SYSTEM_ROOT'))exit();
include_once SYSTEM_ROOT.'update_check.php';

//下载体积、条目数、解压后总大小的上限。仓库连截图带工具也就十几 MB，留足余量
define('OUPD_MAX_ZIP', 64 * 1024 * 1024);
define('OUPD_MAX_ENTRIES', 5000);
define('OUPD_MAX_UNZIPPED', 160 * 1024 * 1024);
//备份保留几份，多出来的从旧到新删
define('OUPD_KEEP_BACKUPS', 5);
//预检下载的 zip 多久内可以直接拿来更新
define('OUPD_PENDING_TTL', 1800);

/**
 * 在线更新要用到的运行条件，返回不满足的项（空数组 = 都满足）。
 */
function oupd_requirements(){
	$errs = [];
	if(!class_exists('ZipArchive'))$errs[] = '服务器没有 PHP zip 扩展（ZipArchive），解不开更新包';
	if(!function_exists('curl_init'))$errs[] = '服务器没有 PHP curl 扩展，下载不了更新包';
	if(!is_writable(ROOT))$errs[] = '网站根目录不可写，PHP 没法覆盖文件';
	if(oupd_data_dir() === '')$errs[] = '无法创建 data/update_backup/ 目录，没地方放下载包和备份';
	return $errs;
}

/**
 * 下载包和备份放这里。目录里带 .htaccess 和空 index.html，
 * Nginx 不认 .htaccess，所以文件名里另外带随机串，别人猜不到地址。
 */
function oupd_data_dir(){
	$dir = ROOT.'data/update_backup/';
	if(!is_dir($dir)){
		if(!@mkdir($dir, 0755, true) && !is_dir($dir))return '';
	}
	if(!is_file($dir.'.htaccess'))@file_put_contents($dir.'.htaccess', "Order Deny,Allow\nDeny from all\n");
	if(!is_file($dir.'index.html'))@file_put_contents($dir.'index.html', '');
	return is_writable($dir) ? $dir : '';
}

/**
 * 更新/还原请求用的一次会话令牌：后台 ajax 只校验 Referer，
 * 覆盖站点文件这种操作再多要一层，防止被别的页面借管理员的登录态发起。
 */
function oupd_token(){
	if(empty($_SESSION['oupd_token']))$_SESSION['oupd_token'] = bin2hex(random_bytes(16));
	return $_SESSION['oupd_token'];
}

function oupd_check_token($token){
	return is_string($token) && $token !== '' && !empty($_SESSION['oupd_token']) && hash_equals($_SESSION['oupd_token'], $token);
}

/**
 * 站点实际的后台目录名。仓库里叫 admin/，站长改了名的话要映射过去，
 * 否则更新会在站点里凭空多出一个新的 admin/ 目录，真正在用的后台反而没更新。
 */
function oupd_admin_dir(){
	global $conf;
	$dir = isset($conf['admin_dir']) ? trim(str_replace('\\', '/', (string)$conf['admin_dir']), '/') : '';
	if($dir === '' || !preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $dir) || strpos($dir, '..') !== false || !is_dir(ROOT.$dir))return 'admin';
	return $dir;
}

/**
 * 仓库里的这个路径为什么不写进站点；返回空串表示要写。
 * 前半段是全量包的排除清单（DEC-20260903-001），后半段是站点运行时自己生成、永远不能被覆盖的东西。
 */
function oupd_skip_reason($rel){
	$top = strpos($rel, '/') === false ? $rel : substr($rel, 0, strpos($rel, '/'));
	$is_top_file = strpos($rel, '/') === false;
	if($is_top_file && in_array($rel, ['config.php', 'README.md', 'LICENSE', 'AGENTS.md', 'CLAUDE.md', 'pan.code-workspace', '.gitignore', '.gitattributes'], true))return '不随站点发布';
	if($is_top_file && strpos($rel, '发布介绍') === 0)return '发布宣传材料';
	if(!$is_top_file && in_array($top, ['.git', '.github', '.agents', '.codex', '.claude', 'img', 'tests', 'tools', '发布素材', 'data', 'tmp', 'temp', 'file', 'log', 'logs', 'cache', 'runtime', 'uploads', 'upload', 'storage'], true))return '不随站点发布';
	if(strtolower(substr($rel, -4)) === '.zip')return '压缩包';
	if($rel === 'install/install.lock')return '安装锁';
	foreach(['includes/vendor/', 'assets/css/custom/', 'includes/sponsor/images/'] as $p){
		if(strpos($rel, $p) === 0)return '站点运行时文件';
	}
	if($rel === 'includes/log.txt')return '站点运行时文件';
	return '';
}

/**
 * zip 条目名 → 仓库内相对路径。GitHub 打的包外面套一层「仓库名-提交号/」，这里去掉。
 * 任何可疑的名字（绝对路径、..、反斜杠、盘符、空段）都返回 false，整个包作废。
 */
function oupd_entry_rel($name, &$root){
	if(!is_string($name) || $name === '' || strpos($name, "\0") !== false || strpos($name, '\\') !== false || strpos($name, ':') !== false)return false;
	if($name[0] === '/')return false;
	$parts = explode('/', $name);
	$first = array_shift($parts);
	if($first === '' || $first === '.' || $first === '..')return false;
	if($root === null)$root = $first;
	elseif($root !== $first)return false;
	//目录条目以 / 结尾，拆出来最后一段是空串
	if(end($parts) === '')array_pop($parts);
	foreach($parts as $p){
		if($p === '' || $p === '.' || $p === '..')return false;
	}
	return implode('/', $parts);
}

/**
 * 下载 $sha 这个提交的 zip 到 $file。先 codeload，不通再走 api.github.com 的 zipball。
 */
function oupd_download($sha, $file, &$err = null){
	$err = '';
	$urls = [
		'https://codeload.github.com/'.UPDATE_REPO.'/zip/'.$sha,
		'https://api.github.com/repos/'.UPDATE_REPO.'/zipball/'.$sha,
	];
	$errs = [];
	foreach($urls as $url){
		$fp = @fopen($file, 'wb');
		if(!$fp){
			$err = '没法写入下载文件，检查 data/update_backup/ 是否可写';
			return false;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 180);
		//要拿来执行的代码，证书校验必须开着；主机缺 CA 证书时宁可失败也不裸奔
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_USERAGENT, 'pan-online-update/'.VERSION);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		//边下边看体积，超过上限就中止，别让一个异常的响应把磁盘写满
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($h, $dl_total, $dl_now){
			return $dl_now > OUPD_MAX_ZIP ? 1 : 0;
		});
		$ok = curl_exec($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$cerr = curl_error($ch);
		$cno = curl_errno($ch);
		curl_close($ch);
		fclose($fp);
		clearstatcache(true, $file);
		if($ok && $code == 200 && filesize($file) > 0)return true;
		$host = parse_url($url, PHP_URL_HOST);
		//60 = 证书校验失败，77 = 读不到 CA 证书文件
		if($cno == 60 || $cno == 77)$ca_problem = true;
		if($cerr !== '')$errs[] = $host.'：'.$cerr;
		elseif($code == 403 || $code == 429)$errs[] = $host.'：GitHub 限流（HTTP '.$code.'），过一会儿再试';
		else $errs[] = $host.'：HTTP '.$code;
	}
	@unlink($file);
	$err = '下载更新包失败（'.implode('；', $errs).'）';
	if(!empty($ca_problem))$err .= '。看起来是服务器缺少 CA 根证书（Windows 主机常见）：在 php.ini 里把 curl.cainfo 指向一份 cacert.pem 后重试';
	return false;
}

/**
 * 打开并检查下载好的 zip，成功返回包信息，失败返回 false 并写 $err。
 * 返回 ['zip'=>ZipArchive, 'files'=>[仓库相对路径=>条目序号], 'version', 'db_version']
 */
function oupd_inspect($file, &$err = null){
	$err = '';
	$zip = new ZipArchive();
	if($zip->open($file) !== true){
		$err = '更新包不是有效的 zip，可能下载不完整';
		return false;
	}
	if($zip->numFiles <= 0 || $zip->numFiles > OUPD_MAX_ENTRIES){
		$err = '更新包条目数异常（'.$zip->numFiles.'）';
		$zip->close();
		return false;
	}
	$root = null;
	$files = [];
	$total = 0;
	for($i = 0; $i < $zip->numFiles; $i++){
		$st = $zip->statIndex($i);
		if(!$st){
			$zip->close();
			$err = '更新包第 '.$i.' 个条目读不出来';
			return false;
		}
		$rel = oupd_entry_rel($st['name'], $root);
		if($rel === false){
			$zip->close();
			$err = '更新包里有不安全的路径：'.mb_substr($st['name'], 0, 120);
			return false;
		}
		//符号链接一律拒收：解出来指向站外的文件，覆盖时就成了往站外写
		if(method_exists($zip, 'getExternalAttributesIndex') && $zip->getExternalAttributesIndex($i, $opsys, $attr)){
			//3 = ZipArchive::OPSYS_UNIX，高 16 位是 Unix 文件类型和权限
			if($opsys == 3 && (($attr >> 16) & 0170000) == 0120000){
				$zip->close();
				$err = '更新包里有符号链接，拒绝更新：'.$rel;
				return false;
			}
		}
		if($rel === '' || substr($st['name'], -1) === '/')continue;
		$total += $st['size'];
		if($total > OUPD_MAX_UNZIPPED){
			$zip->close();
			$err = '更新包解压后体积超过上限';
			return false;
		}
		$files[$rel] = $i;
	}
	if(!isset($files['includes/common.php'])){
		$zip->close();
		$err = '更新包里没有 includes/common.php，不像是本程序的仓库';
		return false;
	}
	$common = $zip->getFromIndex($files['includes/common.php']);
	$version = $db_version = '';
	if(preg_match('/define\(\s*[\'"]VERSION[\'"]\s*,\s*[\'"]([0-9]{1,10})[\'"]/', $common, $m))$version = $m[1];
	if(preg_match('/define\(\s*[\'"]DB_VERSION[\'"]\s*,\s*[\'"]([0-9]{1,10})[\'"]/', $common, $m))$db_version = $m[1];
	if($version === '' || $db_version === ''){
		$zip->close();
		$err = '更新包的 includes/common.php 里读不到版本号';
		return false;
	}
	return ['zip'=>$zip, 'files'=>$files, 'version'=>$version, 'db_version'=>$db_version];
}

/**
 * 仓库相对路径 → 站点相对路径（只有后台目录需要换名字）
 */
function oupd_target_rel($rel, $admin_dir){
	if($admin_dir !== 'admin' && strpos($rel, 'admin/') === 0)return $admin_dir.'/'.substr($rel, 6);
	return $rel;
}

/**
 * 往上找最近一个已经存在的目录，看它可不可写（新建文件、新建目录都要靠它）
 */
function oupd_parent_writable($path){
	$dir = dirname($path);
	while(!is_dir($dir)){
		$up = dirname($dir);
		if($up === $dir)return false;
		$dir = $up;
	}
	return is_writable($dir);
}

/**
 * 和站点现有文件逐个比对，算出要写哪些文件。
 * 先比大小，大小一样再比 CRC32（zip 里本来就存着），省得把每个文件都读一遍。
 */
function oupd_plan($pkg){
	$zip = $pkg['zip'];
	$admin_dir = oupd_admin_dir();
	$plan = ['changed'=>[], 'added'=>[], 'unchanged'=>0, 'skipped'=>[], 'unwritable'=>[], 'admin_dir'=>$admin_dir];
	foreach($pkg['files'] as $rel => $idx){
		$why = oupd_skip_reason($rel);
		if($why !== ''){
			$plan['skipped'][] = $rel;
			continue;
		}
		$target = oupd_target_rel($rel, $admin_dir);
		$path = ROOT.$target;
		$st = $zip->statIndex($idx);
		if(is_file($path)){
			if(filesize($path) == $st['size'] && hash_file('crc32b', $path) === sprintf('%08x', $st['crc'] & 0xffffffff)){
				$plan['unchanged']++;
				continue;
			}
			//写法是同目录临时文件再改名，要求的是目录可写
			if(!is_writable(dirname($path)))$plan['unwritable'][] = $target;
			$plan['changed'][$target] = $idx;
		}elseif(is_dir($path)){
			//站点里同名的是个目录，没法用文件覆盖
			$plan['unwritable'][] = $target;
		}else{
			if(!oupd_parent_writable($path))$plan['unwritable'][] = $target;
			$plan['added'][$target] = $idx;
		}
	}
	return $plan;
}

function oupd_lock(){
	$dir = oupd_data_dir();
	if($dir === '')return false;
	$fp = @fopen($dir.'update.lock', 'c');
	if(!$fp)return false;
	if(!flock($fp, LOCK_EX | LOCK_NB)){
		fclose($fp);
		return false;
	}
	return $fp;
}

function oupd_unlock($fp){
	if($fp){
		flock($fp, LOCK_UN);
		fclose($fp);
	}
}

/**
 * 要装的提交号必须是版本检查缓存里那 20 条提交之一：
 * 页面上给站长看的就是这些，装的也只能是这些，不接受随手填的 sha。
 */
function oupd_known_commit($sha){
	if(!is_string($sha) || !preg_match('/^[0-9a-f]{40}$/', $sha))return false;
	$cache = update_cache_read();
	if(!$cache || empty($cache['commits']))return false;
	foreach($cache['commits'] as $c){
		if(isset($c['sha']) && $c['sha'] === $sha)return true;
	}
	return false;
}

function oupd_installed_sha(){
	$sha = (string)getSetting('update_installed_sha');
	return preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : '';
}

/**
 * 预检留下的下载包：[sha, 文件名, 时间]。只认 data/update_backup/ 下符合命名规则的文件
 */
function oupd_pending_get($sha){
	$p = json_decode((string)getSetting('update_pending'), true);
	if(!is_array($p) || empty($p['sha']) || $p['sha'] !== $sha || empty($p['file']) || empty($p['time']))return '';
	if(!preg_match('/^dl-[0-9a-f]{7}-[0-9a-f]{16}\.zip$/', $p['file']))return '';
	if(time() - intval($p['time']) > OUPD_PENDING_TTL)return '';
	$file = oupd_data_dir().$p['file'];
	return is_file($file) ? $file : '';
}

/**
 * 清掉过期或不再需要的下载包（预检留下的那个除外）
 */
function oupd_clean_downloads($keep = ''){
	$dir = oupd_data_dir();
	if($dir === '')return;
	foreach((array)glob($dir.'dl-*.zip') as $f){
		if($keep !== '' && realpath($f) === realpath($keep))continue;
		@unlink($f);
	}
}

/**
 * 取到（或下载）指定提交的包并检查。供预检和正式更新共用。
 */
function oupd_fetch($sha, &$err = null, &$file = null){
	$err = '';
	$dir = oupd_data_dir();
	$file = oupd_pending_get($sha);
	if($file === ''){
		oupd_clean_downloads();
		$file = $dir.'dl-'.substr($sha, 0, 7).'-'.bin2hex(random_bytes(8)).'.zip';
		if(!oupd_download($sha, $file, $err))return false;
		saveSetting('update_pending', json_encode(['sha'=>$sha, 'file'=>basename($file), 'time'=>time()]));
	}
	$pkg = oupd_inspect($file, $err);
	if(!$pkg){
		@unlink($file);
		saveSetting('update_pending', '');
		return false;
	}
	//降级保护：仓库的版本号比站点还低，说明站点上有没推到仓库的改动，覆盖等于回退
	if(intval($pkg['version']) < intval(VERSION) || intval($pkg['db_version']) < intval(DB_VERSION)){
		$pkg['zip']->close();
		$err = '仓库版本（'.$pkg['version'].'，数据库 '.$pkg['db_version'].'）比当前站点（'.VERSION.'，数据库 '.DB_VERSION.'）还低，覆盖会把站点改回旧代码，已拒绝。请用全量包更新';
		return false;
	}
	return $pkg;
}

/**
 * 预检：下载、检查、比对，不写任何站点文件。
 */
function oupd_prepare($sha){
	if($errs = oupd_requirements())return ['code'=>-1, 'msg'=>implode('；', $errs)];
	if(!oupd_known_commit($sha))return ['code'=>-1, 'msg'=>'提交号不在最近的提交列表里，请先「重新检查」再试'];
	$lock = oupd_lock();
	if(!$lock)return ['code'=>-1, 'msg'=>'另一个更新或还原正在进行，请稍后再试'];
	@set_time_limit(300);
	$pkg = oupd_fetch($sha, $err);
	if(!$pkg){
		oupd_unlock($lock);
		return ['code'=>-1, 'msg'=>$err];
	}
	$plan = oupd_plan($pkg);
	$result = oupd_plan_summary($pkg, $plan, $sha);
	$pkg['zip']->close();
	oupd_unlock($lock);
	$result['code'] = 0;
	return $result;
}

/**
 * 预检、更新共用的摘要：改几个、加几个、哪些写不了、要不要跑数据库升级、依赖有没有变
 */
function oupd_plan_summary($pkg, $plan, $sha){
	global $conf;
	$zip = $pkg['zip'];
	$composer_changed = false;
	if(isset($pkg['files']['includes/composer.json'])){
		$new = $zip->getFromIndex($pkg['files']['includes/composer.json']);
		$old = @file_get_contents(SYSTEM_ROOT.'composer.json');
		$composer_changed = ($old === false || str_replace("\r\n", "\n", $old) !== str_replace("\r\n", "\n", (string)$new));
	}
	$changed = array_keys($plan['changed']);
	$added = array_keys($plan['added']);
	sort($changed);
	sort($added);
	return [
		'sha'          => $sha,
		'version'      => $pkg['version'],
		'db_version'   => $pkg['db_version'],
		'local_version'=> VERSION,
		'local_db'     => isset($conf['version']) ? (string)$conf['version'] : '',
		'changed'      => array_slice($changed, 0, 300),
		'added'        => array_slice($added, 0, 300),
		'changed_count'=> count($changed),
		'added_count'  => count($added),
		'unchanged'    => $plan['unchanged'],
		'skipped_count'=> count($plan['skipped']),
		'unwritable'   => array_slice($plan['unwritable'], 0, 50),
		'admin_dir'    => $plan['admin_dir'],
		'need_db'      => intval($pkg['db_version']) > intval(isset($conf['version']) ? $conf['version'] : 0),
		'composer_changed' => $composer_changed,
	];
}

/**
 * 写一个文件：先写同目录临时文件再改名，避免别的请求读到写了一半的 PHP。
 */
function oupd_write_file($path, $data, &$created_dirs){
	$dir = dirname($path);
	if(!is_dir($dir)){
		//记下新建的目录，回滚时从深到浅删掉
		$missing = [];
		$d = $dir;
		while(!is_dir($d)){
			$missing[] = $d;
			$up = dirname($d);
			if($up === $d)break;
			$d = $up;
		}
		if(!@mkdir($dir, 0755, true) && !is_dir($dir))return false;
		foreach($missing as $m)$created_dirs[] = $m;
	}
	$tmp = $path.'.oupd-'.bin2hex(random_bytes(4));
	if(@file_put_contents($tmp, $data) !== strlen($data)){
		@unlink($tmp);
		return false;
	}
	if(is_file($path))@chmod($tmp, fileperms($path) & 0777);
	if(!@rename($tmp, $path)){
		//Windows 上目标存在时 rename 会失败，退一步先删再改名
		if(!(@unlink($path) && @rename($tmp, $path))){
			@unlink($tmp);
			return false;
		}
	}
	return true;
}

/**
 * 正式更新。
 */
function oupd_apply($sha){
	global $conf;
	if($errs = oupd_requirements())return ['code'=>-1, 'msg'=>implode('；', $errs)];
	if(!oupd_known_commit($sha))return ['code'=>-1, 'msg'=>'提交号不在最近的提交列表里，请先「重新检查」再试'];
	$lock = oupd_lock();
	if(!$lock)return ['code'=>-1, 'msg'=>'另一个更新或还原正在进行，请稍后再试'];
	@set_time_limit(600);
	//浏览器那头断开（关页面、超时）也要把这一轮做完，做一半停下才是最糟的
	@ignore_user_abort(true);

	$pkg = oupd_fetch($sha, $err, $dlfile);
	if(!$pkg){
		oupd_unlock($lock);
		return ['code'=>-1, 'msg'=>$err];
	}
	$zip = $pkg['zip'];
	$plan = oupd_plan($pkg);
	$summary = oupd_plan_summary($pkg, $plan, $sha);
	if($plan['unwritable']){
		$zip->close();
		oupd_unlock($lock);
		$summary['code'] = -1;
		$summary['msg'] = '有 '.count($plan['unwritable']).' 个文件或目录不可写，一个都没改。请先修正权限（见下方列表）';
		return $summary;
	}
	if(!$plan['changed'] && !$plan['added']){
		$zip->close();
		oupd_clean_downloads();
		saveSetting('update_pending', '');
		saveSetting('update_installed_sha', $sha);
		saveSetting('update_cache', '');
		oupd_unlock($lock);
		$summary['code'] = 0;
		$summary['msg'] = '站点文件和这个提交完全一致，不需要更新';
		$summary['backup'] = '';
		return $summary;
	}

	//① 备份：被覆盖的旧文件原样存进去，外加一份清单（本次新建了哪些文件，还原时要删）
	$backup = oupd_make_backup($plan, $sha, $berr);
	if($backup === false){
		$zip->close();
		oupd_unlock($lock);
		return ['code'=>-1, 'msg'=>'备份失败，一个文件都没改：'.$berr];
	}

	//② 站长删过 install 目录的站点，覆盖后会多出 install/index.php 却没有锁，整站会被安装检测拦住
	if(!is_file(ROOT.'install/install.lock')){
		if(!is_dir(ROOT.'install'))@mkdir(ROOT.'install', 0755, true);
		@file_put_contents(ROOT.'install/install.lock', '安装锁');
	}

	//③ 逐个写入，失败就整轮回滚
	$written = [];
	$created_dirs = [];
	$fail = '';
	foreach($plan['changed'] + $plan['added'] as $target => $idx){
		$data = $zip->getFromIndex($idx);
		if($data === false){
			$fail = '读取更新包里的 '.$target.' 失败';
			break;
		}
		if(!oupd_write_file(ROOT.$target, $data, $created_dirs)){
			$fail = '写入 '.$target.' 失败';
			break;
		}
		$written[] = $target;
	}
	$zip->close();
	if($fail !== ''){
		$rb = oupd_rollback($backup, $written, $created_dirs);
		oupd_after_write();
		oupd_unlock($lock);
		return ['code'=>-1, 'msg'=>$fail.'。'.($rb === '' ? '已把本次写过的 '.count($written).' 个文件全部还原，站点保持更新前的样子' : '自动还原也出了问题：'.$rb.'。请到下方「备份」里手动还原 '.basename($backup))];
	}

	//④ 收尾
	$from_sha = oupd_installed_sha();
	saveSetting('update_installed_sha', $sha);
	saveSetting('update_pending', '');
	saveSetting('update_cache', '');
	saveSetting('update_last', json_encode([
		'time'=>time(), 'sha'=>$sha, 'from_sha'=>$from_sha, 'version'=>$pkg['version'],
		'changed'=>count($plan['changed']), 'added'=>count($plan['added']), 'backup'=>basename($backup),
	]));
	oupd_clean_downloads();
	oupd_prune_backups();
	oupd_after_write();
	oupd_unlock($lock);
	$summary['code'] = 0;
	$summary['msg'] = '更新完成';
	$summary['backup'] = basename($backup);
	return $summary;
}

/**
 * 写完文件之后的公共收尾：opcache 不清的话，PHP 可能继续跑内存里的旧代码；
 * 404.html 在仓库里是默认外观，覆盖后按当前外观重新写一遍。
 */
function oupd_after_write(){
	global $conf;
	if(function_exists('opcache_reset'))@opcache_reset();
	if(function_exists('sync_404_theme') && !empty($conf['site_theme']))sync_404_theme($conf['site_theme']);
}

function oupd_make_backup($plan, $sha, &$err = null){
	$err = '';
	$dir = oupd_data_dir();
	$name = 'backup-'.date('Ymd-His').'-'.substr($sha, 0, 7).'-'.bin2hex(random_bytes(4)).'.zip';
	$file = $dir.$name;
	$zip = new ZipArchive();
	if($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
		$err = '建不了备份文件';
		return false;
	}
	foreach($plan['changed'] as $target => $idx){
		if(!$zip->addFile(ROOT.$target, 'files/'.$target)){
			$zip->close();
			@unlink($file);
			$err = '备份 '.$target.' 失败';
			return false;
		}
	}
	$manifest = [
		'time'      => time(),
		'to_sha'    => $sha,
		'from_sha'  => oupd_installed_sha(),
		'from_version' => VERSION,
		'changed'   => array_keys($plan['changed']),
		'added'     => array_keys($plan['added']),
	];
	$zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	if(!$zip->close()){
		@unlink($file);
		$err = '备份文件写盘失败（磁盘满了？）';
		return false;
	}
	//关掉再打开数一遍，确认备份真的完整落盘了，才敢开始覆盖
	$chk = new ZipArchive();
	$chk_ok = false;
	if($chk->open($file) === true){
		$chk_ok = ($chk->numFiles == count($plan['changed']) + 1);
		$chk->close();
	}
	if(!$chk_ok){
		@unlink($file);
		$err = '备份文件校验不通过';
		return false;
	}
	return $file;
}

/**
 * 把备份里的文件写回去、删掉本次新建的文件。
 * $only 为 null 时处理清单里的全部文件（手动还原）；否则只处理这些（更新中途失败的自动回滚）。
 * 返回空串表示全部成功，否则是出错的说明。
 */
function oupd_restore_files($file, $only = null, $created_dirs = []){
	$zip = new ZipArchive();
	if($zip->open($file) !== true)return '备份文件打不开';
	$manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
	if(!is_array($manifest) || !isset($manifest['changed'], $manifest['added'])){
		$zip->close();
		return '备份里没有有效的清单';
	}
	$errs = [];
	$dummy = [];
	foreach($manifest['changed'] as $target){
		if($only !== null && !in_array($target, $only, true))continue;
		if(!oupd_safe_target($target)){
			$errs[] = $target.'（路径不安全，跳过）';
			continue;
		}
		$data = $zip->getFromName('files/'.$target);
		if($data === false || !oupd_write_file(ROOT.$target, $data, $dummy))$errs[] = $target;
	}
	foreach($manifest['added'] as $target){
		if($only !== null && !in_array($target, $only, true))continue;
		if(!oupd_safe_target($target))continue;
		if(is_file(ROOT.$target) && !@unlink(ROOT.$target))$errs[] = $target.'（删不掉）';
	}
	$zip->close();
	//更新时新建的目录，空了就删掉（从深到浅）
	rsort($created_dirs);
	foreach($created_dirs as $d)@rmdir($d);
	return $errs ? implode('、', array_slice($errs, 0, 10)).(count($errs) > 10 ? ' 等 '.count($errs).' 个' : '') : '';
}

function oupd_rollback($backup, $written, $created_dirs){
	return oupd_restore_files($backup, $written, $created_dirs);
}

/**
 * 清单里的路径同样不能信：备份文件理论上只有我们自己写，但还原前再挡一次 ..
 */
function oupd_safe_target($target){
	if(!is_string($target) || $target === '' || strpos($target, "\0") !== false || strpos($target, '\\') !== false || strpos($target, ':') !== false || $target[0] === '/')return false;
	foreach(explode('/', $target) as $p){
		if($p === '' || $p === '.' || $p === '..')return false;
	}
	return oupd_skip_reason($target) === '' || strpos($target, 'install/') === 0;
}

function oupd_backup_name_ok($name){
	return is_string($name) && preg_match('/^backup-[0-9]{8}-[0-9]{6}-[0-9a-f]{7}-[0-9a-f]{8}\.zip$/', $name);
}

/**
 * 备份列表，新的在前
 */
function oupd_backups(){
	$dir = oupd_data_dir();
	if($dir === '')return [];
	$list = [];
	foreach((array)glob($dir.'backup-*.zip') as $f){
		$name = basename($f);
		if(!oupd_backup_name_ok($name))continue;
		$item = ['name'=>$name, 'time'=>filemtime($f), 'size'=>filesize($f), 'to_sha'=>'', 'from_sha'=>'', 'changed'=>0, 'added'=>0];
		$zip = new ZipArchive();
		if($zip->open($f) === true){
			$m = json_decode((string)$zip->getFromName('manifest.json'), true);
			if(is_array($m)){
				$item['time'] = isset($m['time']) ? intval($m['time']) : $item['time'];
				$item['to_sha'] = isset($m['to_sha']) && preg_match('/^[0-9a-f]{40}$/', $m['to_sha']) ? $m['to_sha'] : '';
				$item['from_sha'] = isset($m['from_sha']) && preg_match('/^[0-9a-f]{40}$/', $m['from_sha']) ? $m['from_sha'] : '';
				$item['changed'] = isset($m['changed']) ? count((array)$m['changed']) : 0;
				$item['added'] = isset($m['added']) ? count((array)$m['added']) : 0;
			}
			$zip->close();
		}
		$list[] = $item;
	}
	usort($list, function($a, $b){ return $b['time'] - $a['time']; });
	return $list;
}

function oupd_prune_backups(){
	$dir = oupd_data_dir();
	$list = oupd_backups();
	foreach(array_slice($list, OUPD_KEEP_BACKUPS) as $item)@unlink($dir.$item['name']);
}

/**
 * 手动还原某一份备份：把那次更新覆盖掉的文件写回去，删掉那次新建的文件。
 * 数据库结构不会跟着退回去（升级脚本只加不减，旧代码一般照常能跑）。
 */
function oupd_restore($name){
	if(!oupd_backup_name_ok($name))return ['code'=>-1, 'msg'=>'备份名称不对'];
	$file = oupd_data_dir().$name;
	if(!is_file($file))return ['code'=>-1, 'msg'=>'备份文件不存在'];
	$lock = oupd_lock();
	if(!$lock)return ['code'=>-1, 'msg'=>'另一个更新或还原正在进行，请稍后再试'];
	@set_time_limit(300);
	@ignore_user_abort(true);
	$zip = new ZipArchive();
	$m = null;
	if($zip->open($file) === true){
		$m = json_decode((string)$zip->getFromName('manifest.json'), true);
		$zip->close();
	}
	$err = oupd_restore_files($file);
	//还原后站点回到了那次更新之前的提交；记不清的话就清空，交给版本号去判断
	$from = is_array($m) && isset($m['from_sha']) && preg_match('/^[0-9a-f]{40}$/', $m['from_sha']) ? $m['from_sha'] : '';
	saveSetting('update_installed_sha', $from);
	saveSetting('update_cache', '');
	oupd_after_write();
	oupd_unlock($lock);
	if($err !== '')return ['code'=>-1, 'msg'=>'部分文件没能还原：'.$err];
	return ['code'=>0, 'msg'=>'已还原到 '.date('Y-m-d H:i', is_array($m) && isset($m['time']) ? intval($m['time']) : filemtime($file)).' 那次更新之前的文件'];
}
