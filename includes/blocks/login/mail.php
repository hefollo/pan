<?php
/*
 * 登录页：邮箱登录 / 注册 / 找回密码三个标签（脚本在页脚之后，表单的 id 脚本要用）。
 * 邮箱登录入口：开着注册就一定显示；关掉注册后，只要站里已经有邮箱账号也要留着入口。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '邮箱登录与注册',
	'group' => '登录页',
	'scope' => 'front',
	'pages' => ['login'],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if(!$d['show_mail'])return null;
		return [
			'show_reg' => $d['show_reg'],
			'show_forget' => $d['show_forget'],
			'captcha' => $d['captcha_question'],
		];
	},
];
