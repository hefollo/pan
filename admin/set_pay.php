<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '购买套餐设置';

/*
 * 套餐的增删改走本页自己的 POST（带来源校验），不经过 ajax.php?act=set。
 * 带 ajax=1 的请求只返回 JSON：列表 HTML 由服务端渲染好，前端替换表格即可，
 * 整个页面不用刷新。没有 JS 时表单照常整页提交，逻辑完全一样。
 */
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
/*
 * 套餐列表的表格内容。整页渲染和 AJAX 刷新都用它，保证两边显示完全一致
 */
function render_plan_rows($plans){
	ob_start();
?>
<?php if(!$plans){?>
    <tr><td colspan="10" align="center">还没有添加套餐</td></tr>
<?php } foreach($plans as $p){?>
    <tr class="plan-row" data-id="<?php echo intval($p['id'])?>">
      <td><?php echo intval($p['id'])?></td>
      <td><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8')?></td>
      <td><?php echo (isset($p['category']) && $p['category'] !== '') ? htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">未分类</span>'?></td>
      <td>¥<?php echo htmlspecialchars(number_format(floatval($p['price']), 2, '.', ''))?></td>
      <td><?php echo htmlspecialchars(plan_limit_display($p))?></td>
      <td><?php echo htmlspecialchars(plan_limit_text($p['upload_size'], 'MB'))?></td>
      <td><?php echo htmlspecialchars(plan_days_text($p['days']))?></td>
      <td><?php echo intval($p['sort'])?></td>
      <td><?php echo intval($p['enable']) === 1 ? '<span class="label label-success">上架</span>' : '<span class="label label-default">下架</span>'?></td>
      <td>
        <a class="btn btn-xs btn-primary plan-edit" href="./set_pay.php?edit=<?php echo intval($p['id'])?>"
           data-id="<?php echo intval($p['id'])?>"
           data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8')?>"
           data-category="<?php echo isset($p['category']) ? htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8') : ''?>"
           data-price="<?php echo htmlspecialchars(number_format(floatval($p['price']), 2, '.', ''))?>"
           data-days="<?php echo intval($p['days'])?>"
           data-limit-mode="<?php echo (isset($p['limit_mode']) && $p['limit_mode']==='add') ? 'add' : 'set'?>"
           data-upload-limit="<?php echo intval($p['upload_limit'])?>"
           data-upload-size="<?php echo intval($p['upload_size'])?>"
           data-sort="<?php echo intval($p['sort'])?>"
           data-enable="<?php echo intval($p['enable'])?>"
           data-remark="<?php echo htmlspecialchars($p['remark'], ENT_QUOTES, 'UTF-8')?>">编辑</a>
        <form method="post" style="display:inline" class="plan-op" data-confirm="确定切换上架状态吗？">
          <input type="hidden" name="do" value="plan_toggle"/><input type="hidden" name="id" value="<?php echo intval($p['id'])?>"/>
          <button type="submit" class="btn btn-xs btn-default"><?php echo intval($p['enable'])===1?'下架':'上架'?></button>
        </form>
        <form method="post" style="display:inline" class="plan-op" data-confirm="删除套餐不影响已产生的订单，确定删除吗？">
          <input type="hidden" name="do" value="plan_delete"/><input type="hidden" name="id" value="<?php echo intval($p['id'])?>"/>
          <button type="submit" class="btn btn-xs btn-danger">删除</button>
        </form>
      </td>
    </tr>
<?php }?>
<?php
	return ob_get_clean();
}

