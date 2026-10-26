<?php
/**
 * 前台公共外壳（DEC-20261025-003）：<head>、导航、侧栏卡片、页脚。
 *
 * 外壳本身是一份布局（页面名 _shell），中间的 shell.page 卡片是「页面内容放这儿」的占位。
 * header.php 调 theme_shell_open() 输出占位之前的部分，页面自己输出内容，
 * footer.php 调 theme_shell_close() 输出占位之后的部分。
 * <head> 和页面末尾那几行基础脚本（jQuery 插件、Bootstrap、Material 初始化）不是卡片，
 * 是主题可以覆盖、但站长在编辑器里删不掉的固定模板 shell/head、shell/tail：删了整站就坏了。
 */

if(!defined('IN_CRONLITE'))exit('Access Denied');

/* shell.page 卡片输出的占位标记：带 NUL 字符，正常页面内容里不会出现 */
function theme_shell_marker(){
	return "\x00pan-shell-page\x00";
}

/* <body> 上的类名：theme-<id>，工作台家族加 studio-layout，布局型加 layout-theme，再加主题清单里写的 */
function theme_body_class($id){
	$m = theme_get($id);
	$cls = theme_chain_classes($id, 'theme-');
	$flags = [];
	foreach(theme_chain($id) as $tid){
		$t = theme_get($tid);
		$flags = array_merge($flags, $t['flags']);
	}
	if(in_array('studio', $flags, true))$cls .= ' studio-layout';
	if(in_array('layout', $flags, true))$cls .= ' layout-theme';
	if($m && $m['body_class'] !== '')$cls .= ' '.$m['body_class'];
	return $cls;
}

/*
 * 内置外观的样式表（S26）：assets/css/build/ 下按外观拆好的 style.<id>.css / admin.<id>.css（tools/build_theme_css.php 生成），
 * 只含可能对这套外观生效的规则，顺序和源文件一样。上传的主题用继承链上最近的内置外观那份，没有内置祖先用 _base。
 * 拆分文件缺失，或者源文件改过还没重新生成（manifest.json 里记的 md5 对不上），就用整份 style.css / admin.css。
 */
function theme_css_bundle_url($id, $name, $base = './'){
	static $fresh = [];
	$src = 'assets/css/'.$name.'.css';
	$full = $base.$src.'?v='.asset_ver($src);
	if(!isset($fresh[$name])){
		$fresh[$name] = false;
		$man = json_decode((string)@file_get_contents(ROOT.'assets/css/build/manifest.json'), true);
		if(is_array($man) && isset($man['sources'][$name]) && is_string($man['sources'][$name]) && is_file(ROOT.$src) && md5_file(ROOT.$src) === $man['sources'][$name]){
			$fresh[$name] = $man['sources'][$name];
		}
	}
	if($fresh[$name] === false)return $full;
	$key = '_base';
	foreach(theme_chain($id) as $tid){
		$m = theme_get($tid);
		if($m && $m['_source'] === 'builtin'){ $key = $tid; break; }
	}
	$file = 'assets/css/build/'.$name.'.'.$key.'.css';
	if(!is_file(ROOT.$file))return $full;
	return $base.$file.'?v='.substr($fresh[$name], 0, 8);
}

/* 主题包自己带的样式表 / 脚本（theme.json 的 styles、scripts），继承链从父到子依次加载 */
function theme_asset_list($id, $key, $base = './'){
	$out = [];
	foreach(array_reverse(theme_chain($id)) as $tid){
		$m = theme_get($tid);
		foreach($m[$key] as $path){
			$url = theme_asset_url($tid, $path, $base);
			if($url !== '')$out[] = ['url'=>$url];
		}
	}
	return $out;
}

/**
 * 输出 doctype 到 <body>，再加外壳布局里页面内容之前的部分。
 * 外壳布局里页面内容之后的部分先存着，等 footer.php 来取。
 */
