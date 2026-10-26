<?php
/*
 * 工作台家族右栏：存储空间。程序本身没有「总容量」这个概念（只限每日个数和单文件大小），
 * 所以只报已用量，不编一个假的总量。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '存储空间面板',
	'group' => '工作台',
	'scope' => 'front',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'存储空间'],
		['key'=>'used_label', 'type'=>'text', 'label'=>'已用空间', 'default'=>'已用空间'],
		['key'=>'count_label', 'type'=>'text', 'label'=>'文件数量', 'default'=>'文件数量'],
		['key'=>'size_label', 'type'=>'text', 'label'=>'单文件上限', 'default'=>'单文件上限'],
	],
	'data' => function($cfg, &$ctx){
		global $DB;
		$size_limit = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		$where = layout_ctx_where($ctx);
		$used = theme_memo('size|'.$where, function() use ($DB, $where){ return layout_storage_used($DB, $where); });
		$counts = layout_ctx_counts($ctx);
		return [
			'used' => size_format($used ? $used : 0),
			'total' => number_format($counts['']),
			'size_text' => $size_limit > 0 ? ($size_limit.' MB') : '不限制',
		];
	},
];
