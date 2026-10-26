<?php
/* 登录页：站长一种登录方式都没配时的提示 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '无法登录提示',
	'group' => '登录页',
	'scope' => 'front',
	'pages' => ['login'],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		return (!$d['has_oauth'] && !$d['show_mail']) ? [] : null;
	},
];
