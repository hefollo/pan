<?php
/* 「我的文件」顶部工具条：搜索、文件夹分享管理、上传（在某个文件夹里时是「上传到这里」） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '文件工具条',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		include_once SYSTEM_ROOT.'usercenter.php';
		$s = uc_files_state();
		return [
			'ft' => $s['ft'],
			'kw' => $s['kw'],
			'here' => $s['fd_on'] && $s['fd_cur'] > 0 && !$s['fd_searching'],
			'folder' => $s['fd_cur'],
		];
	},
];
