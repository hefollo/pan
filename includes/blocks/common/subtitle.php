<?php
/*
 * 标题下面的一行说明文字。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '说明文字',
	'group' => '通用',
	'scope' => 'front',
	'schema' => [
		['key'=>'text', 'type'=>'text', 'label'=>'文字', 'default'=>''],
	],
	'data' => function($cfg, &$ctx){
		return $cfg['text'] === '' ? null : ['text' => $cfg['text']];
	},
];
