<?php
/*
 * 工作台家族右栏：快捷操作，只放这个站真的开着的入口；关掉的功能不占格子，
 * 一个都没开时至少留个上传入口，别出现空面板；最多摆四个，多了换行反而乱。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '快捷操作面板',
	'group' => '工作台',
	'scope' => 'front',
	'data' => function($cfg, &$ctx){
		global $conf, $islogin2;
		$acts = [];
		$acts[] = ['href'=>'./upload.php', 'icon'=>'fa-cloud-upload', 'label'=>'上传文件', 'cls'=>' primary'];
		$acts[] = ['href'=>!empty($islogin2) ? './user.php?tab=files' : './?m=mine', 'icon'=>'fa-folder-open', 'label'=>'我的文件', 'cls'=>''];
		if(function_exists('is_buy_open') && is_buy_open())$acts[] = ['href'=>'./buy.php', 'icon'=>'fa-shopping-cart', 'label'=>'购买权限', 'cls'=>''];
		if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1)$acts[] = ['href'=>'./sponsor.php', 'icon'=>'fa-money', 'label'=>'赞助名单', 'cls'=>''];
		if(!isset($conf['violation_open']) || $conf['violation_open'] == 1)$acts[] = ['href'=>'./violation.php', 'icon'=>'fa-gavel', 'label'=>'违规公示', 'cls'=>''];
		if(count($acts) < 3)$acts[] = ['href'=>'./upload.php', 'icon'=>'fa-link', 'label'=>'生成外链', 'cls'=>''];
		return ['acts' => array_slice($acts, 0, 4)];
	},
];
