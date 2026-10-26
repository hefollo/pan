<?php
/* 个人中心的页签导航（概览 / 我的文件 / 订单记录 / 上传 API 密钥 / 账号设置，按站点功能开关显示） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '页签导航',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		include_once SYSTEM_ROOT.'usercenter.php';
		$cur = uc_current_tab($ctx);
		$tabs = [];
		foreach($ctx['data']['tabs'] as $key => $t){
			$tabs[] = ['key'=>$key, 'label'=>$t[0], 'icon'=>$t[1], 'active'=>$cur === $key];
		}
		return ['tabs' => $tabs];
	},
];
