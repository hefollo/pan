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
	'description' => '上传区本体（选择文件、存储位置、密码）；上传的行为不能改，这里改文字',
	'schema' => [
		['key'=>'pick', 'type'=>'text', 'label'=>'选择文件按钮', 'default'=>'选择文件/批量上传'],
		['key'=>'login', 'type'=>'text', 'label'=>'要求登录时的按钮', 'default'=>'请先登录'],
		['key'=>'storage', 'type'=>'text', 'label'=>'存储位置标签', 'default'=>'存储位置'],
		['key'=>'show', 'type'=>'text', 'label'=>'「在首页显示」勾选框文字', 'default'=>'在首页文件列表显示'],
		['key'=>'pwd', 'type'=>'text', 'label'=>'密码框提示', 'default'=>'请输入密码（留空则不设置）'],
	],
	'data' => function($cfg, &$ctx){
		global $conf, $islogin2;
		$d = $ctx['data'];
		$vue = function($h){ return str_replace(['{{', '}}'], ['{&#8203;{', '}&#8203;}'], $h); };
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
			'pick_html' => $vue(theme_text_html($cfg['pick'])),
			'login_html' => $vue(theme_text_html($cfg['login'])),
			'storage_html' => $vue(theme_text_html($cfg['storage'])),
			'show_html' => $vue(theme_text_html($cfg['show'])),
			'pwd_placeholder' => str_replace(['{{', '}}'], ['{ {', '} }'], $cfg['pwd']),
			'forbid' => $conf['forcelogin'] == 1 && !$islogin2,
			'has_storage' => !empty($storages),
			'storages' => $storages,
		];
	},
];
