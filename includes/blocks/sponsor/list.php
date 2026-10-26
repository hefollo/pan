<?php
/* 赞助记录表（后台「赞助名单管理」里录入的） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '赞助记录',
	'group' => '赞助与公示',
	'scope' => 'front',
	'data' => function($cfg, &$ctx){
		global $DB;
		$rows = $DB->getAll("SELECT `name`,`platform`,`amount`,`sponsor_time` FROM pre_sponsor ORDER BY id ASC");
		$list = [];
		$i = 1;
		foreach($rows ? $rows : [] as $r){
			$list[] = ['n'=>$i++, 'name'=>$r['name'], 'platform'=>$r['platform'], 'amount'=>$r['amount'], 'time'=>$r['sponsor_time']];
		}
		return ['rows'=>$list, 'count'=>count($list)];
	},
];
