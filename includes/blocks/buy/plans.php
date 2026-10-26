<?php
/*
 * 购买页：按分类分组的套餐卡片。登录后按这个用户现在的等级算能不能买、实付多少
 * （续费、折抵、补差价都在 plan_quote 里），游客看到的是原价和「登录后购买」。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '套餐列表',
	'group' => '购买页',
	'scope' => 'front',
	'pages' => ['buy'],
	'data' => function($cfg, &$ctx){
		global $islogin2, $userrow;
		$groups = plan_group_list($ctx['data']['plans']);
		$out = [];
		foreach($groups as $group_name => $group_plans){
			$plans = [];
			foreach($group_plans as $plan){
				$kind = plan_kind($plan);
				$quote = $islogin2 ? plan_quote($userrow, $plan) : null;
				$price_show = ($quote && $quote['ok']) ? $quote['price'] : floatval($plan['price']);
				if($kind === 'level'){
					$features = level_features(level_get($plan['level_id']));
				}elseif($kind === 'bonus'){
					$features = ['每天多传 '.max(0, intval($plan['upload_limit'])).' 个，加在当前等级的数量上'];
				}elseif($kind === 'traffic'){
					$features = ['分享流量 +'.traffic_mb_text($plan['traffic']), '本月等级的分享流量用完后才扣'];
				}elseif($kind === 'utraffic'){
					$features = ['上传流量 +'.traffic_mb_text($plan['upload_traffic']), '本月等级的上传流量用完后才扣'];
				}else{
					$features = ['在线编辑文本、代码文件'];
				}
				//两种流量包都没有有效期，一律永久
				$features[] = ($kind === 'traffic' || $kind === 'utraffic') ? '永久有效，用完为止' : plan_days_text($plan['days']);
				if(!empty($plan['remark']))$features[] = $plan['remark'];
				$warn = '';
				if($quote && !$quote['ok'])$warn = $quote['msg'];
				elseif($quote && $quote['note'] !== '')$warn = $quote['note'];
				$plans[] = [
					'id' => intval($plan['id']),
					'name' => $plan['name'],
					'price' => number_format($price_show, 2, '.', ''),
					'origin' => ($quote && $quote['ok'] && $quote['price'] < $quote['origin']) ? number_format($quote['origin'], 2, '.', '') : '',
					'features' => $features,
					'warn' => $warn,
					'can_buy' => $quote && $quote['ok'],
					'btn' => ($quote && $kind === 'level' && strpos($quote['note'], '续费') === 0) ? '续费' : '立即购买',
				];
			}
			$out[] = ['name'=>(string)$group_name, 'count'=>count($group_plans), 'plans'=>$plans];
		}
		return [
			'groups' => $out,
			'only_one' => count($groups) === 1 && !$ctx['data']['upgrades'],
			'logged_in' => !empty($islogin2),
		];
	},
];
