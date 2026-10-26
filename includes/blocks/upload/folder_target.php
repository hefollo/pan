<?php
/*
 * 从「我的文件」某个文件夹点「上传到这里」进来时，页面顶部标出目标位置。
 * 只管显示，真正放哪儿由 ajax.php 的 folder_upload_target() 再校验一次归属。
 * 这一条要放在上传页挂载点外面：文件夹名是用户输入的，放进 #app 会被 Vue 当成模板解析。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '上传目标文件夹',
	'group' => '上传页',
	'scope' => 'front',
	'pages' => ['upload'],
	'description' => '从「我的文件」某个文件夹点上传时才显示',
	'schema' => [
		['key'=>'label', 'type'=>'text', 'label'=>'前面的文字', 'default'=>'上传到：'],
		['key'=>'root', 'type'=>'text', 'label'=>'根目录的名字', 'default'=>'我的文件'],
	],
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if($d['folder_id'] <= 0)return null;
		$path = [];
		foreach($d['folder_path'] as $f){
			$path[] = ['href'=>$d['folder_back'].'&folder='.$f['id'], 'name'=>$f['name']];
		}
		$vue = function($h){ return str_replace(['{{', '}}'], ['{&#8203;{', '}&#8203;}'], $h); };
		return ['back'=>$d['folder_back'], 'path'=>$path, 'label_html'=>$vue(theme_text_html($cfg['label'])), 'root_html'=>$vue(theme_text_html($cfg['root']))];
	},
];
