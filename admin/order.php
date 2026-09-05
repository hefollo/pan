<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '订单记录';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$msg = '';
if(isset($_POST['do'])){
	if(!checkRefererHost())exit('来源校验失败');
	if($_POST['do'] === 'clean'){
		//只清未支付/已关闭的，并且留出一小时，免得把正在付款的那笔删掉
		$DB->exec("DELETE FROM pre_order WHERE status<>1 AND addtime < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
		$msg = '未支付订单已清理';
	}else{
	$id = intval($_POST['id']);
	$order = $DB->getRow("SELECT * FROM pre_order WHERE id=:id LIMIT 1", [':id'=>$id]);
	if($order){
		if($_POST['do'] === 'regrant' && intval($order['status']) === 1){
			//补发：发货失败（比如当时用户被删了）时手动再发一次，权限规则跟自动发放一致
			$msg = grant_plan_to_user($order['uid'], $order) ? '已重新发放权限' : '补发失败，请确认该用户还存在';
		}elseif($_POST['do'] === 'recheck' && intval($order['status']) !== 1){
			//去支付渠道问一遍这笔到底有没有付款，付过就补上
			$res = check_order_paid($order);
			if($res['code'] != 0)$msg = '查询失败：'.$res['msg'];
			else $msg = !empty($res['paid']) ? '这笔订单已支付，权限已经发放' : '支付渠道显示这笔订单还没有付款';
		}elseif($_POST['do'] === 'close' && intval($order['status']) === 0){
			$DB->exec("UPDATE pre_order SET status=2 WHERE id=:id", [':id'=>$id]);
			$msg = '订单已关闭';
		}
	}
	}
}

$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : -1;
$where = $status_filter >= 0 ? " WHERE status=".$status_filter : '';
$numrows = intval($DB->getColumn("SELECT count(*) FROM pre_order".$where));
$pagesize = 20;
$pages = max(1, ceil($numrows / $pagesize));
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if($page > $pages)$page = $pages;
$offset = $pagesize * ($page - 1);
$orders = $DB->getAll("SELECT o.*, u.nickname FROM pre_order o LEFT JOIN pre_user u ON u.uid=o.uid".
	($status_filter >= 0 ? " WHERE o.status=".$status_filter : '')." ORDER BY o.id DESC LIMIT {$offset},{$pagesize}");
if(!is_array($orders))$orders = [];

$paid_total = $DB->getColumn("SELECT IFNULL(SUM(price),0) FROM pre_order WHERE status=1");
$paid_count = intval($DB->getColumn("SELECT count(*) FROM pre_order WHERE status=1"));
$status_text = [0=>'<span class="label label-warning">待支付</span>', 1=>'<span class="label label-success">已支付</span>', 2=>'<span class="label label-default">已关闭</span>'];
?>
<div class="container">
<div class="admin-page-wide">
<?php if($msg){?><div class="alert alert-info"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div><?php }?>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">订单记录</h3></div>
<div class="panel-body">
  已成功支付 <b><?php echo $paid_count?></b> 笔，累计金额 <b>¥<?php echo htmlspecialchars(number_format(floatval($paid_total), 2, '.', ''))?></b>。
  <div style="margin-top:10px">
    <a class="btn btn-xs <?php echo $status_filter<0?'btn-primary':'btn-default'?>" href="./order.php">全部</a>
    <a class="btn btn-xs <?php echo $status_filter===1?'btn-primary':'btn-default'?>" href="./order.php?status=1">已支付</a>
    <a class="btn btn-xs <?php echo $status_filter===0?'btn-primary':'btn-default'?>" href="./order.php?status=0">待支付</a>
    <a class="btn btn-xs <?php echo $status_filter===2?'btn-primary':'btn-default'?>" href="./order.php?status=2">已关闭</a>
    <form method="post" style="display:inline;margin-left:10px" onsubmit="return confirm('会删除一小时前所有未支付/已关闭的订单，已支付的不受影响，确定吗？')">
      <input type="hidden" name="do" value="clean"/>
      <button type="submit" class="btn btn-xs btn-default">清理未支付订单</button>
    </form>
  </div>
</div>
<div class="table-responsive">
<table class="table table-striped table-hover">
  <thead><tr><th>订单号</th><th>用户</th><th>套餐</th><th>金额</th><th>支付方式</th><th>权限</th><th>状态</th><th>下单时间</th><th>支付时间</th><th>操作</th></tr></thead>
  <tbody>
<?php if(!$orders){?>
    <tr><td colspan="10" align="center">暂无订单</td></tr>
<?php } foreach($orders as $o){?>
    <tr>
      <td><small><?php echo htmlspecialchars($o['trade_no'], ENT_QUOTES, 'UTF-8')?></small><?php echo $o['alipay_no'] ? '<br/><small class="text-muted">'.htmlspecialchars($o['alipay_no'], ENT_QUOTES, 'UTF-8').'</small>' : ''?></td>
      <td><?php echo htmlspecialchars($o['nickname'] === null ? '(用户已删除)' : $o['nickname'], ENT_QUOTES, 'UTF-8')?><br/><small class="text-muted">UID <?php echo intval($o['uid'])?></small></td>
      <td><?php echo htmlspecialchars($o['plan_name'], ENT_QUOTES, 'UTF-8')?></td>
      <td>¥<?php echo htmlspecialchars(number_format(floatval($o['price']), 2, '.', ''))?></td>
      <td><small><?php echo htmlspecialchars(pay_method_name(isset($o['pay_type']) ? $o['pay_type'] : ''))?></small></td>
      <td><small>每日 <?php echo htmlspecialchars(plan_limit_display($o))?><br/>单文件 <?php echo htmlspecialchars(plan_limit_text($o['upload_size'], 'MB'))?><br/><?php echo htmlspecialchars(plan_days_text($o['days']))?></small></td>
      <td><?php echo isset($status_text[intval($o['status'])]) ? $status_text[intval($o['status'])] : ''?></td>
      <td><small><?php echo htmlspecialchars($o['addtime'])?></small></td>
      <td><small><?php echo htmlspecialchars($o['paytime'] ? $o['paytime'] : '-')?></small></td>
      <td>
<?php if(intval($o['status']) === 1){?>
        <form method="post" style="display:inline" onsubmit="return confirm('会按该订单的套餐重新发放一次权限，增加型套餐会再叠加一次数量，确定吗？')">
          <input type="hidden" name="do" value="regrant"/><input type="hidden" name="id" value="<?php echo intval($o['id'])?>"/>
          <button type="submit" class="btn btn-xs btn-default">补发权限</button>
        </form>
<?php }else{?>
        <form method="post" style="display:inline">
          <input type="hidden" name="do" value="recheck"/><input type="hidden" name="id" value="<?php echo intval($o['id'])?>"/>
          <button type="submit" class="btn btn-xs btn-default" title="去支付渠道查这笔订单的真实状态，付过就补发权限">查询支付状态</button>
        </form>
<?php if(intval($o['status']) === 0){?>
        <form method="post" style="display:inline" onsubmit="return confirm('确定关闭这笔待支付订单吗？')">
          <input type="hidden" name="do" value="close"/><input type="hidden" name="id" value="<?php echo intval($o['id'])?>"/>
          <button type="submit" class="btn btn-xs btn-default">关闭</button>
        </form>
<?php } }?>
      </td>
    </tr>
<?php }?>
  </tbody>
</table>
</div>
<div class="panel-footer">
  共 <?php echo $numrows?> 条，第 <?php echo $page?> / <?php echo $pages?> 页
<?php if($pages > 1){
	$link = $status_filter >= 0 ? '&status='.$status_filter : '';
?>
  <span class="pull-right">
<?php if($page > 1){?><a class="btn btn-xs btn-default" href="./order.php?page=<?php echo $page-1 . $link?>">上一页</a><?php }?>
<?php if($page < $pages){?><a class="btn btn-xs btn-default" href="./order.php?page=<?php echo $page+1 . $link?>">下一页</a><?php }?>
  </span>
<?php }?>
</div>
</div>
</div>
</div>
</body>
</html>
