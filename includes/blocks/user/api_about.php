<?php
/* 上传 API 页签：这是什么、接口地址、请求方式 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => 'API 说明',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $siteurl;
		return ['endpoint' => $siteurl.'api.php'];
	},
];
