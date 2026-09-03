<?php
/**
 * 布局型外观（控制台侧栏风 / 数据控制台风 / 上传门户风 / 深色工作台风 / macOS 窗口风 / 渐变仪表盘风）
 * 额外用到的结构块。
 * 这些外观在原型里有统计卡、类型筛选、右侧预览等，纯 CSS 做不出来，统一放在这里生成，
 * 其它外观完全不会输出这些标签，保持原样。
 */
if(!defined('SYSTEM_ROOT'))exit();

//右侧预览面板自动拉取文本内容的体积上限，超过就只显示类型图标
define('LAYOUT_TEXT_PREVIEW_MAX', 256 * 1024);

/**
 * 布局型外观的统计数字只是装饰，允许有几分钟延迟，统一走文件缓存，
 * 避免每次打开页面都对 pre_file 做一次全表统计（该表只有 id/token/hash/uid 索引）
 */
function layout_cache_file($key){
	$dir = sys_get_temp_dir();
	if(!$dir || !is_dir($dir) || !is_writable($dir)) return null;
	return rtrim($dir, '/\\').'/mpimg_layout_'.md5(SYSTEM_ROOT.'|'.$key).'.json';
}

function layout_cache_get($key, $ttl){
	$file = layout_cache_file($key);
	if(!$file || !is_file($file)) return null;
	if(filemtime($file) + $ttl < time()) return null;
	$raw = @file_get_contents($file);
	if($raw === false) return null;
	$data = json_decode($raw, true);
	return is_array($data) ? $data : null;
}

function layout_cache_set($key, $data){
	$file = layout_cache_file($key);
	if(!$file) return;
	@file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * 当前访客今日已上传数量：会话内缓存 2 分钟，登录用户走 uid 索引，游客只能按 ip 扫描
 */
function layout_today_upload_count($DB){
	global $islogin2, $uid, $clientip;
	$who = !empty($islogin2) ? 'u'.intval($uid) : 'i'.$clientip;
	$day = date('Y-m-d');
	if(isset($_SESSION['layout_today']) && is_array($_SESSION['layout_today'])
		&& $_SESSION['layout_today']['who'] === $who
		&& $_SESSION['layout_today']['day'] === $day
		&& $_SESSION['layout_today']['time'] + 120 > time()){
		return intval($_SESSION['layout_today']['num']);
	}
	$since = $day.' 00:00:00';
	if(!empty($islogin2)){
		$num = intval($DB->getColumn("SELECT count(*) from pre_file WHERE uid='".intval($uid)."' AND addtime>='".$since."'"));
	}else{
		//和上传接口用同一个维度统计，否则卡片上显示的数字和实际能不能传对不上
		$num = intval($DB->getColumn("SELECT count(*) from pre_file WHERE ipkey=:k AND addtime>=:t", [':k'=>client_ip_key(), ':t'=>$since]));
	}
	$_SESSION['layout_today'] = ['who'=>$who, 'day'=>$day, 'num'=>$num, 'time'=>time()];
	return $num;
}

/**
 * 紧凑的权限条，给没有侧栏的外观用（上传门户风 + 15 套配色型）。
 * 内容和侧栏那两张卡一致：今日上传、单文件大小、到期时间、购买入口。
 *
 * $where 传 'list' 或 'upload'，只影响文案，不影响数据。
 */
function render_permission_bar($DB, $where = 'list'){
	global $conf, $islogin2, $userrow, $site_theme;
	//有侧栏卡的外观就不用再显示一遍了
	if(in_array($site_theme, ['console', 'workspace', 'dashboard'], true))return '';
	//渐变仪表盘风的文件列表页顶部已经有一张额度卡，同样的内容不再重复一条；上传页没有那张卡，照常显示
	if($site_theme === 'cockpit' && $where === 'list')return '';

	$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
	$today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
	$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;

	$items = [];
	$items[] = ['fa-cloud-upload', '今日上传', $limit > 0 ? ($today.' / '.$limit) : ($today.' 个（不限）')];
	$items[] = ['fa-file-o', '单文件', $size > 0 ? ($size.' MB') : '不限制'];

	if(!empty($islogin2)){
		$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
		if(empty($expire)){
			$items[] = ['fa-clock-o', '有效期', '永久有效'];
		}elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){
			$items[] = ['fa-clock-o', '有效期', '已过期'];
		}else{
			$left = max(1, ceil((strtotime($expire) - time()) / 86400));
			$items[] = ['fa-clock-o', '有效期', '剩 '.$left.' 天'];
		}
		if(!empty($userrow['bonus_limit']) && $limit > 0){
			$items[] = ['fa-plus-circle', '加量包', '+'.intval($userrow['bonus_limit']).' 个/天'];
		}
	}

	ob_start();
?>
<div class="perm-bar">
<?php foreach($items as $it){?>
    <span class="perm-item"><i class="fa <?php echo $it[0]?>" aria-hidden="true"></i><em><?php echo $it[1]?></em><b><?php echo htmlspecialchars($it[2], ENT_QUOTES, 'UTF-8')?></b></span>
<?php }?>
<?php if(function_exists('is_buy_open') && is_buy_open()){?>
    <a class="perm-buy" href="./buy.php"><?php echo !empty($islogin2) ? '购买权限' : '登录后可购买更多额度'?> <i class="fa fa-angle-right" aria-hidden="true"></i></a>
<?php }elseif(empty($islogin2) && !empty($conf['userlogin'])){?>
    <a class="perm-buy perm-buy-plain" href="./login.php">登录后额度独立计算 <i class="fa fa-angle-right" aria-hidden="true"></i></a>
<?php }?>
</div>
<?php
	return ob_get_clean();
}

