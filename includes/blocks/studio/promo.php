<?php
/*
 * 列表下面那条推广横幅（紫韵会员风 / 蓝天白云风用）。
 * 卖点文案是固定的，「立即升级」按钮只有真开了购买功能才出现；
 * 「只在能购买时显示」打开时，买不了就整条不出现（蓝天白云风这条本身就是升级广告）。
 * 卖点（chips）每行一条：图标|文字。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '推广横幅',
	'group' => '工作台',
	'scope' => 'front',
	'schema' => [
		['key'=>'title', 'type'=>'html', 'label'=>'标题', 'default'=>'安全、快速、无限可能'],
		['key'=>'sub', 'type'=>'html', 'label'=>'说明', 'default'=>'让数据创造更多价值'],
		['key'=>'chips', 'type'=>'lines', 'label'=>'卖点', 'default'=>"fa-lock|多重安全加密\nfa-rocket|高速稳定传输\nfa-globe|全球内容分发\nfa-magic|简单高效易用"],
		['key'=>'slogan', 'type'=>'html', 'label'=>'手写标语', 'default'=>'存储美好<br>分享精彩'],
		['key'=>'need_buy', 'type'=>'switch', 'label'=>'只在能购买时显示', 'default'=>0],
	],
	'data' => function($cfg, &$ctx){
		$buy = function_exists('is_buy_open') && is_buy_open();
		if($cfg['need_buy'] && !$buy)return null;
		$chips = [];
		foreach(theme_config_lines($cfg['chips'], 2) as $c)$chips[] = ['icon'=>$c[0], 'text_html'=>$c[1]];
		return [
			'title_html' => $cfg['title'],
			'sub_html' => $cfg['sub'],
			'chips' => $chips,
			'slogan_html' => $cfg['slogan'],
			'buy' => $buy,
		];
	},
];
