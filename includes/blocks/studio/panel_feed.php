<?php
/* 工作台家族右栏：最近动态（最新几条上传，缓存 2 分钟） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '最近动态面板',
	'group' => '工作台',
	'scope' => 'front',
	'schema' => [
		['key'=>'limit', 'type'=>'number', 'label'=>'条数', 'default'=>5, 'min'=>1, 'max'=>20],
	],
	'data' => function($cfg, &$ctx){
		global $DB;
		$items = [];
		foreach(layout_recent_uploads($DB, layout_ctx_where($ctx), intval($cfg['limit'])) as $row){
			$items[] = [
				'href' => './file.php?hash='.urlencode($row['token']),
				'name' => $row['name'],
				'group' => layout_type_group($row['type']),
				'icon' => function_exists('type_to_icon') ? type_to_icon($row['type']) : 'fa-file-o',
				'addtime' => $row['addtime'],
			];
		}
		return ['items' => $items];
	},
];