function theme_shell_open($id){
	global $conf, $title, $is_file;
	//首屏底色：自己没有配色表就用继承链上第一个有的（上传主题继承内置外观时）
	$guard_spec = false;
	foreach(theme_chain($id) as $tid){
		if($guard_spec)break;
		$guard_spec = function_exists('theme_gradient_of') ? theme_gradient_of($tid) : false;
	}
	$guard_bg = $guard_spec ? (!empty($guard_spec['bg'][0]) ? $guard_spec['bg'][0] : $guard_spec['pg']) : '';
	if(!preg_match('/^#[0-9a-fA-F]{6}$/', $guard_bg))$guard_bg = '#ffffff';
	$head = theme_tpl_render('shell/head', [
		'title_html' => (string)$title,
		'icon_html' => site_icon_tags(),
		'keywords_html' => isset($conf['keywords']) ? $conf['keywords'] : '',
		'description_html' => isset($conf['description']) ? $conf['description'] : '',
		'guard_bg' => $guard_bg,
		'is_file' => !empty($is_file),
		'style_ver' => asset_ver('assets/css/style.css'),
		'style_url' => theme_css_bundle_url($id, 'style', ''),
		'recolor_html' => theme_recolor_tag($id),
		'gradient_html' => theme_gradient_style($id),
		'settings_css_html' => theme_settings_css($id).theme_settings_bridge_css($id, 'front'),
		'theme_styles' => theme_asset_list($id, 'styles'),
		'body_class' => theme_body_class($id),
		'theme' => theme_vars($id),
	], $id);
	$html = theme_render_layout('_shell', [], 'front', $id);
	$marker = theme_shell_marker();
	$pos = strpos($html, $marker);
	if($pos === false){
		$GLOBALS['_theme_shell_after'] = '';
		return $head.$html;
	}
	$GLOBALS['_theme_shell_after'] = substr($html, $pos + strlen($marker));
	return $head.substr($html, 0, $pos);
}

/* 外壳布局里页面内容之后的部分 + 固定的基础脚本 */
function theme_shell_close($id){
	$after = isset($GLOBALS['_theme_shell_after']) ? $GLOBALS['_theme_shell_after'] : '';
	$GLOBALS['_theme_shell_after'] = '';
	$tail = theme_tpl_render('shell/tail', [
		'theme_id' => $id,
		'theme' => theme_vars($id),
		'is_studio' => in_array($id, studio_family_keys(), true),
		'version' => VERSION,
		'theme_scripts' => theme_asset_list($id, 'scripts'),
	], $id);
	return $after.$tail.theme_preview_banner_html();
}

/* ============ 后台外壳 ============ */

/*
 * 后台页面开头：<head>（固定模板 admin/head）、后台外壳布局 admin/_shell（导航等卡片），
 * 再加后台公共脚本和动态换页的起点标记（固定模板 admin/shell_script，删了后台就不能用了）。
 * 后台跟着当前外观走：内置外观靠 admin.css 里的 admin-theme-* 作用域，上传的主题可以在
 * theme.json 的 admin_styles 里带后台样式表，主题设置生成的 CSS 变量也在后台输出。
 */
function theme_admin_shell_open($id, $body_class, $doc_title_html){
	$html = theme_tpl_render('admin/head', [
		'doc_title_html' => $doc_title_html,
		'icon_html' => site_icon_tags('../'),
		'admin_ver' => asset_ver('assets/css/admin.css'),
		'admin_css_url' => theme_css_bundle_url($id, 'admin', '../'),
		'recolor_html' => theme_recolor_tag($id, 'admin', '../'),
		'gradient_html' => theme_gradient_style($id, 'admin'),
		'theme_styles' => theme_asset_list($id, 'admin_styles', '../'),
		'settings_css_html' => theme_settings_css($id).theme_settings_bridge_css($id, 'admin'),
		'body_class' => $body_class,
		'theme' => theme_vars($id),
	], $id);
	$html .= theme_render_layout('admin/_shell', [], 'admin', $id);
	$html .= theme_tpl_render('admin/shell_script', ['theme' => theme_vars($id)], $id);
	return $html.theme_preview_banner_html('../');
}

/* ============ 后台外观设置 ============ */

/*
 * 外观设置页的外观卡片：按主题注册表分组生成（布局型、配色型、站长上传的），模板 admin/theme_cards。
 * 内置外观的卡片里是 CSS 画的小预览（admin.css 里 .appearance-preview-<id>），上传的主题有 preview 图就显示图。
 */
function theme_admin_cards_html($active){
	$groups = [
		'layout' => ['title'=>'布局型外观', 'desc'=>'会改变页面结构：导航位置、内容排版都不一样，前台和后台同时生效。', 'themes'=>[]],
		'color' => ['title'=>'配色型外观', 'desc'=>'只改变颜色、背景纹理和质感，页面结构保持默认的顶部导航布局。', 'themes'=>[]],
		'upload' => ['title'=>'上传的主题', 'desc'=>'站长上传的主题包（在「主题包管理」里安装、删除），前台和后台一起换。', 'themes'=>[]],
	];
	foreach(theme_registry() as $id => $m){
		$g = $m['_source'] === 'upload' ? 'upload' : $m['group'];
		$groups[$g]['themes'][] = [
			'id' => $id,
			'name' => $m['name'],
			'description' => $m['description'],
			'active' => $id === $active,
			'preview_url' => $m['preview'] !== '' ? theme_asset_url($id, $m['preview'], '../') : '',
			'upload' => $m['_source'] === 'upload',
			'version' => $m['version'],
			'author' => $m['author'],
		];
	}
	$list = [];
	foreach($groups as $g)if($g['themes'])$list[] = $g;
	return theme_tpl_render('admin/theme_cards', ['groups'=>$list], $active);
}

