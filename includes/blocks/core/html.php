<?php
/*
 * 自定义 HTML：站长自己写的一段标记，原样输出（和页脚代码、公告一样，只有管理员能改）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '自定义 HTML',
	'group' => '布局',
	'scope' => 'both',
	'schema' => [
		['key'=>'html', 'type'=>'textarea', 'label'=>'HTML 内容', 'default'=>''],
	],
	'data' => function($cfg, &$ctx){
		return ['content_html' => $cfg['html']];
	},
];