$msg = '';
$msgtype = 'success';
if($islogin != 1){
	if($is_ajax)exit('{"code":-1,"msg":"登录状态已失效，请重新登录后台"}');
}elseif(isset($_POST['do'])){
	if(!checkRefererHost())exit($is_ajax ? '{"code":-1,"msg":"来源校验失败"}' : '来源校验失败');
	$do = $_POST['do'];
	if($do === 'plan_save'){
		$id = intval($_POST['id']);
		$name = trim($_POST['name']);
		$price = round(floatval($_POST['price']), 2);
		$days = intval($_POST['days']);
		$upload_limit = intval($_POST['upload_limit']);
		$limit_mode = (isset($_POST['limit_mode']) && $_POST['limit_mode'] === 'add') ? 'add' : 'set';
		$upload_size = intval($_POST['upload_size']);
		$sort = intval($_POST['sort']);
		$enable = intval($_POST['enable']) === 1 ? 1 : 0;
		$remark = trim($_POST['remark']);
		$category = mb_substr(trim($_POST['category']), 0, 32, 'UTF-8');
		if($name === ''){
			$msg = '套餐名称不能为空'; $msgtype = 'danger';
		}elseif($price <= 0){
			$msg = '套餐价格必须大于 0'; $msgtype = 'danger';
		}elseif($limit_mode === 'add' && $upload_limit <= 0){
			$msg = '选择「在现有基础上增加」时，每日上传数量必须大于 0'; $msgtype = 'danger';
		}else{
			$data = [
				'name' => $name,
				'category' => $category,
				'price' => $price,
				'days' => max(0, $days),
				'upload_limit' => $upload_limit < -1 ? -1 : $upload_limit,
				'limit_mode' => $limit_mode,
				'upload_size' => $upload_size < -1 ? -1 : $upload_size,
				'sort' => $sort,
				'enable' => $enable,
				'remark' => $remark,
			];
			if($id > 0){
				$DB->update('plan', $data, ['id'=>$id]);
				$msg = '套餐已更新';
			}else{
				$data['addtime'] = 'NOW()';
				$DB->insert('plan', $data);
				$msg = '套餐已添加';
			}
		}
	}elseif($do === 'plan_seed'){
		//一键导入推荐套餐：同名的跳过，不会覆盖你已经改过的套餐
		$exists = [];
		foreach(plan_list(false) as $p_old){ $exists[] = $p_old['name']; }
		$added = 0;
		foreach(default_plans() as $d){
			if(in_array($d['name'], $exists))continue;
			$d['enable'] = 1;
			$d['addtime'] = 'NOW()';
			if($DB->insert('plan', $d))$added++;
		}
		$msg = $added > 0 ? ('已导入 '.$added.' 个推荐套餐，价格和额度可以直接改') : '推荐套餐都已经存在了，没有重复导入';
	}elseif($do === 'plan_delete'){
		$id = intval($_POST['id']);
		//订单里已经存了套餐快照，删套餐不会影响历史订单
		$DB->exec("DELETE FROM pre_plan WHERE id=:id", [':id'=>$id]);
		$msg = '套餐已删除';
	}elseif($do === 'plan_toggle'){
		$id = intval($_POST['id']);
		$DB->exec("UPDATE pre_plan SET enable=1-enable WHERE id=:id", [':id'=>$id]);
		$msg = '上架状态已切换';
	}
}

$plans = plan_list(false);
//已有的分类，给表单的输入框做候选，省得手打错字分成两组
$categories = [];
foreach($plans as $p_tmp){
	$c_tmp = isset($p_tmp['category']) ? trim($p_tmp['category']) : '';
	if($c_tmp !== '' && !in_array($c_tmp, $categories))$categories[] = $c_tmp;
}
$enabled_count = intval($DB->getColumn("SELECT count(*) FROM pre_plan WHERE enable=1"));

