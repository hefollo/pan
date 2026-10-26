<?php
/* 购买页：支付方式（不止一种时给单选，只有一种时直接写出来） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '支付方式',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'description' => '支付方式在后台「支付设置」里开关',
	'schema' => [
		['key'=>'label', 'type'=>'text', 'label'=>'前面的文字', 'default'=>'支付方式'],
	],
	'data' => function($cfg, &$ctx){
		$methods = pay_methods();
		$keys = array_keys($methods);
		$list = [];
		foreach($methods as $mk => $m)$list[] = ['key'=>$mk, 'name'=>$m['name'], 'desc'=>$m['desc'], 'first'=>$mk === $keys[0]];
		return [
			'multi' => count($methods) > 1,
			'list' => $list,
			'single' => count($methods) === 1 ? $keys[0] : '',
			'single_name' => count($methods) === 1 ? $methods[$keys[0]]['name'] : '',
		];
	},
];
