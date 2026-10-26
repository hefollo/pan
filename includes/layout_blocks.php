<?php
/**
 * 布局型外观用到的统计与数据函数（今日上传、类型统计、已用存储、最近上传、问候语……）。
 * 以前这里还生成各外观的统计卡、筛选标签、横幅、右侧栏的 HTML，现在那些都是卡片了
 * （includes/blocks/ 下，模板在 includes/templates/blocks/，DEC-20261025-003），这里只留卡片共用的数据。
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
	/*
	 * 缓存键里带着列表的 WHERE 片段，而这个片段有两处是访客说了算的：
	 *   ① 搜索关键词 ?kw= 会拼成 name LIKE '%...%'
	 *   ② 游客「我的文件」会拼成 id IN (本次会话传过的文件id)
	 * 每换一个值就在临时目录里多留一批 json，而且从不回收，随手刷几万个不同的 kw
	 * 就能把 inode 占满。这两种都是一次性视图，本来就要全表扫，缓存不了也没损失，
	 * 直接不给文件名（上面两个函数拿到 null 就会跳过读写）。
	 *
	 * 类型筛选拼出来的是 type IN ('jpg',...)，取值只有固定那几组，是有界的，
	 * 所以这里只认 " id IN (" 而不是笼统的 " IN ("，别把它一起误伤了。
	 * 首页、按类型筛选这些反复打开的固定视图照常走缓存。
	 */
	if(strpos($key, 'LIKE') !== false || strpos($key, ' id IN (') !== false) return null;
	return rtrim($dir, '/\\').'/mpimg_layout_'.md5(SYSTEM_ROOT.'|'.$key).'.json';
}

function layout_cache_get($key, $ttl){
	$file = layout_cache_file($key);
	if(!$file || !is_file($file)) return null;
	if(filemtime($file) + $ttl < time()) return null;
	$raw = @file_get_contents($file);
	if($raw === false) return null;
	$data = json_decode($raw, true);
	//算出这份缓存之后文件又有变动（上传、删除、改状态……，见 layout_cache_bump()），作废
	if(!is_array($data) || !isset($data['__t'], $data['d']) || !is_array($data['d'])) return null;
	if(floatval($data['__t']) < layout_cache_stamp()) return null;
	return $data['d'];
}

function layout_cache_set($key, $data){
	$file = layout_cache_file($key);
	if(!$file) return;
	//记本次请求开始的时刻，不记写入时刻：算到一半有人删了文件，这份结果也得作废
	$t = isset($_SERVER['REQUEST_TIME_FLOAT']) ? floatval($_SERVER['REQUEST_TIME_FLOAT']) : microtime(true);
	@file_put_contents($file, json_encode(['__t'=>$t, 'd'=>$data]), LOCK_EX);
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
		$num = intval($DB->getColumn("SELECT count(*) from pre_file WHERE uid='".intval($uid)."' AND addtime>='".$since."' AND copied=0"));
	}else{
		//和上传接口用同一个维度统计，否则卡片上显示的数字和实际能不能传对不上
		$num = intval($DB->getColumn("SELECT count(*) from pre_file WHERE ipkey=:k AND addtime>=:t AND copied=0", [':k'=>client_ip_key(), ':t'=>$since]));
	}
	$_SESSION['layout_today'] = ['who'=>$who, 'day'=>$day, 'num'=>$num, 'time'=>time()];
	return $num;
}

/*
 * 紧凑权限条（common.permbar 卡片）的数据：今日上传、单文件大小、有效期、加量包、购买入口。
 * 哪些外观不放权限条（侧栏里已有权限卡的、仪表盘首页已有额度卡的）由各外观的默认布局决定。
 */
