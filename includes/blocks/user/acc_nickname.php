<?php
/* 账号设置：修改昵称（昵称入库时已经转义过，旧代码原样输出） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '修改昵称',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $userrow;
		return ['nickname_html' => $userrow['nickname']];
	},
];
