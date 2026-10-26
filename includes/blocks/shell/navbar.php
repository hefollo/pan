<?php
/*
 * 导航栏。布局型外观靠 CSS 把它变成左侧侧栏，所以侧栏底部的卡片（今日上传、我的权限、升级卡）
 * 都是它的子卡片，渲染在 navbar-collapse 的末尾。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '导航栏',
	'group' => '外壳',
	'scope' => 'front',
	'pages' => ['_shell'],
	'container' => true,
	/*
	 * 菜单项可以在「页面布局 → 前台公共外壳 → 导航栏」里配置（DEC-20261026-001）：内置项排序、隐藏、改名、换图标，
	 * 加自定义页面和链接。没配置过（空串）就是下面的默认顺序，输出和原来一模一样。
	 */
	'schema' => [
		['key'=>'menu', 'type'=>'menu', 'label'=>'菜单项', 'default'=>'', 'help'=>'菜单全站共用，换外观不变；拖动排序；内置项的开关（赞助名单、购买、违规公示）关掉时不显示'],
		['key'=>'show_mine', 'type'=>'switch', 'label'=>'右侧显示「我的文件」', 'default'=>1],
		['key'=>'mine_text', 'type'=>'text', 'label'=>'「我的文件」文字', 'default'=>'我的文件'],
		['key'=>'login_text', 'type'=>'text', 'label'=>'没登录时右侧的文字', 'default'=>'未登录'],
	],
	'data' => function($cfg, &$ctx){
		global $conf, $islogin2, $userrow, $is_file;
		$layout = in_array($ctx['theme_id'], layout_theme_keys(), true);
		//当前是哪个自定义页面（page.php 设的；网站入口显示首页时也算），网站入口页
		$cur_page = isset($GLOBALS['custom_page_current']) ? (string)$GLOBALS['custom_page_current'] : '';
		$home = home_page_slug();
		//内置项：能不能显示、地址、当前页判断
		$avail = [];
		$files_cls = $cur_page !== '' ? null : checkIfActive('index,');
		if($home !== '' && $cur_page === '' && basename($_SERVER['SCRIPT_NAME']) === 'index.php')$files_cls = 'active';
		//设了网站入口以后 / 显示首页，文件列表要带个参数才进得去
		$avail['files'] = ['has_class'=>true, 'cls'=>$files_cls, 'href'=>$home !== '' ? './?list=1' : './', 'icon'=>'fa-list', 'label'=>'文件列表'];
		$avail['upload'] = ['has_class'=>true, 'cls'=>checkIfActive('upload'), 'href'=>'./upload.php', 'icon'=>'fa-upload', 'label'=>'上传文件'];
		//赞助名单可以在「网站信息设置」里整个关掉；布局型外观用站内的赞助页，其它外观跳原来的独立赞助页
		if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1){
			$avail['sponsor'] = $layout
				? ['has_class'=>true, 'cls'=>checkIfActive('sponsor'), 'href'=>'./sponsor.php', 'icon'=>'fa-money', 'label'=>'赞助名单']
				: ['has_class'=>false, 'cls'=>'', 'href'=>'./includes/sponsor/', 'icon'=>'fa-money', 'label'=>'赞助名单'];
		}
		if(function_exists('is_buy_open') && is_buy_open()){
			$avail['buy'] = ['has_class'=>true, 'cls'=>checkIfActive('buy'), 'href'=>'./buy.php', 'icon'=>'fa-shopping-cart', 'label'=>'购买权限'];
		}
		if(!isset($conf['violation_open']) || $conf['violation_open'] == 1){
			$avail['violation'] = ['has_class'=>true, 'cls'=>checkIfActive('violation'), 'href'=>'./violation.php', 'icon'=>'fa-gavel', 'label'=>'违规公示'];
		}
		//菜单是网站级的（换外观不变，见 nav_menu_site()；预览时用草稿）；网站没配置过时才看主题默认布局里给的
		$menu_json = nav_menu_site(layout_preview_active());
		if($menu_json === '')$menu_json = $cfg['menu'];
		$menu = $menu_json !== '' ? json_decode($menu_json, true) : null;
		if(!is_array($menu)){
			$menu = [];
			foreach(array_keys(nav_menu_builtins()) as $k)$menu[] = ['type'=>'builtin', 'key'=>$k];
		}
		$left = [];
		foreach($menu as $it){
			if(!empty($it['hidden']))continue;
			if($it['type'] === 'builtin'){
				if(empty($avail[$it['key']]))continue;
				$row = $avail[$it['key']];
			}elseif($it['type'] === 'page'){
				//删掉的页面不显示
				$p = custom_page_get($it['slug']);
				if(!$p)continue;
				$row = ['has_class'=>true, 'cls'=>$cur_page === $p['slug'] ? 'active' : null, 'href'=>'./page.php?p='.$p['slug'], 'icon'=>'fa-file-text-o', 'label'=>$p['title']];
			}else{
				$row = ['has_class'=>false, 'cls'=>'', 'href'=>$it['href'], 'icon'=>'fa-link', 'label'=>$it['label']];
				if(!empty($it['blank']))$row['blank'] = true;
			}
			if(isset($it['label']))$row['label'] = $it['label'];
			if(isset($it['icon']))$row['icon'] = $it['icon'];
			$left[] = $row;
		}
		if(!empty($is_file)){
			$left[] = ['has_class'=>true, 'cls'=>checkIfActive('file'), 'href'=>'', 'icon'=>'fa-file', 'label'=>'文件查看'];
		}
		$data = [
			'brand_html' => $conf['title'],
			'nav_left' => $left,
			'logged_in' => !empty($islogin2),
			'mine_user_cls' => checkIfActive('user'),
			'mine_guest_cls' => checkIfActive('mine'),
			'userlogin' => !empty($conf['userlogin']),
			'login_cls' => checkIfActive('login'),
		];
		if(!empty($islogin2)){
			//有头像就盖在登录方式图标上；图标的盒子不动，所以各外观的导航排版都不受影响
			$face = user_avatar_url($userrow);
			$data['nav_icon'] = $userrow['type']=='qq' ? 'qq' : ($userrow['type']=='mail' ? 'envelope' : 'wechat');
			$data['nav_face'] = $face;
			$data['has_face'] = $face !== '';
			//昵称在旧代码里就是原样输出的
			$data['nickname_html'] = $userrow['nickname'];
		}
		return $data;
	},
];
