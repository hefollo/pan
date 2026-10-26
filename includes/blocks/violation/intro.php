<?php
/* 违规公示页的标题和说明（说明文字在后台「违规公示管理」里改） */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '公示页标题',
	'group' => '赞助与公示',
	'scope' => 'front',
	'data' => function($cfg, &$ctx){
		global $conf;
		return ['notice' => isset($conf['violation_notice']) && $conf['violation_notice'] !== '' ? $conf['violation_notice']
			: '本站严格遵守国家法律法规，对用户举报及系统检测发现的违规文件一律予以封禁，并在此公示。文件名、上传IP等信息已做脱敏处理。'];
	},
];
