<?php
/*
 * 个人中心的订单记录（每页 20 条）。当面付没有回调，打开页面时补查漏单，和 buy.php 一个路子，60 秒内只查一次。
 */
if(!defined('IN_CRONLITE'))exit();
return [
	'name' => '订单记录',
	'group' => '个人中心',
	'scope' => 'front',
	'pages' => ['user'],
	'data' => function($cfg, &$ctx){
		global $DB, $uid, $userrow;
		$uid = intval($uid);
		if(!isset($_SESSION['buy_rescue_time']) || $_SESSION['buy_rescue_time'] + 60 < time()){
			$_SESSION['buy_rescue_time'] = time();
			if(rescue_pending_orders($uid, 1) > 0){
				$userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid=:uid LIMIT 1", [':uid'=>$uid]);
			}
		}
		$num = intval($DB->getColumn("SELECT count(*) FROM pre_order WHERE uid=".$uid));
		$pagesize = 20;
		$pages = max(1, ceil($num / $pagesize));
		$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
		if($page > $pages)$page = $pages;
		$offset = $pagesize * ($page - 1);
		$status = [0=>['待支付','warn'], 1=>['已支付','ok'], 2=>['已关闭','muted']];
		$orders = [];
		$rs = $DB->query("SELECT * FROM pre_order WHERE uid=".$uid." ORDER BY id DESC LIMIT {$offset},{$pagesize}");
		while($rs && $o = $rs->fetch()){
			$st = isset($status[intval($o['status'])]) ? $status[intval($o['status'])] : ['未知','muted'];
			$orders[] = [
				'trade_no' => $o['trade_no'],
				'plan_name' => $o['plan_name'],
				'price' => $o['price'],
				'pay_name' => $o['pay_type'] === 'epay' ? '易支付' : '支付宝',
				'status' => $st[0],
				'status_cls' => $st[1],
				'addtime' => $o['addtime'],
				'paytime' => $o['paytime'] ? $o['paytime'] : '—',
			];
		}
		$pager = [];
		for($i = 1; $i <= $pages; $i++)$pager[] = ['n'=>$i, 'current'=>$i == $page];
		return ['orders'=>$orders, 'num'=>$num, 'page'=>$page, 'pages'=>$pages, 'multi'=>$pages > 1, 'pager'=>$pager];
	},
];
