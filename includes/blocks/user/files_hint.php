<?php
/* 「我的文件」里关于「私密」的说明 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '私密说明',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'text', 'type'=>'textarea', 'label'=>'说明', 'default'=>'「私密」只是不在首页公共列表里出现，外链本身照样能打开；真要限制访问，请给文件设置访问密码。', 'help'=>'清空不显示'],
	],
];
