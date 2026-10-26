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
	'description' => '收款码图片在后台「赞助设置」里换',
	'schema' => [
		['key'=>'label_wx', 'type'=>'text', 'label'=>'微信', 'default'=>'微信赞助'],
		['key'=>'label_qq', 'type'=>'text', 'label'=>'QQ 钱包', 'default'=>'QQ钱包赞助'],
		['key'=>'label_ali', 'type'=>'text', 'label'=>'支付宝', 'default'=>'支付宝赞助'],
	],
	'data' => function($cfg, &$ctx){
		global $conf;
		$methods = [
			['key'=>'sponsor_img_weixin', 'default'=>'images/weixin.png',   'label'=>$cfg['label_wx']],
			['key'=>'sponsor_img_qq',     'default'=>'images/qq.png',       'label'=>$cfg['label_qq']],
			['key'=>'sponsor_img_alipay', 'default'=>'images/zhifubao.png', 'label'=>$cfg['label_ali']],
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
