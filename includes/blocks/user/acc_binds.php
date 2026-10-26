<?php
/*
 * 账号设置：登录方式（已绑定的、可绑定的快捷登录、绑定邮箱表单）。
 * 注册时用的那种登录方式不能解绑，账号至少要保留一个能登进来的入口。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '登录方式',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'登录方式'],
	],
	'data' => function($cfg, &$ctx){
		global $userrow;
		$has = [];
		$ids = [];
		foreach(user_identities($userrow) as $one){
			$has[$one['type']] = true;
			$ids[] = [
				'type' => $one['type'],
				'icon' => $one['type']=='qq' ? 'qq' : ($one['type']=='mail' ? 'envelope' : 'wechat'),
				'name' => login_type_name($one['type']),
				'primary' => !empty($one['primary']),
				'sub' => $one['type']=='mail' ? $one['openid'] : '已绑定',
			];
		}
		$oauth = [];
		foreach(bindable_login_types() as $tk => $tname){
			if(isset($has[$tk]))continue;
			$oauth[] = ['type'=>$tk, 'icon'=>$tk=='qq' ? 'qq' : 'wechat', 'name'=>$tname];
		}
		$mail_ready = is_mail_ready();
		return [
			'ids' => $ids,
			'oauth' => $oauth,
			'has_mail' => isset($has['mail']),
			'mail_ready' => $mail_ready,
			'bind_form' => !isset($has['mail']) && $mail_ready,
		];
	},
];