if($is_ajax){
	@header('Content-Type: application/json; charset=UTF-8');
	exit(json_encode([
		'code' => $msgtype === 'danger' ? -1 : 0,
		'msg' => $msg,
		'rows' => render_plan_rows($plans),
		'categories' => $categories,
		'enabled' => $enabled_count,
	], JSON_UNESCAPED_UNICODE));
}

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$edit = null;
if(isset($_GET['edit'])){
	$edit = plan_get(intval($_GET['edit']));
}
$pay_ready = !empty($conf['alipay_appid']) && !empty($conf['alipay_private_key']) && !empty($conf['alipay_public_key']);
//已开启并且配置完整的支付方式，直接列出来，省得站长猜哪里没配好
$pay_names = [];
foreach(pay_methods() as $m){ $pay_names[] = $m['name']; }
$pay_names = implode('、', $pay_names);
//默认展开的标签页：只开了易支付时就直接进易支付，省得每次都要点一下
$pay_tab = (empty($conf['alipay_open']) && !empty($conf['epay_open'])) ? 'epay' : 'alipay';
if(isset($_GET['tab']) && $_GET['tab'] === 'epay')$pay_tab = 'epay';
?>
<div class="container" style="padding-top:70px;">
<?php if($msg){?>
<div class="alert alert-<?php echo $msgtype?>"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div>
<?php }?>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">支付方式设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal pay-common" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">商品名称</label>
	  <div class="col-sm-9"><input type="text" name="pay_subject" value="<?php echo htmlspecialchars(isset($conf['pay_subject'])?$conf['pay_subject']:'赞助', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="赞助"/>
	  <p class="help-block">付款时显示的商品名，两种支付方式都用它。<b>建议保持“赞助”这类中性名字</b>：名称里带「网盘」「外链」「会员」之类的词，部分易支付站点会按违禁商品拦截，提示「该商品禁止出售」。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">购买页公告</label>
	  <div class="col-sm-9"><input type="text" name="buy_notice" value="<?php echo htmlspecialchars(isset($conf['buy_notice'])?$conf['buy_notice']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="选填，显示在购买页顶部"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存通用设置" class="btn btn-primary form-control"/></div>
	</div>
  </form>
  <ul class="nav nav-tabs pay-tabs" role="tablist">
    <li class="<?php echo $pay_tab === 'alipay' ? 'active' : ''?>"><a href="#tab-alipay" data-toggle="tab" role="tab">支付宝当面付
      <?php echo !empty($conf['alipay_open']) ? '<span class="label label-success">已开启</span>' : '<span class="label label-default">未开启</span>'?></a></li>
    <li class="<?php echo $pay_tab === 'epay' ? 'active' : ''?>"><a href="#tab-epay" data-toggle="tab" role="tab">易支付
      <?php echo !empty($conf['epay_open']) ? '<span class="label label-success">已开启</span>' : '<span class="label label-default">未开启</span>'?></a></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane<?php echo $pay_tab === 'alipay' ? ' active' : ''?>" id="tab-alipay">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝当面付</label>
	  <div class="col-sm-9"><select class="form-control" name="alipay_open" default="<?php echo isset($conf['alipay_open'])?$conf['alipay_open']:0?>"><option value="0">关闭</option><option value="1">开启</option></select>
	  <p class="help-block">开启并配置完整后，购买页会出现「支付宝当面付」这种支付方式。两种支付方式至少要开一个，前台才会出现「购买权限」入口。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">应用 ID（appid）</label>
	  <div class="col-sm-9"><input type="text" name="alipay_appid" value="<?php echo htmlspecialchars(isset($conf['alipay_appid'])?$conf['alipay_appid']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="支付宝开放平台应用的 APPID，纯数字"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">支付宝公钥</label>
	  <div class="col-sm-9"><textarea name="alipay_public_key" class="form-control" rows="4" placeholder="开放平台里「支付宝公钥」的内容，粘贴纯 base64 或带 BEGIN/END 都行"><?php echo htmlspecialchars(isset($conf['alipay_public_key'])?$conf['alipay_public_key']:'', ENT_QUOTES, 'UTF-8')?></textarea>
	  <p class="help-block">用来校验支付宝返回的内容，防止被伪造。注意不要填成「应用公钥」。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户应用私钥</label>
	  <div class="col-sm-9"><textarea name="alipay_private_key" class="form-control" rows="4" placeholder="生成密钥时得到的应用私钥（PKCS#1，RSA2）"><?php echo htmlspecialchars(isset($conf['alipay_private_key'])?$conf['alipay_private_key']:'', ENT_QUOTES, 'UTF-8')?></textarea>
	  <p class="help-block">用来给请求签名。这是敏感信息，请确保后台账号密码安全。</p></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存设置" class="btn btn-primary form-control"/></div>
	</div>
  </form>
      <div class="alert alert-info" style="margin-top:10px">
        当面付需要在支付宝开放平台为应用签约「当面付」产品，服务器要能访问 openapi.alipay.com。
      </div>
    </div>
    <div class="tab-pane<?php echo $pay_tab === 'epay' ? ' active' : ''?>" id="tab-epay">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">易支付</label>
	  <div class="col-sm-9"><select class="form-control" name="epay_open" default="<?php echo isset($conf['epay_open'])?$conf['epay_open']:0?>"><option value="0">关闭</option><option value="1">开启</option></select>
	  <p class="help-block">兼容彩虹易支付等同类接口。用户下单后跳到你填的易支付站点付款，付完本站自动开通权限。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">接口地址</label>
	  <div class="col-sm-9"><input type="text" name="epay_apiurl" value="<?php echo htmlspecialchars(isset($conf['epay_apiurl'])?$conf['epay_apiurl']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="https://pay.example.com/"/>
	  <p class="help-block">易支付站点的根地址，填到域名或目录即可（程序会自己拼 submit.php 和 api.php）。
	  <b>请务必使用 https</b>：易支付的查单响应本身没有签名，走明文 http 的话，能在网络中间做手脚的人可以伪造「已支付」骗到权限。<?php
		if(!empty($conf['epay_apiurl']) && stripos(trim($conf['epay_apiurl']), 'http://') === 0){
			echo '<br/><span style="color:#d9534f">当前填的是 http:// 地址，存在被伪造支付结果的风险，建议改成 https://。</span>';
		}
	  ?></p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户 ID（PID）</label>
	  <div class="col-sm-9"><input type="text" name="epay_pid" value="<?php echo htmlspecialchars(isset($conf['epay_pid'])?$conf['epay_pid']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="易支付后台的商户ID，纯数字"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">商户密钥（KEY）</label>
	  <div class="col-sm-9"><input type="text" name="epay_key" value="<?php echo htmlspecialchars(isset($conf['epay_key'])?$conf['epay_key']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="易支付后台的商户密钥"/>
	  <p class="help-block">用来给下单参数签名、校验回调，属于敏感信息。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开放的支付通道</label>
	  <div class="col-sm-9">
		<input type="hidden" name="epay_type" id="epayType" value="<?php echo htmlspecialchars(implode(',', array_keys(epay_channels())), ENT_QUOTES, 'UTF-8')?>"/>
