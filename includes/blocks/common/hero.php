<?php
/*
 * 通用横幅（DEC-20261026-001）：标题、说明、最多两个按钮、可选配图。
 * 跨外观通用：颜色全部取外观自己的变量（主色、面板色、文字色），样式在 style.css 的「通用展示卡片」一段。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '横幅',
	'group' => '通用展示',
	'scope' => 'front',
	'description' => '大标题 + 说明 + 按钮，可带配图，适合做首页顶部',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'欢迎'],
		['key'=>'text', 'type'=>'textarea', 'label'=>'说明', 'default'=>'', 'help'=>'可以多行'],
		['key'=>'btn1_text', 'type'=>'text', 'label'=>'主按钮文字', 'default'=>''],
		['key'=>'btn1_link', 'type'=>'text', 'label'=>'主按钮链接', 'default'=>'./upload.php'],
		['key'=>'btn2_text', 'type'=>'text', 'label'=>'次按钮文字', 'default'=>''],
		['key'=>'btn2_link', 'type'=>'text', 'label'=>'次按钮链接', 'default'=>''],
		['key'=>'image', 'type'=>'image', 'label'=>'配图地址', 'default'=>'', 'help'=>'留空不显示；站内图片写相对地址'],
		['key'=>'align', 'type'=>'select', 'label'=>'对齐', 'default'=>'left', 'options'=>['left'=>'靠左', 'center'=>'居中']],
		['key'=>'tone', 'type'=>'select', 'label'=>'底色', 'default'=>'soft', 'options'=>['soft'=>'面板色', 'primary'=>'主色', 'plain'=>'无底色']],
	],
	'data' => function($cfg, &$ctx){
		$btns = [];
		foreach([1, 2] as $i){
			$href = common_card_href($cfg['btn'.$i.'_link']);
			if($cfg['btn'.$i.'_text'] !== '' && $href !== '')$btns[] = ['text'=>$cfg['btn'.$i.'_text'], 'href'=>$href, 'primary'=>$i === 1];
		}
		return [
			'lines' => common_card_lines($cfg['text']),
			'buttons' => $btns,
			'has_buttons' => !empty($btns),
			'center' => $cfg['align'] === 'center',
			'tone' => $cfg['tone'],
		];
	},
];
