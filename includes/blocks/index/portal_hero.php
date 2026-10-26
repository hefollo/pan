<?php
/*
 * 上传门户风的首屏大上传区。直接把上传页那套 Vue 组件挂在这儿（同一个 assets/js/uploadnew.js），
 * 需要的 DOM 就四个：#app、#fileInput（拖拽区）、#file（隐藏的文件框）、#csrf_token。
 * 外层仍然保留 <a href="./upload.php">：Vue 或 CDN 没加载成功时 @click.prevent 不生效，
 * 点击就退回原来的跳转行为。首屏只做「拖进来就传」，密码、存储位置这些选项仍然在上传页设置。
 * 搜索结果和「我的文件」里不显示，免得把结果顶到下面去。
 * 模板里用 <% %> 当 Mustache 分隔符，因为 Vue 自己要用 {{ }}。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '首屏上传区',
	'group' => '首页',
	'scope' => 'front',
	'pages' => ['index'],
	'schema' => [
		['key'=>'kicker', 'type'=>'text', 'label'=>'小标题', 'default'=>'快速 · 安全 · 长期可用', 'help'=>'清空不显示'],
		['key'=>'title', 'type'=>'textarea', 'label'=>'大标题', 'default'=>"把文件放上来，\n链接带去任何地方。", 'help'=>'换行就是标题里的换行'],
		['key'=>'desc', 'type'=>'textarea', 'label'=>'说明', 'default'=>'上传图片、视频、文档或压缩包，即刻生成可分享的外链。无需安装客户端，打开浏览器就能用。'],
		['key'=>'trust', 'type'=>'lines', 'label'=>'打勾的卖点', 'default'=>"支持批量上传\n自动生成外链\n多种存储可选", 'help'=>'一行一条；清空不显示'],
		['key'=>'hint_size', 'type'=>'text', 'label'=>'上传框提示（有大小限制）', 'default'=>'单个文件最大 {size} MB，支持任意格式，也可以 Ctrl+V 粘贴', 'help'=>'{size} 是单个文件上限（MB）'],
		['key'=>'hint_free', 'type'=>'text', 'label'=>'上传框提示（不限大小）', 'default'=>'不限制文件大小，支持任意格式，也可以 Ctrl+V 粘贴'],
		['key'=>'pick_btn', 'type'=>'text', 'label'=>'选择文件按钮', 'default'=>'选择本地文件'],
		['key'=>'login_title', 'type'=>'text', 'label'=>'要求登录时的标题', 'default'=>'登录后即可上传'],
		['key'=>'login_sub', 'type'=>'text', 'label'=>'要求登录时的说明', 'default'=>'本站要求登录后才能上传文件'],
		['key'=>'login_btn', 'type'=>'text', 'label'=>'去登录按钮', 'default'=>'去登录'],
		['key'=>'tip', 'type'=>'switch', 'label'=>'显示底部「去上传页」提示', 'default'=>1],
	],
	'data' => function($cfg, &$ctx){
		global $DB, $conf, $islogin2;
		$d = $ctx['data'];
		if($d['kw'] || $d['is_mine'])return null;
		$size = get_effective_upload_size_limit();
		$limit = get_effective_upload_count_limit();
		//今日已传数量走会话缓存（2 分钟），只用于前端预检，真正的限额由服务端再判一次
		$used = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
		//开了多存储时跟上传页口径一致：默认落到第一个可用存储；没开就留空，由服务端用默认存储
		$storage = '';
		if(function_exists('storage_multi_open') && storage_multi_open()){
			$list = storage_allowed_list();
			if(count($list) > 1)$storage = $list[0];
		}
		$forbid = (isset($conf['forcelogin']) && $conf['forcelogin'] == 1 && empty($islogin2));
		//强制登录又没登录时首屏是个去登录的入口，不需要上传脚本
		if(!$forbid){
			theme_defer('index.portal_scripts', theme_tpl_render('blocks/index/portal_scripts', [
				'size' => intval($size),
				'limit' => intval($limit),
				'used' => intval($used),
				'remaining' => intval($limit > 0 ? max(0, $limit - $used) : -1),
				'storage_json' => json_encode($storage),
				'show_default' => upload_show_default() ? 'true' : 'false',
				'version' => VERSION,
			], $ctx['theme_id']), 30);
		}
		//这块是 Vue 的挂载点，文字里的 {{ 会被 Vue 当成表达式，拆开
		$vue = function($html){ return str_replace(['{{', '}}'], ['{&#8203;{', '}&#8203;}'], $html); };
		$trust = [];
		foreach(common_card_lines($cfg['trust']) as $l)$trust[] = ['text_html'=>$vue(theme_text_html($l['text']))];
		return [
			'forbid' => $forbid,
			'hint_html' => $vue($size > 0 ? theme_text_html($cfg['hint_size'], ['size'=>intval($size)]) : theme_text_html($cfg['hint_free'])),
			'kicker_html' => $vue(theme_text_html(trim($cfg['kicker']))),
			'title_html' => $vue(theme_text_html(trim($cfg['title']), [], true)),
			'desc_html' => $vue(theme_text_html(trim($cfg['desc']), [], true)),
			'trust' => $trust,
			'has_trust' => !empty($trust),
			'pick_html' => $vue(theme_text_html($cfg['pick_btn'])),
			'login_title_html' => $vue(theme_text_html($cfg['login_title'])),
			'login_sub_html' => $vue(theme_text_html($cfg['login_sub'])),
			'login_btn_html' => $vue(theme_text_html($cfg['login_btn'])),
			'csrf' => $d['csrf_token'],
		];
	},
];
