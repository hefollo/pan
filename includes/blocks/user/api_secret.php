<?php
/* 上传 API 页签：刚创建的密钥只显示一次的那个框（usercenter.js 填内容） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '新密钥展示框',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'description' => '刚创建密钥时才出现',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'密钥只显示这一次，请立即复制保存'],
		['key'=>'sub', 'type'=>'text', 'label'=>'说明', 'default'=>'离开或刷新页面后无法再次查看完整密钥，遗失后请删除并重新创建。'],
	],
];
