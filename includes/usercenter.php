<?php
/*
 * 个人中心（user.php）各卡片共用的数据：头像底下垫的图标、「我的文件」的查询条件与分页、订单分页。
 * 一次请求只算一次（theme_memo），卡片可以拆开随意摆。只给登录用户用。
 */
if(!defined('IN_CRONLITE'))exit();

/* 当前页签：页签列表在 user.php 里按站点功能开关算好放进布局数据 */
function uc_current_tab($ctx){
	return isset($ctx['data']['tab']) ? $ctx['data']['tab'] : 'overview';
}

/* 头像底下垫的图标（按注册方式）：没有头像、或者图片加载失败时露出来 */
function uc_face_icon_html(){
	global $userrow;
	return '<i class="fa fa-'.($userrow['type']=='qq'?'qq':($userrow['type']=='mail'?'envelope':'wechat')).'" aria-hidden="true"></i>';
}

/*
 * 「我的文件」：搜索、类型筛选、文件夹、分页。
 * 搜索或按类型筛选时跨全部文件夹查，结果里标出所在文件夹；否则按当前文件夹这一层显示。
 */
function uc_files_state(){
	return theme_memo('uc.files', function(){
		global $DB, $uid;
		$s = [];
		$s['kw'] = (isset($_GET['kw']) && is_string($_GET['kw'])) ? trim(strip_tags($_GET['kw'])) : '';
		$s['ft'] = (isset($_GET['ft']) && is_string($_GET['ft']) && array_key_exists($_GET['ft'], layout_type_filters())) ? $_GET['ft'] : '';
		$s['fd_on'] = can_use_folders();
		$s['fd_all'] = $s['fd_on'] ? folder_all(folder_owner(false)) : [];
		$s['fd_cur'] = $s['fd_on'] ? folder_current($s['fd_all']) : 0;
		$s['fd_searching'] = ($s['kw'] !== '' || $s['ft'] !== '');
		$s['fd_url'] = './user.php?tab=files';
		$where = " uid=".intval($uid);
		$qs = 'tab=files';
		if($s['kw'] !== ''){
			$where .= " AND name LIKE '%".daddslashes($s['kw'])."%'";
			$qs .= '&kw='.urlencode($s['kw']);
		}
		$where_base = $where;
		if($s['ft'] !== ''){
			$where .= layout_type_filter_sql($s['ft']);
			$qs .= '&ft='.urlencode($s['ft']);
		}
		//放在 $where_base 之后：筛选标签上的计数仍按全部文件算
		if($s['fd_on'] && !$s['fd_searching']){
			$where .= " AND folder_id=".$s['fd_cur'];
			if($s['fd_cur'] > 0)$qs .= '&folder='.$s['fd_cur'];
		}
		$s['where'] = $where;
		$s['where_base'] = $where_base;
		$s['qs'] = $qs;
		$s['numrows'] = intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE{$where}"));
		$s['pagesize'] = 20;
		$s['pages'] = max(1, ceil($s['numrows'] / $s['pagesize']));
		$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
		if($page > $s['pages'])$page = $s['pages'];
		$s['page'] = $page;
		return $s;
	});
}

/* 「我的文件」筛选标签上的计数：只统计当前用户，数据量小，不走缓存 */
function uc_files_counts(){
	return theme_memo('uc.files.counts', function(){
		global $DB;
		$s = uc_files_state();
		//键要跟 layout_type_filters() 一一对应，少一个（以前少了 audio）那个标签就恒为 0
		$counts = ['' => 0, 'image'=>0, 'video'=>0, 'audio'=>0, 'doc'=>0, 'archive'=>0];
		$rs = $DB->query("SELECT type, count(*) AS num FROM pre_file WHERE{$s['where_base']} GROUP BY type");
		if($rs){
			while($r = $rs->fetch()){
				$n = intval($r['num']);
				$counts[''] += $n;
				$g = layout_type_group($r['type']);
				if(isset($counts[$g]))$counts[$g] += $n;
			}
		}
		return $counts;
	});
}