function permission_bar_data($where = 'list'){
	global $DB, $conf, $islogin2, $userrow;
	$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
	$today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
	$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;

	$items = [];
	$items[] = ['icon'=>'fa-cloud-upload', 'label'=>'今日上传', 'value'=>$limit > 0 ? ($today.' / '.$limit) : ($today.' 个（不限）')];
	$items[] = ['icon'=>'fa-file-o', 'label'=>'单文件', 'value'=>$size > 0 ? ($size.' MB') : '不限制'];

	if(!empty($islogin2)){
		$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
		if(empty($expire)){
			$items[] = ['icon'=>'fa-clock-o', 'label'=>'有效期', 'value'=>'永久有效'];
		}elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){
			$items[] = ['icon'=>'fa-clock-o', 'label'=>'有效期', 'value'=>'已过期'];
		}else{
			$left = max(1, ceil((strtotime($expire) - time()) / 86400));
			$items[] = ['icon'=>'fa-clock-o', 'label'=>'有效期', 'value'=>'剩 '.$left.' 天'];
		}
		if(function_exists('user_bonus_limit') && user_bonus_limit($userrow) > 0 && $limit > 0){
			$items[] = ['icon'=>'fa-plus-circle', 'label'=>'加量包', 'value'=>'+'.user_bonus_limit($userrow).' 个/天'];
		}
	}
	$data = ['items'=>$items, 'where'=>$where];
	if(function_exists('is_buy_open') && is_buy_open()){
		$data['buy_text'] = !empty($islogin2) ? '购买权限' : '登录后可购买更多额度';
	}elseif(empty($islogin2) && !empty($conf['userlogin'])){
		$data['login_tip'] = true;
	}
	return $data;
}

/**
 * 当前用户的会员等级，给侧栏“我的权限”卡和个人中心显示用。
 * bought 表示现在有一个没过期的会员等级（或管理员等级），plan_name 是等级名（键名是改成会员等级之前起的）
 */
function layout_user_plan($DB){
	global $islogin2, $userrow;
	if(empty($islogin2) || !function_exists('user_level'))return null;
	$level = user_level($userrow);
	$member = level_is_custom($level) || level_is_admin($level);
	return ['bought' => $member, 'plan_name' => $level['name'], 'paytime' => ''];
}

/**
 * 全站今日上传数量，给数据控制台风的统计卡用，缓存 5 分钟
 */
