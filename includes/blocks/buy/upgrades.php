<?php
/* 购买页：限时会员补差价升级到更高等级（到期时间不变） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '补差价升级',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'description' => '限时会员才有；能升到哪些等级由系统算',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'补差价升级'],
		['key'=>'sub', 'type'=>'text', 'label'=>'说明', 'default'=>'到期时间不变，只补到期前这段时间的差价'],
		['key'=>'name_text', 'type'=>'text', 'label'=>'每项的名字', 'default'=>'升级到{name}', 'help'=>'{name} 是等级名'],
		['key'=>'expire_text', 'type'=>'text', 'label'=>'到期时间那一行', 'default'=>'到期时间不变：{date}', 'help'=>'{date} 是到期时间'],
		['key'=>'btn', 'type'=>'text', 'label'=>'按钮', 'default'=>'补差价升级'],
	],
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
				'name_html' => theme_text_html($cfg['name_text'], ['name'=>htmlspecialchars($up['level']['name'], ENT_QUOTES, 'UTF-8')]),
				'expire_html' => theme_text_html($cfg['expire_text'], ['date'=>htmlspecialchars($up['expire'], ENT_QUOTES, 'UTF-8')]),
			];
		}
		return ['upgrades' => $list];
	},
];
