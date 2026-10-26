<?php
/*
 * 渐变仪表盘风右栏：快捷入口，只放当前站点真的开着的功能。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '快捷入口',
	'group' => '仪表盘',
	'scope' => 'front',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'快捷入口'],
		['key'=>'links', 'type'=>'lines', 'label'=>'自定义入口', 'default'=>'', 'help'=>'一行一个：文字|链接|图标（图标是 fa-xxx，可不写）；留空用默认的几项'],
	],
	'data' => function($cfg, &$ctx){
		global $conf, $islogin2;
		$links = [];
		$links[] = ['href'=>'./upload.php', 'icon'=>'fa-cloud-upload', 'label'=>'上传文件'];
		$links[] = ['href'=>!empty($islogin2) ? './user.php?tab=files' : './?m=mine', 'icon'=>'fa-folder-open', 'label'=>'我的文件'];
		if(function_exists('is_buy_open') && is_buy_open())$links[] = ['href'=>'./buy.php', 'icon'=>'fa-shopping-cart', 'label'=>'购买权限'];
		if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1)$links[] = ['href'=>'./sponsor.php', 'icon'=>'fa-money', 'label'=>'赞助名单'];
		if($custom = theme_link_lines($cfg['links']))$links = $custom;
		return ['links'=>$links];
	},
];
