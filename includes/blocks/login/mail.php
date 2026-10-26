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
	'description' => '邮箱登录、注册、找回密码在后台「用户登录设置」里开关',
	'schema' => [
		['key'=>'tab_login', 'type'=>'text', 'label'=>'登录页签', 'default'=>'邮箱登录'],
		['key'=>'tab_reg', 'type'=>'text', 'label'=>'注册页签', 'default'=>'注册'],
		['key'=>'btn_login', 'type'=>'text', 'label'=>'登录按钮', 'default'=>'登录'],
		['key'=>'btn_reg', 'type'=>'text', 'label'=>'注册按钮', 'default'=>'注册并登录'],
		['key'=>'forget_text', 'type'=>'text', 'label'=>'忘记密码链接', 'default'=>'忘记密码？'],
		['key'=>'forget_tip', 'type'=>'text', 'label'=>'找回密码页的说明', 'default'=>'填注册时用的邮箱，我们会发一封验证码过去。改完密码所有设备都要重新登录。', 'help'=>'清空不显示'],
		['key'=>'btn_reset', 'type'=>'text', 'label'=>'重置密码按钮', 'default'=>'重置密码'],
	],
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
