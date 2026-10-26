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
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		$view = $d['view'];
		if($view === 'error')return ['error_view'=>true, 'error'=>$d['error']];
		if($view !== 'unlock' && $view !== 'unlock_file')return null;
		$is_file = $view === 'unlock_file';
		return [
			'unlock_view' => true,
			'is_file' => $is_file,
			'h1' => $is_file ? '请输入文件密码' : '请输入分享密码',
			'p' => $is_file ? '该文件另外设置了密码，请向分享者获取。' : '输入分享者提供的密码后，即可浏览文件夹。',
			'error' => $d['error'],
			'action' => share_url($d['file_id'] > 0 ? ['file'=>$d['file_id']] : []),
			'csrf' => $d['csrf'],
			'back' => share_url(),
		];
	},
];
