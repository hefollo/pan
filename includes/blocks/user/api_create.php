<?php
/* 上传 API 页签：创建密钥（名称、IP 白名单、有效期） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '创建 API 密钥',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'创建上传 API 密钥'],
		['key'=>'btn', 'type'=>'text', 'label'=>'创建按钮', 'default'=>'创建密钥'],
	],
	'data' => function($cfg, &$ctx){
		$def = api_key_default_expire_days();
		$options = [];
		foreach([30, 90, 180, 365, 0] as $days){
			$options[] = ['days'=>$days, 'selected'=>$days === $def, 'label'=>$days === 0 ? '永久有效' : $days.' 天有效'];
		}
		return [
			'options' => $options,
			'custom' => in_array($def, [30, 90, 180, 365, 0], true) ? false : $def,
			'limit' => api_key_limit(),
		];
	},
];
