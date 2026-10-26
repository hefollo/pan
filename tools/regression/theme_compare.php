<?php
/*
 * 外观重构的 HTML 对比回归（DEC-20261025-003）。
 *
 * 做法：同一个测试库上起两个站，一个是改造前的代码（基线），一个是当前代码；
 * 逐套外观切换 pre_config.site_theme，按同样的身份抓同样的页面，规范化以后逐字比较。
 * 「按页面逐页切换、28 套一起切」就靠它保证每切一页、28 套外观的页面结构都没变。
 *
 * 用法（命令行）：
 *   php theme_compare.php --a=http://127.0.0.1:18600 --b=http://127.0.0.1:18601 \
 *       --db=127.0.0.1:3306:pan_thm:root: --tokens=tokens.json --out=<输出目录> \
 *       [--themes=console,mac] [--only=front|admin|<url 片段>] [--conf=k=v,k2=v2]
 *   tokens.json：{"user1":"<user_token>","user2":"…","admin":"<admin_token>"}（测试站里生成）
 *
 * 规范化只抹掉两边天然不同、和外观无关的东西：静态资源的 ?v= 缓存参数、各种随机令牌、
 * 连续空白合成一个空格。空白的「有无」保留：行内元素之间有没有空格，页面上是看得出来的。
 */

if(PHP_SAPI !== 'cli')exit('只能在命令行运行');
//站点按北京时间输出，「今天」也按北京时间算（命令行默认 UTC，凌晨会算成前一天）
date_default_timezone_set('Asia/Shanghai');

