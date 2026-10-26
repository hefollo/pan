<?php
/* 账号设置：头像（上传后可裁剪，自己上传过才有「恢复默认」；脚本 usercenter.js） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '头像设置',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $userrow;
		include_once SYSTEM_ROOT.'usercenter.php';
		return [
			'face_icon_html' => uc_face_icon_html(),
			'avatar_img_html' => user_avatar_img($userrow),
			'custom' => user_avatar_is_custom($userrow['faceimg']),
		];
	},
];
