<?php
/*
 * 上传页的 Vue 挂载点（<div class="container" id="app" v-cloak>）：上传面板必须放在它里面。
 * 用户输入的东西（比如目标文件夹名）不能放进来，会被 Vue 当成模板解析（{{ }} 注入）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '上传页挂载点',
	'group' => '上传页',
	'scope' => 'front',
	'pages' => ['upload'],
	'container' => true,
	'required' => true,
	'description' => '上传组件（Vue）挂在这里，上传页的卡片放在它里面才能用',
];