$opt = [];
foreach(array_slice($argv, 1) as $arg){
	if(preg_match('/^--([a-z]+)=(.*)$/', $arg, $m))$opt[$m[1]] = $m[2];
}
foreach(['a', 'b', 'db', 'tokens', 'out'] as $k){
	if(empty($opt[$k]))exit("缺少参数 --$k\n");
}
list($dbhost, $dbport, $dbname, $dbuser, $dbpwd) = array_pad(explode(':', $opt['db'], 5), 5, '');
$pdo = new PDO("mysql:host=$dbhost;port=$dbport;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpwd);
$tokens = json_decode(file_get_contents($opt['tokens']), true);
$out = rtrim($opt['out'], '/\\').'/';
@mkdir($out, 0777, true);

$all_themes = ['cloud', 'night', 'neon', 'aurora', 'onefour', 'celadon', 'lilac', 'paper',
	'blush', 'sky', 'mint', 'sunset', 'abyss', 'emerald', 'sakura',
	'dashboard', 'console', 'portal', 'workspace', 'mac', 'cockpit', 'studio', 'nebula', 'royal', 'crisp', 'azure', 'neo', 'skyline'];
$themes = !empty($opt['themes']) ? explode(',', $opt['themes']) : $all_themes;

/* 页面清单：[身份, 地址]。身份 guest / user1（普通用户）/ user2（会员）/ admin */
$pages = [
	['guest', '/'], ['guest', '/?kw=jpg'], ['guest', '/?ft=image'], ['guest', '/?sort=big'], ['guest', '/?page=2'], ['guest', '/?m=mine'], ['guest', '/?m=mine&kw=jpg'], ['guest', '/?m=mine&ft=image'], ['guest', '/?kw=zzzz'], ['guest', '/?ft=audio'], ['guest', '/?sort=small'],
	['guest', '/upload.php'], ['guest', '/login.php'], ['guest', '/sponsor.php'], ['guest', '/violation.php'], ['guest', '/buy.php'],
	['guest', '/file.php?hash=tok00000000000000000000000000001'], ['guest', '/file.php?hash=tok00000000000000000000000000003'],
	['guest', '/file.php?hash=tok00000000000000000000000000004'], ['guest', '/file.php?hash=tok00000000000000000000000000005'],
	['guest', '/file.php?hash=tok00000000000000000000000000009'], ['guest', '/file.php?hash=tok00000000000000000000000000010'],
	['guest', '/file.php?hash=tok00000000000000000000000000006'],
	['guest', '/file.php?hash=tok00000000000000000000000000009&pwd=1234'], ['guest', '/file.php?hash=tok00000000000000000000000000008'], ['guest', '/file.php?hash=tok00000000000000000000000000020'],
	['guest', '/file.php?hash=tok00000000000000000000000000021'], ['guest', '/file.php?hash=tok00000000000000000000000000022'],
	['user1', '/file.php?hash=tok00000000000000000000000000005'], ['user1', '/file.php?hash=tok00000000000000000000000000010'], ['user1', '/file.php?hash=tok00000000000000000000000000011'], ['user2', '/file.php?hash=tok00000000000000000000000000008'],
	['guest', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90'],
	['user1', '/'], ['user1', '/upload.php'], ['user1', '/upload.php?folder=2'], ['user1', '/user.php'], ['user1', '/user.php?tab=files'], ['user1', '/user.php?tab=files&folder=1'],
	['user1', '/user.php?tab=orders'], ['user1', '/user.php?tab=account'], ['user1', '/user.php?tab=files&kw=jpg'], ['user1', '/user.php?tab=files&ft=image'], ['user1', '/user.php?tab=files&folder=2'], ['user1', '/user.php?tab=files&page=2'], ['user1', '/user.php?tab=api'], ['user1', '/buy.php'], ['user1', '/buy.php?paid=1'], ['user1', '/buy.php?paid=0'], ['user1', '/edit.php?id=5'], ['user1', '/edit.php?id=13'], ['user1', '/edit.php?id=10'],
	['user1', '/file.php?hash=tok00000000000000000000000000002'], ['user1', '/sponsor.php'], ['user1', '/violation.php'],
	['user1', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90'], ['user1', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90&file=2'],
	['guest', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90&file=2'], ['guest', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90&file=5'], ['guest', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90&mode=all'], ['guest', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90&mode=all&q=%E8%AF%B4%E6%98%8E'], ['guest', '/share.php?token=a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90&folder=2'],
	['guest', '/share.php?token=zz'], ['guest', '/share.php?token=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
	['user2', '/'], ['user2', '/user.php'], ['user2', '/buy.php'], ['user2', '/user.php?tab=orders'], ['user2', '/user.php?tab=account'], ['user2', '/user.php?tab=files'], ['user2', '/user.php?tab=orders&page=2'],
	['admin', '/admin/'], ['admin', '/admin/file.php'], ['admin', '/admin/folders.php'], ['admin', '/admin/folders.php?uid=1'], ['admin', '/admin/folders.php?uid=1&folder=1'], ['admin', '/admin/user.php'], ['admin', '/admin/level.php'], ['admin', '/admin/level.php?edit=4'],
	['admin', '/admin/order.php'], ['admin', '/admin/order.php?status=1'], ['admin', '/admin/order.php?page=2'], ['admin', '/admin/green_log.php'], ['admin', '/admin/mail_log.php'], ['admin', '/admin/replace.php'],
	['admin', '/admin/update.php'], ['admin', '/admin/set.php?mod=site'], ['admin', '/admin/set.php?mod=appearance'], ['admin', '/admin/set.php?mod=account'],
	['admin', '/admin/set.php?mod=api'], ['admin', '/admin/set.php?mod=file'], ['admin', '/admin/set.php?mod=green'], ['admin', '/admin/set.php?mod=iptype'],
	['admin', '/admin/set.php?mod=user'], ['admin', '/admin/set_mail.php'], ['admin', '/admin/set_pay.php'], ['admin', '/admin/set_pay.php?edit=1'], ['admin', '/admin/set.php'], ['admin', '/admin/set_script.php'],
	['admin', '/admin/set_sponsor.php'], ['admin', '/admin/set_stor.php'], ['admin', '/admin/set_violation.php'], ['guest', '/admin/login.php'],
];
if(!empty($opt['only'])){
	$only = $opt['only'];
	$pages = array_values(array_filter($pages, function($p) use ($only){
		if($only === 'front')return strpos($p[1], '/admin/') !== 0;
		if($only === 'admin')return strpos($p[1], '/admin/') === 0;
		return strpos($p[1], $only) !== false;
	}));
}

function fetch($base, $role, $path, $tokens){
	$ch = curl_init($base.$path);
	$cookie = 'PHPSESSID=themecmp'.$role;
	if($role === 'admin')$cookie .= '; admin_token='.rawurlencode($tokens['admin']);
	if($role === 'user1' || $role === 'user2')$cookie .= '; user_token='.rawurlencode($tokens[$role]);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_PROXY => '',
		CURLOPT_NOPROXY => '*',
		CURLOPT_TIMEOUT => 60,
		CURLOPT_HTTPHEADER => [
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0',
			'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
			'Accept-Language: zh-CN,zh;q=0.9',
			'Cookie: '.$cookie,
		],
	]);
	$res = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	curl_close($ch);
	if($res === false)return [0, '', ''];
	$head = substr($res, 0, $hsize);
	$loc = preg_match('/^Location:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : '';
	return [$code, $loc, substr($res, $hsize)];
}

function normalize($html, $hosts){
	//两个站的地址不同（端口不一样），页面里拼出来的绝对地址统一抹成 HOST
	$html = str_replace($hosts, 'HOST', $html);
	//数据库版本号本来就该不同（升级了），不算外观差异
	$html = preg_replace('/(数据库(?:版本)? ?)\d{4}/u', '$1DBV', $html);
	//「服务器时间」「检查时间」这类当前时刻，两次请求可能差一秒：今天的日期时间一律抹掉（测试数据都是往年的）
	$html = preg_replace('/'.date('Y-m-d').'[ T]\d\d:\d\d(:\d\d)?/', 'NOW', $html);
	//按外观拆分的样式表（S26）和整份的是同一套规则，地址抹成整份的再比；样式是否等价由计算样式对比另外验证
	$html = preg_replace('#assets/css/build/(style|admin)\.[a-z0-9_\-]+\.css#', 'assets/css/$1.css', $html);
	$html = preg_replace('/\?v=[A-Za-z0-9._\-]+/', '?v=X', $html);
	$html = preg_replace('/[0-9a-f]{32,128}/i', 'HEX', $html);
	//authcode 生成的令牌、base64 一类的长串
	$html = preg_replace('/[A-Za-z0-9+\/]{40,}={0,2}/', 'B64', $html);
	//重构里有意新增、基线没有的内容（外观设置页的「主题包」面板及其脚本），抹掉再比，其余部分照样逐字对
	$html = preg_replace('#<div class="panel panel-default theme-packages-panel">.*?</script>#s', '', $html);
	//后台导航里新增的「页面布局」一项（后台布局编辑器）
	$html = preg_replace('#\s*<li class="[^"]*"><a href="\./layout\.php"><i class="fa fa-th-large fa-fw"></i> 页面布局</a></li>#u', '', $html);
	//HTML 注释不渲染，抹掉再比（后台拆成卡片后 <!-- /.row --> 这类注释不再逐字保留）；IE 条件注释留着
	$html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);
	$html = preg_replace('/\s+/u', ' ', $html);
	/*
	 * 块级标签两侧的空白浏览器不渲染（源码里的换行缩进），抹掉；卡片模板拼出来的块之间
	 * 往往没有原来那些换行，这不算外观差异。行内元素（a/span/button/i/b/input/img/li……）
	 * 两侧的空白照样逐个比较：按钮、图标、链接之间有没有空格，页面上看得出来。
	 * li 故意不算块级：有的外观把导航/分页的 li 排成 inline-block，空格会变成间距。
	 */
	$block = 'div|section|aside|header|footer|nav|main|article|figure|table|thead|tbody|tfoot|tr|td|th|ul|ol|p|h[1-6]|form|dl|dt|dd|script|style|link|meta|title|head|body|html|noscript|hr|br';
	$html = preg_replace('#\s*(</?(?:'.$block.')\b[^>]*>)\s*#', '$1', $html);
	return trim($html);
}

/* 找第一处不同，前后各带一段上下文 */
function first_diff($a, $b){
	$n = min(strlen($a), strlen($b));
	$i = 0;
	while($i < $n && $a[$i] === $b[$i])$i++;
	if($i === $n && strlen($a) === strlen($b))return null;
	$from = max(0, $i - 160);
	return ['pos'=>$i, 'a'=>substr($a, $from, 420), 'b'=>substr($b, $from, 420)];
}

$total = 0; $same = 0; $diffs = [];
$hosts = [];
foreach([$opt['a'], $opt['b']] as $u){
	$h = parse_url($u, PHP_URL_HOST).(parse_url($u, PHP_URL_PORT) ? ':'.parse_url($u, PHP_URL_PORT) : '');
	$hosts[] = $h;
	$hosts[] = rawurlencode($h);
}
$set = $pdo->prepare("REPLACE INTO pre_config (k, v) VALUES ('site_theme', ?)");
$orig = $pdo->query("SELECT v FROM pre_config WHERE k='site_theme'")->fetchColumn();
//--conf=k=v,k2=v2：这一轮先临时改几项配置（覆盖默认数据走不到的分支），跑完改回去
$conf_backup = [];
if(!empty($opt['conf'])){
	foreach(explode(',', $opt['conf']) as $kv){
		list($ck, $cv) = array_pad(explode('=', $kv, 2), 2, '');
		$st = $pdo->prepare("SELECT v FROM pre_config WHERE k=?");
		$st->execute([$ck]);
		$conf_backup[$ck] = $st->fetchColumn();
		$pdo->prepare("REPLACE INTO pre_config (k, v) VALUES (?, ?)")->execute([$ck, $cv]);
	}
}
foreach($themes as $theme){
	$set->execute([$theme]);
	foreach($pages as $p){
		list($role, $path) = $p;
		$total++;
		$ra = fetch($opt['a'], $role, $path, $tokens);
		$rb = fetch($opt['b'], $role, $path, $tokens);
		$na = normalize($ra[2], $hosts);
		$nb = normalize($rb[2], $hosts);
		$key = $theme.' '.$role.' '.$path;
		if($ra[0] !== $rb[0] || $ra[1] !== $rb[1]){
			$diffs[] = $key.'  状态不同：'.$ra[0].' '.$ra[1].' / '.$rb[0].' '.$rb[1];
			continue;
		}
		$d = first_diff($na, $nb);
		if($d === null){$same++; continue;}
		$slug = preg_replace('/[^A-Za-z0-9]+/', '_', $key);
		file_put_contents($out.$slug.'.a.html', $na);
		file_put_contents($out.$slug.'.b.html', $nb);
		$diffs[] = $key.'  第 '.$d['pos'].' 字节起不同'."\n    基线：".$d['a']."\n    现在：".$d['b'];
	}
	fwrite(STDERR, "$theme 完成\n");
}
if($orig !== false)$set->execute([$orig]);
foreach($conf_backup as $ck => $cv){
	if($cv === false)$pdo->prepare("DELETE FROM pre_config WHERE k=?")->execute([$ck]);
	else $pdo->prepare("REPLACE INTO pre_config (k, v) VALUES (?, ?)")->execute([$ck, $cv]);
}
$report = '共 '.$total.' 个页面，一致 '.$same.'，不一致 '.count($diffs)."\n".implode("\n", $diffs)."\n";
file_put_contents($out.'report.txt', $report);
echo $report;
