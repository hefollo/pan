<?php
/* 赞助记录表（后台「赞助名单管理」里录入的） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '赞助记录',
	'group' => '赞助与公示',
	'scope' => 'front',
	'schema' => [
		['key'=>'empty', 'type'=>'text', 'label'=>'没有记录时', 'default'=>'暂无赞助记录，成为第一个赞助者吧！'],
		['key'=>'summary', 'type'=>'text', 'label'=>'底部合计', 'default'=>'共 {n} 条赞助记录', 'help'=>'{n} 是条数'],
	],
	'data' => function($cfg, &$ctx){
		global $DB;
		$rows = $DB->getAll("SELECT `name`,`platform`,`amount`,`sponsor_time` FROM pre_sponsor ORDER BY id ASC");
		$list = [];
		$i = 1;
		foreach($rows ? $rows : [] as $r){
			$list[] = ['n'=>$i++, 'name'=>$r['name'], 'platform'=>$r['platform'], 'amount'=>$r['amount'], 'time'=>$r['sponsor_time']];
		}
		return ['rows'=>$list, 'summary_html'=>theme_text_html($cfg['summary'], ['n'=>count($list)])];
	},
];