/**
 * 当前用户最近一笔已支付的订单，给侧栏“我的权限”卡显示套餐名用。
 * 侧栏每个页面都要渲染，所以同样走会话缓存；老站点还没有 pre_order 表时直接当没买过
 */
function layout_user_plan($DB){
	global $islogin2, $uid;
	if(empty($islogin2))return null;
	$who = intval($uid);
	if(isset($_SESSION['layout_plan']) && is_array($_SESSION['layout_plan'])
		&& $_SESSION['layout_plan']['uid'] === $who
		&& $_SESSION['layout_plan']['time'] + 120 > time()){
		return $_SESSION['layout_plan']['data'];
	}
	$row = false;
	try{
		$row = $DB->getRow("SELECT plan_name, paytime FROM pre_order WHERE uid=".$who." AND status=1 ORDER BY id DESC LIMIT 1");
	}catch(Exception $e){
		$row = false;
	}
	$data = [
		'bought' => $row ? true : false,
		'plan_name' => $row ? $row['plan_name'] : '',
		'paytime' => $row ? $row['paytime'] : '',
	];
	$_SESSION['layout_plan'] = ['uid'=>$who, 'data'=>$data, 'time'=>time()];
	return $data;
}

/**
 * 全站今日上传数量，给数据控制台风的统计卡用，缓存 5 分钟
 */
function layout_today_total($DB, $where_sql){
	$key = 'today|'.$where_sql.'|'.date('Y-m-d');
	$hit = layout_cache_get($key, 300);
	if($hit !== null && isset($hit['num'])) return intval($hit['num']);
	$num = intval($DB->getColumn("SELECT count(*) from pre_file WHERE{$where_sql} AND addtime>='".date('Y-m-d 00:00:00')."'"));
	layout_cache_set($key, ['num'=>$num]);
	return $num;
}

/**
 * 文件类型分组 -> 扩展名列表，跟 type_to_icon 用同一套后台配置
 */
function layout_type_group_exts($group){
	global $conf;
	$image = array_merge(explode('|', isset($conf['type_image'])?$conf['type_image']:''), ['png','jpg','jpeg','gif','bmp','webp','ico','svg','tif','tiff','heic','avif','psd','raw']);
	$video = array_merge(explode('|', isset($conf['type_video'])?$conf['type_video']:''), ['mp4','webm','flv','f4v','mov','3gp','avi','mpg','mpeg','wmv','mkv','ts','rm','rmvb','m3u8','m4v','mts']);
	$audio = array_merge(explode('|', isset($conf['type_audio'])?$conf['type_audio']:''), ['mp3','wav','wma','ogg','m4a','flac','ape','aac','mid','midi']);
	$doc   = ['txt','text','log','md','pdf','doc','docx','rtf','wps','odt','xls','xlsx','ods','ppt','pptx','pptm','csv','json','xml','yml','yaml'];
	$archive = ['zip','7z','rar','tgz','gz','xz','tar','jar','iso','cab','bz2','arj','lzh'];
	$map = ['image'=>$image, 'video'=>$video, 'audio'=>$audio, 'doc'=>$doc, 'archive'=>$archive];
	if(!isset($map[$group])) return [];
	$exts = array_values(array_unique(array_filter(array_map('strtolower', $map[$group]), 'strlen')));
	return $exts;
}

