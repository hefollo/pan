<?php
/*
 * 工作台家族右栏：我的权限（每日上传、单文件、下载速度、分享 / 上传流量）。
 * 「额外的行」每行一条：图标|颜色|名称|值（深空科技风的原型多一行「同时下载数」）；
 * 「升级按钮」是紫韵会员风把升级入口直接做进权限卡里的那个，没开购买功能时不出现。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '我的权限面板',
	'group' => '工作台',
	'scope' => 'front',
	'schema' => [
		['key'=>'extra_rows', 'type'=>'lines', 'label'=>'额外的行', 'default'=>''],
		['key'=>'vip_btn', 'type'=>'switch', 'label'=>'升级按钮', 'default'=>0],
	],
	'data' => function($cfg, &$ctx){
		global $islogin2, $userrow;
		$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
		$size_limit = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		if(!empty($islogin2)){
			$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
			if(empty($expire)){$badge_cls = ' ok'; $badge = '永久有效';}
			elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){$badge_cls = ' bad'; $badge = '已过期';}
			else{$badge_cls = ' ok'; $badge = '剩 '.max(1, ceil((strtotime($expire) - time()) / 86400)).' 天';}
		}else{
			$badge_cls = ''; $badge = '未登录';
		}
		$rows = [
			['icon'=>'fa-cloud-upload', 'color'=>'blue', 'label'=>'每日上传', 'value'=>$limit > 0 ? ($limit.' 个') : '不限制'],
			['icon'=>'fa-file-o', 'color'=>'indigo', 'label'=>'单文件大小', 'value'=>$size_limit > 0 ? ($size_limit.' MB') : '不限制'],
			['icon'=>'fa-bolt', 'color'=>'amber', 'label'=>'下载速度', 'value'=>speed_text(get_effective_download_speed_kbps())],
		];
		//每月分享流量只给登录用户看：游客没有自己的用量可言（DEC-20261022-001）
		$traffic = (!empty($islogin2) && function_exists('user_traffic_info')) ? user_traffic_info($userrow) : null;
		if($traffic){
			$rows[] = ['icon'=>'fa-exchange', 'color'=>'cyan', 'label'=>'分享流量', 'value'=>$traffic['unlimited'] ? '不限制' : (size_format($traffic['used']).' / '.size_format($traffic['quota']))];
			if(!$traffic['unlimited'] && $traffic['pack'] > 0)$rows[] = ['icon'=>'fa-archive', 'color'=>'cyan', 'label'=>'分享流量包', 'value'=>size_format($traffic['pack'])];
		}
		//每月上传流量游客也有（按 IP 计），所以不分登录与否都显示（DEC-20261025-001）
		if(function_exists('upload_traffic_info')){
			$upload = upload_traffic_info();
			$rows[] = ['icon'=>'fa-upload', 'color'=>'blue', 'label'=>'上传流量', 'value'=>$upload['unlimited'] ? '不限制' : (size_format($upload['used']).' / '.size_format($upload['quota']))];
			if(!$upload['unlimited'] && $upload['pack'] > 0)$rows[] = ['icon'=>'fa-archive', 'color'=>'blue', 'label'=>'上传流量包', 'value'=>size_format($upload['pack'])];
		}
		foreach(theme_config_lines($cfg['extra_rows'], 4) as $r){
			$rows[] = ['icon'=>$r[0], 'color'=>$r[1], 'label'=>$r[2], 'value'=>$r[3]];
		}
		return [
			'badge_cls' => $badge_cls,
			'badge' => $badge,
			'rows' => $rows,
			'vip' => $cfg['vip_btn'] && function_exists('is_buy_open') && is_buy_open(),
		];
	},
];
