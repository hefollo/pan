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
	'description' => '没开放购买时不显示',
	'schema' => [
		['key'=>'title_new', 'type'=>'text', 'label'=>'标题（没买过）', 'default'=>'升级获取更多权限'],
		['key'=>'title_renew', 'type'=>'text', 'label'=>'标题（买过）', 'default'=>'续费 / 升级权限'],
		['key'=>'text', 'type'=>'textarea', 'label'=>'说明', 'default'=>"更大的存储空间\n更稳定的高速下载体验", 'help'=>'一行一句；清空不显示'],
		['key'=>'btn_new', 'type'=>'text', 'label'=>'按钮文字（没买过）', 'default'=>'购买权限'],
		['key'=>'btn_renew', 'type'=>'text', 'label'=>'按钮文字（买过）', 'default'=>'续费权限'],
		['key'=>'href', 'type'=>'text', 'label'=>'按钮链接', 'default'=>'./buy.php'],
	],
	'data' => function($cfg, &$ctx){
		global $DB, $islogin2;
		if(!function_exists('is_buy_open') || !is_buy_open())return null;
		$plan = (!empty($islogin2) && function_exists('layout_user_plan')) ? layout_user_plan($DB) : null;
		$bought = ($plan && !empty($plan['bought']));
		$href = common_card_href($cfg['href']);
		return [
			'title' => $bought ? $cfg['title_renew'] : $cfg['title_new'],
			'btn' => $bought ? $cfg['btn_renew'] : $cfg['btn_new'],
			'text_html' => trim($cfg['text']) === '' ? '' : theme_text_html(trim($cfg['text']), [], true),
			'href' => $href !== '' ? $href : './buy.php',
		];
	},
];
