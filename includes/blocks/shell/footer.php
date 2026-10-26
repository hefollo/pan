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
	'data' => function($cfg, &$ctx){
		$groups = footer_code_groups();
		$shown = [];
		foreach($groups['shown'] as $code)$shown[] = ['code_html'=>$code];
		return [
			'year' => date('Y'),
			'shown' => $shown,
			'runtime_open' => site_runtime_open(),
			'runtime_start_ms' => site_runtime_start() * 1000,
			'has_silent' => !empty($groups['silent']),
			'silent_html' => implode("\n", $groups['silent']),
		];
	},
];
