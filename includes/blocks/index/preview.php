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
	'data' => function($cfg, &$ctx){
		theme_defer('index.workspace_js', '<script src="./assets/js/layout-workspace.js?v='.VERSION.'"></script>'."\n", 10);
		return [];
	},
];
