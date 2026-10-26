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
	'data' => function($cfg, &$ctx){
		$d = $ctx['data'];
		if($d['folder_id'] <= 0)return null;
		$path = [];
		foreach($d['folder_path'] as $f){
			$path[] = ['href'=>$d['folder_back'].'&folder='.$f['id'], 'name'=>$f['name']];
		}
		return ['back'=>$d['folder_back'], 'path'=>$path];
	},
];
