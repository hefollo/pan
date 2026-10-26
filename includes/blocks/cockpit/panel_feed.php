<?php
/*
 * 渐变仪表盘风右栏：最近上传（最新几条，缓存 2 分钟）。
 * 列表页的文件名是直接输出的（上传时已清洗过），这里仍然按不可信内容转义一次。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '最近上传',
	'group' => '仪表盘',
	'scope' => 'front',
	'schema' => [
		['key'=>'limit', 'type'=>'number', 'label'=>'条数', 'default'=>6, 'min'=>1, 'max'=>20],
	],
	'data' => function($cfg, &$ctx){
		global $DB;
		$items = [];
		foreach(layout_recent_uploads($DB, layout_ctx_where($ctx), intval($cfg['limit'])) as $row){
			$items[] = [
				'href' => './file.php?hash='.urlencode($row['token']),
				'group' => layout_type_group($row['type']),
				'name' => $row['name'],
				'icon' => function_exists('type_to_icon') ? type_to_icon($row['type']) : 'fa-file-o',
				'size' => size_format($row['size']),
				'ago' => layout_time_ago($row['addtime']),
			];
		}
		return ['items'=>$items, 'limit'=>intval($cfg['limit'])];
	},
];
