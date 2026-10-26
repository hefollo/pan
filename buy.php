<?php
/*
 * 购买权限页
 *
 * 支付方式两种：支付宝当面付（页内扫码）和易支付（跳转收银台）。
 * 两种都靠前端每 3 秒调一次 act=query，由服务端去渠道查单，查到已支付就发放权限，
 * 不需要渠道反向访问本站，有 CDN 或防火墙的站点也能用；易支付另外还支持异步通知。
 *
 * 当面付没有回调，用户付完直接关页面就没人轮询了，所以打开购买页和重新下单时
 * 都会调 rescue_pending_orders() 把没入账的旧订单补查一遍。
 */
include("./includes/common.php");

//下单和查单都要登录：订单要绑到 uid 上，权限也发到 uid 上
$act = isset($_GET['act']) ? $_GET['act'] : '';

/*
 * 易支付的异步通知和同步跳转。这两个是易支付站点带着签名回调过来的，
 * 不做来源校验（本来就来自外部），改成验签 + 核对金额，验不过一律不发货。
 */
if($act === 'notify' || $act === 'return'){
	//大部分易支付站点用 GET 发通知，个别用 POST，两种都收下
	$params = array_merge($_GET, $_POST);
	unset($params['act']);
	$epay = epay_client();
	$ok = $epay->isReady() && $epay->verify($params);
	$order = false;
	if($ok && !empty($params['out_trade_no'])){
		$order = $DB->getRow("SELECT * FROM pre_order WHERE trade_no=:no LIMIT 1", [':no'=>$params['out_trade_no']]);
	}
	//金额必须对得上，防止有人自己拼一个小额订单来开通权限
	if($order && isset($order['pay_type']) && $order['pay_type'] === 'epay'
		&& isset($params['trade_status']) && $params['trade_status'] === 'TRADE_SUCCESS'
		&& round(floatval($params['money']), 2) >= round(floatval($order['price']), 2)){
		if(!finish_order($order, isset($params['trade_no']) ? $params['trade_no'] : '')){
			if($act === 'notify')exit('fail');
			@header('Location: ./buy.php?paid=0');
			exit;
		}
		if($act === 'notify')exit('success');
		@header('Location: ./buy.php?paid=1');
		exit;
	}
	if($act === 'notify')exit('fail');
	@header('Location: ./buy.php?paid=0');
	exit;
}

