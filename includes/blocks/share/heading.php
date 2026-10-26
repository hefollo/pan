<?php
/* 文件夹分享页标题：文件夹名、「内容随文件夹实时更新」、有效期（解锁之后才显示） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '分享标题',
	'group' => '文件夹分享',
	'scope' => 'front',
	'pages' => ['share'],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if(!in_array($d['view'], ['list', 'file'], true))return null;
		return ['title' => $d['title'], 'expire' => $d['ctx']['share']['expiretime'] ? $d['ctx']['share']['expiretime'] : ''];
	},
];
