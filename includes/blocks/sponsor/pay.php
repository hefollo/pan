<?php
/*
 * 赞助收款码（微信 / QQ 钱包 / 支付宝），沿用后台「赞助名单管理」里的配置，配置成空则不显示这一种。
 * 后台存的是相对 includes/sponsor/ 的路径，页面在站点根目录，要补上前缀。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '赞助收款码',
	'group' => '赞助与公示',
	'scope' => 'front',
	'data' => function($cfg, &$ctx){
		global $conf;
		$methods = [
			['key'=>'sponsor_img_weixin', 'default'=>'images/weixin.png',   'label'=>'微信赞助'],
			['key'=>'sponsor_img_qq',     'default'=>'images/qq.png',       'label'=>'QQ钱包赞助'],
			['key'=>'sponsor_img_alipay', 'default'=>'images/zhifubao.png', 'label'=>'支付宝赞助'],
		];
		$list = [];
		foreach($methods as $m){
			$url = isset($conf[$m['key']]) ? trim($conf[$m['key']]) : $m['default'];
			if($url === '')continue;
			if(!preg_match('#^(https?:)?//#i', $url) && $url[0] !== '/')$url = 'includes/sponsor/'.$url;
			$list[] = ['url'=>$url, 'label'=>$m['label']];
		}
		return $list ? ['list'=>$list] : null;
	},
];
