<?php
/*
 * 容器：把子卡片包进一个标签里。各外观原来那些外层结构（layout-shell、cockpit-main、well……）
 * 都是用它在布局里表达的；站长在编辑器里也可以用它分栏、加背景。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '容器',
	'group' => '布局',
	'scope' => 'both',
	'container' => true,
	'schema' => [
		['key'=>'tag', 'type'=>'select', 'label'=>'标签', 'default'=>'div', 'options'=>['div'=>'div', 'section'=>'section', 'aside'=>'aside', 'main'=>'main', 'header'=>'header', 'nav'=>'nav']],
		['key'=>'class', 'type'=>'text', 'label'=>'CSS 类名', 'default'=>'', 'help'=>'多个类名用空格隔开'],
		['key'=>'dom_id', 'type'=>'text', 'label'=>'元素 id', 'default'=>''],
		['key'=>'style', 'type'=>'text', 'label'=>'行内样式', 'default'=>'', 'help'=>'如 float: none;，一般不用填'],
	],
	'data' => function($cfg, &$ctx){
		//类名和 id 只留安全字符：模板会转义，这里再收紧一道，免得写出奇怪的属性值
		return [
			'class_attr' => trim(preg_replace('/[^A-Za-z0-9_\- ]+/', '', $cfg['class'])),
			'id_attr' => preg_replace('/[^A-Za-z0-9_\-]+/', '', $cfg['dom_id']),
			//行内样式带了能跳出属性或注入的东西（引号、尖括号、反斜杠、url(、expression）就整条不要
			'style_attr' => preg_match('/url\s*\(|expression|javascript|[<>"\'\\\\]/i', $cfg['style']) ? '' : trim($cfg['style']),
		];
	},
];
