<?php
/*
 * 图文（DEC-20261026-001）：一张图 + 标题、说明、按钮，图片可在左或右；手机上图片在上。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '图文',
	'group' => '通用展示',
	'scope' => 'front',
	'description' => '左图右文或左文右图',
	'schema' => [
		['key'=>'image', 'type'=>'image', 'label'=>'图片地址', 'default'=>''],
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'标题'],
		['key'=>'text', 'type'=>'textarea', 'label'=>'说明', 'default'=>''],
		['key'=>'btn_text', 'type'=>'text', 'label'=>'按钮文字', 'default'=>''],
		['key'=>'btn_link', 'type'=>'text', 'label'=>'按钮链接', 'default'=>''],
		['key'=>'image_pos', 'type'=>'select', 'label'=>'图片位置', 'default'=>'left', 'options'=>['left'=>'左边', 'right'=>'右边']],
	],
	'data' => function($cfg, &$ctx){
		$href = common_card_href($cfg['btn_link']);
		return [
			'lines' => common_card_lines($cfg['text']),
			'has_btn' => $cfg['btn_text'] !== '' && $href !== '',
			'btn_href' => $href,
			'right' => $cfg['image_pos'] === 'right',
		];
	},
];
