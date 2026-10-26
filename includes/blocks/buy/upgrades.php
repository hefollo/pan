<?php
/* 购买页：限时会员补差价升级到更高等级（到期时间不变） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '补差价升级',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'data' => function($cfg, &$ctx){
		$ups = $ctx['data']['upgrades'];
		if(!$ups)return null;
		$list = [];
		foreach($ups as $up){
			$list[] = [
				'id' => intval($up['level']['id']),
				'name' => $up['level']['name'],
				'price' => number_format($up['price'], 2, '.', ''),
				'features' => level_features($up['level']),
				'expire' => $up['expire'],
			];
		}
		return ['upgrades' => $list];
	},
];
