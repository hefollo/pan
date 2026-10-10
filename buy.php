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
$methods = pay_methods();
$method_keys = array_keys($methods);
$channels = isset($methods['epay']) ? epay_channels() : [];
//通道图标：FontAwesome 4.7 里没有支付宝，用信用卡图标代替
$channel_icons = ['alipay'=>'credit-card', 'wxpay'=>'weixin', 'qqpay'=>'qq'];
include SYSTEM_ROOT.'header.php';
?>
<div class="container">
    <div class="well bs-component buypage">
        <h2>购买权限</h2>
        <p class="buy-sub">选择需要的会员等级，支付成功后权限立即生效。</p>
<?php if(isset($_GET['paid'])){?>
        <div class="buy-notice <?php echo $_GET['paid'] == '1' ? 'is-ok' : 'is-warn'?>">
            <i class="fa fa-<?php echo $_GET['paid'] == '1' ? 'check-circle' : 'exclamation-circle'?>" aria-hidden="true"></i>
            <?php echo $_GET['paid'] == '1' ? '支付成功，权限已经发放到你的账号。' : '没有查到这笔支付。如果你已经付款，请稍等片刻后刷新本页，或联系站长处理。'?>
        </div>
<?php }?>
<?php if(!empty($conf['buy_notice'])){?>
        <div class="buy-notice"><i class="fa fa-info-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($conf['buy_notice'], ENT_QUOTES, 'UTF-8')?></div>
<?php }?>
<?php if($islogin2){
	$cur_limit = limit_number_text(get_effective_upload_count_limit(), '个/天');
	$cur_size = size_mb_text(get_effective_upload_size_limit());
	$cur_speed = speed_text(get_effective_download_speed_kbps());
	$cur_bonus = user_bonus_limit($userrow);
	$cur_edit = current_online_edit_text();
?>
        <div class="buy-current">
            <span>当前等级</span>
            <strong><?php echo htmlspecialchars(user_level_text($userrow), ENT_QUOTES, 'UTF-8')?>　每日上传 <?php echo htmlspecialchars($cur_limit)?><?php if($cur_bonus > 0 && get_effective_upload_count_limit() > 0){?>（含加量包 +<?php echo $cur_bonus?>，<?php echo empty($userrow['bonus_expire']) ? '永久' : htmlspecialchars($userrow['bonus_expire']).' 到期'?>）<?php }?>　单文件 <?php echo htmlspecialchars($cur_size)?>　下载 <?php echo htmlspecialchars($cur_speed)?>　在线编辑 <?php echo htmlspecialchars($cur_edit)?></strong>
        </div>
<?php }?>
<?php if(count($methods) > 1){?>
        <div class="buy-methods">
            <span>支付方式</span>
<?php foreach($methods as $mk => $m){?>
            <label class="buy-method<?php echo $mk === $method_keys[0] ? ' is-on' : ''?>">
                <input type="radio" name="pay_type" value="<?php echo $mk?>"<?php echo $mk === $method_keys[0] ? ' checked' : ''?>/>
                <b><?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8')?></b>
                <i><?php echo htmlspecialchars($m['desc'], ENT_QUOTES, 'UTF-8')?></i>
            </label>
<?php }?>
        </div>
<?php }elseif($method_keys){?>
        <div class="buy-methods buy-methods-single">
            <span>支付方式</span>
            <b><?php echo htmlspecialchars($methods[$method_keys[0]]['name'], ENT_QUOTES, 'UTF-8')?></b>
            <input type="hidden" name="pay_type" value="<?php echo $method_keys[0]?>"/>
        </div>
<?php }?>
<?php if($upgrades){?>
        <div class="buy-group">
            <div class="buy-group-title"><span>补差价升级</span><i>到期时间不变，只补到期前这段时间的差价</i></div>
        <div class="buy-plans">
<?php foreach($upgrades as $up){?>
            <div class="buy-plan">
                <div class="buy-plan-name">升级到<?php echo htmlspecialchars($up['level']['name'], ENT_QUOTES, 'UTF-8')?></div>
                <div class="buy-plan-price"><small>¥</small><?php echo htmlspecialchars(number_format($up['price'], 2, '.', ''))?></div>
                <ul class="buy-plan-list">
<?php foreach(level_features($up['level']) as $feature){?>
                    <li><i class="fa fa-check" aria-hidden="true"></i> <?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8')?></li>
<?php }?>
                    <li><i class="fa fa-check" aria-hidden="true"></i> 到期时间不变：<?php echo htmlspecialchars($up['expire'], ENT_QUOTES, 'UTF-8')?></li>
                </ul>
                <button type="button" class="buy-plan-btn" data-plan="u<?php echo intval($up['level']['id'])?>">补差价升级</button>
            </div>
<?php }?>
        </div>
        </div>
<?php }?>
<?php
	$groups = plan_group_list($plans);
	$only_one = count($groups) === 1 && !$upgrades;
	foreach($groups as $group_name => $group_plans){
?>
        <div class="buy-group">
<?php if(!$only_one){?>
            <div class="buy-group-title"><span><?php echo htmlspecialchars((string)$group_name, ENT_QUOTES, 'UTF-8')?></span><i><?php echo count($group_plans)?> 个套餐</i></div>
<?php }?>
        <div class="buy-plans">
<?php foreach($group_plans as $plan){
	$kind = plan_kind($plan);
	//登录后按这个用户现在的等级算：能不能买、实付多少（续费、折抵、补差价都在 plan_quote 里）
	$quote = $islogin2 ? plan_quote($userrow, $plan) : null;
	$price_show = ($quote && $quote['ok']) ? $quote['price'] : floatval($plan['price']);
	if($kind === 'level'){
		$features = level_features(level_get($plan['level_id']));
	}elseif($kind === 'bonus'){
		$features = ['每天多传 '.max(0, intval($plan['upload_limit'])).' 个，加在当前等级的数量上'];
	}else{
		$features = ['在线编辑文本、代码文件'];
	}
	$features[] = plan_days_text($plan['days']);
	if(!empty($plan['remark']))$features[] = $plan['remark'];
?>
            <div class="buy-plan">
                <div class="buy-plan-name"><?php echo htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8')?></div>
                <div class="buy-plan-price"><small>¥</small><?php echo htmlspecialchars(number_format($price_show, 2, '.', ''))?><?php if($quote && $quote['ok'] && $quote['price'] < $quote['origin']){?><del style="margin-left:8px;color:var(--muted);font-size:13px;font-weight:400">¥<?php echo htmlspecialchars(number_format($quote['origin'], 2, '.', ''))?></del><?php }?></div>
                <ul class="buy-plan-list">
<?php foreach($features as $feature){?>
                    <li><i class="fa fa-check" aria-hidden="true"></i> <?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8')?></li>
<?php }?>
                </ul>
<?php if($quote && !$quote['ok']){?>
                <div class="buy-plan-warn"><?php echo htmlspecialchars($quote['msg'], ENT_QUOTES, 'UTF-8')?></div>
<?php }elseif($quote && $quote['note'] !== ''){?>
                <div class="buy-plan-warn"><?php echo htmlspecialchars($quote['note'], ENT_QUOTES, 'UTF-8')?></div>
<?php }?>
<?php if($islogin2){ if($quote['ok']){?>
                <button type="button" class="buy-plan-btn" data-plan="<?php echo intval($plan['id'])?>"><?php echo ($kind === 'level' && strpos($quote['note'], '续费') === 0) ? '续费' : '立即购买'?></button>
<?php } }else{?>
                <a class="buy-plan-btn" href="./login.php">登录后购买</a>
<?php }?>
            </div>
<?php }?>
        </div>
        </div>
<?php }?>
        <p class="buy-tip">买的是会员等级：有效期内按该等级的权限使用，到期后回到普通用户；同一个等级再买是续费，天数接在到期时间后面。<br/>
        想换成更高的等级，限时会员用上面的「补差价升级」，只补到期前这段时间的差价，到期时间不变；升级后想延长时间，再买新等级的套餐。<br/>
        加量包和在线编辑包是附加包，各有自己的到期时间，不影响会员到期时间。</p>
    </div>
