<?php
/*
 * 渐变仪表盘风：顶部那张渐变额度卡。
 * 左边是已用存储量，右边的圆环走「今日上传 / 每日上限」；没有上限的账号圆环画满，中间写「不限」。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '额度卡',
	'group' => '仪表盘',
	'scope' => 'front',
	'schema' => [
		['key'=>'label', 'type'=>'text', 'label'=>'左上角文字', 'default'=>'已用存储'],
		['key'=>'sub', 'type'=>'text', 'label'=>'数字下面一行', 'default'=>'共 {total} 个文件 · 今日新增 {today} 个', 'help'=>'{total} 文件数，{today} 今日新增；清空不显示'],
		['key'=>'ring_label', 'type'=>'text', 'label'=>'圆环下的文字', 'default'=>'今日额度'],
	],
	'data' => function($cfg, &$ctx){
		global $DB, $islogin2, $userrow;
		$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
		$size_limit = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		$today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
		$percent = $limit > 0 ? min(100, round($today / $limit * 100)) : 100;
		$where = layout_ctx_where($ctx);
		$used_bytes = theme_memo('size|'.$where, function() use ($DB, $where){ return layout_storage_used($DB, $where); });
		$used = size_format($used_bytes ? $used_bytes : 0);
		$parts = explode(' ', $used);
		$counts = layout_ctx_counts($ctx);
		$pills = [];
		$pills[] = ['icon'=>'fa-bolt', 'text'=>$limit > 0 ? ('今日额度 '.$today.' / '.$limit) : ('今日已传 '.$today.' 个')];
		$pills[] = ['icon'=>'fa-file-o', 'text'=>$size_limit > 0 ? ('单文件 '.$size_limit.' MB') : '单文件不限大小'];
		if(!empty($islogin2)){
			$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
			if(empty($expire)){
				$pills[] = ['icon'=>'fa-shield', 'text'=>'权限永久有效'];
			}elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){
				$pills[] = ['icon'=>'fa-exclamation-circle', 'text'=>'权限已过期'];
			}else{
				$left = max(1, ceil((strtotime($expire) - time()) / 86400));
				$pills[] = ['icon'=>'fa-clock-o', 'text'=>'权限剩 '.$left.' 天'];
			}
		}elseif(function_exists('is_buy_open') && is_buy_open()){
			$pills[] = ['icon'=>'fa-user-circle', 'text'=>'登录后额度独立计算'];
		}
		return [
			'used_num' => isset($parts[0]) ? $parts[0] : '0',
			'used_unit' => isset($parts[1]) ? $parts[1] : 'B',
			'total' => number_format($counts['']),
			'sub_html' => theme_text_html($cfg['sub'], ['total'=>number_format($counts['']), 'today'=>number_format(layout_ctx_today_total($ctx))]),
			'pills' => $pills,
			//圆环半径 54，周长 2πr ≈ 339.3，按百分比截出实线段
			'dash' => round(339.3 * $percent / 100, 1),
			'ring_text' => $limit > 0 ? $percent.'%' : '不限',
		];
	},
];
