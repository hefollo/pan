<?php
/* 账号设置：修改密码（还没设过密码的账号只给说明，绑定邮箱时会一并设置） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '密码',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题（有密码时）', 'default'=>'修改密码'],
		['key'=>'btn', 'type'=>'text', 'label'=>'修改按钮', 'default'=>'修改密码'],
		['key'=>'tip', 'type'=>'text', 'label'=>'说明（有密码时）', 'default'=>'修改成功后，其它设备上的登录状态会立即失效，当前设备保持登录。', 'help'=>'清空不显示'],
		['key'=>'nopwd_title', 'type'=>'text', 'label'=>'标题（没设密码时）', 'default'=>'密码'],
		['key'=>'nopwd_tip', 'type'=>'text', 'label'=>'说明（没设密码时）', 'default'=>'当前账号还没有设置密码。上面绑定邮箱时会一并设置密码，之后就能用邮箱 + 密码登录。'],
	],
	'data' => function($cfg, &$ctx){
		global $userrow;
		return ['has_pwd' => !empty($userrow['password'])];
	},
];
