<?php
/*
 * 紧凑的权限条：今日上传、单文件大小、有效期、加量包、购买入口（没有侧栏卡的外观用）。
 * 「用在」只影响文案：上传页的购买链接文字和列表页一样，留着这个开关是为了以后两边能分开写。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '权限条',
	'group' => '通用',
	'scope' => 'front',
	'schema' => [
		['key'=>'where', 'type'=>'select', 'label'=>'用在', 'default'=>'list', 'options'=>['list'=>'文件列表页', 'upload'=>'上传页']],
	],
	'data' => function($cfg, &$ctx){
		return permission_bar_data($cfg['where']);
	},
];
