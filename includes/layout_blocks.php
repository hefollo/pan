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
		'audio' => '音频',
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
	$counts = ['' => 0, 'image' => 0, 'video' => 0, 'audio' => 0, 'doc' => 0, 'archive' => 0];
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
function layout_render_stats($counts, $today_count, $extra = ''){
	$cards = [
		['fa-files-o', 'all', number_format($counts['']), '全部文件'],
		['fa-picture-o', 'image', number_format($counts['image']), '图片文件'],
		['fa-video-camera', 'video', number_format($counts['video']), '视频文件'],
	];
	//有的外观排 5 张卡，中间插一张文档或音频；不传就还是原来的 4 张
	if($extra === 'doc')  $cards[] = ['fa-file-text-o', 'doc', number_format(isset($counts['doc']) ? $counts['doc'] : 0), '文档文件'];
	if($extra === 'audio')$cards[] = ['fa-music', 'audio', number_format(isset($counts['audio']) ? $counts['audio'] : 0), '音频文件'];
	$cards[] = ['fa-clock-o', 'today', number_format($today_count), '今日上传'];
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

/* ===================== 蓝白工作台风（studio） =====================
 * 结构：左侧白色侧栏（分组导航 + 底部升级卡）、顶部搜索条、内容区是
 * 主视觉横幅 + 四张统计卡 + 文件面板，右侧再挂一列数据面板。
 * 侧栏和顶栏在每个页面都有，所以顶栏由 header.php 输出；
 * 横幅、统计卡、右侧栏只在文件列表页出现，由 index.php 调用。
 */

/**
 * 顶部条：搜索框 + 上传按钮 + 头像。
 * 搜索走首页那套 kw 参数，和「文件列表」页里的搜索是同一个入口；
 * 站点关掉了文件搜索就不显示搜索框，只留右边的按钮。
 */
function layout_render_studio_topbar(){
	global $conf, $islogin2, $userrow;
	$kw = isset($_GET['kw']) && is_string($_GET['kw']) ? $_GET['kw'] : '';
	$html = '<header class="studio-topbar">';
	if(!empty($conf['filesearch'])){
		$html .= '<form class="studio-search" action="./" method="GET" role="search">'
			.'<i class="fa fa-search" aria-hidden="true"></i>'
			.'<input type="search" name="kw" id="studioSearch" autocomplete="off" placeholder="搜索文件名、格式、标签…" value="'.htmlspecialchars($kw, ENT_QUOTES, 'UTF-8').'" required>'
			//快捷键由 layout-studio.js 接管，没开 JS 时它就只是个说明标签
			.'<kbd class="studio-kbd">Ctrl K</kbd></form>';
	}else{
		$html .= '<div class="studio-search studio-search-off"><i class="fa fa-folder-open-o" aria-hidden="true"></i><span>'
			.htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8').'</span></div>';
	}
	$html .= '<div class="studio-topbar-side">'
		.'<a class="studio-upload" href="./upload.php"><i class="fa fa-cloud-upload" aria-hidden="true"></i> 上传文件</a>';
	if(!empty($islogin2)){
		$name = !empty($userrow['nickname']) ? $userrow['nickname'] : '我';
		$name_safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		$initial = layout_name_initial($name);
		$html .= '<a class="studio-avatar" href="./user.php" title="'.$name_safe.'">'
			.($initial !== '' ? htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') : '<i class="fa fa-user" aria-hidden="true"></i>')
			.'</a>';
	}else{
		$html .= '<a class="studio-avatar studio-avatar-guest" href="./login.php" title="登录"><i class="fa fa-user-o" aria-hidden="true"></i></a>';
	}
	return $html.'</div></header>';
}

/**
 * 侧栏底部的升级卡。购买功能没开就整块不显示（这个站根本卖不了权限，
 * 挂个买不了的入口只会误导人）；开着的话文案按登录状态和买没买过分别写。
 */
function layout_render_studio_upsell(){
	global $DB, $islogin2;
	if(!function_exists('is_buy_open') || !is_buy_open())return '';
	$plan = (!empty($islogin2) && function_exists('layout_user_plan')) ? layout_user_plan($DB) : null;
	$bought = ($plan && !empty($plan['bought']));
	$title = $bought ? '续费 / 升级权限' : '升级获取更多权限';
	$btn = $bought ? '续费权限' : '购买权限';
	return '<div class="studio-upsell">'
		.'<span class="studio-upsell-icon"><i class="fa fa-diamond" aria-hidden="true"></i></span>'
		.'<strong>'.$title.'</strong>'
		.'<p>更大的存储空间<br>更稳定的高速下载体验</p>'
		.'<a class="studio-upsell-btn" href="./buy.php">'.$btn.' <i class="fa fa-angle-right" aria-hidden="true"></i></a>'
		.'</div>';
}

/**
 * 文件列表页顶部的主视觉横幅：左边标题文案，右边一张纯 SVG 画的文件夹插画
 * （不引外部图片，跟着外观配色走）
 */
function layout_render_studio_hero($total_files, $is_mine){
	global $conf, $site_theme, $islogin2;
	$site = htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8');
	//五套外观共用同一块横幅结构，差别在于文案、手写标语和多出来的那几行卖点
	$c = [
		'kicker'=>'', 'title'=>'', 'sub'=>'', 'tags'=>'', 'slogan'=>'让文件分享<br>更简单高效！',
		'feats'=>[], 'checks'=>[], 'cta'=>false, 'badge'=>true, 'search'=>false,
	];
	switch($site_theme){
		case 'nebula':
			$c['kicker'] = '高效 · 安全 · 无限可能';
			$c['title'] = $site;
			$c['sub'] = '让文件分享更简单、更安全、更高效';
			$c['slogan'] = '好的分享<br>让世界更近';
			$c['badge'] = false;
			$c['feats'] = [
				['fa-bolt', 'blue', '极速上传', '全球加速节点'],
				['fa-shield', 'violet', '安全可靠', '多重数据保护'],
				['fa-share-alt', 'green', '随时分享', '一键生成外链'],
			];
			break;
		case 'royal':
			$c['kicker'] = '欢迎使用';
			$c['title'] = $site;
			$c['sub'] = '让文件分享更简单高效';
			$c['tags'] = '安全存储 · 极速上传 · 随时随地访问';
			$c['slogan'] = '好文件<br>值得被分享';
			$c['badge'] = false;
			break;
		case 'crisp':
			$c['title'] = '高效存储 · 自由分享';
			$c['sub'] = $site.'，让文件传输更简单、更安全、更高效。';
			$c['slogan'] = '分享创造价值<br>让数据触手可及！';
			$c['badge'] = false;
			$c['checks'] = ['高速上传下载', '多格式在线预览', '永久外链分享', '企业级安全防护'];
			$c['cta'] = true;
			break;
		case 'azure':
			$c['title'] = $site.'<br><span>让文件分享更简单</span>';
			$c['sub'] = '安全存储 · 高速下载 · 永久分享';
			$c['slogan'] = '你的文件<br>触手可达！';
			$c['badge'] = false;
			$c['feats'] = [
				['fa-download', 'blue', '高速下载', ''],
				['fa-lock', 'violet', '安全加密', ''],
				['fa-refresh', 'green', '多端同步', ''],
			];
			break;
		case 'skyline':
			$c['title'] = '云端存储，随时随地';
			$c['sub'] = '大容量 · 高速上传下载 · 安全加密 · 永久存储';
			$c['slogan'] = '让文件随心<br>去到任何地方';
			$c['badge'] = false;
			$c['search'] = true;   //这套外观的搜索框在横幅里，不在顶栏
			break;
		case 'neo':
			$c['title'] = '让分享更有态度';
			$c['sub'] = '高效的文件管理与分享平台，简单 · 安全 · 永久可用';
			$c['slogan'] = '文件不止存储<br>更是连接世界的方式！';
			$c['badge'] = false;
			$c['cta'] = true;
			break;
		default:
			//蓝白工作台风：横幅就是当前列表的标题
			$c['title'] = $is_mine ? '我的文件' : '文件列表';
			$c['sub'] = $is_mine ? '这里是你上传过的全部文件，可随时下载或复制外链。' : '管理、预览并分享你上传的所有内容。';
	}

	$body = '';
	if($c['kicker'] !== '')$body .= '<span class="studio-hero-kicker">'.$c['kicker'].'</span>';
	$body .= '<h1>'.$c['title'].'</h1>';
	if($c['sub'] !== '')$body .= '<p>'.$c['sub'].'</p>';
	if($c['tags'] !== '')$body .= '<p class="studio-hero-tags">'.$c['tags'].'</p>';
	if($c['checks']){
		$body .= '<div class="studio-hero-checks">';
		foreach($c['checks'] as $t){
			$body .= '<span><i class="fa fa-check-circle" aria-hidden="true"></i> '.$t.'</span>';
		}
		$body .= '</div>';
	}
	if($c['cta']){
		//两个按钮都指向站里真有的页面，没有的功能不摆空按钮
		$mine = !empty($islogin2) ? './user.php?tab=files' : './?m=mine';
		$up_text = ($site_theme === 'neo') ? '立即上传' : '上传文件';
		$body .= '<div class="studio-hero-cta">'
			.'<a class="studio-hero-btn primary" href="./upload.php"><i class="fa fa-upload" aria-hidden="true"></i> '.$up_text.'</a>'
			.'<a class="studio-hero-btn" href="'.$mine.'"><i class="fa fa-link" aria-hidden="true"></i> 我的文件</a></div>';
	}
	//横幅里的大搜索框：走首页那套 kw 参数，站点关掉搜索功能就不出现
	if($c['search'] && !empty($conf['filesearch'])){
		$kw = isset($_GET['kw']) && is_string($_GET['kw']) ? $_GET['kw'] : '';
		$body .= '<form class="studio-hero-search" action="./" method="GET" role="search">'
			.'<i class="fa fa-search" aria-hidden="true"></i>'
			.'<input type="search" name="kw" autocomplete="off" placeholder="搜索文件名、格式、标签等…" value="'.htmlspecialchars($kw, ENT_QUOTES, 'UTF-8').'" required>'
			.'<button type="submit"><i class="fa fa-search" aria-hidden="true"></i> 搜索</button></form>';
	}
	if($c['feats']){
		$body .= '<div class="studio-hero-feats">';
		foreach($c['feats'] as $f){
			$body .= '<span class="studio-feat"><i class="fa '.$f[0].' studio-feat-'.$f[1].'" aria-hidden="true"></i>'
				.'<b>'.$f[2].'</b>'.($f[3] !== '' ? '<em>'.$f[3].'</em>' : '').'</span>';
		}
		$body .= '</div>';
	}

	$html = '<section class="studio-hero"><div class="studio-hero-main">';
	if($c['badge'])$html .= '<span class="studio-hero-badge"><i class="fa fa-folder-open" aria-hidden="true"></i></span>';
	$html .= '<div class="studio-hero-copy">'.$body.'</div></div>'
		.'<span class="studio-hero-slogan">'.$c['slogan'].'</span>'
		.'<span class="studio-hero-art" aria-hidden="true">'.layout_studio_art().'</span>'
		.'</section>';
	return $html;
}

/**
 * 列表下面那条推广横幅（紫韵会员风 / 蓝天白云风才有）。
 * 卖点文案是固定的，但「立即升级」按钮只有真开了购买功能才出现。
 */
function layout_render_studio_promo(){
	global $site_theme;
	if(!in_array($site_theme, ['royal', 'azure'], true))return '';
	$buy = function_exists('is_buy_open') && is_buy_open();
	if($site_theme === 'azure' && !$buy)return '';   //这条本身就是升级广告，买不了就别出现
	$title = $site_theme === 'azure' ? '升级会员，解锁更多强大功能' : '安全、快速、无限可能';
	$sub = $site_theme === 'azure' ? '更大的存储空间 · 更稳定的高速下载 · 专属高级功能' : '让数据创造更多价值';
	$chips = $site_theme === 'azure'
		? [['fa-database', '大容量存储'], ['fa-download', '高速下载通道'], ['fa-clock-o', '文件永久保存'], ['fa-headphones', '专属客服支持']]
		: [['fa-lock', '多重安全加密'], ['fa-rocket', '高速稳定传输'], ['fa-globe', '全球内容分发'], ['fa-magic', '简单高效易用']];
	$html = '<section class="studio-promo"><div class="studio-promo-main">'
		.'<h3>'.$title.'</h3><p>'.$sub.'</p><div class="studio-promo-chips">';
	foreach($chips as $ch){
		$html .= '<span><i class="fa '.$ch[0].'" aria-hidden="true"></i> '.$ch[1].'</span>';
	}
	$html .= '</div></div>';
	$html .= '<span class="studio-promo-slogan">'.($site_theme === 'azure' ? '不只是存储<br>更是无限可能！' : '存储美好<br>分享精彩').'</span>';
	if($buy)$html .= '<a class="studio-promo-btn" href="./buy.php"><i class="fa fa-diamond" aria-hidden="true"></i> 立即升级 <i class="fa fa-angle-right" aria-hidden="true"></i></a>';
	return $html.'</section>';
}

//横幅右侧那张插画：文件夹 + 云，纯 SVG，颜色用外观变量，换主色时跟着变
function layout_studio_art(){
	return '<svg viewBox="0 0 240 170" xmlns="http://www.w3.org/2000/svg">'
		.'<defs>'
		.'<linearGradient id="studioArtA" x1="0" y1="0" x2="1" y2="1">'
		.'<stop offset="0" stop-color="#c9dcff"/><stop offset="1" stop-color="#7ba4fb"/></linearGradient>'
		.'<linearGradient id="studioArtB" x1="0" y1="0" x2="0" y2="1">'
		.'<stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#dbe7ff"/></linearGradient>'
		.'</defs>'
		.'<ellipse cx="128" cy="148" rx="86" ry="12" fill="#dfe8fb"/>'
		//文件夹后板
		.'<path d="M46 52a12 12 0 0 1 12-12h38l14 16h60a12 12 0 0 1 12 12v62a12 12 0 0 1-12 12H58a12 12 0 0 1-12-12z" fill="url(#studioArtA)"/>'
		//里面露出的纸张
		.'<rect x="66" y="52" width="120" height="56" rx="8" fill="url(#studioArtB)"/>'
		.'<rect x="80" y="68" width="62" height="7" rx="3.5" fill="#c3d5f7"/>'
		.'<rect x="80" y="83" width="40" height="7" rx="3.5" fill="#dbe6fb"/>'
		//文件夹前板
		.'<path d="M40 76h156a10 10 0 0 1 9.8 12l-10 54a12 12 0 0 1-11.8 9.7H52A12 12 0 0 1 40.2 142l-10-54A10 10 0 0 1 40 76z" fill="#9dbdfd" opacity=".95"/>'
		//云
		.'<g transform="translate(150 18)">'
		.'<path d="M18 34a15 15 0 0 1 1.8-29.9A21 21 0 0 1 58 12a13 13 0 0 1-2 26z" fill="#ffffff"/>'
		.'<path d="M18 34a15 15 0 0 1 1.8-29.9A21 21 0 0 1 58 12a13 13 0 0 1-2 26z" fill="none" stroke="#dbe7ff" stroke-width="2"/>'
		.'</g>'
		.'</svg>';
}

/**
 * 文件面板顶部的工具条：排序 + 列表/网格切换。
 * 排序是真的（index.php 按白名单拼 ORDER BY），视图切换由 layout-studio.js 存 localStorage。
 */
function layout_render_studio_tools($sort, $base_query){
	$opts = ['new'=>'上传时间 ↓', 'old'=>'上传时间 ↑', 'big'=>'文件大小 ↓', 'small'=>'文件大小 ↑'];
	if(!isset($opts[$sort]))$sort = 'new';
	$html = '<div class="studio-tools"><div class="studio-sort"><label for="studioSort" class="sr-only">排序方式</label>'
		.'<select id="studioSort" class="studio-sort-sel" data-base="'.htmlspecialchars($base_query, ENT_QUOTES, 'UTF-8').'">';
	foreach($opts as $k => $label){
		$html .= '<option value="'.$k.'"'.($sort === $k ? ' selected' : '').'>'.$label.'</option>';
	}
	$html .= '</select><i class="fa fa-angle-down" aria-hidden="true"></i></div>'
		.'<span class="studio-viewtoggle" id="studioViewToggle">'
		.'<button type="button" class="active" data-studio-view="list" title="列表视图" aria-label="列表视图"><i class="fa fa-list" aria-hidden="true"></i></button>'
		.'<button type="button" data-studio-view="grid" title="网格视图" aria-label="网格视图"><i class="fa fa-th-large" aria-hidden="true"></i></button>'
		.'</span></div>';
	return $html;
}

/**
 * 右侧一列面板：今日上传（环形进度）、我的权限、快捷操作、最近动态。
 * 数据都来自已有的统计函数，不额外查库（最近动态那一次带缓存）。
 */
function layout_render_studio_side($DB, $where_sql, $total_files = 0){
	global $conf, $islogin2, $userrow, $site_theme;
	$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
	$size_limit = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
	$today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
	$percent = $limit > 0 ? min(100, round($today / $limit * 100)) : 0;
	//圆环半径 42，周长 2πr ≈ 263.9
	$dash = round(263.9 * $percent / 100, 1);

	//今日上传
	$p_today = '<section class="studio-panel">'
		.'<div class="studio-panel-head"><strong>今日上传</strong><span class="studio-panel-num">'.intval($today).' 个</span></div>'
		.'<div class="studio-quota">'
		.'<div class="studio-ring"><svg viewBox="0 0 100 100" aria-hidden="true">'
		.'<circle class="studio-ring-bg" cx="50" cy="50" r="42"></circle>'
		.'<circle class="studio-ring-fg" cx="50" cy="50" r="42" stroke-dasharray="'.$dash.' 263.9"></circle>'
		.'</svg><b>'.$percent.'%</b></div>'
		.'<div class="studio-quota-info">'
		.'<p>今日已上传 <b>'.intval($today).'</b> 个文件</p>'
		.'<p class="studio-quota-label">今日上传额度</p>'
		.'<span class="studio-quota-bar"><i style="width:'.($limit > 0 ? $percent : 100).'%"></i></span>'
		.'<em>'.intval($today).' / '.($limit > 0 ? intval($limit) : '不限制').'</em>'
		.'</div></div></section>';

	//我的权限
	$badge = '';
	if(!empty($islogin2)){
		$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
		if(empty($expire)) $badge = '<span class="studio-badge ok">永久有效</span>';
		elseif(function_exists('is_user_permission_active') && !is_user_permission_active()) $badge = '<span class="studio-badge bad">已过期</span>';
		else $badge = '<span class="studio-badge ok">剩 '.max(1, ceil((strtotime($expire) - time()) / 86400)).' 天</span>';
	}else{
		$badge = '<span class="studio-badge">未登录</span>';
	}
	$rows = [
		['fa-cloud-upload', 'blue', '每日上传', $limit > 0 ? ($limit.' 个') : '不限制'],
		['fa-file-o', 'indigo', '单文件大小', $size_limit > 0 ? ($size_limit.' MB') : '不限制'],
		['fa-bolt', 'amber', '下载速度', '不限制'],
	];
	//深空科技风的原型里多一行，凑够四条
	if($site_theme === 'nebula') $rows[] = ['fa-clone', 'cyan', '同时下载数', '不限制'];
	$p_right = '<section class="studio-panel studio-panel-rights">'
		.'<div class="studio-panel-head"><strong>我的权限</strong>'.$badge.'</div><div class="studio-rights">';
	foreach($rows as $r){
		$p_right .= '<div class="studio-right-row"><span class="studio-right-icon studio-ico-'.$r[1].'">'
			.'<i class="fa '.$r[0].'" aria-hidden="true"></i></span><em>'.$r[2].'</em><b>'
			.htmlspecialchars($r[3], ENT_QUOTES, 'UTF-8').'</b></div>';
	}
	$p_right .= '</div>';
	//紫韵会员风把升级入口直接做进权限卡里
	if($site_theme === 'royal' && function_exists('is_buy_open') && is_buy_open()){
		$p_right .= '<a class="studio-vip-btn" href="./buy.php"><i class="fa fa-diamond" aria-hidden="true"></i> 升级会员 <i class="fa fa-angle-right" aria-hidden="true"></i></a>';
	}
	$p_right .= '</section>';

	//云端门户风右上角那张蓝色上传卡：点了就去上传页，不做半吊子的拖拽假象
	$p_drop = '';
	if($site_theme === 'skyline'){
		$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		$p_drop = '<a class="studio-drop" href="./upload.php">'
			.'<span class="studio-drop-icon"><i class="fa fa-cloud-upload" aria-hidden="true"></i></span>'
			.'<strong>把文件交给云端</strong><em>点下面的按钮选择文件，或到上传页拖拽</em>'
			.'<span class="studio-drop-btn"><i class="fa fa-upload" aria-hidden="true"></i> 选择文件</span>'
			.'<span class="studio-drop-feats">'
			.'<i><b class="fa fa-bolt" aria-hidden="true"></b> 高速上传</i>'
			.'<i><b class="fa fa-shield" aria-hidden="true"></b> 安全加密</i>'
			.'<i><b class="fa fa-infinity fa-clock-o" aria-hidden="true"></b> '.($size > 0 ? ('单文件 '.$size.' MB') : '不限大小').'</i>'
			.'</span></a>';
	}

	//存储空间：只有清爽极简风、蓝天白云风和云端门户风的原型里有这块。
	//程序本身没有"总容量"这个概念（只限每日个数和单文件大小），所以只报已用量，不编一个假的总量
	$p_store = '';
	if(in_array($site_theme, ['crisp', 'azure', 'skyline'], true)){
		$used = function_exists('layout_storage_used') ? layout_storage_used($DB, $where_sql) : 0;
		$p_store = '<section class="studio-panel"><div class="studio-panel-head"><strong>存储空间</strong>'
			.'<span class="studio-panel-num">'.htmlspecialchars(size_format($used ? $used : 0), ENT_QUOTES, 'UTF-8').'</span></div>'
			.'<div class="studio-rights">'
			.'<div class="studio-right-row"><span class="studio-right-icon studio-ico-blue"><i class="fa fa-database" aria-hidden="true"></i></span><em>已用空间</em><b>'
			.htmlspecialchars(size_format($used ? $used : 0), ENT_QUOTES, 'UTF-8').'</b></div>'
			.'<div class="studio-right-row"><span class="studio-right-icon studio-ico-indigo"><i class="fa fa-files-o" aria-hidden="true"></i></span><em>文件数量</em><b>'
			.number_format($total_files).' 个</b></div>'
			.'<div class="studio-right-row"><span class="studio-right-icon studio-ico-amber"><i class="fa fa-file-o" aria-hidden="true"></i></span><em>单文件上限</em><b>'
			.($size_limit > 0 ? ($size_limit.' MB') : '不限制').'</b></div>'
			.'</div></section>';
	}

	//快捷操作：只放这个站真的开着的入口
	$acts = [];
	$acts[] = ['./upload.php', 'fa-cloud-upload', '上传文件', ' primary'];
	$acts[] = [!empty($islogin2) ? './user.php?tab=files' : './?m=mine', 'fa-folder-open', '我的文件', ''];
	if(function_exists('is_buy_open') && is_buy_open()) $acts[] = ['./buy.php', 'fa-shopping-cart', '购买权限', ''];
	if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1) $acts[] = ['./sponsor.php', 'fa-money', '赞助名单', ''];
	if(!isset($conf['violation_open']) || $conf['violation_open'] == 1) $acts[] = ['./violation.php', 'fa-gavel', '违规公示', ''];
	//关掉的功能不占格子；一个都没开时至少留个上传入口，别出现空面板
	if(count($acts) < 3) $acts[] = ['./upload.php', 'fa-link', '生成外链', ''];
	//最多摆四个，多了换行反而乱
	$acts = array_slice($acts, 0, 4);
	$p_acts = '<section class="studio-panel"><div class="studio-panel-head"><strong>快捷操作</strong></div><div class="studio-acts">';
	foreach($acts as $a){
		$p_acts .= '<a class="studio-act'.$a[3].'" href="'.$a[0].'"><i class="fa '.$a[1].'" aria-hidden="true"></i><span>'.$a[2].'</span></a>';
	}
	$p_acts .= '</div></section>';

	//最近动态
	$feed = '';
	foreach(layout_recent_uploads($DB, $where_sql, 5) as $row){
		$name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
		$icon = function_exists('type_to_icon') ? type_to_icon($row['type']) : 'fa-file-o';
		$feed .= '<a class="studio-feed-item" href="./file.php?hash='.urlencode($row['token']).'" title="'.$name.'">'
			.'<span class="studio-feed-icon" data-group="'.layout_type_group($row['type']).'"><i class="fa '.$icon.'" aria-hidden="true"></i></span>'
			.'<span class="studio-feed-body"><b>上传了文件 '.$name.'</b><em>'.htmlspecialchars($row['addtime'], ENT_QUOTES, 'UTF-8').'</em></span></a>';
	}
	if($feed === '') $feed = '<p class="studio-empty">还没有上传记录。</p>';
	$p_feed = '<section class="studio-panel"><div class="studio-panel-head"><strong>最近动态</strong>'
		.'<a class="studio-panel-more" href="./">全部 <i class="fa fa-angle-right" aria-hidden="true"></i></a></div>'
		.'<div class="studio-feed">'.$feed.'</div></section>';

	//面板顺序按各套外观的原型排：会员风把权限卡放最上，极简风和白云风先放存储空间
	if($site_theme === 'royal')      $order = [$p_right, $p_today, $p_acts, $p_feed];
	elseif($site_theme === 'crisp')  $order = [$p_store, $p_today, $p_right, $p_feed];
	elseif($site_theme === 'azure')  $order = [$p_today, $p_right, $p_acts, $p_feed, $p_store];
	elseif($site_theme === 'skyline')$order = [$p_drop, $p_store, $p_feed, $p_acts, $p_right];
	else                             $order = [$p_today, $p_right, $p_acts, $p_feed];

	return '<aside class="studio-side">'.implode('', $order).'</aside>';
}