<?php $epay_on = array_keys(epay_channels()); foreach(['alipay'=>'支付宝', 'wxpay'=>'微信支付', 'qqpay'=>'QQ钱包'] as $ck => $cname){?>
		<label class="checkbox-inline"><input type="checkbox" class="epay-channel" value="<?php echo $ck?>"<?php echo in_array($ck, $epay_on) ? ' checked' : ''?>/> <?php echo $cname?></label>
<?php }?>
	  <p class="help-block">勾选哪几个，用户点「立即购买」后的弹窗里就出现哪几个，选完直接进入对应的付款方式。前提是你的易支付站点开通了该通道；一个都不勾等于三个全开。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">参数编码</label>
	  <div class="col-sm-9"><select class="form-control" name="epay_charset" default="<?php echo isset($conf['epay_charset'])?$conf['epay_charset']:'UTF-8'?>">
		<option value="UTF-8">UTF-8（默认）</option>
		<option value="GBK">GBK</option>
	  </select>
	  <p class="help-block">如果在易支付后台看到订单名称是乱码，说明对方站点是 GBK 的，改成 GBK 再下一单试试。</p></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存设置" class="btn btn-primary form-control"/></div>
	</div>
  </form>
      <div class="alert alert-info" style="margin-top:10px">
        异步通知地址：<code><?php echo htmlspecialchars(rtrim($siteurl, '/').'/buy.php?act=notify', ENT_QUOTES, 'UTF-8')?></code>，
        程序会自动带给易支付，一般不需要在对方后台再填一遍。<br/>
        即使通知发不过来也没关系：购买页会主动查单，付款成功照样自动开通。
      </div>
    </div>
  </div>
  <div class="alert alert-info" style="margin-top:10px">
    当前状态：可用的支付方式 <b><?php echo $pay_names ? htmlspecialchars($pay_names, ENT_QUOTES, 'UTF-8') : '无（前台不会显示购买入口）'?></b>，
    已上架套餐 <b id="planEnabledCount"><?php echo $enabled_count?></b> 个。两种方式可以只开一个，也可以都开着让用户自己选。
  </div>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title" id="planFormTitle"><?php echo $edit ? '编辑套餐' : '添加套餐'?></h3></div>
<div class="panel-body">
  <form method="post" role="form" class="plan-form" id="planForm">
	<input type="hidden" name="do" value="plan_save"/>
	<input type="hidden" name="id" id="planId" value="<?php echo $edit ? intval($edit['id']) : 0?>"/>
	<datalist id="plan-cats">
