<?php
/*
 * 文件页的预览面板：图片直接显示，音频 / 视频挂播放器（脚本在页脚之后加载），
 * 其它类型显示图标、大小和下载 / 查看内容 / 在线预览按钮；待审或被屏蔽的视频只给说明。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '文件预览',
	'group' => '文件页',
	'scope' => 'front',
	'pages' => ['file'],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		$row = $d['row'];
		$ft = $d['filetype'];
		$video_ok = $ft == 3 && $row['block'] == 0;
		if($ft == 2 || $video_ok){
			$plug = '';
			if($d['type'] == 'm3u8')$plug = 'hls.js';
			if($d['type'] == 'flv' || $d['type'] == 'f4v')$plug = 'flv.js';
			theme_defer('file.player', theme_tpl_render('blocks/file/player_scripts', [
				'audio' => $ft == 2,
				'video' => $video_ok,
				'name_json' => json_encode($d['name'], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?: '""',
				'viewurl_all_html' => $d['viewurl_all'],
				'hls' => $d['type'] == 'm3u8',
				'flv' => $d['type'] == 'flv' || $d['type'] == 'f4v',
				'plug' => $plug,
			], $ctx['theme_id']), 10);
		}
		return [
			'title_html' => $d['title'],
			//带密码的地址里有 '&'，写进属性必须转义，否则像 &copy 这种会被当成实体解析，图片加载不出来
			'viewurl' => $d['viewurl'],
			'is_image' => $ft == 1,
			'is_audio' => $ft == 2,
			'is_video' => $video_ok,
			'is_video_blocked' => $ft == 3 && !$video_ok,
			'is_other' => !in_array($ft, [1, 2, 3]),
			'blocked_text' => $row['block'] == 2 ? '视频文件需审核通过后才能在线播放和下载，请等待审核通过。' : '该视频已被屏蔽，无法在线播放或下载。',
			'icon' => type_to_icon($d['type']),
			'name_html' => $d['name'],
			'size' => size_format($row['size']),
			'downurl_html' => $d['downurl'],
			'texturl_html' => $d['texturl'],
			'text_viewable' => $d['text_viewable'],
			'office_url_html' => $d['office_url'],
		];
	},
];
