<?php
/*
 * 渐变仪表盘风：顶部问候栏（问候语 + 今日 / 当前列表文件数 + 上传入口 + 头像）。
 * 写「当前列表共」而不是「站内共」：?m=mine 时是这个人自己的文件数。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '问候栏',
	'group' => '仪表盘',
	'scope' => 'front',
	'data' => function($cfg, &$ctx){
		global $DB, $islogin2, $userrow;
		$logged = !empty($islogin2);
		$name = $logged && !empty($userrow['nickname']) ? $userrow['nickname'] : '访客';
		$initial = layout_name_initial($name);
		$counts = layout_ctx_counts($ctx);
		return [
			'greeting' => layout_greeting(),
			'name' => $name,
			'today' => intval(function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0),
			'total' => number_format($counts['']),
			'logged_in' => $logged,
			'initial' => $initial,
			'has_initial' => $initial !== '',
			'avatar_img_html' => $logged ? user_avatar_img($userrow) : '',
		];
	},
];
