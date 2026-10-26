<?php
/*
 * macOS 窗口风：列表上方的拖拽提示区。真正的上传在 upload.php，这里只是个入口。
 * 搜索 / 筛选状态下不显示，免得把刚查出来的结果顶到屏幕外面去。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '拖拽上传入口',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'点击选择文件，或拖拽到此处'],
		['key'=>'hint_size', 'type'=>'text', 'label'=>'提示（有大小限制）', 'default'=>'单个文件最大 {size} MB · 支持图片 / 视频 / 音频 / 文档 / 压缩包', 'help'=>'{size} 是单个文件上限（MB）'],
		['key'=>'hint_free', 'type'=>'text', 'label'=>'提示（不限大小）', 'default'=>'不限制文件大小 · 支持图片 / 视频 / 音频 / 文档 / 压缩包'],
	],
	'data' => function($cfg, &$ctx){
		if($ctx['data']['kw'] || $ctx['data']['ft'] !== '')return null;
		$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		return ['hint_html' => $size > 0 ? theme_text_html($cfg['hint_size'], ['size'=>intval($size)]) : theme_text_html($cfg['hint_free'])];
	},
];
