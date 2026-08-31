<?php
/**
 * 布局型外观（控制台侧栏风 / 数据控制台风 / 上传门户风 / 深色工作台风）额外用到的结构块。
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
