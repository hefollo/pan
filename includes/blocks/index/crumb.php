<?php
/* 面包屑：工作空间 > 全部文件 / 我的文件（深色工作台风） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '面包屑',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'data' => function($cfg, &$ctx){
		return ['label' => !empty($ctx['data']['is_mine']) ? '我的文件' : '全部文件'];
	},
];
