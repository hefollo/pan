<?php
/*
 * 按钮组（DEC-20261026-001）：一排按钮，每行一个「文字|链接|样式」，样式写 primary（主色）或留空（普通）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '按钮组',
	'group' => '通用展示',
	'scope' => 'front',
	'description' => '一排快捷入口按钮',
	'schema' => [
		['key'=>'items', 'type'=>'lines', 'label'=>'按钮', 'default'=>"上传文件|./upload.php|primary\n文件列表|./?list=1", 'help'=>'每行一个：文字|链接|样式（primary 为主色，留空为普通）'],
		['key'=>'align', 'type'=>'select', 'label'=>'对齐', 'default'=>'left', 'options'=>['left'=>'靠左', 'center'=>'居中']],
	],
	'data' => function($cfg, &$ctx){
		$items = [];
		foreach(preg_split('/\r?\n/', $cfg['items']) as $line){
			$p = array_map('trim', explode('|', $line));
			$href = isset($p[1]) ? common_card_href($p[1]) : '';
			if($p[0] === '' || $href === '')continue;
			$items[] = ['text'=>$p[0], 'href'=>$href, 'primary'=>isset($p[2]) && $p[2] === 'primary'];
			if(count($items) >= 12)break;
		}
		if(!$items)return null;
		return ['items'=>$items, 'center'=>$cfg['align'] === 'center'];
	},
];
