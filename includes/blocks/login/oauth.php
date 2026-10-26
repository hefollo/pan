<?php
/* 登录页：QQ / 微信快捷登录按钮（底色、图标在后台「用户登录设置」里改） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '快捷登录',
	'group' => '登录页',
	'scope' => 'front',
	'pages' => ['login'],
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
