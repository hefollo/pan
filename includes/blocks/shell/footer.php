<?php
/*
 * 页脚。结构约定（改模板时别破坏）：
 *   1. <footer class="footer"> 必须直接挂在 <body> 下：各外观的页脚样式都是按 body>footer.footer 写的，
 *      所以这张卡片只能放在外壳布局的最外层，不要放进容器里。
 *   2. 页脚前面的 #siteFooterGap 是占位块，内容不满一屏时由脚本撑高，页脚就落在窗口底部。
 *   3. 版权和每条看得见的页脚代码各占一格（.site-footer-item），统计代码这类看不见的放 .site-footer-extra。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '页脚',
	'group' => '外壳',
	'scope' => 'front',
	'pages' => ['_shell'],
	'description' => '页脚代码、建站时间在后台「网站信息设置」里改，这里改版权文字和运行时间的说明',
	'schema' => [
		['key'=>'copyright', 'type'=>'text', 'label'=>'版权文字', 'default'=>'Copyright © {year} {title}', 'help'=>'{year} 是今年，{title} 是带链接的网站名；清空就不显示这一项'],
		['key'=>'runtime_label', 'type'=>'text', 'label'=>'运行时间前面的文字', 'default'=>'本站已安全运行：', 'help'=>'是否显示运行时间在「网站信息设置」里开关'],
	],
	'data' => function($cfg, &$ctx){
		$groups = footer_code_groups();
		$shown = [];
		foreach($groups['shown'] as $code)$shown[] = ['code_html'=>$code];
		//© 写成 &copy;：和原来模板里的写法一致
		$copy = str_replace('©', '&copy;', theme_text_html($cfg['copyright'], ['year'=>date('Y'), 'title'=>'<a href="/">'.$ctx['site_vars']['title_html'].'</a>']));
		return [
			'copyright_html' => $copy,
			'shown' => $shown,
			'runtime_open' => site_runtime_open(),
			'runtime_start_ms' => site_runtime_start() * 1000,
			'has_silent' => !empty($groups['silent']),
			'silent_html' => implode("\n", $groups['silent']),
		];
	},
];
