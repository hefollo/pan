<?php
/*
 * 上传面板：拖拽区、进度 / 结果 / 队列（Vue 渲染，脚本是 assets/js/uploadnew.js）、
 * 选择文件按钮、存储位置、是否在首页显示、访问密码。
 * 子卡片显示在标题和按钮之间（默认放权限条，让用户上传前就知道今天还能传多少）。
 * 这张卡片必须放在「上传页挂载点」里面：#fileInput、#file、#csrf_token、#uploadFile 这些 id 脚本要用。
 * 模板用 <% %> 当 Mustache 分隔符，因为 Vue 自己要用 {{ }}。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '上传面板',
	'group' => '上传页',
	'scope' => 'front',
	'pages' => ['upload'],
	'container' => true,
	'data' => function($cfg, &$ctx){
		global $conf, $islogin2;
		$d = $ctx['data'];
		$storages = [];
		foreach($d['storage_options'] as $o)$storages[] = ['key'=>$o['key'], 'name'=>$o['name']];
		theme_defer('upload.scripts', theme_tpl_render('blocks/upload/scripts', [
			'size' => $d['size'],
			'limit' => intval($d['limit']),
			'used' => intval($d['used']),
			'remaining' => intval($d['remaining']),
			'folder_id' => intval($d['folder_id']),
			//存储下拉的默认选中项必须在这儿给出：v-model 首次渲染会把 select 刷成未选中，那时再读就是 null
			'storage_json' => json_encode($d['storage_options'] ? $d['storage_options'][0]['key'] : ''),
			'show_default' => upload_show_default() ? 'true' : 'false',
			'version' => VERSION,
		], $ctx['theme_id']), 10);
		return [
			'csrf' => $d['csrf_token'],
			'forbid' => $conf['forcelogin'] == 1 && !$islogin2,
			'has_storage' => !empty($storages),
			'storages' => $storages,
		];
	},
];
