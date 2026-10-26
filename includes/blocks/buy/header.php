<?php
/* 购买页标题、副标题、支付结果提示（?paid=）、后台填的购买页公告 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '购买页标题',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'data' => function($cfg, &$ctx){
		global $conf;
		return [
			'paid_set' => isset($_GET['paid']),
			'paid_ok' => isset($_GET['paid']) && $_GET['paid'] == '1',
			'notice' => !empty($conf['buy_notice']) ? $conf['buy_notice'] : '',
		];
	},
];