/**
 * 类型筛选的可选项；键要跟 URL 上的 ft 参数一致
 */
function layout_type_filters(){
	return [
		'' => '全部',
		'image' => '图片',
		'video' => '视频',
		'doc' => '文档',
		'archive' => '压缩包',
	];
}

/**
 * 把 ft 参数转成 SQL 条件，扩展名只来自上面的白名单，不会把用户输入拼进 SQL
 */
function layout_type_filter_sql($ft){
	$exts = layout_type_group_exts($ft);
	if(!$exts) return '';
	$safe = [];
	foreach($exts as $ext){
		if(preg_match('/^[a-z0-9]{1,10}$/', $ext)) $safe[] = "'".$ext."'";
	}
	if(!$safe) return '';
	return " AND type IN (".implode(',', $safe).")";
}

/**
 * 按分组统计当前列表里的文件数：一次 GROUP BY 查完，不为每个标签单独查一遍
 */
function layout_type_counts($DB, $where_sql){
	$counts = ['' => 0, 'image' => 0, 'video' => 0, 'doc' => 0, 'archive' => 0];
	//GROUP BY type 在没有 type 索引的大表上是全表扫描，缓存 5 分钟
	$cache_key = 'counts|'.$where_sql;
	$hit = layout_cache_get($cache_key, 300);
	if($hit !== null){
		foreach($counts as $k => $v){ if(isset($hit[$k])) $counts[$k] = intval($hit[$k]); }
		return $counts;
	}
	$groups = ['image','video','doc','archive'];
	$lookup = [];
	foreach($groups as $g){
		foreach(layout_type_group_exts($g) as $ext){
			if(!isset($lookup[$ext])) $lookup[$ext] = $g;
		}
	}
	$rs = $DB->query("SELECT type, count(*) as num FROM pre_file WHERE{$where_sql} GROUP BY type");
	if(!$rs) return $counts;
	while($row = $rs->fetch()){
		$num = intval($row['num']);
		$counts[''] += $num;
		$ext = strtolower((string)$row['type']);
		if(isset($lookup[$ext])) $counts[$lookup[$ext]] += $num;
	}
	layout_cache_set($cache_key, $counts);
	return $counts;
}

/**
 * 文件所属分组，用来给列表行加 data-group，CSS 靠它给图标上色
 */
function layout_type_group($type){
	$type = strtolower((string)$type);
	foreach(['image','video','audio','doc','archive'] as $g){
		if(in_array($type, layout_type_group_exts($g), true)) return $g;
	}
	return 'other';
}

/**
 * 数据控制台风的统计卡
 */
function layout_render_stats($counts, $today_count){
	$cards = [
		['fa-files-o', 'all', number_format($counts['']), '全部文件'],
		['fa-picture-o', 'image', number_format($counts['image']), '图片文件'],
		['fa-video-camera', 'video', number_format($counts['video']), '视频文件'],
		['fa-clock-o', 'today', number_format($today_count), '今日上传'],
	];
	$html = '<div class="layout-stats">';
	foreach($cards as $c){
		$html .= '<div class="layout-stat layout-stat-'.$c[1].'">'
			.'<span class="layout-stat-icon"><i class="fa '.$c[0].'" aria-hidden="true"></i></span>'
			.'<div><strong>'.$c[2].'</strong><span>'.$c[3].'</span></div></div>';
	}
	return $html.'</div>';
}

/**
 * 类型筛选标签，保留当前的 m/kw 参数
 */
