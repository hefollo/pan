<?php
/* 工作台家族右栏：今日上传（环形进度 + 额度条） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '今日上传面板',
	'group' => '工作台',
	'scope' => 'front',
	'data' => function($cfg, &$ctx){
		global $DB;
		$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
		$today = intval(function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0);
		$percent = $limit > 0 ? min(100, round($today / $limit * 100)) : 0;
		return [
			'today' => $today,
			'percent' => $percent,
			//圆环半径 42，周长 2πr ≈ 263.9
			'dash' => round(263.9 * $percent / 100, 1),
			'bar' => $limit > 0 ? $percent : 100,
			'limit_text' => $limit > 0 ? intval($limit) : '不限制',
		];
	},
];
