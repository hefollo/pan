<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '邮件发信设置';

/*
 * 测试发信走本页自己的 POST（带来源校验），返回 JSON，前端就地显示每个通道的尝试结果。
 * 处理逻辑要放在 head.php 之前，否则 JSON 前面会混进后台页面的 HTML。
 */
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
if($islogin != 1){
	if($is_ajax)exit('{"code":-1,"msg":"登录状态已失效，请重新登录后台"}');
}elseif($is_ajax && isset($_POST['do']) && $_POST['do'] === 'test'){
	if(!checkRefererHost())exit('{"code":-1,"msg":"来源校验失败"}');
	@header('Content-Type: application/json; charset=UTF-8');
	$to = isset($_POST['to']) ? trim($_POST['to']) : '';
	if(!filter_var($to, FILTER_VALIDATE_EMAIL)){
		exit(json_encode(['code'=>-1, 'msg'=>'收件地址格式不正确']));
	}
	$mailer = mailer();
	if(!$mailer->senders()){
		exit(json_encode(['code'=>-1, 'msg'=>'还没有勾选任何发信通道，请先勾选并保存设置']));
	}
	$subject = '【'.$conf['title'].'】发信测试';
	$html = '<p>这是一封来自 <b>'.htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8').'</b> 的测试邮件。</p>'
		.'<p>能收到这封信，说明发信通道已经配置成功，可以用来发送注册验证码了。</p>'
		.'<p style="color:#888;font-size:12px">发送时间：'.date('Y-m-d H:i:s').'</p>';
	$res = $mailer->send($to, $subject, $html);
	exit(json_encode([
		'code' => !empty($res['ok']) ? 0 : -1,
		'msg' => !empty($res['ok']) ? ('发送成功，通道：'.$res['sender'].'，请到收件箱（和垃圾箱）确认') : $res['msg'],
		'attempts' => $mailer->attempts(),
	], JSON_UNESCAPED_UNICODE));
}

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$mail_ready = is_mail_ready();
$mail_names = [];
foreach(mailer()->senders() as $sender){
	if($sender->isReady())$mail_names[] = $sender->name();
}
$mail_names = implode('、', $mail_names);
?>
<div class="container" style="padding-top:70px;">

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">发件人设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">发件地址</label>
	  <div class="col-sm-9"><input type="text" name="mail_from" value="<?php echo htmlspecialchars(isset($conf['mail_from'])?$conf['mail_from']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="noreply@example.com"/>
	  <p class="help-block">收件人看到的发件地址，两种发信方式共用。用 SMTP 时这里<b>必须和下面的 SMTP 账号是同一个邮箱</b>，否则会被服务器拒收。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">发件人名称</label>
	  <div class="col-sm-9"><input type="text" name="mail_from_name" value="<?php echo htmlspecialchars(isset($conf['mail_from_name'])?$conf['mail_from_name']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="<?php echo htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8')?>"/>
	  <p class="help-block">选填，显示在收件箱里的发件人名字，留空则直接显示发件地址。</p></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存发件人设置" class="btn btn-primary form-control"/></div>
	</div>
  </form>
  <div class="alert alert-info" style="margin-top:10px">
	当前可用的发信通道：<b><?php echo $mail_names ? htmlspecialchars($mail_names, ENT_QUOTES, 'UTF-8') : '无（发信功能未开启）'?></b>。
	<b>没有默认通道，勾选哪个用哪个，一个都不勾就是关闭发信。</b>
	勾选多个时按「SMTP → Resend → Brevo → SendGrid」的顺序依次尝试，前一个失败会自动切到下一个。
  </div>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">发信通道</h3></div>
