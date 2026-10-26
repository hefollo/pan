<?php
/*
 * 侧栏底部的今日上传统计卡（侧栏型外观用：控制台侧栏风、数据控制台风、深色工作台风）。
 * 统计走会话缓存，pre_file 上没有 ip/addtime 索引，不能每次打开页面都扫一遍。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '今日上传',
	'group' => '侧栏',
	'scope' => 'front',
	'pages' => ['_shell'],
	'data' => function($cfg, &$ctx){
		global $DB;
		$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
		$today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
		$percent = $limit > 0 ? min(100, round($today / $limit * 100)) : min(100, $today * 10);
		return [
			'count_text' => $limit > 0 ? $today.' / '.$limit : $today.' 个',
			'percent' => intval($percent),
			'tip' => $limit > 0 ? ('今日还可上传 '.max(0, $limit - $today).' 个文件') : '当前账号不限每日上传数量',
		];
	},
];