function layout_render_filters($counts, $ft, $base_query){
	$html = '<div class="layout-filters">';
	foreach(layout_type_filters() as $key => $label){
		$num = isset($counts[$key]) ? intval($counts[$key]) : 0;
		$query = $base_query;
		if($key !== '') $query .= ($query === '' ? '' : '&').'ft='.$key;
		$href = './'.($query === '' ? '' : '?'.$query);
		$active = ($ft === $key) ? ' active' : '';
		$html .= '<a class="layout-filter'.$active.'" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'
			.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').' <em>'.$num.'</em></a>';
	}
	return $html.'</div>';
}

/**
 * macOS 窗口风：列表上方的拖拽提示区。
 * 真正的上传逻辑在 upload.php，这里只是个入口，点一下就跳过去，
 * 所以用 <a> 而不是 <form>，不需要额外的 JS。
 */
function layout_render_mac_drop(){
	$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
	$hint = $size > 0 ? ('单个文件最大 '.$size.' MB · 支持图片 / 视频 / 音频 / 文档 / 压缩包')
		: '不限制文件大小 · 支持图片 / 视频 / 音频 / 文档 / 压缩包';
	return '<a class="mac-drop" href="./upload.php">'
		.'<span class="mac-drop-icon"><i class="fa fa-cloud-upload" aria-hidden="true"></i></span>'
		.'<strong>点击选择文件，或拖拽到此处</strong>'
		.'<small>'.htmlspecialchars($hint, ENT_QUOTES, 'UTF-8').'</small></a>';
}

/**
 * macOS 窗口风：文件列表的网格 / 列表视图切换。
 * 没开 JS 时两个按钮点不动，列表保持默认的网格视图，不影响下载和查看，
 * 所以这里直接输出 button，由 layout-mac.js 接管点击并把选择存进 localStorage。
 */
function layout_render_mac_viewtoggle(){
	return '<span class="mac-viewtoggle" id="macViewToggle">'
		.'<button type="button" class="active" data-mac-view="grid" title="网格视图" aria-label="网格视图"><i class="fa fa-th-large" aria-hidden="true"></i></button>'
		.'<button type="button" data-mac-view="list" title="列表视图" aria-label="列表视图"><i class="fa fa-list" aria-hidden="true"></i></button>'
		.'</span>';
}

/**
 * 深色工作台风的右侧文件预览面板，内容由 layout-workspace.js 点击列表行时填充
 */
function layout_render_preview(){
	return '<aside class="layout-preview" id="layoutPreview">'
		.'<div class="layout-preview-head"><span>文件预览</span></div>'
		.'<div class="layout-preview-empty">在左侧选择一个文件，这里会显示它的详细信息与外链。</div>'
		.'<div class="layout-preview-body" hidden>'
		.'<div class="layout-preview-art"><i class="fa fa-file-o" aria-hidden="true"></i></div>'
		.'<h2 class="layout-preview-name"></h2>'
		.'<div class="layout-preview-sub"></div>'
		.'<div class="layout-preview-actions">'
		.'<a class="layout-preview-download" href="#"><i class="fa fa-download" aria-hidden="true"></i> 下载文件</a>'
		.'<button type="button" class="layout-preview-copy"><i class="fa fa-link" aria-hidden="true"></i> 复制链接</button>'
		.'</div>'
		.'<dl class="layout-preview-meta">'
		.'<div><dt>文件大小</dt><dd data-field="size"></dd></div>'
		.'<div><dt>文件格式</dt><dd data-field="type"></dd></div>'
		.'<div><dt>上传时间</dt><dd data-field="time"></dd></div>'
		.'<div><dt>上传者IP</dt><dd data-field="ip"></dd></div>'
		.'</dl>'
		.'<div class="layout-preview-link"><label>外链地址</label>'
		.'<div class="layout-preview-link-row"><code></code>'
		.'<button type="button" class="layout-preview-copy2" title="复制"><i class="fa fa-clone" aria-hidden="true"></i></button></div></div>'
		.'<a class="layout-preview-open" href="#"><i class="fa fa-external-link" aria-hidden="true"></i> 打开文件页</a>'
		.'</div></aside>';
}

/**
 * 渐变仪表盘风：全站（或当前筛选条件下）已用存储量，单位字节。
 * SUM(size) 在 pre_file 上没有索引可用，是一次全表扫描，和类型统计一样缓存 5 分钟。
 */
