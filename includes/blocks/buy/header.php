<?php
/* 购买页标题、副标题、支付结果提示（?paid=）、后台填的购买页公告 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '购买页标题',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'description' => '页面顶部的公告在后台「支付设置」里改',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'购买权限'],
		['key'=>'sub', 'type'=>'text', 'label'=>'副标题', 'default'=>'选择需要的会员等级，支付成功后权限立即生效。', 'help'=>'清空不显示'],
		['key'=>'paid_ok', 'type'=>'text', 'label'=>'支付成功回来时的提示', 'default'=>'支付成功，权限已经发放到你的账号。'],
		['key'=>'paid_fail', 'type'=>'text', 'label'=>'没查到支付时的提示', 'default'=>'没有查到这笔支付。如果你已经付款，请稍等片刻后刷新本页，或联系站长处理。'],
	],
	'data' => function($cfg, &$ctx){
		global $conf;
		return [
			'paid_set' => isset($_GET['paid']),
			'paid_ok' => isset($_GET['paid']) && $_GET['paid'] == '1',
			'notice' => !empty($conf['buy_notice']) ? $conf['buy_notice'] : '',
		];
	},
];
