<?php
/*
 * 页面内容的位置。外壳布局里必须有且只有一个，编辑器里不能删（可以挪）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '页面内容',
	'group' => '外壳',
	'scope' => 'front',
	'pages' => ['_shell'],
	'required' => true,
	'data' => function($cfg, &$ctx){
		return ['marker_html' => theme_shell_marker()];
	},
];