function layout_storage_used($DB, $where_sql){
	$key = 'size|'.$where_sql;
	$hit = layout_cache_get($key, 300);
	if($hit !== null && isset($hit['size'])) return floatval($hit['size']);
	$size = floatval($DB->getColumn("SELECT sum(size) from pre_file WHERE{$where_sql}"));
	layout_cache_set($key, ['size'=>$size]);
	return $size;
}

/**
 * 渐变仪表盘风：右侧「最近上传」用的几条记录。
 * 走 id 倒序 + LIMIT 本身不慢，但侧栏每个页面都要渲染，仍然缓存 2 分钟少查几次。
 */
function layout_recent_uploads($DB, $where_sql, $limit = 6){
	$limit = max(1, min(20, intval($limit)));
	$key = 'recent|'.$limit.'|'.$where_sql;
	$hit = layout_cache_get($key, 120);
	if($hit !== null && isset($hit['rows']) && is_array($hit['rows'])) return $hit['rows'];
	$rows = [];
	$rs = $DB->query("SELECT token, name, type, size, addtime FROM pre_file WHERE{$where_sql} ORDER BY id DESC LIMIT ".$limit);
	if($rs){
		while($row = $rs->fetch()){
			$rows[] = [
				'token' => $row['token'],
				'name' => $row['name'],
				'type' => $row['type'],
				'size' => $row['size'],
				'addtime' => $row['addtime'],
			];
		}
	}
	layout_cache_set($key, ['rows'=>$rows]);
	return $rows;
}

/**
 * “3 分钟前”这种相对时间；超过 30 天直接显示日期，再往前算天数没意义
 */
function layout_time_ago($time){
	$ts = strtotime((string)$time);
	if(!$ts) return (string)$time;
	$diff = time() - $ts;
	if($diff < 0) return date('Y-m-d', $ts);
	if($diff < 60) return '刚刚';
	if($diff < 3600) return floor($diff / 60).' 分钟前';
	if($diff < 86400) return floor($diff / 3600).' 小时前';
	if($diff < 2592000) return floor($diff / 86400).' 天前';
	return date('Y-m-d', $ts);
}

/**
 * 按当前时间给一句问候语，渐变仪表盘风的顶部问候栏用
 */
function layout_greeting(){
	$hour = intval(date('G'));
	if($hour < 6) return '凌晨好';
	if($hour < 9) return '早上好';
	if($hour < 12) return '上午好';
	if($hour < 14) return '中午好';
	if($hour < 18) return '下午好';
	return '晚上好';
}

/**
 * 昵称的首字，用来当头像里的文字。没装 mbstring 时按字节截会截出半个汉字，退回空字符串让调用方显示图标。
 */
function layout_name_initial($name){
	$name = trim((string)$name);
	if($name === '') return '';
	if(function_exists('mb_substr')) return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
	return preg_match('/^[A-Za-z0-9]/', $name) ? strtoupper(substr($name, 0, 1)) : '';
}

/**
 * 渐变仪表盘风：顶部问候栏（问候语 + 今日/全站文件数 + 上传入口 + 头像）
 */
