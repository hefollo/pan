<?php
/* 上传 API 页签：刚创建的密钥只显示一次的那个框（usercenter.js 填内容） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '新密钥展示框',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
];
