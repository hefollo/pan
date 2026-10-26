<?php
/*
 * 后台面板：页面用 theme_panel_begin()/theme_panel_end() 捕获的一块内容（一个设置面板、一张表格……），
 * 按编号摆在这里。主题覆盖 blocks/admin/panel 模板可以给面板外面包一层；面板里面的表单是后台程序的，
 * 字段名和 id 脚本要用，只能靠样式换皮。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '后台面板',
	'group' => '后台',
	'scope' => 'admin',
	'schema' => [
		['key'=>'panel', 'type'=>'text', 'label'=>'面板编号', 'default'=>''],
	],
	'data' => function($cfg, &$ctx){
		$p = theme_panel_get($cfg['panel']);
		if($p === null)return null;
		return ['content_html' => $p['html'], 'panel_id' => $cfg['panel'], 'label' => $p['label']];
	},
];