<div class="panel-body">
  <ul class="nav nav-tabs mail-tabs" role="tablist">
	<li class="active"><a href="#tab-smtp" data-toggle="tab" role="tab">SMTP
	  <?php echo !empty($conf['mail_smtp_open']) ? '<span class="label label-success">已启用</span>' : '<span class="label label-default">未启用</span>'?></a></li>
	<li><a href="#tab-resend" data-toggle="tab" role="tab">Resend
	  <?php echo !empty($conf['mail_resend_open']) ? '<span class="label label-success">已启用</span>' : '<span class="label label-default">未启用</span>'?></a></li>
	<li><a href="#tab-brevo" data-toggle="tab" role="tab">Brevo
	  <?php echo !empty($conf['mail_brevo_open']) ? '<span class="label label-success">已启用</span>' : '<span class="label label-default">未启用</span>'?></a></li>
	<li><a href="#tab-sendgrid" data-toggle="tab" role="tab">SendGrid
	  <?php echo !empty($conf['mail_sendgrid_open']) ? '<span class="label label-success">已启用</span>' : '<span class="label label-default">未启用</span>'?></a></li>
  </ul>
  <div class="tab-content">

	<div class="tab-pane active" id="tab-smtp">
	  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
		<div class="form-group">
		  <label class="col-sm-3 control-label">启用 SMTP</label>
		  <div class="col-sm-9"><select class="form-control" name="mail_smtp_open" default="<?php echo isset($conf['mail_smtp_open'])?$conf['mail_smtp_open']:0?>"><option value="0">不启用</option><option value="1">启用</option></select>
		  <p class="help-block">用你自己的邮箱账号发信（QQ邮箱、163、企业邮箱都行），密码填<b>授权码</b>而不是登录密码。</p></div>
		</div><br/>
		<div class="form-group">
		  <label class="col-sm-3 control-label">服务器地址</label>
		  <div class="col-sm-9"><input type="text" name="mail_smtp_host" value="<?php echo htmlspecialchars(isset($conf['mail_smtp_host'])?$conf['mail_smtp_host']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="smtp.qq.com"/>
		  <p class="help-block">QQ邮箱 smtp.qq.com、163 smtp.163.com、腾讯企业邮 smtp.exmail.qq.com。</p></div>
		</div><br/>
		<div class="form-group">
		  <label class="col-sm-3 control-label">端口与加密</label>
		  <div class="col-sm-9">
			<div class="row">
			  <div class="col-xs-6"><input type="number" name="mail_smtp_port" value="<?php echo htmlspecialchars(isset($conf['mail_smtp_port'])?$conf['mail_smtp_port']:'465', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="465"/></div>
			  <div class="col-xs-6"><select class="form-control" name="mail_smtp_secure" default="<?php echo isset($conf['mail_smtp_secure'])?$conf['mail_smtp_secure']:'ssl'?>">
				<option value="ssl">SSL（端口 465）</option>
				<option value="tls">STARTTLS（端口 587）</option>
				<option value="none">不加密（端口 25）</option>
			  </select></div>
			</div>
		  <p class="help-block"><b>推荐 465 + SSL</b>。国内云服务器基本都封禁了 25 端口，不加密那种多半连不通。</p></div>
		</div><br/>
		<div class="form-group">
		  <label class="col-sm-3 control-label">邮箱账号</label>
		  <div class="col-sm-9"><input type="text" name="mail_smtp_user" value="<?php echo htmlspecialchars(isset($conf['mail_smtp_user'])?$conf['mail_smtp_user']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="你的邮箱地址"/></div>
		</div><br/>
		<div class="form-group">
		  <label class="col-sm-3 control-label">授权码</label>
		  <div class="col-sm-9"><input type="text" name="mail_smtp_pass" value="<?php echo htmlspecialchars(isset($conf['mail_smtp_pass'])?$conf['mail_smtp_pass']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="在邮箱设置里生成的授权码"/>
		  <p class="help-block">QQ邮箱在「设置 - 账号 - POP3/SMTP服务」里开启并生成授权码，不是 QQ 密码。属于敏感信息。</p></div>
		</div><br/>
		<div class="form-group">
		  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存 SMTP 设置" class="btn btn-primary form-control"/></div>
		</div>
	  </form>
	</div>

	<div class="tab-pane" id="tab-resend">
	  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
		<div class="form-group">
		  <label class="col-sm-3 control-label">启用 Resend</label>
		  <div class="col-sm-9"><select class="form-control" name="mail_resend_open" default="<?php echo isset($conf['mail_resend_open'])?$conf['mail_resend_open']:0?>"><option value="0">不启用</option><option value="1">启用</option></select>
		  <p class="help-block">走 HTTPS 接口发信，不受服务器封禁 25/465 端口的影响。免费额度每天约 100 封。</p></div>
		</div><br/>
		<div class="form-group">
		  <label class="col-sm-3 control-label">API Key</label>
		  <div class="col-sm-9"><input type="text" name="mail_resend_key" value="<?php echo htmlspecialchars(isset($conf['mail_resend_key'])?$conf['mail_resend_key']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="re_xxxxxxxx"/>
		  <p class="help-block">在 resend.com 后台创建。发件地址的域名需要先在它那里验证过，否则会被拒绝。</p></div>
		</div><br/>
		<div class="form-group">
		  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存 Resend 设置" class="btn btn-primary form-control"/></div>
		</div>
	  </form>
	</div>

	<div class="tab-pane" id="tab-brevo">
	  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
		<div class="form-group">
		  <label class="col-sm-3 control-label">启用 Brevo</label>
		  <div class="col-sm-9"><select class="form-control" name="mail_brevo_open" default="<?php echo isset($conf['mail_brevo_open'])?$conf['mail_brevo_open']:0?>"><option value="0">不启用</option><option value="1">启用</option></select>
		  <p class="help-block">同样走 HTTPS 接口，免费额度每天约 300 封，是三家里最宽松的。</p></div>
		</div><br/>
		<div class="form-group">
		  <label class="col-sm-3 control-label">API Key</label>
		  <div class="col-sm-9"><input type="text" name="mail_brevo_key" value="<?php echo htmlspecialchars(isset($conf['mail_brevo_key'])?$conf['mail_brevo_key']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="xkeysib-xxxxxxxx"/>
		  <p class="help-block">在 brevo.com 后台「SMTP & API」里创建 API Key（v3）。</p></div>
		</div><br/>
		<div class="form-group">
		  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存 Brevo 设置" class="btn btn-primary form-control"/></div>
		</div>
	  </form>
	</div>

	<div class="tab-pane" id="tab-sendgrid">
	  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
		<div class="form-group">
		  <label class="col-sm-3 control-label">启用 SendGrid</label>
		  <div class="col-sm-9"><select class="form-control" name="mail_sendgrid_open" default="<?php echo isset($conf['mail_sendgrid_open'])?$conf['mail_sendgrid_open']:0?>"><option value="0">不启用</option><option value="1">启用</option></select>
		  <p class="help-block">同样走 HTTPS 接口，免费额度每天约 100 封。</p></div>
		</div><br/>
		<div class="form-group">
		  <label class="col-sm-3 control-label">API Key</label>
		  <div class="col-sm-9"><input type="text" name="mail_sendgrid_key" value="<?php echo htmlspecialchars(isset($conf['mail_sendgrid_key'])?$conf['mail_sendgrid_key']:'', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="SG.xxxxxxxx"/>
		  <p class="help-block">在 sendgrid.com 后台创建，需要有 Mail Send 权限，并完成发件人或域名验证。</p></div>
		</div><br/>
		<div class="form-group">
		  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存 SendGrid 设置" class="btn btn-primary form-control"/></div>
		</div>
	  </form>
	</div>

  </div>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">邮箱注册设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">邮箱注册</label>
	  <div class="col-sm-9"><select class="form-control" name="mail_reg_open" default="<?php echo isset($conf['mail_reg_open'])?$conf['mail_reg_open']:0?>"><option value="0">关闭</option><option value="1">开启</option></select>
	  <p class="help-block">开启后登录页会出现「注册」标签，用户填邮箱收验证码即可注册。需要先配好上面的发信通道，并且「用户登录设置」里的登录功能是开启的。<br/>关闭后已注册的用户仍然可以用邮箱登录，只是不能再注册新账号。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">算术验证码</label>
	  <div class="col-sm-9"><select class="form-control" name="mail_captcha_open" default="<?php echo isset($conf['mail_captcha_open'])?$conf['mail_captcha_open']:1?>"><option value="1">开启</option><option value="0">关闭</option></select>
	  <p class="help-block">在「获取验证码」前加一道简单算术题（比如 3 + 5 = ?），纯服务端生成、不依赖第三方也不用画图。<b>建议保持开启</b>：光靠频率限制挡不住脚本，加了这一步刷接口的成本会高很多。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">验证码有效期</label>
	  <div class="col-sm-9"><div class="input-group"><input type="number" name="mail_code_expire" value="<?php echo htmlspecialchars(isset($conf['mail_code_expire'])?$conf['mail_code_expire']:'10', ENT_QUOTES, 'UTF-8')?>" class="form-control" min="1" max="60"/><span class="input-group-addon">分钟</span></div>
	  <p class="help-block">验证码错 5 次会自动作废，需要重新获取。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">同邮箱发送间隔</label>
	  <div class="col-sm-9"><div class="input-group"><input type="number" name="mail_send_interval" value="<?php echo htmlspecialchars(isset($conf['mail_send_interval'])?$conf['mail_send_interval']:'60', ENT_QUOTES, 'UTF-8')?>" class="form-control" min="0"/><span class="input-group-addon">秒</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每日发送上限</label>
	  <div class="col-sm-9">
		<div class="row">
		  <div class="col-xs-6"><div class="input-group"><span class="input-group-addon">同邮箱</span><input type="number" name="mail_daily_limit" value="<?php echo htmlspecialchars(isset($conf['mail_daily_limit'])?$conf['mail_daily_limit']:'10', ENT_QUOTES, 'UTF-8')?>" class="form-control" min="0"/></div></div>
		  <div class="col-xs-6"><div class="input-group"><span class="input-group-addon">同 IP</span><input type="number" name="mail_ip_daily_limit" value="<?php echo htmlspecialchars(isset($conf['mail_ip_daily_limit'])?$conf['mail_ip_daily_limit']:'20', ENT_QUOTES, 'UTF-8')?>" class="form-control" min="0"/></div></div>
		</div>
	  <p class="help-block">填 0 表示不限制。「同 IP」用的是伪造不了的来源地址：只有请求确实来自 Cloudflare 时才采信 CF 的真实 IP 头，否则一律按 TCP 连接的对端地址算。<br/><b>建议把「同 IP」调到 5 左右</b>：正常人注册一次最多试两三回，调小能明显压制脚本刷接口。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">全站发信上限</label>
	  <div class="col-sm-9">
		<div class="row">
		  <div class="col-xs-6"><div class="input-group"><span class="input-group-addon">每小时</span><input type="number" name="mail_hour_limit" value="<?php echo htmlspecialchars(isset($conf['mail_hour_limit'])?$conf['mail_hour_limit']:'50', ENT_QUOTES, 'UTF-8')?>" class="form-control" min="0"/></div></div>
		  <div class="col-xs-6"><div class="input-group"><span class="input-group-addon">每天</span><input type="number" name="mail_site_daily" value="<?php echo htmlspecialchars(isset($conf['mail_site_daily'])?$conf['mail_site_daily']:'200', ENT_QUOTES, 'UTF-8')?>" class="form-control" min="0"/></div></div>
		</div>
	  <p class="help-block">最后一道保险：就算有人换着邮箱和网络来刷，也烧不掉整个发信额度。正常站点碰不到这个数，按自己的邮箱额度设置即可（QQ 邮箱个人账号一天大约几十封）。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">邮箱域名黑名单</label>
	  <div class="col-sm-9"><textarea name="mail_domain_deny" class="form-control" rows="3" placeholder="mailinator.com&#10;10minutemail.com"><?php echo htmlspecialchars(isset($conf['mail_domain_deny'])?$conf['mail_domain_deny']:'', ENT_QUOTES, 'UTF-8')?></textarea>
	  <p class="help-block">一行一个域名，用来挡一次性邮箱。留空表示不限制。</p></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" value="保存注册设置" class="btn btn-primary form-control"/></div>
	</div>
  </form>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">测试发信</h3></div>
