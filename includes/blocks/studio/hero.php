<?php
/*
 * 工作台家族文件列表页顶部的主视觉横幅：左边标题文案，右边一张纯 SVG 画的文件夹插画
 * （不引外部图片，跟着外观配色走）。
 *
 * 各套外观的差别全在配置里（见各自 themes/<id>/layouts/index.json）。文案类配置可以写 HTML，
 * 里面的 {site} 换成网站标题（已转义），{list_title} / {list_sub} 换成「文件列表 / 我的文件」和对应说明。
 * 卖点（feats）每行一条：图标|颜色|标题|副标题；勾选卖点（checks）每行一条。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '主视觉横幅',
	'group' => '工作台',
	'scope' => 'front',
	'schema' => [
		['key'=>'kicker', 'type'=>'html', 'label'=>'小标题', 'default'=>''],
		['key'=>'title', 'type'=>'html', 'label'=>'大标题', 'default'=>'{list_title}'],
		['key'=>'sub', 'type'=>'html', 'label'=>'说明', 'default'=>'{list_sub}'],
		['key'=>'tags', 'type'=>'html', 'label'=>'标签行', 'default'=>''],
		['key'=>'slogan', 'type'=>'html', 'label'=>'手写标语', 'default'=>'让文件分享<br>更简单高效！'],
		['key'=>'checks', 'type'=>'lines', 'label'=>'勾选卖点', 'default'=>'', 'help'=>'每行一条'],
		['key'=>'feats', 'type'=>'lines', 'label'=>'图标卖点', 'default'=>'', 'help'=>'每行一条：图标|颜色|标题|副标题，如 fa-bolt|blue|极速上传|全球加速节点'],
		['key'=>'cta', 'type'=>'switch', 'label'=>'两个大按钮', 'default'=>0],
		['key'=>'cta_text', 'type'=>'text', 'label'=>'上传按钮文字', 'default'=>'上传文件'],
		['key'=>'badge', 'type'=>'switch', 'label'=>'左侧文件夹图标', 'default'=>1],
		['key'=>'search', 'type'=>'switch', 'label'=>'横幅里的大搜索框', 'default'=>0, 'help'=>'站点关掉文件搜索时不出现'],
	],
	'data' => function($cfg, &$ctx){
		global $conf, $islogin2;
		$mine = !empty($ctx['data']['is_mine']);
		$fill = function($s) use ($conf, $mine){
			return strtr($s, [
				'{site}' => htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8'),
				'{list_title}' => $mine ? '我的文件' : '文件列表',
				'{list_sub}' => $mine ? '这里是你上传过的全部文件，可随时下载或复制外链。' : '管理、预览并分享你上传的所有内容。',
			]);
		};
		$checks = [];
		foreach(theme_config_lines($cfg['checks']) as $c)$checks[] = ['text_html'=>$fill($c[0])];
		$feats = [];
		foreach(theme_config_lines($cfg['feats'], 4) as $f){
			$feats[] = ['icon'=>$f[0], 'color'=>$f[1], 'title_html'=>$fill($f[2]), 'sub_html'=>$fill($f[3])];
		}
		return [
			'kicker_html' => $fill($cfg['kicker']),
			'title_html' => $fill($cfg['title']),
			'sub_html' => $fill($cfg['sub']),
			'tags_html' => $fill($cfg['tags']),
			'slogan_html' => $fill($cfg['slogan']),
			'checks' => $checks,
			'has_checks' => !empty($checks),
			'feats' => $feats,
			'has_feats' => !empty($feats),
			//两个按钮都指向站里真有的页面
			'mine_url' => !empty($islogin2) ? './user.php?tab=files' : './?m=mine',
			'search' => $cfg['search'] && !empty($conf['filesearch']),
			'kw' => isset($_GET['kw']) && is_string($_GET['kw']) ? $_GET['kw'] : '',
		];
	},
];
