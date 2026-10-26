<?php
/* 面包屑：工作空间 > 全部文件 / 我的文件（深色工作台风） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '面包屑',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'schema' => [
		['key'=>'root', 'type'=>'text', 'label'=>'第一级文字', 'default'=>'工作空间'],
		['key'=>'all_text', 'type'=>'text', 'label'=>'全部文件时', 'default'=>'全部文件'],
		['key'=>'mine_text', 'type'=>'text', 'label'=>'我的文件时', 'default'=>'我的文件'],
	],
	'data' => function($cfg, &$ctx){
		return ['label' => !empty($ctx['data']['is_mine']) ? $cfg['mine_text'] : $cfg['all_text']];
	},
];
