<?php
/*
 * 列表标题栏：标题（文件列表 / 我上传的文件）+ 搜索框，可选 macOS 窗口风的视图切换和「上传」按钮。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '标题与搜索',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'schema' => [
		['key'=>'cta', 'type'=>'text', 'label'=>'上传按钮文字', 'default'=>'', 'help'=>'留空不显示按钮'],
		['key'=>'mac_toggle', 'type'=>'switch', 'label'=>'网格 / 列表视图切换', 'default'=>0, 'help'=>'macOS 窗口风的访达式切换'],
	],
	'data' => function($cfg, &$ctx){
		global $conf;
		$d = $ctx['data'];
		if($cfg['mac_toggle']){
			//没开 JS 时两个按钮点不动，列表保持默认的网格视图；layout-mac.js 接管点击并记住选择
			theme_defer('index.mac_js', '<script src="./assets/js/layout-mac.js?v='.VERSION.'"></script>'."\n", 20);
		}
		return [
			'htext_html' => $d['htext_html'],
			'search' => $conf['filesearch'] == 1,
			'm_set' => isset($_GET['m']) && is_string($_GET['m']),
			'm' => isset($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : '',
			'kw' => (string)$d['kw'],
			'cta' => $cfg['cta'],
		];
	},
];
