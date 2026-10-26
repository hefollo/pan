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
		return [
			'forbid' => $forbid,
			'size_text' => $size > 0 ? ('单个文件最大 '.$size.' MB，支持任意格式') : '不限制文件大小，支持任意格式',
			'csrf' => $d['csrf_token'],
		];
	},
];
