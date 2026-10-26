<?php
/*
 * 在线编辑面板：文件信息、保存 / 查看文件 / 我的文件按钮、编辑框（Ctrl+S 保存，脚本在页脚之后）。
 * 文件名、内容、编码在 edit.php 里已经转义过（ENT_QUOTES | ENT_SUBSTITUTE），所以是 _html。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '在线编辑器',
	'group' => '在线编辑',
	'scope' => 'front',
	'pages' => ['edit'],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		$row = $d['row'];
		return [
			'file_name_html' => $d['file_name'],
			'type' => $row['type'],
			'size' => size_format($row['size']),
			'encoding_html' => $d['file_encoding'],
			'max' => size_format($d['max_size']),
			'converted' => !empty($d['converted']),
			'view_url_html' => $d['view_url'],
			'id' => intval($row['id']),
			'csrf_html' => $d['csrf_token'],
			'content_html' => $d['file_content'],
		];
	},
];
