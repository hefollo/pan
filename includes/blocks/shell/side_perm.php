<?php
/*
 * 侧栏底部的「我的权限」卡：当前额度、流量、到期时间、会员等级，方便随时看还剩多久。只给登录用户显示。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '我的权限',
	'group' => '侧栏',
	'scope' => 'front',
	'pages' => ['_shell'],
	'data' => function($cfg, &$ctx){
		global $DB, $islogin2, $userrow;
		if(empty($islogin2))return null;
		$limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
		$size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
		$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
		$plan = function_exists('layout_user_plan') ? layout_user_plan($DB) : null;
		if(empty($expire)){
			$state = '永久有效'; $state_cls = 'ok';
		}elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){
			$state = '已过期'; $state_cls = 'expired';
		}else{
			$left = max(1, ceil((strtotime($expire) - time()) / 86400));
			$state = '剩 '.$left.' 天'; $state_cls = $left <= 7 ? 'warn' : 'ok';
		}
		$data = [
			'state' => $state,
			'state_cls' => $state_cls,
			'limit_text' => $limit > 0 ? $limit.' 个' : '不限制',
			'size_text' => $size > 0 ? $size.' MB' : '不限制',
		];
		//每月分享流量：自己的文件本月被取走多少（DEC-20261022-001）
		$traffic = function_exists('user_traffic_info') ? user_traffic_info($userrow) : null;
		if($traffic){
			$data['traffic'] = [
				'text' => $traffic['unlimited'] ? '不限制' : size_format($traffic['used']).' / '.size_format($traffic['quota']),
				'pack' => (!$traffic['unlimited'] && $traffic['pack'] > 0) ? size_format($traffic['pack']) : '',
			];
		}
		//每月上传流量：本月本站收了自己多少上传数据（DEC-20261025-001）
		$upload = function_exists('upload_traffic_info') ? upload_traffic_info() : null;
		if($upload){
			$data['upload'] = [
				'text' => $upload['unlimited'] ? '不限制' : size_format($upload['used']).' / '.size_format($upload['quota']),
				'pack' => (!$upload['unlimited'] && $upload['pack'] > 0) ? size_format($upload['pack']) : '',
			];
		}
		if(function_exists('user_bonus_limit') && user_bonus_limit($userrow) > 0 && $limit > 0){
			$data['bonus'] = user_bonus_limit($userrow);
		}
		if(!empty($expire))$data['expire_date'] = date('Y-m-d', strtotime($expire));
		if($plan && $plan['bought'])$data['plan_name'] = $plan['plan_name'];
		if(function_exists('is_buy_open') && is_buy_open()){
			$data['buy_text'] = ($plan && $plan['bought']) ? '续费 / 升级会员' : '购买会员';
		}
		return $data;
	},
];