function layout_render_cockpit_head($DB, $total_files){
	global $islogin2, $userrow;
	$logged = !empty($islogin2);
	$name = $logged && !empty($userrow['nickname']) ? $userrow['nickname'] : '访客';
	$name_safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
	$today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
	$initial = layout_name_initial($name);

	$html = '<div class="cockpit-head">'
		.'<div class="cockpit-hi"><h1>'.layout_greeting().'，'.$name_safe.'</h1>'
		//"当前列表共"而不是"站内共"：?m=mine 传进来的是这个人自己的文件数，写成站内会对不上
		.'<p>今天已上传 <b>'.intval($today).'</b> 个文件 · 当前列表共 <b>'.number_format($total_files).'</b> 个文件</p></div>'
		.'<div class="cockpit-head-side">'
		.'<a class="cockpit-upload" href="./upload.php"><i class="fa fa-plus" aria-hidden="true"></i> 上传文件</a>';
	if($logged){
		$html .= '<a class="cockpit-avatar" href="./user.php" title="'.$name_safe.'">'
			.($initial !== '' ? htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') : '<i class="fa fa-user" aria-hidden="true"></i>').'</a>';
	}else{
		$html .= '<a class="cockpit-avatar cockpit-avatar-guest" href="./login.php" title="登录"><i class="fa fa-user-o" aria-hidden="true"></i></a>';
	}
	return $html.'</div></div>';
}

/**
 * 渐变仪表盘风：顶部那张渐变额度卡。
 * 左边是已用存储量，右边的圆环走“今日上传 / 每日上限”；没有上限的账号圆环画满，中间写“不限”。
 */
function layout_render_cockpit_quota($DB, $used_bytes, $total_files, $today_site){
	global $islogin2, $userrow;
	$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
	$size_limit = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
	$today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
	$percent = $limit > 0 ? min(100, round($today / $limit * 100)) : 100;
	$ring_text = $limit > 0 ? $percent.'%' : '不限';
	//圆环半径 54，周长 2πr ≈ 339.3，按百分比截出实线段
	$dash = round(339.3 * $percent / 100, 1);

	$used = size_format($used_bytes ? $used_bytes : 0);
	$parts = explode(' ', $used);
	$used_num = isset($parts[0]) ? $parts[0] : '0';
	$used_unit = isset($parts[1]) ? $parts[1] : 'B';

	$pills = [];
	$pills[] = ['fa-bolt', $limit > 0 ? ('今日额度 '.$today.' / '.$limit) : ('今日已传 '.$today.' 个')];
	$pills[] = ['fa-file-o', $size_limit > 0 ? ('单文件 '.$size_limit.' MB') : '单文件不限大小'];
	if(!empty($islogin2)){
		$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
		if(empty($expire)){
			$pills[] = ['fa-shield', '权限永久有效'];
		}elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){
			$pills[] = ['fa-exclamation-circle', '权限已过期'];
		}else{
			$left = max(1, ceil((strtotime($expire) - time()) / 86400));
			$pills[] = ['fa-clock-o', '权限剩 '.$left.' 天'];
		}
	}elseif(function_exists('is_buy_open') && is_buy_open()){
		$pills[] = ['fa-user-circle', '登录后额度独立计算'];
	}

	$html = '<section class="cockpit-quota">'
		.'<div class="cockpit-quota-main">'
		.'<span class="cockpit-quota-label"><i class="fa fa-database" aria-hidden="true"></i> 已用存储</span>'
		.'<div class="cockpit-quota-num"><strong>'.htmlspecialchars($used_num, ENT_QUOTES, 'UTF-8').'</strong>'
		.'<span>'.htmlspecialchars($used_unit, ENT_QUOTES, 'UTF-8').'</span></div>'
		.'<p class="cockpit-quota-sub">共 '.number_format($total_files).' 个文件 · 今日新增 '.number_format($today_site).' 个</p>'
		.'<div class="cockpit-quota-pills">';
	foreach($pills as $p){
		$html .= '<span><i class="fa '.$p[0].'" aria-hidden="true"></i> '.htmlspecialchars($p[1], ENT_QUOTES, 'UTF-8').'</span>';
	}
	$html .= '</div></div>'
		.'<div class="cockpit-ring">'
		.'<svg viewBox="0 0 128 128" aria-hidden="true">'
		.'<circle class="cockpit-ring-bg" cx="64" cy="64" r="54"></circle>'
		.'<circle class="cockpit-ring-fg" cx="64" cy="64" r="54" stroke-dasharray="'.$dash.' 339.3"></circle>'
		.'</svg>'
		.'<div class="cockpit-ring-text"><strong>'.htmlspecialchars($ring_text, ENT_QUOTES, 'UTF-8').'</strong><span>今日额度</span></div>'
		.'</div></section>';
	return $html;
}

/**
 * 渐变仪表盘风：右侧栏（存储分布 / 最近上传 / 快捷入口）。
 * 类型统计是调用方已经查好的那一份，这里只有“最近上传”会再查一次（带缓存）。
 */
