<?php
/* 赞助名单页的标题和致谢语 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '赞助页标题',
	'group' => '赞助与公示',
	'scope' => 'front',
	'schema' => [
		['key'=>'text', 'type'=>'text', 'label'=>'致谢语', 'default'=>'感谢每一位支持本站的朋友，您的支持是本站持续运行的动力。'],
	],
];
