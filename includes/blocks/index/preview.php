<?php
/*
 * 深色工作台风的右侧文件预览面板，内容由 layout-workspace.js 点击列表行时填充
 * （读文件列表每一行上的 data-* 属性）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '文件预览面板',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'文件预览'],
		['key'=>'empty', 'type'=>'text', 'label'=>'没选文件时的提示', 'default'=>'在左侧选择一个文件，这里会显示它的详细信息与外链。'],
		['key'=>'download_text', 'type'=>'text', 'label'=>'下载按钮', 'default'=>'下载文件'],
		['key'=>'copy_text', 'type'=>'text', 'label'=>'复制按钮', 'default'=>'复制链接'],
		['key'=>'link_label', 'type'=>'text', 'label'=>'外链地址标签', 'default'=>'外链地址'],
		['key'=>'open_text', 'type'=>'text', 'label'=>'打开文件页链接', 'default'=>'打开文件页'],
	],
	'data' => function($cfg, &$ctx){
		theme_defer('index.workspace_js', '<script src="./assets/js/layout-workspace.js?v='.VERSION.'"></script>'."\n", 10);
		return [];
	},
];