function layout_render_cockpit_side($DB, $counts, $where_sql){
	global $conf, $islogin2;
	$counts = is_array($counts) ? $counts : [];
	$total = isset($counts['']) ? intval($counts['']) : 0;
	$groups = [
		['image', '图片'],
		['video', '视频'],
		['doc', '文档'],
		['archive', '压缩包'],
	];
	$known = 0;
	foreach($groups as $g){ $known += isset($counts[$g[0]]) ? intval($counts[$g[0]]) : 0; }
	//一个扩展名只会落进一个分组，剩下的都算“其他”；负数说明统计口径对不上，兜底成 0
	$other = max(0, $total - $known);

	$bar = '';
	$legend = '';
	foreach($groups as $g){
		$num = isset($counts[$g[0]]) ? intval($counts[$g[0]]) : 0;
		$pct = $total > 0 ? round($num / $total * 100, 2) : 0;
		if($pct > 0) $bar .= '<i class="cockpit-seg cockpit-seg-'.$g[0].'" style="width:'.$pct.'%"></i>';
		$legend .= '<div class="cockpit-dist-row"><span class="cockpit-dot cockpit-seg-'.$g[0].'"></span>'
			.'<em>'.$g[1].'</em><b>'.number_format($num).'</b></div>';
	}
	$other_pct = $total > 0 ? round($other / $total * 100, 2) : 0;
	if($other_pct > 0) $bar .= '<i class="cockpit-seg cockpit-seg-other" style="width:'.$other_pct.'%"></i>';
	$legend .= '<div class="cockpit-dist-row"><span class="cockpit-dot cockpit-seg-other"></span>'
		.'<em>其他</em><b>'.number_format($other).'</b></div>';
	if($bar === '') $bar = '<i class="cockpit-seg cockpit-seg-empty" style="width:100%"></i>';

	$feed = '';
	foreach(layout_recent_uploads($DB, $where_sql, 6) as $row){
		//列表页的文件名是直接 echo 的（上传时已清洗过），这里仍然按不可信内容转义一次
		$name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
		$href = './file.php?hash='.urlencode($row['token']);
		$icon = function_exists('type_to_icon') ? type_to_icon($row['type']) : 'fa-file-o';
		$feed .= '<a class="cockpit-feed-item" href="'.$href.'" data-group="'.layout_type_group($row['type']).'" title="'.$name.'">'
			.'<span class="cockpit-feed-icon"><i class="fa '.$icon.'" aria-hidden="true"></i></span>'
			.'<span class="cockpit-feed-body"><b>'.$name.'</b>'
			.'<em>'.htmlspecialchars(size_format($row['size']), ENT_QUOTES, 'UTF-8').' · '.layout_time_ago($row['addtime']).'</em></span></a>';
	}
	if($feed === '') $feed = '<p class="cockpit-empty">还没有人上传过文件。</p>';

	//快捷入口只放当前站点真的开着的功能，关掉的入口不出现
	$links = [];
	$links[] = ['./upload.php', 'fa-cloud-upload', '上传文件'];
	$links[] = [!empty($islogin2) ? './user.php?tab=files' : './?m=mine', 'fa-folder-open', '我的文件'];
	if(function_exists('is_buy_open') && is_buy_open()) $links[] = ['./buy.php', 'fa-shopping-cart', '购买权限'];
	if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1) $links[] = ['./sponsor.php', 'fa-money', '赞助名单'];
	$link_html = '';
	foreach($links as $l){
		$link_html .= '<a class="cockpit-link" href="'.$l[0].'"><i class="fa '.$l[1].'" aria-hidden="true"></i>'
			.'<span>'.$l[2].'</span><i class="fa fa-angle-right cockpit-link-arrow" aria-hidden="true"></i></a>';
	}

	return '<aside class="cockpit-side">'
		.'<section class="cockpit-panel"><div class="cockpit-panel-head"><strong>存储分布</strong><small>按文件类型</small></div>'
		.'<div class="cockpit-dist-bar">'.$bar.'</div><div class="cockpit-dist-list">'.$legend.'</div></section>'
		.'<section class="cockpit-panel"><div class="cockpit-panel-head"><strong>最近上传</strong><small>最新 6 条</small></div>'
		.'<div class="cockpit-feed">'.$feed.'</div></section>'
		.'<section class="cockpit-panel"><div class="cockpit-panel-head"><strong>快捷入口</strong></div>'
		.'<div class="cockpit-links">'.$link_html.'</div></section>'
		.'</aside>';
}