<?php foreach($categories as $c){?>
	  <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8')?>"></option>
<?php }?>
	</datalist>
	<div class="row">
	  <div class="col-sm-4 form-group">
		<label>套餐名称</label>
		<input type="text" name="name" value="<?php echo $edit ? htmlspecialchars($edit['name'], ENT_QUOTES, 'UTF-8') : ''?>" class="form-control" placeholder="例如：月卡" required/>
		<span class="hint">显示在套餐卡片最上方</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>套餐分类</label>
		<input type="text" name="category" list="plan-cats" value="<?php echo $edit && isset($edit['category']) ? htmlspecialchars($edit['category'], ENT_QUOTES, 'UTF-8') : ''?>" class="form-control" placeholder="例如：包月套餐 / 加量包"/>
		<span class="hint">购买页按分类分区展示，留空则归到“其他套餐”</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>价格（元）</label>
		<input type="number" name="price" value="<?php echo $edit ? htmlspecialchars($edit['price']) : ''?>" class="form-control" min="0.01" step="0.01" required/>
		<span class="hint">支付宝扫码支付的金额</span>
	  </div>
	</div>
	<div class="row">
	  <div class="col-sm-4 form-group">
		<label>每日上传数量</label>
		<div class="row plan-inline">
		  <div class="col-xs-12 col-sm-7">
			<select class="form-control" name="limit_mode">
			  <option value="set" <?php echo (!$edit || (isset($edit['limit_mode']) && $edit['limit_mode']!=='add'))?'selected':''?>>设为</option>
			  <option value="add" <?php echo ($edit && isset($edit['limit_mode']) && $edit['limit_mode']==='add')?'selected':''?>>在现有数量上增加</option>
			</select>
		  </div>
		  <div class="col-xs-12 col-sm-5">
			<input type="number" name="upload_limit" value="<?php echo $edit ? intval($edit['upload_limit']) : 0?>" class="form-control" min="-1" step="1"/>
		  </div>
		</div>
		<span class="hint">设为：0 不限 / N 每天 N 个 / -1 不改动；增加：重复购买会一直叠加</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>单文件大小（MB）</label>
		<input type="number" name="upload_size" value="<?php echo $edit ? intval($edit['upload_size']) : -1?>" class="form-control" min="-1" step="1"/>
		<span class="hint">0 不限 / N 最大 N MB / -1 不改动（保持用户现有的大小）</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>有效期（天）</label>
		<input type="number" name="days" value="<?php echo $edit ? intval($edit['days']) : 30?>" class="form-control" min="0" step="1"/>
		<span class="hint">0 为永久；天数会在用户现有剩余时间上叠加</span>
	  </div>
	</div>
	<div class="row">
	  <div class="col-sm-4 form-group">
		<label>套餐说明</label>
		<input type="text" name="remark" value="<?php echo $edit ? htmlspecialchars($edit['remark'], ENT_QUOTES, 'UTF-8') : ''?>" class="form-control" placeholder="选填"/>
		<span class="hint">作为一条卖点显示在卡片里</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>排序</label>
		<input type="number" name="sort" value="<?php echo $edit ? intval($edit['sort']) : 0?>" class="form-control" step="1"/>
		<span class="hint">数字小的排前面，分类的先后顺序也看它</span>
	  </div>
	  <div class="col-sm-4 form-group">
		<label>是否上架</label>
		<select class="form-control" name="enable">
		  <option value="1" <?php echo (!$edit || intval($edit['enable'])===1)?'selected':''?>>上架</option>
		  <option value="0" <?php echo ($edit && intval($edit['enable'])===0)?'selected':''?>>下架</option>
		</select>
		<span class="hint">下架后购买页不再显示</span>
	  </div>
	</div>
	<div class="plan-actions">
	  <button type="submit" class="btn btn-primary" id="planSubmit"><?php echo $edit ? '保存修改' : '添加套餐'?></button>
	  <a class="btn btn-default" href="./set_pay.php" id="planCancel"<?php echo $edit ? '' : ' style="display:none"'?>>取消编辑</a>
	  <span class="plan-editing" id="planEditing"<?php echo $edit ? '' : ' style="display:none"'?>>正在编辑 ID <b id="planEditingId"><?php echo $edit ? intval($edit['id']) : ''?></b></span>
	</div>
  </form>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">套餐列表
  <form method="post" class="plan-op pull-right" style="margin-top:-4px" data-confirm="会添加一组推荐套餐（同名的自动跳过），导入后可以随意修改价格和额度，确定吗？">
    <input type="hidden" name="do" value="plan_seed"/>
    <button type="submit" class="btn btn-xs btn-default">一键导入推荐套餐</button>
  </form>
