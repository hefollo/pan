<?php
/* 上传提示：访客 IP、单文件上限、每日剩余、当前权限、视频审核说明 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '上传提示',
	'group' => '上传页',
	'scope' => 'front',
	'pages' => ['upload'],
	'description' => '每一行清空就不显示；花括号里的是自动填的数字',
	'schema' => [
		['key'=>'title', 'type'=>'text', 'label'=>'标题', 'default'=>'上传提示'],
		['key'=>'ip', 'type'=>'text', 'label'=>'IP 那一行', 'default'=>'**您的IP是{ip}，请不要上传违规文件！', 'help'=>'{ip} 是访客的 IP'],
		['key'=>'size', 'type'=>'text', 'label'=>'有大小限制时', 'default'=>'**上传无格式限制，当前服务器单个文件上传最大支持{size}！', 'help'=>'{size} 是加粗的「N MB」'],
		['key'=>'nosize', 'type'=>'text', 'label'=>'不限大小时', 'default'=>'**上传无格式限制，无大小限制！'],
		['key'=>'limit', 'type'=>'text', 'label'=>'每日数量那一行', 'default'=>'**当前账号每天最多可上传 {limit} 个文件，今日剩余 {remaining} 个。', 'help'=>'不限数量时不显示'],
		['key'=>'expire', 'type'=>'text', 'label'=>'权限到期那一行', 'default'=>'**当前账号权限：{expire}。'],
		['key'=>'video', 'type'=>'text', 'label'=>'视频审核那一行', 'default'=>'**当前网站已开启视频文件审核，如果上传的是视频文件，需要等待审核通过后才能下载和播放。', 'help'=>'没开视频审核时不显示'],
	],
	'data' => function($cfg, &$ctx){
		global $conf, $clientip;
		$d = $ctx['data'];
		$vue = function($h){ return str_replace(['{{', '}}'], ['{&#8203;{', '}&#8203;}'], $h); };
		$b = function($v){ return '<b>'.htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8').'</b>'; };
		return [
			'title_html' => $vue(theme_text_html($cfg['title'])),
			'ip_html' => $vue(theme_text_html($cfg['ip'], ['ip'=>htmlspecialchars((string)$clientip, ENT_QUOTES, 'UTF-8')])),
			'size_html' => $vue($d['size'] ? theme_text_html($cfg['size'], ['size'=>'<b>'.htmlspecialchars((string)$d['size'], ENT_QUOTES, 'UTF-8').'MB</b>']) : theme_text_html($cfg['nosize'])),
			'limit_html' => $d['limit'] ? $vue(theme_text_html($cfg['limit'], ['limit'=>$b($d['limit']), 'remaining'=>$b($d['remaining'])])) : '',
			'expire_html' => $d['expire_text'] ? $vue(theme_text_html($cfg['expire'], ['expire'=>$b($d['expire_text'])])) : '',
			'video_html' => $conf['videoreview'] == 1 ? $vue(theme_text_html($cfg['video'])) : '',
		];
	},
];
