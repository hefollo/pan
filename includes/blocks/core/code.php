<?php
/*
 * 代码（DEC-20261026-001 补充）：站长直接写的 HTML / CSS / JS，原样输出（和「自定义 HTML」一样，只有管理员能改）。
 * CSS 输出成一段 <style>，JS 登记到页面末尾（jQuery、Bootstrap 之后）执行，所以脚本里能直接用 $。
 * 想要一整页都自己写，就新建页面时选「空白代码页」，再在页面设置里关掉「显示网站外壳」。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '代码',
	'group' => '布局',
	'scope' => 'front',
	'description' => '直接写 HTML、CSS、JS',
	'schema' => [
		['key'=>'html', 'type'=>'code', 'lang'=>'html', 'label'=>'HTML', 'default'=>''],
		['key'=>'css', 'type'=>'code', 'lang'=>'css', 'label'=>'CSS', 'default'=>''],
		['key'=>'js', 'type'=>'code', 'lang'=>'js', 'label'=>'JS', 'default'=>'', 'help'=>'在页面最后执行，可以用 jQuery（$）'],
	],
	'data' => function($cfg, &$ctx){
		if($cfg['html'] === '' && $cfg['css'] === '' && $cfg['js'] === '')return null;
		//写了 </style>、</script> 会把外面那层标签提前关掉，转成 <\/…>（在 CSS / JS 里含义不变）
		$css = $cfg['css'] === '' ? '' : '<style>'."\n".preg_replace('#</(style)#i', '<\\/$1', $cfg['css'])."\n".'</style>'."\n";
		if($cfg['js'] !== ''){
			static $n = 0;
			$n++;
			theme_defer('core_code_js_'.$n, '<script>'."\n".preg_replace('#</(script)#i', '<\\/$1', $cfg['js'])."\n".'</script>'."\n", 70);
		}
		return ['content_html' => $css.$cfg['html']];
	},
];
