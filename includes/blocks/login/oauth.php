<?php
/* 登录页：QQ / 微信快捷登录按钮（底色、图标在后台「用户登录设置」里改） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '快捷登录',
	'group' => '登录页',
	'scope' => 'front',
	'pages' => ['login'],
	'description' => 'QQ / 微信按钮的颜色和图标在后台「用户登录设置」里改',
	'schema' => [
		['key'=>'divider', 'type'=>'text', 'label'=>'和邮箱登录之间的分隔文字', 'default'=>'其他方式登录'],
		['key'=>'choose', 'type'=>'text', 'label'=>'只有快捷登录时的提示', 'default'=>'请选择登录方式'],
		['key'=>'tip', 'type'=>'text', 'label'=>'底部说明', 'default'=>'新用户快捷登录后会自动注册账号', 'help'=>'清空不显示'],
	],
	'data' => function($cfg, &$ctx){
		global $conf;
		$d = $ctx['data'];
		if(!$d['has_oauth'])return null;
		return [
			'show_mail' => $d['show_mail'],
			'qq_html' => $conf['login_qq'] ? login_btn_html('qq') : '',
			'wx_html' => $conf['login_wx'] ? login_btn_html('wx') : '',
		];
	},
];
