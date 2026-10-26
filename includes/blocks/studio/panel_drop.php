<?php
/* 云端门户风右上角那张蓝色上传卡：点了就去上传页，不做半吊子的拖拽假象 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '上传卡',
	'group' => '工作台',
	'scope' => 'front',
	'data' => function($cfg, &$ctx){
		$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		return ['size_text' => $size > 0 ? ('单文件 '.$size.' MB') : '不限大小'];
	},
];