/* 外观设置页里「主题设置」面板：当前主题声明了可调设置（theme.json 的 settings）才有，内置外观都没有 */
function theme_admin_settings_html($id){
	$m = theme_get($id);
	if(!$m || !$m['settings'])return '';
	$vals = theme_settings($id);
	$fields = [];
	foreach($m['settings'] as $f){
		$v = $vals[$f['key']];
		$item = ['key'=>$f['key'], 'label'=>$f['label'], 'help'=>$f['help'], 'value'=>(string)$v, 'is_'.(in_array($f['type'], ['html', 'lines'], true) ? 'textarea' : $f['type'])=>true];
		if($f['type'] === 'switch')$item['on'] = !empty($v);
		if($f['type'] === 'number'){
			$item['has_min'] = $f['min'] !== null; $item['min'] = $f['min'];
			$item['has_max'] = $f['max'] !== null; $item['max'] = $f['max'];
		}
		if($f['type'] === 'select'){
			$item['options'] = [];
			foreach($f['options'] as $ok => $ol)$item['options'][] = ['value'=>$ok, 'label'=>$ol, 'selected'=>(string)$ok === (string)$v];
		}
		$fields[] = $item;
	}
	return theme_tpl_render('admin/theme_settings', ['id'=>$id, 'name'=>$m['name'], 'fields'=>$fields], $id);
}

/* 外观设置页里「主题包」面板：上传安装、已上传的列表（导出 / 删除）、把任意外观导出成新主题的起点、最近的模板错误 */
function theme_admin_packages_html($active){
	$uploads = [];
	$all = [];
	foreach(theme_registry() as $id => $m){
		$all[] = ['id'=>$id, 'name'=>$m['name'], 'active'=>$id === $active];
		if($m['_source'] !== 'upload')continue;
		$parent = $m['extends'] !== '' && theme_get($m['extends']) ? theme_get($m['extends'])['name'] : '';
		$uploads[] = ['id'=>$id, 'name'=>$m['name'], 'version'=>$m['version'], 'author'=>$m['author'], 'extends'=>$parent, 'active'=>$id === $active];
	}
	$lim = theme_package_limits();
	$data = ['uploads'=>$uploads, 'has_uploads'=>!empty($uploads), 'all'=>$all, 'max_zip'=>size_format($lim['zip_bytes'])];
	$log = theme_tpl_cache_dir() !== '' ? theme_tpl_cache_dir().'/errors.log' : '';
	if($log !== '' && is_file($log)){
		$lines = array_slice(array_filter(explode("\n", (string)@file_get_contents($log))), -5);
		if($lines)$data['errors'] = ['lines'=>array_reverse($lines)];
	}
	return theme_tpl_render('admin/theme_packages', $data, $active);
}

/*
 * 预览模式（后台布局编辑器）下页面上多出来的东西：右下角一条「正在预览草稿」的提示（带退出按钮），
 * 以及 assets/js/layout-preview.js（在编辑器的预览框里点页面元素，就选中对应的卡片）。平时都是空串。
 */
function theme_preview_banner_html($base = './'){
	if(!layout_preview_active())return '';
	$theme = theme_get(theme_preview_theme());
	return '<div id="panPreviewBar" style="position:fixed;right:12px;bottom:12px;z-index:2147483000;padding:8px 14px;border-radius:999px;background:#1f2937;color:#fff;font:13px/1.4 sans-serif;box-shadow:0 6px 20px rgba(0,0,0,.25)">'
		.'正在预览草稿（'.htmlspecialchars($theme ? $theme['name'] : '', ENT_QUOTES, 'UTF-8').'）'
		.' <a href="javascript:void(0)" style="color:#93c5fd;margin-left:6px" onclick="document.cookie=\'pan_preview=; path=/; max-age=0\';location.reload()">退出预览</a></div>'
		.'<script src="'.$base.'assets/js/layout-preview.js?v='.asset_ver('assets/js/layout-preview.js').'"></script>';
}
