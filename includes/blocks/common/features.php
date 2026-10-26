<?php
/*
 * 特性网格（DEC-20261026-001）：几格「图标 + 标题 + 说明」，每行一格「fa-图标|标题|说明」。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '特性网格',
	'group' => '通用展示',
	'scope' => 'front',
	'description' => '几格图标、标题和一句说明，介绍网站特点',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>''],
		['key'=>'items', 'type'=>'lines', 'label'=>'格子', 'default'=>"fa-cloud-upload|快速上传|拖拽、粘贴、批量上传\nfa-link|一键外链|图片、音乐、视频直链\nfa-lock|密码保护|分享可设访问密码", 'help'=>'每行一格：图标|标题|说明（图标是 Font Awesome 4 的名字，比如 fa-star）'],
		['key'=>'cols', 'type'=>'select', 'label'=>'每行几格', 'default'=>'3', 'options'=>['2'=>'2', '3'=>'3', '4'=>'4']],
	],
	'data' => function($cfg, &$ctx){
		$items = [];
		foreach(preg_split('/\r?\n/', $cfg['items']) as $line){
			$p = array_map('trim', explode('|', $line));
			if(count($p) < 2 || $p[1] === '')continue;
			$items[] = ['icon'=>preg_match('/^fa-[a-z0-9-]{1,30}$/', $p[0]) ? $p[0] : 'fa-check', 'title'=>$p[1], 'text'=>isset($p[2]) ? $p[2] : ''];
			if(count($items) >= 12)break;
		}
		if(!$items)return null;
		return ['items'=>$items, 'cols'=>$cfg['cols']];
	},
];