function layout_today_total($DB, $where_sql){
	$key = 'today|'.$where_sql.'|'.date('Y-m-d');
	$hit = layout_cache_get($key, 300);
	if($hit !== null && isset($hit['num'])) return intval($hit['num']);
	$num = intval($DB->getColumn("SELECT count(*) from pre_file WHERE{$where_sql} AND addtime>='".date('Y-m-d 00:00:00')."' AND copied=0"));
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
 * 扩展名 -> 分组 的反查表，一次建好给按 type 归组的地方复用。
 * 同一个扩展名可能出现在多张表里（后台的「图片格式」「视频格式」是站长自己填的），
 * 先出现的分组优先，顺序跟筛选标签一致；后台首页的类型统计也用这张表，
 * 保证前台标签和后台首页算出来的是同一套数字。
 */
function layout_type_group_lookup(){
	static $lookup = null;
	if($lookup !== null) return $lookup;
	$lookup = [];
	foreach(['image','video','audio','doc','archive'] as $g){
		foreach(layout_type_group_exts($g) as $ext){
			if(!isset($lookup[$ext])) $lookup[$ext] = $g;
		}
	}
	return $lookup;
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
	/*
	 * GROUP BY type 在没有 type 索引的大表上是全表扫描，缓存 5 分钟。
	 * 缓存键里的 counts2 是第二版：第一版漏统计音频，标签上的「音频」恒为 0，
	 * 换个键让旧缓存直接作废，不用等 5 分钟过期。
	 */
	$cache_key = 'counts2|'.$where_sql;
	$hit = layout_cache_get($cache_key, 300);
	if($hit !== null){
		foreach($counts as $k => $v){ if(isset($hit[$k])) $counts[$k] = intval($hit[$k]); }
		return $counts;
	}
	//分组表统一从 layout_type_group_lookup() 取，漏掉某一组就会像以前的音频那样恒为 0
	$lookup = layout_type_group_lookup();
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
	$lookup = layout_type_group_lookup();
	$type = strtolower((string)$type);
	return isset($lookup[$type]) ? $lookup[$type] : 'other';
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

/* ===================== 首页卡片共用的数据 =====================
 * 多张卡片要用同一份统计（类型计数、今日上传、已用存储）时只算一次；
 * 条件来自 index.php 交给布局的 $ctx['data']['sql_base']（不带类型筛选的列表条件）。
 * 别的页面放了这些卡片、没有 sql_base 时按首页的公共列表算。
 */
function layout_ctx_where($ctx){
	return isset($ctx['data']['sql_base']) ? $ctx['data']['sql_base'] : " hide=0 AND block=0";
}

function layout_ctx_counts($ctx){
	global $DB;
	$where = layout_ctx_where($ctx);
	return theme_memo('counts|'.$where, function() use ($DB, $where){ return layout_type_counts($DB, $where); });
}

function layout_ctx_today_total($ctx){
	global $DB;
	$where = layout_ctx_where($ctx);
	return theme_memo('today|'.$where, function() use ($DB, $where){ return layout_today_total($DB, $where); });
}

/* 列表地址上要保留的 m / kw 参数（类型筛选、排序的链接都要带上） */
function layout_ctx_base_query($ctx){
	$d = isset($ctx['data']) ? $ctx['data'] : [];
	$q = '';
	if(!empty($d['is_mine']))$q .= 'm=mine';
	if(!empty($d['kw']))$q .= ($q === '' ? '' : '&').'kw='.urlencode($d['kw']);
	return $q;
}

/*
 * 列表翻页的那一串链接（首页、«、页码、»、尾页），和首页文件列表同一套规则：每 10 页一组。
 * $prefix 是「xxx.php?page=」，$link 是跟在页码后面的其它参数（已经 urlencode 过，原样拼进 href）。
 */
function layout_pager_items($page, $pages, $prefix, $link = ''){
	$items = [];
	$item = function($disabled, $p, $label) use ($prefix, $link){
		return ['disabled'=>$disabled, 'href_html'=>$disabled ? '' : $prefix.$p.$link, 'label_html'=>$label];
	};
	if($page > 1){
		$items[] = $item(false, 1, '首页');
		$items[] = $item(false, $page - 1, '&laquo;');
	}else{
		$items[] = $item(true, 0, '首页');
		$items[] = $item(true, 0, '&laquo;');
	}
	$pagegroup = 10;
	$start = $page < $pagegroup ? 1 : floor($page / $pagegroup) * $pagegroup;
	$end = min($start + $pagegroup, $pages);
	for($i = $start; $i <= $end; $i++){
		$items[] = $i == $page ? $item(true, 0, (string)$i) : $item(false, $i, (string)$i);
	}
	if($page < $pages){
		$items[] = $item(false, $page + 1, '&raquo;');
		$items[] = $item(false, $pages, '尾页');
	}else{
		$items[] = $item(true, 0, '&raquo;');
		$items[] = $item(true, 0, '尾页');
	}
	return $items;
}

/* ===== 通用展示卡片（includes/blocks/common/hero|media|buttons|features，DEC-20261026-001）共用 ===== */

/* 卡片里站长填的链接：只收 http(s) 和站内相对地址（./xxx、/xxx、?xxx、#xxx），挡掉 javascript: 之类；不合格返回空串 */
function common_card_href($u){
	$u = trim((string)$u);
	if($u === '' || strlen($u) > 500)return '';
	return preg_match('#^(https?://[^\s"\'<>]+|(?![A-Za-z][A-Za-z0-9+.\-]*:)[^\s"\'<>]+)$#', $u) ? $u : '';
}

/*
 * 站长自己写的链接列表（快捷入口、快捷操作卡片，DEC-20261026-002）：一行一个「文字|链接|图标」，
 * 图标是 Font Awesome 4 的 fa-xxx，不写或写错用 fa-link；链接不合格的行跳过。返回 [{label, href, icon}]，没有合格的行返回空数组。
 */
function theme_link_lines($text){
	$out = [];
	foreach(theme_config_lines($text, 3) as $row){
		list($label, $href, $icon) = $row;
		$href = common_card_href($href);
		if($label === '' || $href === '')continue;
		$out[] = ['label'=>$label, 'href'=>$href, 'icon'=>preg_match('/^fa-[a-z0-9\-]{1,30}$/', $icon) ? $icon : 'fa-link'];
	}
	return $out;
}

/* 多行说明拆成段落数组 [{text}]，空行去掉 */
function common_card_lines($text){
	$out = [];
	foreach(preg_split('/\r?\n/', (string)$text) as $line){
		$line = trim($line);
		if($line !== '')$out[] = ['text'=>$line];
	}
	return $out;
}
