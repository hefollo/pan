<?php
/*
 * 文件夹分享页：打不开时的提示（分享不存在 / 过期 / 不在范围内），以及要密码时的解锁表单。
 * 分享页是独立页面（自己的 folder-share.css，严格的 CSP：不许行内样式和脚本），换皮只能靠样式表。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '分享提示与解锁',
	'group' => '文件夹分享',
	'scope' => 'front',
	'pages' => ['share'],
	'schema' => [
		['key'=>'error_title', 'type'=>'text', 'label'=>'打不开时的标题', 'default'=>'暂时无法访问'],
		['key'=>'home_text', 'type'=>'text', 'label'=>'打不开时的按钮', 'default'=>'返回首页'],
		['key'=>'lock_eyebrow', 'type'=>'text', 'label'=>'要密码时的小字', 'default'=>'受密码保护的分享'],
		['key'=>'h1_folder', 'type'=>'text', 'label'=>'分享密码的标题', 'default'=>'请输入分享密码'],
		['key'=>'p_folder', 'type'=>'text', 'label'=>'分享密码的说明', 'default'=>'输入分享者提供的密码后，即可浏览文件夹。'],
		['key'=>'h1_file', 'type'=>'text', 'label'=>'文件密码的标题', 'default'=>'请输入文件密码'],
		['key'=>'p_file', 'type'=>'text', 'label'=>'文件密码的说明', 'default'=>'该文件另外设置了密码，请向分享者获取。'],
		['key'=>'pwd_label', 'type'=>'text', 'label'=>'密码框标签', 'default'=>'访问密码'],
		['key'=>'unlock_btn', 'type'=>'text', 'label'=>'解锁按钮', 'default'=>'解锁查看'],
		['key'=>'back_text', 'type'=>'text', 'label'=>'返回文件夹链接', 'default'=>'返回文件夹'],
	],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		$view = $d['view'];
		if($view === 'error')return ['error_view'=>true, 'error'=>$d['error']];
		if($view !== 'unlock' && $view !== 'unlock_file')return null;
		$is_file = $view === 'unlock_file';
		return [
			'unlock_view' => true,
			'is_file' => $is_file,
			'h1' => $is_file ? $cfg['h1_file'] : $cfg['h1_folder'],
			'p' => $is_file ? $cfg['p_file'] : $cfg['p_folder'],
			'error' => $d['error'],
			'action' => share_url($d['file_id'] > 0 ? ['file'=>$d['file_id']] : []),
			'csrf' => $d['csrf'],
			'back' => share_url(),
		];
	},
];
