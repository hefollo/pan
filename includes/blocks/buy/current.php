<?php
/* 购买页：当前等级与各项额度（只给登录用户显示） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '当前等级',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'description' => '登录后才显示',
	'schema' => [
		['key'=>'label', 'type'=>'text', 'label'=>'前面的文字', 'default'=>'当前等级'],
		['key'=>'show_limit', 'type'=>'switch', 'label'=>'显示每日上传', 'default'=>1],
		['key'=>'show_size', 'type'=>'switch', 'label'=>'显示单文件大小', 'default'=>1],
		['key'=>'show_speed', 'type'=>'switch', 'label'=>'显示下载速度', 'default'=>1],
		['key'=>'show_traffic', 'type'=>'switch', 'label'=>'显示分享流量', 'default'=>1],
		['key'=>'show_upload', 'type'=>'switch', 'label'=>'显示上传流量', 'default'=>1],
		['key'=>'show_edit', 'type'=>'switch', 'label'=>'显示在线编辑', 'default'=>1],
	],
	'data' => function($cfg, &$ctx){
		global $islogin2, $userrow;
		if(!$islogin2)return null;
		$bonus = user_bonus_limit($userrow);
		return [
			'level_text' => user_level_text($userrow),
			'limit_text' => limit_number_text(get_effective_upload_count_limit(), '个/天'),
			'bonus' => ($bonus > 0 && get_effective_upload_count_limit() > 0) ? $bonus : 0,
			'bonus_expire_text' => empty($userrow['bonus_expire']) ? '永久' : $userrow['bonus_expire'].' 到期',
			'size_text' => size_mb_text(get_effective_upload_size_limit()),
			'speed_text' => speed_text(get_effective_download_speed_kbps()),
			'traffic_text' => user_traffic_text($userrow),
			'upload_text' => upload_traffic_text(),
			'edit_text' => current_online_edit_text(),
		];
	},
];
