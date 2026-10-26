<?php
/* 上传 API 页签：这是什么、接口地址、请求方式 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => 'API 说明',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'heading', 'type'=>'text', 'label'=>'标题', 'default'=>'这是本站的“文件上传 API”'],
		['key'=>'text', 'type'=>'textarea', 'label'=>'说明', 'default'=>'供脚本、应用程序或支持自定义上传接口的图床工具，通过 HTTP 自动把文件上传到你的本站账号。它不是登录密码，也不能用来登录用户中心。'],
	],
	'data' => function($cfg, &$ctx){
		global $siteurl;
		return ['endpoint' => $siteurl.'api.php'];
	},
];
