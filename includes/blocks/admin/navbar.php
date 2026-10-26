<?php
/*
 * 后台导航：一级菜单固定 5 项（首页 / 文件 / 用户 / 设置 / 退出），分组按「对象」分：
 * 管文件的进「文件」，管人和钱的进「用户」，配置项全进「设置」。
 * 侧栏外观下这些下拉是常驻展开的（admin.css 里改成 static），看起来就是三个带标题的分组。
 * 后台的动态换页（admin/shell_script）按 .navbar-nav 下的链接找页面，结构换皮时要保留。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '后台导航',
	'group' => '后台外壳',
	'scope' => 'admin',
	'description' => '后台菜单是固定的，只能用通用选项调样式',
	'pages' => ['admin/_shell'],
	'data' => function($cfg, &$ctx){
		global $conf, $islogin;
		if(empty($islogin))return null;
		return [
			'brand_html' => admin_brand_html(),
			'a_index' => checkIfActive('index,'),
			'a_file' => checkIfActive('file,folders,replace,green_log'),
			'a_user' => checkIfActive('user,order,mail_log'),
			'a_set' => checkIfActive('set,layout,set_stor,set_script,set_sponsor,set_violation,set_pay,set_mail,update,level'),
			's_file' => admin_sub_active('file.php'),
			's_folders' => admin_sub_active('folders.php'),
			's_replace' => admin_sub_active('replace.php'),
			's_green_log' => admin_sub_active('green_log.php'),
			's_user' => admin_sub_active('user.php'),
			's_order' => admin_sub_active('order.php'),
			's_mail_log' => admin_sub_active('mail_log.php'),
			's_site' => admin_sub_active('set.php', 'site'),
			's_appearance' => admin_sub_active('set.php', 'appearance'),
			's_layout' => admin_sub_active('layout.php'),
			's_script' => admin_sub_active('set_script.php'),
			's_setfile' => admin_sub_active('set.php', 'file'),
			's_stor' => admin_sub_active('set_stor.php'),
			's_api' => admin_sub_active('set.php', 'api'),
			's_setuser' => admin_sub_active('set.php', 'user'),
			's_mail' => admin_sub_active('set_mail.php'),
			's_level' => admin_sub_active('level.php'),
			's_pay' => admin_sub_active('set_pay.php'),
			's_green' => admin_sub_active('set.php', 'green'),
			's_violation' => admin_sub_active('set_violation.php'),
			's_iptype' => admin_sub_active('set.php', 'iptype'),
			's_sponsor' => admin_sub_active('set_sponsor.php'),
			's_account' => admin_sub_active('set.php', 'account'),
			's_update' => admin_sub_active('update.php'),
			//内容检测没开的话，检测记录整项不显示（设置页仍在「安全与合规」组里，用来开它）
			'green_log_on' => !empty($conf['green_check']),
			//赞助名单整个关掉时，后台这一项也不显示（开关在「网站信息设置」里）
			'sponsor_on' => !isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1,
		];
	},
];
