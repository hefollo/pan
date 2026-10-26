<?php
/* 账号设置：修改昵称（昵称入库时已经转义过，旧代码原样输出） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '修改昵称',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'修改昵称'],
		['key'=>'btn', 'type'=>'text', 'label'=>'保存按钮', 'default'=>'保存'],
	],
	'data' => function($cfg, &$ctx){
		global $userrow;
		return ['nickname_html' => $userrow['nickname']];
	},
];
