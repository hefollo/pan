<?php
/*
 * 广告位脚本：后台「广告位设置」里配的广告靠它插进页面。拿掉这张卡片，前台就不显示广告。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '广告位脚本',
	'group' => '外壳',
	'scope' => 'front',
	'pages' => ['_shell'],
	'data' => function($cfg, &$ctx){
		return ['version' => VERSION];
	},
];
