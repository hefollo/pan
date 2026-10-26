<?php
/* 云端门户风右上角那张蓝色上传卡：点了就去上传页，不做半吊子的拖拽假象 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '上传卡',
	'group' => '工作台',
	'scope' => 'front',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'把文件交给云端'],
		['key'=>'sub', 'type'=>'text', 'label'=>'说明', 'default'=>'点下面的按钮选择文件，或到上传页拖拽'],
		['key'=>'btn', 'type'=>'text', 'label'=>'按钮', 'default'=>'选择文件'],
		['key'=>'feat1', 'type'=>'text', 'label'=>'卖点一', 'default'=>'高速上传'],
		['key'=>'feat2', 'type'=>'text', 'label'=>'卖点二', 'default'=>'安全加密'],
	],
	'data' => function($cfg, &$ctx){
		$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		return ['size_text' => $size > 0 ? ('单文件 '.$size.' MB') : '不限大小'];
	},
];
