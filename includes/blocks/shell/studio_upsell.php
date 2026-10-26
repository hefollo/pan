<?php
/*
 * 工作台家族侧栏底部的升级卡。购买功能没开就整块不显示（这个站根本卖不了权限，
 * 挂个买不了的入口只会误导人）；开着的话文案按登录状态和买没买过分别写。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '升级卡',
	'group' => '侧栏',
	'scope' => 'front',
	'pages' => ['_shell'],
	'data' => function($cfg, &$ctx){
		global $DB, $islogin2;
		if(!function_exists('is_buy_open') || !is_buy_open())return null;
		$plan = (!empty($islogin2) && function_exists('layout_user_plan')) ? layout_user_plan($DB) : null;
		$bought = ($plan && !empty($plan['bought']));
		return [
			'title' => $bought ? '续费 / 升级权限' : '升级获取更多权限',
			'btn' => $bought ? '续费权限' : '购买权限',
		];
	},
];
