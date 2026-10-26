<?php
/* 登录页标题（开着注册时是「登录 / 注册」） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '登录标题',
	'group' => '登录页',
	'scope' => 'front',
	'pages' => ['login'],
	'schema' => [
		['key'=>'text_reg', 'type'=>'text', 'label'=>'开放注册时', 'default'=>'登录 / 注册'],
		['key'=>'text_login', 'type'=>'text', 'label'=>'不开放注册时', 'default'=>'登录'],
	],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if(!$d['has_oauth'] && !$d['show_mail'])return null;
		return ['show_reg' => $d['show_reg']];
	},
];
