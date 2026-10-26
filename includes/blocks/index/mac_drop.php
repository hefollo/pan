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
	'data' => function($cfg, &$ctx){
		if($ctx['data']['kw'] || $ctx['data']['ft'] !== '')return null;
		$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		return ['hint' => $size > 0 ? ('单个文件最大 '.$size.' MB · 支持图片 / 视频 / 音频 / 文档 / 压缩包')
			: '不限制文件大小 · 支持图片 / 视频 / 音频 / 文档 / 压缩包'];
	},
];