</h3></div>
<div class="table-responsive">
<table class="table table-striped table-hover">
  <thead><tr><th>ID</th><th>名称</th><th>分类</th><th>价格</th><th>每日数量</th><th>单文件大小</th><th>有效期</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
  <tbody id="planTbody">
<?php echo render_plan_rows($plans);?>
  </tbody>
</table>
</div>
</div>
</div>

<style>
.pay-common{padding-bottom:6px;margin-bottom:14px;border-bottom:1px solid #eee}
.pay-tabs{margin-bottom:18px}
.pay-tabs>li>a{color:#5b6478;font-weight:700}
.pay-tabs>li>a .label{margin-left:6px;font-weight:600;vertical-align:1px}
.tab-content>.tab-pane{padding-top:4px}
.plan-form .form-group{margin-bottom:10px}
.plan-form label{display:block;margin-bottom:4px;font-weight:700}
.plan-form .hint{display:block;margin-top:4px;min-height:32px;color:#8a94a6;font-size:12px;line-height:16px}
.plan-form .plan-inline{margin-left:-5px;margin-right:-5px}
.plan-form .plan-inline>div{padding-left:5px;padding-right:5px}
@media (max-width:767px){.plan-form .plan-inline>div+div{margin-top:6px}}
.plan-form .plan-actions{padding-top:4px;border-top:1px solid #eee;margin-top:6px}
.plan-form .plan-actions .btn{margin-top:10px}
.plan-form .plan-editing{margin-left:10px;color:#8a94a6;font-size:13px}
.plan-row.plan-row-active>td{background:#eef3ff !important}
</style>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script>
/*
 * 购买套餐设置整页都是就地加载：
 *   点“编辑”把该行数据填进上面的表单（数据随列表一起输出，零请求）
 *   添加/保存/上下架/删除都用 ajax=1 提交，服务端返回渲染好的列表 HTML，前端替换表格
 * 没有 JS 时这些表单仍然是普通 POST，功能不受影响。
 */
(function(){
	var form = document.getElementById('planForm');
	if(!form || typeof jQuery === 'undefined')return;
	var $ = jQuery;
	var title = document.getElementById('planFormTitle'),
		submit = document.getElementById('planSubmit'),
		cancel = document.getElementById('planCancel'),
		editing = document.getElementById('planEditing'),
		editingId = document.getElementById('planEditingId');

	function field(name){ return form.querySelector('[name="'+name+'"]'); }
	function tip(msg){ if(typeof layer !== 'undefined'){ layer.msg(msg); }else{ alert(msg); } }

	function setActiveRow(id){
		$('.plan-row').removeClass('plan-row-active');
		if(id)$('.plan-row[data-id="'+id+'"]').addClass('plan-row-active');
	}

	function fill(d){
		field('name').value = d.name;
		field('category').value = d.category;
		field('price').value = d.price;
		field('days').value = d.days;
		field('limit_mode').value = d.limitMode;
		field('upload_limit').value = d.uploadLimit;
		field('upload_size').value = d.uploadSize;
		field('sort').value = d.sort;
		field('enable').value = d.enable;
		field('remark').value = d.remark;
		document.getElementById('planId').value = d.id;
	}

	function scrollToForm(){
		try{ form.scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){ form.scrollIntoView(); }
	}

	function toEdit(a){
		fill({
			id: a.getAttribute('data-id'),
			name: a.getAttribute('data-name'),
			category: a.getAttribute('data-category'),
			price: a.getAttribute('data-price'),
			days: a.getAttribute('data-days'),
			limitMode: a.getAttribute('data-limit-mode'),
			uploadLimit: a.getAttribute('data-upload-limit'),
			uploadSize: a.getAttribute('data-upload-size'),
			sort: a.getAttribute('data-sort'),
			enable: a.getAttribute('data-enable'),
			remark: a.getAttribute('data-remark')
		});
		title.innerHTML = '编辑套餐';
		submit.innerHTML = '保存修改';
		cancel.style.display = '';
		editing.style.display = '';
		editingId.innerHTML = a.getAttribute('data-id');
		setActiveRow(a.getAttribute('data-id'));
		scrollToForm();
		field('name').focus();
	}

	function toAdd(scroll){
		fill({id:0, name:'', category:'', price:'', days:30, limitMode:'set', uploadLimit:0, uploadSize:-1, sort:0, enable:1, remark:''});
		title.innerHTML = '添加套餐';
		submit.innerHTML = '添加套餐';
		cancel.style.display = 'none';
		editing.style.display = 'none';
		setActiveRow(null);
		if(scroll)scrollToForm();
	}

	//用服务端渲染好的 HTML 替换表格，同时刷新上架数量和分类候选
	function apply(res){
		$('#planTbody').html(res.rows);
		$('#planEnabledCount').text(res.enabled);
		var dl = $('#plan-cats').empty();
		for(var i=0;i<res.categories.length;i++){
			dl.append($('<option></option>').attr('value', res.categories[i]));
		}
	}

	function post(data, done){
		data.ajax = '1';
		var load = (typeof layer !== 'undefined') ? layer.load(2) : null;
		$.post('./set_pay.php', data, function(res){
			if(load !== null)layer.close(load);
			if(!res || typeof res !== 'object'){ tip('返回内容异常，请刷新页面重试'); return; }
			if(res.code !== 0){ tip(res.msg || '操作失败'); return; }
			apply(res);
			if(res.msg)tip(res.msg);
			if(done)done();
		}, 'json').fail(function(){
			if(load !== null)layer.close(load);
			tip('请求失败，请检查网络后重试');
		});
	}

	//表单校验交给浏览器，required 没填时不发请求
	$(form).on('submit', function(e){
		e.preventDefault();
		if(form.checkValidity && !form.checkValidity()){
			if(form.reportValidity)form.reportValidity();
			return;
		}
		var data = {};
		$(this).serializeArray().forEach(function(item){ data[item.name] = item.value; });
		var wasEdit = parseInt(data.id, 10) > 0;
		post(data, function(){ toAdd(wasEdit); });
	});

	//列表是整块替换的，这里统一用事件委托，不用每次重新绑定
	$(document).on('click', '.plan-edit', function(e){ e.preventDefault(); toEdit(this); });
	$(document).on('submit', '.plan-op', function(e){
		e.preventDefault();
		var msg = this.getAttribute('data-confirm');
		if(msg && !confirm(msg))return;
		var data = {};
		$(this).serializeArray().forEach(function(item){ data[item.name] = item.value; });
		var editingNow = document.getElementById('planId').value;
		post(data, function(){
			//正在编辑的那个套餐被删了，表单退回新增状态
			if(data['do'] === 'plan_delete' && String(data.id) === String(editingNow))toAdd(false);
			else setActiveRow(editingNow > 0 ? editingNow : null);
		});
	});

	if(cancel)cancel.onclick = function(e){ e.preventDefault(); toAdd(true); return false; };
	//带 ?edit= 打开时高亮对应的那一行
	var m = location.search.match(/[?&]edit=(\d+)/);
	if(m)setActiveRow(m[1]);
})();
</script>
<script>
function saveSetting(obj){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {icon:1, closeBtn:false}, function(){ window.location.reload(); });
			}else{
				layer.alert(data.msg, {icon:2});
			}
		},
		error:function(){ layer.close(ii); layer.msg('服务器错误'); }
	});
	return false;
}
$(function(){
	$('select[default]').each(function(){ $(this).val($(this).attr('default')); });
	//保存设置会刷新页面，这里记一下当前停在哪个支付标签页，刷新回来还在原处
	try{
		var saved = localStorage.getItem('pay_tab');
		if(saved && !location.search.match(/[?&]tab=/)){
			var link = $('.pay-tabs a[href="#tab-' + saved + '"]');
			if(link.length)link.tab('show');
		}
	}catch(e){}
	//易支付通道用复选框，保存时拼成 alipay,wxpay,qqpay 这样的一个值
	function syncEpayChannels(){
		var picked = [];
		$('.epay-channel:checked').each(function(){ picked.push(this.value); });
		$('#epayType').val(picked.join(','));
	}
	$('.epay-channel').on('change', syncEpayChannels);
	syncEpayChannels();
	$('.pay-tabs a[data-toggle="tab"]').on('shown.bs.tab', function(){
		try{ localStorage.setItem('pay_tab', this.getAttribute('href').replace('#tab-', '')); }catch(e){}
	});
});
</script>
</body>
</html>
