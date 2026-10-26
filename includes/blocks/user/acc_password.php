<?php
/* 账号设置：修改密码（还没设过密码的账号只给说明，绑定邮箱时会一并设置） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '密码',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $userrow;
		return ['has_pwd' => !empty($userrow['password'])];
	},
];
