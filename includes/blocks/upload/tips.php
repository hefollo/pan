<?php
/* 上传提示：访客 IP、单文件上限、每日剩余、当前权限、视频审核说明 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '上传提示',
	'group' => '上传页',
	'scope' => 'front',
	'pages' => ['upload'],
	'data' => function($cfg, &$ctx){
		global $conf, $clientip;
		$d = $ctx['data'];
		return [
			'clientip' => $clientip,
			'size' => $d['size'],
			'limit' => $d['limit'],
			'remaining' => $d['remaining'],
			'expire_text' => $d['expire_text'],
			'videoreview' => $conf['videoreview'] == 1,
		];
	},
];