</div>

<div class="buy-mask" id="buyMask" hidden>
    <div class="buy-dialog">
        <button type="button" class="buy-close" id="buyClose" aria-label="关闭">×</button>
        <div class="buy-dialog-title" id="buyDialogTitle">支付宝扫码支付</div>
        <div class="buy-dialog-plan" id="buyPlanName"></div>
        <div class="buy-channels" id="buyChannels" hidden>
<?php foreach($channels as $ck => $cname){?>
            <button type="button" class="buy-channel buy-channel-<?php echo $ck?>" data-channel="<?php echo $ck?>">
                <i class="fa fa-<?php echo isset($channel_icons[$ck]) ? $channel_icons[$ck] : 'credit-card'?>" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8')?></span>
            </button>
<?php }?>
        </div>
        <div class="buy-qr" id="buyQr"></div>
        <a class="buy-jump" id="buyJump" href="#" target="_blank" rel="noopener" hidden>打开支付页面</a>
        <div class="buy-amount" id="buyAmount"></div>
        <div class="buy-state" id="buyState">请使用支付宝扫描二维码完成支付</div>
    </div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
//内置浏览器里这个 CDN 经常拉不到，换一个源再试一次；两个都失败也不影响付款，
//buy.js 会自动退化成「打开支付宝付款」的跳转按钮
if(typeof QRCode === 'undefined'){
	document.write('<script src="https:\/\/cdn.jsdelivr.net\/npm\/qrcodejs@1.0.0\/qrcode.min.js"><\/script>');
}
</script>
<script src="./assets/js/buy.js?v=<?php echo asset_ver('assets/js/buy.js')?>"></script>
</body>
</html>
