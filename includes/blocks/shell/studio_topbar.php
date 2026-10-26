<?php
/*
 * 工作台家族的顶部搜索条：每个页面都有，所以放在外壳里。
 * Ctrl K 快捷键由 layout-studio.js 接管，没开 JS 时它就只是个说明标签。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '顶部搜索条',
	'group' => '外壳',
	'scope' => 'front',
	'pages' => ['_shell'],
	'schema' => [
		['key'=>'placeholder', 'type'=>'text', 'label'=>'搜索框提示文字', 'default'=>'搜索文件名、格式、标签…'],
		['key'=>'kbd', 'type'=>'switch', 'label'=>'显示快捷键提示 Ctrl K', 'default'=>1],
		['key'=>'upload_text', 'type'=>'text', 'label'=>'上传按钮文字', 'default'=>'上传文件'],
	],
	'data' => function($cfg, &$ctx){
		global $conf, $islogin2, $userrow;
		$data = [
			'search_open' => !empty($conf['filesearch']),
			'kw' => isset($_GET['kw']) && is_string($_GET['kw']) ? $_GET['kw'] : '',
			'title' => $conf['title'],
			'logged_in' => !empty($islogin2),
		];
		if(!empty($islogin2)){
			$name = !empty($userrow['nickname']) ? $userrow['nickname'] : '我';
			$data['name'] = $name;
			$data['initial'] = layout_name_initial($name);
			$data['has_initial'] = $data['initial'] !== '';
			$data['avatar_img_html'] = user_avatar_img($userrow);
		}
		return $data;
	},
];
