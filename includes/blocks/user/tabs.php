<?php
/* 个人中心的页签导航（概览 / 我的文件 / 订单记录 / 上传 API 密钥 / 账号设置，按站点功能开关显示） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '页签导航',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'description' => '哪些页签出现由后台设置决定（购买、API 关掉时不显示）；这里改名字，留空用默认',
	'schema' => [
		['key'=>'label_overview', 'type'=>'text', 'label'=>'概览', 'default'=>'概览'],
		['key'=>'label_files', 'type'=>'text', 'label'=>'我的文件', 'default'=>'我的文件'],
		['key'=>'label_orders', 'type'=>'text', 'label'=>'订单记录', 'default'=>'订单记录'],
		['key'=>'label_api', 'type'=>'text', 'label'=>'上传 API 密钥', 'default'=>'上传 API 密钥'],
		['key'=>'label_account', 'type'=>'text', 'label'=>'账号设置', 'default'=>'账号设置'],
	],
	'data' => function($cfg, &$ctx){
		include_once SYSTEM_ROOT.'usercenter.php';
		$cur = uc_current_tab($ctx);
		$tabs = [];
		foreach($ctx['data']['tabs'] as $key => $t){
			$label = isset($cfg['label_'.$key]) && trim($cfg['label_'.$key]) !== '' ? $cfg['label_'.$key] : $t[0];
			$tabs[] = ['key'=>$key, 'label'=>$label, 'icon'=>$t[1], 'active'=>$cur === $key];
		}
		return ['tabs' => $tabs];
	},
];
