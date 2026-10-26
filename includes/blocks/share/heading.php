<?php
/* 文件夹分享页标题：文件夹名、「内容随文件夹实时更新」、有效期（解锁之后才显示） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '分享标题',
	'group' => '文件夹分享',
	'scope' => 'front',
	'pages' => ['share'],
	'schema' => [
		['key'=>'eyebrow', 'type'=>'text', 'label'=>'标题上方的小字', 'default'=>'文件夹分享', 'help'=>'清空不显示'],
		['key'=>'sub', 'type'=>'text', 'label'=>'标题下方的说明', 'default'=>'内容随文件夹实时更新'],
		['key'=>'expire_text', 'type'=>'text', 'label'=>'有效期', 'default'=>' · 有效期至 {date}', 'help'=>'{date} 是到期时间；没设有效期时不显示'],
	],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if(!in_array($d['view'], ['list', 'file'], true))return null;
		$expire = $d['ctx']['share']['expiretime'] ? $d['ctx']['share']['expiretime'] : '';
		return ['title' => $d['title'], 'expire' => $expire, 'expire_html' => theme_text_html($cfg['expire_text'], ['date'=>htmlspecialchars($expire, ENT_QUOTES, 'UTF-8')])];
	},
];
