<?php
/* 上传时的彩色加载动画（uploadnew.js 显示 / 隐藏它） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '加载动画',
	'group' => '上传页',
	'scope' => 'front',
	'pages' => ['upload'],
];
