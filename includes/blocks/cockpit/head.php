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
	'schema' => [
		['key'=>'guest_name', 'type'=>'text', 'label'=>'没登录时的称呼', 'default'=>'访客'],
		['key'=>'summary', 'type'=>'text', 'label'=>'问候下面的一行', 'default'=>'今天已上传 {today} 个文件 · 当前列表共 {total} 个文件', 'help'=>'{today} 今日上传数，{total} 当前列表文件数；清空不显示'],
		['key'=>'upload_text', 'type'=>'text', 'label'=>'上传按钮', 'default'=>'上传文件'],
	],
	'data' => function($cfg, &$ctx){
		global $DB, $islogin2, $userrow;
		$logged = !empty($islogin2);
		$name = $logged && !empty($userrow['nickname']) ? $userrow['nickname'] : $cfg['guest_name'];
		$initial = layout_name_initial($name);
		$counts = layout_ctx_counts($ctx);
		$today = intval(function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0);
		return [
			'summary_html' => theme_text_html($cfg['summary'], ['today'=>'<b>'.$today.'</b>', 'total'=>'<b>'.number_format($counts['']).'</b>']),
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