<div class="panel-body">
  <form onsubmit="return sendTest(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">收件地址</label>
	  <div class="col-sm-9">
		<div class="row">
		  <div class="col-xs-8"><input type="email" name="to" id="testTo" class="form-control" placeholder="填一个你能收到信的邮箱" required/></div>
		  <div class="col-xs-4"><button type="submit" class="btn btn-primary form-control">发送测试邮件</button></div>
		</div>
	  <p class="help-block">改完设置记得先保存再测试。失败时会显示每个通道的具体原因（含 SMTP 会话记录），照着提示改就行。</p></div>
	</div>
  </form>
  <div id="testResult" style="display:none"></div>
</div>
</div>

</div>

<style>
.mail-tabs{margin-bottom:18px}
.mail-tabs>li>a{color:#5b6478;font-weight:700}
.mail-tabs>li>a .label{margin-left:6px;font-weight:600;vertical-align:1px}
.tab-content>.tab-pane{padding-top:4px}
.mail-attempt{margin:0 0 8px;padding:10px 12px;border-radius:8px;font-size:13px}
.mail-attempt-ok{background:rgba(31,146,84,.1);color:#1f7a48}
.mail-attempt-fail{background:rgba(220,53,69,.08);color:#b02a37}
.mail-attempt b{display:block;margin-bottom:2px}
.mail-attempt pre{margin:8px 0 0;padding:8px;max-height:180px;overflow:auto;background:rgba(0,0,0,.05);border:0;border-radius:6px;font-size:12px;line-height:1.6;color:#444}
</style>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
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

//测试发信：把每个通道的尝试结果原样显示出来，不然失败了只能靠猜
function sendTest(form){
	var box = $('#testResult');
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : './set_mail.php',
		//do 是 JS 保留字，当键名要加引号，老浏览器内核不加会解析失败
		data : { ajax:'1', 'do':'test', to:$('#testTo').val() },
		dataType : 'json',
		timeout : 60000,
		success : function(res){
			layer.close(ii);
			var html = '<div class="alert alert-' + (res.code==0?'success':'danger') + '">' + esc(res.msg) + '</div>';
			if(res.attempts && res.attempts.length){
				for(var i=0;i<res.attempts.length;i++){
					var a = res.attempts[i];
					html += '<div class="mail-attempt ' + (a.ok?'mail-attempt-ok':'mail-attempt-fail') + '">'
						+ '<b>' + esc(a.name) + (a.ok?'：成功':'：失败') + '</b>' + esc(a.msg);
					if(a.detail && a.detail.length){
						html += '<pre>' + esc(a.detail.join('\n')) + '</pre>';
					}
					html += '</div>';
				}
			}
			box.html(html).show();
		},
		error:function(){ layer.close(ii); box.html('<div class="alert alert-danger">请求失败或超时，请稍后重试</div>').show(); }
	});
	return false;
}

function esc(s){
	return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){
		return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
	});
}

$(function(){
	$('select[default]').each(function(){ $(this).val($(this).attr('default')); });
});
</script>
</body>
</html>
