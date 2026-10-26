<?php
/*
 * 打赏码面板：后台「文件页设置」里的打赏标题和图片，上传页、文件页共用同一套配置，
 * 后台关掉或改图这里同步生效。标题在旧代码里是原样输出的（默认值是一串数字实体）。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '打赏码',
	'group' => '通用',
	'scope' => 'front',
	'schema' => [
		['key'=>'heading_bg', 'type'=>'text', 'label'=>'标题栏底色', 'default'=>'', 'help'=>'留空用外观默认颜色，如 #009688'],
		['key'=>'qr_comment', 'type'=>'switch', 'label'=>'保留旧二维码注释', 'default'=>0, 'help'=>'文件页原来带一行注释掉的二维码图片，只为和旧版输出一致'],
	],
	'data' => function($cfg, &$ctx){
		global $conf;
		$enable = isset($conf['file_reward_enable']) ? ((int)$conf['file_reward_enable'] === 1) : true;
		$title = isset($conf['file_reward_title']) && $conf['file_reward_title'] !== '' ? $conf['file_reward_title'] : '&#25195;&#30721;&#39046;&#32418;&#21253;';
		$image = isset($conf['file_reward_image']) && $conf['file_reward_image'] !== '' ? $conf['file_reward_image'] : 'includes/sponsor/images/zhifubaohb.jpg';
		if(!$enable || empty($image))return null;
		return [
			'title_html' => $title,
			'alt' => strip_tags($title),
			'image' => $image,
			//底色只收颜色值，别让配置写出奇怪的样式
			'bg' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $cfg['heading_bg']) ? $cfg['heading_bg'] : '',
			'thisurl_enc_html' => isset($ctx['data']['thisurl']) ? urlencode($ctx['data']['thisurl']) : '',
		];
	},
];
