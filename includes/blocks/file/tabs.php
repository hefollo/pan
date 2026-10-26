<?php
/*
 * 文件页的选项卡：文件外链、代码调用、文件详情，上传者自己还有「管理」（在线编辑、覆盖上传、删除）。
 * 这些地址和代码在旧代码里都是原样输出的（代码已经 htmlspecialchars 过），所以字段都是 _html。
 * 管理用的脚本放到页脚之后；#hash、#file_id、#csrf_token、#replaceFileInput 是脚本要读的。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '外链与管理',
	'group' => '文件页',
	'scope' => 'front',
	'pages' => ['file'],
	'description' => '「管理」页签只有上传者自己看得到',
	'schema' => [
		['key'=>'tab_link', 'type'=>'text', 'label'=>'外链页签', 'default'=>'文件外链'],
		['key'=>'show_code', 'type'=>'switch', 'label'=>'显示「代码调用」页签', 'default'=>1],
		['key'=>'tab_code', 'type'=>'text', 'label'=>'代码调用页签', 'default'=>'代码调用'],
		['key'=>'show_info', 'type'=>'switch', 'label'=>'显示「文件详情」页签', 'default'=>1],
		['key'=>'tab_info', 'type'=>'text', 'label'=>'文件详情页签', 'default'=>'文件详情'],
		['key'=>'tab_manage', 'type'=>'text', 'label'=>'管理页签', 'default'=>'管理'],
	],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		$row = $d['row'];
		theme_defer('file.manage', theme_tpl_render('blocks/file/manage_scripts', [], $ctx['theme_id']), 20);
		return [
			'is_mine' => $d['is_mine'],
			'is_media' => $d['filetype'] != 0,
			'is_player' => $d['filetype'] >= 2,
			'linktitle_html' => $d['linktitle'],
			'viewurl_all_html' => $d['viewurl_all'],
			'downurl_all_html' => $d['downurl_all'],
			'texturl_all_html' => $d['texturl_all'],
			'text_viewable' => $d['text_viewable'],
			'htmlcode_html' => $d['htmlcode'],
			'htmlcode2_html' => $d['htmlcode2'],
			'ubbcode_html' => $d['ubbcode'],
			'ip_html' => preg_replace('/\d+$/', '*', $row['ip']),
			'addtime_html' => $row['addtime'],
			'count_html' => $row['count'],
			'size_full_html' => size_format($row['size']).' ('.$row['size'].' 字节)',
			'hash_html' => $d['hash'],
			'id' => intval($row['id']),
			'csrf_html' => $d['csrf_token'],
			'editable' => $d['is_editable'],
			'delete_locked' => $d['delete_locked'],
		];
	},
];