if($act === 'create' || $act === 'query'){
	@header('Content-Type: application/json; charset=UTF-8');
	if(!checkRefererHost())exit('{"code":-1,"msg":"来源校验失败"}');
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!is_buy_open())exit('{"code":-1,"msg":"站点未开启购买功能"}');

	if($act === 'create'){
		//支付方式：前端传哪个就用哪个，但必须是后台开着并且配置完整的
		$methods = pay_methods();
		$pay_type = isset($_POST['pay_type']) ? $_POST['pay_type'] : '';
		if(!isset($methods[$pay_type])){
			$keys = array_keys($methods);
			$pay_type = $keys ? $keys[0] : '';
		}
		if($pay_type === '')exit('{"code":-1,"msg":"站点还没有配置可用的支付方式"}');

		/*
		 * 下单前先把这个用户没入账的旧订单查一遍：
		 * 一是把漏单补回来，二是避免下面复用/关闭旧订单时，把一笔其实已经付过的订单给关了
		 * （已付款的订单再去 precreate，支付宝会直接返回错误）。
		 */
		if(rescue_pending_orders($uid) > 0){
			exit('{"code":-1,"msg":"检测到你有一笔已完成的支付，权限已经发放，请刷新页面查看"}');
		}
		$userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid=:uid LIMIT 1", [':uid'=>intval($uid)]);

		/*
		 * 买什么、付多少一律由服务端按数据库里的套餐和这个用户现在的等级算，不接受前端传金额。
		 * 两种入口：plan_id 买一个套餐；upgrade 是补差价升级到某个更高的等级（没有对应的套餐，到期时间不变）。
		 * 能不能买、实付多少都在 plan_quote() / level_upgrade_quote() 里，买了没用的在那里就被拦下。
		 */
		$upgrade_id = isset($_POST['upgrade']) ? intval($_POST['upgrade']) : 0;
		if($upgrade_id > 0){
			$quote = level_upgrade_quote($userrow, level_get($upgrade_id));
			if(empty($quote['ok']))exit(json_encode(['code'=>-1, 'msg'=>$quote['msg']], JSON_UNESCAPED_UNICODE));
			$kind = 'upgrade';
			$plan = ['id'=>0, 'name'=>'升级到'.$quote['level']['name'], 'level_id'=>intval($quote['level']['id']), 'days'=>intval($quote['days']),
				'upload_limit'=>-1, 'limit_mode'=>'set', 'upload_size'=>-1, 'down_speed'=>-1, 'online_edit'=>0];
		}else{
			$plan = plan_get(isset($_POST['plan_id']) ? $_POST['plan_id'] : 0);
			if(!$plan || intval($plan['enable']) !== 1)exit('{"code":-1,"msg":"套餐不存在或已下架"}');
			$quote = plan_quote($userrow, $plan);
			if(empty($quote['ok']))exit(json_encode(['code'=>-1, 'msg'=>$quote['msg']], JSON_UNESCAPED_UNICODE));
			$kind = $quote['kind'];
		}
		$price = round(floatval($quote['price']), 2);
		if($price <= 0)exit('{"code":-1,"msg":"套餐价格设置有误"}');
		$limit_mode = isset($plan['limit_mode']) && $plan['limit_mode'] === 'add' ? 'add' : 'set';

		/*
		 * 反复点“立即购买”不应该一直产生新订单：
		 * 同一个人、同一个套餐、同一种支付方式，两小时内还没支付的那笔直接拿来接着用，
		 * 只有内容对不上（类型、等级、金额、天数——补差价的金额会随剩余时间变）才重新下单。
		 */
		$exist = $DB->getRow("SELECT * FROM pre_order WHERE uid=:uid AND plan_id=:pid AND pay_type=:pt AND status=0
			AND addtime > DATE_SUB(NOW(), INTERVAL 2 HOUR) ORDER BY id DESC LIMIT 1",
			[':uid'=>intval($uid), ':pid'=>intval($plan['id']), ':pt'=>$pay_type]);
		if($exist
			&& number_format(floatval($exist['price']), 2, '.', '') === number_format($price, 2, '.', '')
			&& (isset($exist['kind']) ? $exist['kind'] : '') === $kind
			&& intval(isset($exist['level_id']) ? $exist['level_id'] : 0) === intval($plan['level_id'])
			&& intval($exist['upload_limit']) === intval($plan['upload_limit'])
			&& plan_has_online_edit($exist) === plan_has_online_edit($plan)
			&& intval(isset($exist['traffic']) ? $exist['traffic'] : 0) === intval(isset($plan['traffic']) ? $plan['traffic'] : 0)
			&& intval(isset($exist['upload_traffic']) ? $exist['upload_traffic'] : 0) === intval(isset($plan['upload_traffic']) ? $plan['upload_traffic'] : 0)
			&& intval($exist['days']) === intval($plan['days'])
			&& (isset($exist['limit_mode']) ? $exist['limit_mode'] : 'set') === $limit_mode){
			$trade_no = $exist['trade_no'];
			$order_id = intval($exist['id']);
			//这笔之外的其它待支付订单关掉，避免同时挂着好几个二维码
			$DB->exec("UPDATE pre_order SET status=2 WHERE uid=:uid AND status=0 AND id<>:id",
				[':uid'=>intval($uid), ':id'=>$order_id]);
		}else{
			//真正要新建订单了，先限一下频率：一小时内最多 10 笔，够正常换套餐用，刷不出量
			$recent = intval($DB->getColumn("SELECT count(*) FROM pre_order WHERE uid='".intval($uid)."'
				AND addtime > DATE_SUB(NOW(), INTERVAL 1 HOUR)"));
			if($recent >= 10)exit('{"code":-1,"msg":"下单太频繁了，请稍后再试"}');

			//同一用户未支付的旧订单先关掉，避免二维码越堆越多
			$DB->exec("UPDATE pre_order SET status=2 WHERE uid=:uid AND status=0", [':uid'=>intval($uid)]);

			$trade_no = build_trade_no($uid);
			//数值一律传字符串：DB->insert 把 == '' 的值写成 NULL，整数 0 在老版本 PHP 上也会中
			$order_id = $DB->insert('order', [
				'trade_no' => $trade_no,
				'uid' => strval(intval($uid)),
				'plan_id' => strval(intval($plan['id'])),
				'plan_name' => $plan['name'],
				'price' => strval($price),
				'pay_type' => $pay_type,
				'kind' => $kind,
				'level_id' => strval(intval($plan['level_id'])),
				'upload_limit' => strval(intval($plan['upload_limit'])),
				'limit_mode' => $limit_mode,
				'upload_size' => '-1',
				'down_speed' => '-1',
				'online_edit' => plan_has_online_edit($plan) ? '1' : '0',
				'traffic' => strval(max(0, intval(isset($plan['traffic']) ? $plan['traffic'] : 0))),
				'upload_traffic' => strval(max(0, intval(isset($plan['upload_traffic']) ? $plan['upload_traffic'] : 0))),
				'days' => strval(intval($plan['days'])),
				'status' => '0',
				'ip' => $clientip,
				'addtime' => 'NOW()',
			]);
			if(!$order_id)exit(json_encode(['code'=>-1, 'msg'=>'创建订单失败']));

			//顺手清理三天前没付款的记录，已支付的订单一条都不动
			if(mt_rand(1, 10) === 1){
				$DB->exec("DELETE FROM pre_order WHERE status<>1 AND addtime < DATE_SUB(NOW(), INTERVAL 3 DAY)");
			}
		}

		$subject = pay_subject();
		if($pay_type === 'epay'){
			//易支付走页面跳转：把带签名的收银台地址给前端，前端开新窗口过去付款
			$base = rtrim($siteurl, '/').'/buy.php';
			//支付通道由用户在弹窗里选，但必须是后台勾选开放的那几个
			$channels = epay_channels();
			$channel = isset($_POST['channel']) ? trim($_POST['channel']) : '';
			if(!isset($channels[$channel])){
				$ckeys = array_keys($channels);
				$channel = $ckeys[0];
			}
			$pay_url = epay_client()->payUrl($trade_no, $price, $subject,
				$base.'?act=notify', $base.'?act=return', $channel, pay_subject(),
				isset($conf['epay_charset']) ? $conf['epay_charset'] : 'UTF-8');
			if(!$pay_url){
				$DB->exec("UPDATE pre_order SET status=2 WHERE id=:id", [':id'=>$order_id]);
				exit('{"code":-1,"msg":"易支付参数没有配置完整"}');
			}
			exit(json_encode([
				'code' => 0,
				'pay_type' => 'epay',
				'channel_name' => $channels[$channel],
				'trade_no' => $trade_no,
				'pay_url' => $pay_url,
				'price' => number_format($price, 2, '.', ''),
				'plan_name' => $plan['name'],
			]));
		}

		$alipay = alipay_client();
		$res = $alipay->precreate($trade_no, $price, $subject);
		if($res['code'] != 0){
			$DB->exec("UPDATE pre_order SET status=2 WHERE id=:id", [':id'=>$order_id]);
			exit(json_encode(['code'=>-1, 'msg'=>$res['msg']]));
		}
		exit(json_encode([
			'code' => 0,
			'pay_type' => 'alipay',
			'trade_no' => $trade_no,
			'qr_code' => $res['qr_code'],
			'price' => number_format($price, 2, '.', ''),
			'plan_name' => $plan['name'],
		]));
	}

	//act=query：查订单状态
	$trade_no = isset($_POST['trade_no']) ? trim($_POST['trade_no']) : '';
	if(!preg_match('/^[0-9]{1,32}$/', $trade_no))exit('{"code":-1,"msg":"订单号不正确"}');
	$order = $DB->getRow("SELECT * FROM pre_order WHERE trade_no=:no LIMIT 1", [':no'=>$trade_no]);
	if(!$order || intval($order['uid']) !== intval($uid))exit('{"code":-1,"msg":"订单不存在"}');
	if(intval($order['status']) === 1)exit('{"code":0,"paid":1}');

	//限流：前端 3 秒一次，这里卡 2 秒，防止有人拿它当接口刷支付宝
	if(isset($_SESSION['buy_query_time']) && $_SESSION['buy_query_time'] + 2 > time()){
		exit('{"code":0,"paid":0}');
	}
	$_SESSION['buy_query_time'] = time();

	$res = check_order_paid($order);
	if($res['code'] != 0)exit(json_encode(['code'=>-1, 'msg'=>$res['msg']]));
	exit(json_encode(['code'=>0, 'paid'=>intval($res['paid'])]));
}

if(!is_buy_open()){
	@header('Content-Type: text/html; charset=UTF-8');
	sysmsg('本站暂未开放购买功能。');
}

//打开购买页时顺手把没入账的旧订单查一遍（当面付没有回调，靠这里补漏单），60 秒内只查一次
if($islogin2 && (!isset($_SESSION['buy_rescue_time']) || $_SESSION['buy_rescue_time'] + 60 < time())){
	$_SESSION['buy_rescue_time'] = time();
	//这里只查最近一笔：万一支付渠道超时，页面最多被拖一次，不会成倍放大
	if(rescue_pending_orders($uid, 1) > 0){
		$userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid=:uid LIMIT 1", [':uid'=>intval($uid)]);
	}
}

$title = '购买权限 - ' . $conf['title'];
//实际在卖的套餐：旧版套餐已停售；普通用户本来就能用在线编辑时，在线编辑包不列出来
$plans = plan_list_for_sale();
//限时会员可以补差价升级到的更高等级（到期时间不变）
$upgrades = $islogin2 ? level_upgrade_list($userrow) : [];
include SYSTEM_ROOT.'header.php';
/*
 * 页面内容是布局 buy（DEC-20261025-003）：系统默认在 includes/layouts/buy.json，卡片在 includes/blocks/buy/。
 * 支付方式、通道由卡片自己读；套餐和升级列表这里算好交过去。
 * 二维码库和 buy.js 是整页的脚本（套餐按钮、支付弹窗都靠它），由这里登记。
 */
theme_defer('buy.scripts', theme_tpl_render('blocks/buy/scripts', ['buy_ver'=>asset_ver('assets/js/buy.js')], $site_theme), 10);
echo theme_render_layout('buy', [
	'plans' => $plans,
	'upgrades' => $upgrades,
], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
echo theme_deferred_html();
?></body>
</html>
