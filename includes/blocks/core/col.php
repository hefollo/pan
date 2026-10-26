<?php
/*
 * 栏：「分栏」里的一列，是个容器。添加「分栏」时编辑器按栏数自动建好，一般不用单独添加。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '栏',
	'group' => '布局',
	'scope' => 'both',
	'container' => true,
	'description' => '分栏里的一列，往里放卡片',
	'schema' => [],
];
