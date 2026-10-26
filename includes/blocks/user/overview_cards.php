<?php
/* 个人中心概览：我的文件数、占用空间、今日上传、单文件上限四张卡（uid 上有索引，一次查出总数和总占用） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '概览数字卡',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $DB, $uid;
		$limit = get_effective_upload_count_limit();
		$today = layout_today_upload_count($DB);
		$stat = theme_memo('uc.stat', function() use ($DB, $uid){
			return $DB->getRow("SELECT count(*) AS num, COALESCE(SUM(size),0) AS bytes FROM pre_file WHERE uid=:uid", [':uid'=>intval($uid)]);
		});
		return [
			'num' => number_format($stat ? intval($stat['num']) : 0),
			'bytes' => size_format($stat ? floatval($stat['bytes']) : 0),
			'today_text' => $limit > 0 ? $today.' / '.$limit : $today,
			'limited' => $limit > 0,
			'size_text_html' => limit_number_text(get_effective_upload_size_limit(), 'MB'),
		];
	},
];
