<?php
/*
 * 个人中心的一个页签：只有当前打开的是这个页签时才输出里面的卡片。
 * 页签有没有（订单要开着购买功能，API 要站点开着且等级有权限）由 user.php 决定，
 * 打不开的页签地址会退回「概览」。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '页签内容',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'container' => true,
	'schema' => [
		['key'=>'tab', 'type'=>'select', 'label'=>'页签', 'default'=>'overview', 'options'=>['overview'=>'概览', 'files'=>'我的文件', 'orders'=>'订单记录', 'api'=>'上传 API 密钥', 'account'=>'账号设置']],
	],
	'data' => function($cfg, &$ctx){
		include_once SYSTEM_ROOT.'usercenter.php';
		return uc_current_tab($ctx) === $cfg['tab'] ? [] : null;
	},
];
