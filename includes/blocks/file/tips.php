<?php
/* 文件页右侧的提示（后台「文件页设置」里的文件页公告，原样输出） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '文件页提示',
	'group' => '文件页',
	'scope' => 'front',
	'description' => '提示内容在后台「广告公告位设置」的文件页公告里改',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'提示'],
	],
	'data' => function($cfg, &$ctx){
		global $conf;
		return ['content_html' => isset($conf['gg_file']) ? $conf['gg_file'] : ''];
	},
];
