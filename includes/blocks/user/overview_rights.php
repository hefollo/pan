<?php
/* 个人中心概览：权限与额度（会员等级、每日上传、单文件、分享 / 上传流量、有效期、在线编辑）+ 购买入口 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '权限与额度',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $DB, $userrow;
		$limit = get_effective_upload_count_limit();
		$expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
		$plan = layout_user_plan($DB);
		$bonus = (user_bonus_limit($userrow) > 0 && $limit > 0) ? user_bonus_limit($userrow) : 0;
		$data = [
			'level_name' => user_level($userrow)['name'],
			'limit_text_html' => limit_number_text($limit, '个/天'),
			'bonus' => $bonus,
			'bonus_expire_text' => empty($userrow['bonus_expire']) ? '永久' : $userrow['bonus_expire'].' 到期',
			'size_text_html' => size_mb_text(get_effective_upload_size_limit()),
			'traffic_text' => user_traffic_text($userrow),
			'upload_text' => upload_traffic_text(),
			'expire_text' => empty($expire) ? '永久有效' : $expire.(is_user_permission_active() ? ' 到期' : ' 已过期'),
			'edit_text' => current_online_edit_text(),
		];
		if(function_exists('is_buy_open') && is_buy_open()){
			$data['buy_text'] = ($plan && $plan['bought']) ? '续费 / 升级会员' : '购买会员';
		}
		return $data;
	},
];
