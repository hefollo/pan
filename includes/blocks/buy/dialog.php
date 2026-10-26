<?php
/*
 * 购买页的支付弹窗（扫码 / 跳转收银台，易支付时先选通道），由 assets/js/buy.js 控制。
 * 这张卡片要放在页面最外层（不要放进容器里）：遮罩层按整页定位。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '支付弹窗',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'required' => true,
	'data' => function($cfg, &$ctx){
		$methods = pay_methods();
		$icons = ['alipay'=>'credit-card', 'wxpay'=>'weixin', 'qqpay'=>'qq'];
		$channels = [];
		foreach(isset($methods['epay']) ? epay_channels() : [] as $ck => $cname){
			$channels[] = ['key'=>$ck, 'icon'=>isset($icons[$ck]) ? $icons[$ck] : 'credit-card', 'name'=>$cname];
		}
		return ['channels' => $channels];
	},
];
