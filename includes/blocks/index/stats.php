<?php
/*
 * 统计卡：全部 / 图片 / 视频 / 今日上传，有的外观多一张文档或音频。
 * 数字跟着当前列表走（?m=mine 时是自己的文件），类型统计缓存 5 分钟。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '统计卡',
	'group' => '首页',
	'scope' => 'front',
	'schema' => [
		['key'=>'extra', 'type'=>'select', 'label'=>'第五张卡', 'default'=>'', 'options'=>[''=>'不加', 'doc'=>'文档文件', 'audio'=>'音频文件']],
	],
	'data' => function($cfg, &$ctx){
		$counts = layout_ctx_counts($ctx);
		$cards = [
			['icon'=>'fa-files-o', 'key'=>'all', 'num'=>number_format($counts['']), 'label'=>'全部文件'],
			['icon'=>'fa-picture-o', 'key'=>'image', 'num'=>number_format($counts['image']), 'label'=>'图片文件'],
			['icon'=>'fa-video-camera', 'key'=>'video', 'num'=>number_format($counts['video']), 'label'=>'视频文件'],
		];
		if($cfg['extra'] === 'doc')$cards[] = ['icon'=>'fa-file-text-o', 'key'=>'doc', 'num'=>number_format($counts['doc']), 'label'=>'文档文件'];
		if($cfg['extra'] === 'audio')$cards[] = ['icon'=>'fa-music', 'key'=>'audio', 'num'=>number_format($counts['audio']), 'label'=>'音频文件'];
		$cards[] = ['icon'=>'fa-clock-o', 'key'=>'today', 'num'=>number_format(layout_ctx_today_total($ctx)), 'label'=>'今日上传'];
		return ['cards' => $cards];
	},
];
