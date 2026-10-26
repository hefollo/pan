<?php
/*
 * 分栏（DEC-20261026-002）：把下面的「栏」排成 2～4 列，每一栏是个容器，往里拖卡片。
 * 窄屏自动叠成一列（叠起的宽度可选）。排版样式第一次用到时随页面输出一小段，不依赖外观样式表，
 * 所以前台、后台、无外壳的代码页都能用。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '分栏',
	'group' => '布局',
	'scope' => 'both',
	'container' => true,
	'description' => '把里面的「栏」并排成 2～4 列，手机上自动叠起来；往每一栏里拖卡片',
	'schema' => [
		['key'=>'ratio', 'type'=>'select', 'label'=>'栏数和比例', 'default'=>'1-1', 'options'=>['1-1'=>'两栏 1 : 1', '1-2'=>'两栏 1 : 2（左窄）', '2-1'=>'两栏 2 : 1（右窄）', '1-3'=>'两栏 1 : 3', '3-1'=>'两栏 3 : 1', '1-1-1'=>'三栏 1 : 1 : 1', '1-2-1'=>'三栏 1 : 2 : 1', '1-1-1-1'=>'四栏等宽']],
		['key'=>'gap', 'type'=>'select', 'label'=>'栏间距', 'default'=>'16', 'options'=>['0'=>'0', '8'=>'8 px', '16'=>'16 px', '24'=>'24 px', '32'=>'32 px']],
		['key'=>'valign', 'type'=>'select', 'label'=>'各栏高度', 'default'=>'stretch', 'options'=>['stretch'=>'一样高', 'start'=>'按内容，顶部对齐', 'center'=>'按内容，垂直居中']],
		['key'=>'stack', 'type'=>'select', 'label'=>'什么时候叠成一列', 'default'=>'sm', 'options'=>['sm'=>'手机（宽度 < 768px）', 'md'=>'手机和平板（宽度 < 992px）', 'never'=>'不叠']],
	],
	'data' => function($cfg, &$ctx){
		$cols = implode(' ', array_map(function($n){ return intval($n).'fr'; }, explode('-', $cfg['ratio'])));
		return [
			'css_html' => layout_inline_css_once('cols', '.pan-cols{display:grid;grid-template-columns:var(--pan-cols);gap:var(--pan-gap)}.pan-cols>.pan-col{min-width:0}'
				.'@media (max-width:767px){.pan-cols.pan-stack-sm{grid-template-columns:minmax(0,1fr)}}'
				.'@media (max-width:991px){.pan-cols.pan-stack-md{grid-template-columns:minmax(0,1fr)}}'),
			'style_attr' => '--pan-cols:'.$cols.';--pan-gap:'.intval($cfg['gap']).'px;align-items:'.$cfg['valign'],
			'stack' => $cfg['stack'],
		];
	},
];
