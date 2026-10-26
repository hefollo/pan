<?php
/* 登录页：站长一种登录方式都没配时的提示 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '无法登录提示',
	'group' => '登录页',
	'scope' => 'front',
	'pages' => ['login'],
	'description' => '一种登录方式都没开时才显示',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'暂时无法登录'],
		['key'=>'text', 'type'=>'text', 'label'=>'说明', 'default'=>'站长还没有配置任何登录方式，请稍后再来。'],
	],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		return (!$d['has_oauth'] && !$d['show_mail']) ? [] : null;
	},
];
