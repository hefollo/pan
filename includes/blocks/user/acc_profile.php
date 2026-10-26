<?php
/* 账号设置：账号资料（注册方式、注册时间、最后登录） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '账号资料',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'账号资料'],
	],
	'data' => function($cfg, &$ctx){
		global $userrow;
		return [
			'type_name' => login_type_name($userrow['type']),
			'is_mail' => $userrow['type'] == 'mail',
			'addtime' => $userrow['addtime'],
			'lasttime' => $userrow['lasttime'],
			'loginip' => !empty($userrow['loginip']) ? preg_replace('/\d+$/', '*', $userrow['loginip']) : '',
		];
	},
];
