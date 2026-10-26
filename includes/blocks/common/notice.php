<?php
/*
 * 站点公告条（后台「公告与广告设置」里配的），内容和样式由 script_manager.php 生成。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '站点公告',
	'group' => '通用',
	'scope' => 'front',
	'description' => '公告内容在后台「广告公告位设置」里改',
	'data' => function($cfg, &$ctx){
		global $conf;
		include_once SYSTEM_ROOT.'script_manager.php';
		return ['content_html' => mpimg_render_notice_html($conf)];
	},
];
