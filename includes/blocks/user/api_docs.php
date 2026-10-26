<?php
/* 上传 API 页签：对接文档（鉴权、参数、返回数据、cURL / PHP / Node.js 示例） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => 'API 对接文档',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $siteurl;
		return ['endpoint' => $siteurl.'api.php'];
	},
];
