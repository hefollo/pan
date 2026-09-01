<?php
include("./includes/common.php");

if(!$conf['userlogin']){
    @header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('未开启登录');window.location.href='./';</script>");
}
if(isset($_GET['logout'])){
	if(!checkRefererHost())exit();
	unset($_SESSION['user_block']);
	set_auth_cookie("user_token", "", time() - 1, '/');
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登录！');window.location.href='./login.php';</script>");
}elseif($islogin2==1){
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}elseif(isset($_GET['act']) && $_GET['act']=='connect'){
    @header('Content-Type: application/json; charset=UTF-8');
    $type = isset($_POST['type'])?$_POST['type']:exit('{"code":-1,"msg":"no type"}');
    if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('{"code":-1,"msg":"未配置好快捷登录接口信息"}');
    //接口连不通时每次点击都要等一次超时，还会占住 PHP 进程；失败后 60 秒内直接返回错误，不再去连
    $oauth_fail_file = sys_get_temp_dir().'/pan_oauthfail_'.substr(md5(SYS_KEY.'|oauthconnect'), 0, 16);
    if(is_file($oauth_fail_file) && filemtime($oauth_fail_file) + 60 > time()){
        exit(json_encode(['code'=>-1, 'msg'=>'快捷登录接口暂时无法访问，请稍后再试']));
    }
    $Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $res = $Oauth->login($type);
    if(isset($res['code']) && $res['code']==0){
        @unlink($oauth_fail_file);
        $result = ['code'=>0, 'url'=>$res['url']];
    }elseif(isset($res['code'])){
        $result = ['code'=>-1, 'msg'=>$res['msg']];
    }else{
        //请求没拿到任何结果：接口地址不对、对方挂了或者超时
        @touch($oauth_fail_file);
        $result = ['code'=>-1, 'msg'=>'快捷登录接口连接失败或超时，请检查后台“用户登录设置”里的接口地址'];
    }
    exit(json_encode($result));
}elseif(isset($_GET['act']) && $_GET['act'] === 'captcha'){
	//换一题：不校验令牌，因为它不做任何有代价的事，只是重新出题
	@header('Content-Type: application/json; charset=UTF-8');
	if(!checkRefererHost())exit('{"code":-1,"msg":"来源校验失败"}');
	exit(json_encode(['code'=>0, 'question'=>make_captcha()], JSON_UNESCAPED_UNICODE));

}elseif(isset($_GET['act']) && in_array($_GET['act'], ['sendcode', 'register', 'maillogin'], true)){
	@header('Content-Type: application/json; charset=UTF-8');
	if(!checkRefererHost())exit('{"code":-1,"msg":"来源校验失败"}');
	$act = $_GET['act'];
	/*
	 * 会话令牌：必须先正常打开登录页拿到令牌，才能调这三个接口。
	 * Referer 谁都能伪造，令牌绑在会话上，抓包工具直接重放单个请求会被挡在这里。
	 */
	$token = isset($_POST['token']) ? (string)$_POST['token'] : '';
	if(empty($_SESSION['mail_token']) || !hash_equals($_SESSION['mail_token'], $token)){
		exit('{"code":-1,"msg":"页面已失效，请刷新登录页后重试"}');
	}
	$email = normalize_email(isset($_POST['email']) ? $_POST['email'] : '');
	if(!filter_var($email, FILTER_VALIDATE_EMAIL))exit('{"code":-1,"msg":"邮箱格式不正确"}');

	if($act === 'sendcode'){
		//目前只有注册会用到验证码，找回密码留到下一步做
		if(!is_mail_reg_open())exit('{"code":-1,"msg":"站点未开启邮箱注册"}');
		/*
		 * 会话级频率：令牌逼着调用方维持会话，这两道限制才真正拦得住。
		 * 间隔那道顺便也管住了下面"这个邮箱注册过没有"的查询——它不消耗发信配额，
		 * 不限速的话可以拿来无成本地枚举站里有哪些邮箱。
		 */
		if(isset($_SESSION['sendcode_time']) && $_SESSION['sendcode_time'] + 60 > time()){
			$wait = $_SESSION['sendcode_time'] + 60 - time();
			exit(json_encode(['code'=>-1, 'msg'=>'操作太频繁了，请 '.$wait.' 秒后再试']));
		}
		$today = date('Y-m-d');
		if(!isset($_SESSION['sendcode_day']) || $_SESSION['sendcode_day'] !== $today){
			$_SESSION['sendcode_day'] = $today;
			$_SESSION['sendcode_num'] = 0;
		}
		if(intval($_SESSION['sendcode_num']) >= 5){
			exit('{"code":-1,"msg":"今天获取验证码的次数已用完，请明天再试"}');
		}
		/*
		 * 算术题放在发信之前、也放在会话计数之后：
		 * 答错不消耗发信配额，但要占掉一次会话间隔，脚本没法零成本地一直试。
		 */
		if(is_captcha_open()){
			$err = check_captcha(isset($_POST['captcha']) ? $_POST['captcha'] : '');
			if($err !== ''){
				exit(json_encode(['code'=>-1, 'msg'=>$err, 'question'=>make_captcha()], JSON_UNESCAPED_UNICODE));
			}
		}
		$_SESSION['sendcode_time'] = time();
		$_SESSION['sendcode_num'] = intval($_SESSION['sendcode_num']) + 1;
		if(mail_domain_denied($email))exit('{"code":-1,"msg":"该邮箱域名不被支持，请换一个邮箱"}');
		if($DB->getColumn("SELECT uid FROM pre_user WHERE type='mail' AND openid=:e LIMIT 1", [':e'=>$email])){
			exit(json_encode(['code'=>-1, 'msg'=>'该邮箱已经注册过了，请直接登录']));
		}
		$res = send_mail_code($email, 'register');
		//每次都换一个令牌：抓到的旧令牌用不了第二次
		$_SESSION['mail_token'] = md5(uniqid('', true).mt_rand());
		exit(json_encode([
			'code' => $res['code'],
			'msg' => $res['msg'],
			'token' => $_SESSION['mail_token'],
			'question' => is_captcha_open() ? make_captcha() : '',
		], JSON_UNESCAPED_UNICODE));
	}

	if($act === 'register'){
		if(!is_mail_reg_open())exit('{"code":-1,"msg":"站点未开启邮箱注册"}');
		if(mail_domain_denied($email))exit('{"code":-1,"msg":"该邮箱域名不被支持，请换一个邮箱"}');
		$pwd = isset($_POST['password']) ? (string)$_POST['password'] : '';
		$err = check_password_rule($pwd);
		if($err !== '')exit(json_encode(['code'=>-1, 'msg'=>$err]));

		$check = verify_mail_code($email, isset($_POST['code']) ? $_POST['code'] : '', 'register');
		if($check['code'] != 0)exit(json_encode(['code'=>-1, 'msg'=>$check['msg']], JSON_UNESCAPED_UNICODE));

		//验证码过了再查一次重复：中间可能已经有人用同一个邮箱注册了
		if($DB->getColumn("SELECT uid FROM pre_user WHERE type='mail' AND openid=:e LIMIT 1", [':e'=>$email])){
			exit(json_encode(['code'=>-1, 'msg'=>'该邮箱已经注册过了，请直接登录']));
		}

		$nickname = trim(htmlspecialchars(isset($_POST['nickname']) ? $_POST['nickname'] : '', ENT_QUOTES, 'UTF-8'));
		if($nickname === '')$nickname = substr($email, 0, strpos($email, '@'));
		if(mb_strlen($nickname, 'UTF-8') > 20)$nickname = mb_substr($nickname, 0, 20, 'UTF-8');

		$hash = password_hash($pwd, PASSWORD_DEFAULT);
		if(!$DB->insert('user', [
			'type' => 'mail',
			'openid' => $email,
			'password' => $hash,
			'nickname' => $nickname,
			'faceimg' => '',
			'enable' => 1,
			'regip' => $clientip,
			'loginip' => $clientip,
			'addtime' => 'NOW()',
			'lasttime' => 'NOW()',
		])){
			//唯一索引挡住并发重复注册时也会走到这里
			exit(json_encode(['code'=>-1, 'msg'=>'注册失败，该邮箱可能已被注册']));
		}
		$uid = $DB->lastInsertId();
		user_login_session($uid, ['type'=>'mail', 'openid'=>$email, 'password'=>$hash]);
		exit('{"code":0,"msg":"注册成功"}');
	}

	//act=maillogin
	if(empty($conf['userlogin']))exit('{"code":-1,"msg":"未开启登录"}');
	$pwd = isset($_POST['password']) ? (string)$_POST['password'] : '';
	//同一个邮箱连续输错就锁一会儿，防止拿字典硬撞。记在临时文件里，
	//清 cookie 换 session 也绕不开（快捷登录接口的失败冷却也是这么做的）
	$lock_file = sys_get_temp_dir().'/pan_loginfail_'.substr(md5(SYS_KEY.'|'.$email), 0, 24);
	$fail = @json_decode(@file_get_contents($lock_file), true);
	if(is_array($fail) && intval($fail['count']) >= 5 && intval($fail['time']) + 900 > time()){
		$wait = ceil((intval($fail['time']) + 900 - time()) / 60);
		exit(json_encode(['code'=>-1, 'msg'=>'密码错误次数过多，请 '.$wait.' 分钟后再试']));
	}

	$row = $DB->getRow("SELECT * FROM pre_user WHERE type='mail' AND openid=:e LIMIT 1", [':e'=>$email]);
	if(!$row || empty($row['password']) || !password_verify($pwd, $row['password'])){
		$count = (is_array($fail) && intval($fail['time']) + 900 > time()) ? intval($fail['count']) + 1 : 1;
		@file_put_contents($lock_file, json_encode(['count'=>$count, 'time'=>time()]));
		//不区分"邮箱不存在"和"密码错误"，避免被用来枚举站点里有哪些邮箱
		exit('{"code":-1,"msg":"邮箱或密码不正确"}');
	}
	if(intval($row['enable']) === 0){
		$_SESSION['user_block'] = true;
		exit('{"code":-1,"msg":"当前账号已被禁止登录"}');
	}
	@unlink($lock_file);
	$DB->update('user', ['loginip'=>$clientip, 'lasttime'=>'NOW()'], ['uid'=>$row['uid']]);
	user_login_session($row['uid'], $row);
	exit('{"code":0,"msg":"登录成功"}');

}elseif($_GET['code'] && $_GET['type'] && $_GET['state']){
	if($_GET['state'] != $_SESSION['Oauth_state']){
		sysmsg("<h2>The state does not match. You may be a victim of CSRF.</h2>");
	}
	$type = $_GET['type'];
    $typename = $type=='wx'?'微信':'QQ';
	$Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
	$arr = $Oauth->callback();
	if(isset($arr['code']) && $arr['code']==0){
		$openid=$arr['social_uid'];
		$access_token=$arr['access_token'];
		$nickname=trim($arr['nickname']);
        if(empty($nickname) || $nickname=='-') $nickname = $typename.'用户';
		$faceimg=$arr['faceimg'];
	}elseif(isset($arr['code'])){
		sysmsg('<h3>error:</h3>'.$arr['errcode'].'<h3>msg  :</h3>'.$arr['msg']);
	}else{
		sysmsg('获取登录数据失败');
	}

    $userrow=$DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');
	if(!$userrow){
        if(!$DB->insert('user', [
            'type' => $type,
            'openid' => $openid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
            'enable' => 1,
            'regip' => $clientip,
            'loginip' => $clientip,
            'addtime' => 'NOW()',
            'lasttime' => 'NOW()',
        ]))sysmsg('用户注册失败 '.$DB->error());
        $uid = $DB->lastInsertId();
        unset($_SESSION['user_block']);
	}else{
        if($userrow['enable']==0){
            $_SESSION['user_block'] = true;
            sysmsg('当前用户已被禁止登录');
        }
        unset($_SESSION['user_block']);
        $uid = $userrow['uid'];
        $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
    }
    //签发登录态、归属游客上传的文件，这段和邮箱登录共用一份实现
    user_login_session($uid, ['type'=>$type, 'openid'=>$openid, 'password'=>'']);
    ob_clean();
    exit("<script language='javascript'>window.location.href='./';</script>");
}

//下发会话令牌，登录/注册接口都要带上它（用独立的键，不和上传页的 csrf_token 互相覆盖）
$mail_token = md5(uniqid('', true).mt_rand());
$_SESSION['mail_token'] = $mail_token;
$captcha_question = is_captcha_open() ? make_captcha() : '';

$title = '用户登录 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
?>
<?php
//快捷登录：配了接口并且至少开了一个平台
$has_oauth = !empty($conf['login_apiurl']) && (!empty($conf['login_qq']) || !empty($conf['login_wx']));
//邮箱登录入口：开着注册就一定显示；关掉注册后，只要站里已经有邮箱账号也要留着入口，
//否则老用户会登不进来。这个统计缓存 5 分钟，不用每次打开登录页都查一遍
$has_mail_user = false;
if(!is_mail_reg_open() && !empty($conf['userlogin'])){
	if(isset($_SESSION['has_mail_user']) && isset($_SESSION['has_mail_user_time']) && $_SESSION['has_mail_user_time'] + 300 > time()){
		$has_mail_user = $_SESSION['has_mail_user'];
	}else{
		$has_mail_user = $DB->getColumn("SELECT uid FROM pre_user WHERE type='mail' LIMIT 1") ? true : false;
		$_SESSION['has_mail_user'] = $has_mail_user;
		$_SESSION['has_mail_user_time'] = time();
	}
}
$show_mail = is_mail_reg_open() || $has_mail_user;
$show_reg = is_mail_reg_open();
?>
<div class="container">
<div class="col-xs-10 col-sm-8 col-md-6 col-lg-5 center-block" style="float: none;">
    <div class="well bs-component loginpanel">
<?php if(!$has_oauth && !$show_mail){?>
        <div class="text-center">
            <h4 class="login-title">暂时无法登录</h4>
            <p class="text-muted">站长还没有配置任何登录方式，请稍后再来。</p>
        </div>
<?php }else{?>
        <h4 class="login-title"><?php echo $show_reg ? '登录 / 注册' : '登录'?></h4>

<?php if($show_mail){?>
        <ul class="nav nav-tabs login-tabs" role="tablist">
            <li class="active"><a href="#tab-mail" data-toggle="tab" role="tab">邮箱登录</a></li>
<?php if($show_reg){?>
            <li><a href="#tab-reg" data-toggle="tab" role="tab">注册</a></li>
<?php }?>
        </ul>
        <div class="tab-content">
            <div class="tab-pane active" id="tab-mail">
                <form class="loginform" onsubmit="return mailLogin()">
                    <div class="form-group"><input type="email" id="login_email" class="form-control" placeholder="邮箱地址" autocomplete="username" required/></div>
                    <div class="form-group"><input type="password" id="login_pwd" class="form-control" placeholder="密码" autocomplete="current-password" required/></div>
                    <button type="submit" class="btn btn-primary btn-block loginsubmit">登录</button>
                </form>
            </div>
<?php if($show_reg){?>
            <div class="tab-pane" id="tab-reg">
                <form class="loginform" onsubmit="return doRegister()">
                    <div class="form-group"><input type="email" id="reg_email" class="form-control" placeholder="邮箱地址" autocomplete="username" required/></div>
<?php if($captcha_question !== ''){?>
                    <div class="form-group field-wrap has-prefix">
                        <button type="button" class="field-prefix" onclick="refreshCaptcha()" title="点一下换一题"><span id="captchaQ"><?php echo htmlspecialchars($captcha_question, ENT_QUOTES, 'UTF-8')?></span><i class="fa fa-refresh" aria-hidden="true"></i></button>
                        <input type="text" id="reg_captcha" class="form-control" placeholder="答案" inputmode="numeric" maxlength="3" autocomplete="off" required/>
                    </div>
<?php }?>
                    <div class="form-group field-wrap has-suffix">
                        <input type="text" id="reg_code" class="form-control" placeholder="邮箱验证码" inputmode="numeric" maxlength="6" autocomplete="off" required/>
                        <button type="button" class="field-suffix" id="sendCodeBtn" onclick="sendCode()">获取验证码</button>
                    </div>
                    <div class="form-group"><input type="password" id="reg_pwd" class="form-control" placeholder="设置密码（6-32 位，含字母和数字）" autocomplete="new-password" required/></div>
                    <div class="form-group"><input type="text" id="reg_nick" class="form-control" placeholder="昵称（选填，默认用邮箱前缀）" maxlength="20" autocomplete="off"/></div>
                    <button type="submit" class="btn btn-primary btn-block loginsubmit">注册并登录</button>
                </form>
            </div>
<?php }?>
        </div>
<?php }?>

<?php if($has_oauth){?>
        <?php //快捷登录不再占一个标签页：不管在登录还是注册标签下都能直接点，少一次切换?>
        <div class="login-other">
<?php if($show_mail){?>
            <div class="login-divider"><span>其他方式登录</span></div>
<?php }else{?>
            <p class="text-muted text-center" style="margin-bottom:14px">请选择登录方式</p>
<?php }?>
            <p id="loginform" class="text-center">
                <?php if($conf['login_qq']){?><a href="javascript:connect('qq')" class="btn btn-info btn-fab loginbtn" title="QQ 登录"><i class="fa fa-qq"></i></a><?php }?>
                <?php if($conf['login_wx']){?><a href="javascript:connect('wx')" class="btn btn-success btn-fab loginbtn" title="微信登录"><i class="fa fa-wechat"></i></a><?php }?>
            </p>
            <p class="text-muted text-center login-tip">新用户快捷登录后会自动注册账号</p>
        </div>
<?php }?>
<?php }?>
    </div>
</div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script>
//邮箱登录/注册的三个接口。Accept 必须显式带上：
//txprotect.php 会把「手机 UA + Accept 恰好是 */*」的请求当机器人拦掉
var mailToken = '<?php echo $mail_token?>';
function mailPost(act, data, done){
	data.token = mailToken;
	$.ajax({
		type : 'POST',
		url : 'login.php?act=' + act,
		data : data,
		dataType : 'json',
		headers : {'Accept': 'application/json, text/javascript, */*; q=0.01'},
		timeout : 60000,
		success : function(res){
			//服务端每次发码都会换令牌，这里跟着更新，否则下一次请求会被判成失效
			if(res && res.token)mailToken = res.token;
			//算术题不管对错都会换一道，跟着更新题面
			if(res && res.question)setCaptcha(res.question);
			done(res);
		},
		error : function(xhr, status){
			done({code:-1, msg: status === 'timeout' ? '请求超时，请稍后重试' : '请求失败，请稍后重试'});
		}
	});
}

//算术题：答对一次就作废，所以每次请求后都要换题面、清输入框
function setCaptcha(q){
	$('#captchaQ').text(q);
	$('#reg_captcha').val('');
}
function refreshCaptcha(){
	$.ajax({
		type : 'POST',
		url : 'login.php?act=captcha',
		dataType : 'json',
		headers : {'Accept': 'application/json, text/javascript, */*; q=0.01'},
		success : function(res){ if(res && res.question)setCaptcha(res.question); }
	});
}

var codeTimer = null;
function sendCode(){
	var email = $('#reg_email').val();
	if(!email){ layer.msg('请先填写邮箱地址'); return; }
	if($('#captchaQ').length && !$('#reg_captcha').val()){ layer.msg('请先算出上面那道题'); return; }
	var btn = $('#sendCodeBtn');
	if(btn.prop('disabled')) return;
	btn.prop('disabled', true).text('发送中…');
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	mailPost('sendcode', {email:email, captcha:$('#reg_captcha').val()}, function(res){
		layer.close(ii);
		if(res.code !== 0){
			btn.prop('disabled', false).text('获取验证码');
			layer.alert(res.msg || '发送失败', {icon:2});
			return;
		}
		layer.msg(res.msg || '验证码已发送');
		//倒计时期间不让重复点，服务端也有同样的间隔限制
		var left = 60;
		btn.text(left + ' 秒后重发');
		codeTimer = setInterval(function(){
			left--;
			if(left <= 0){
				clearInterval(codeTimer);
				btn.prop('disabled', false).text('重新获取');
			}else{
				btn.text(left + ' 秒后重发');
			}
		}, 1000);
	});
}

function doRegister(){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	mailPost('register', {
		email: $('#reg_email').val(),
		code: $('#reg_code').val(),
		password: $('#reg_pwd').val(),
		nickname: $('#reg_nick').val()
	}, function(res){
		layer.close(ii);
		if(res.code !== 0){ layer.alert(res.msg || '注册失败', {icon:2}); return; }
		layer.msg('注册成功，正在进入…');
		setTimeout(function(){ window.location.href = './'; }, 800);
	});
	return false;
}

function mailLogin(){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	mailPost('maillogin', {email: $('#login_email').val(), password: $('#login_pwd').val()}, function(res){
		layer.close(ii);
		if(res.code !== 0){ layer.alert(res.msg || '登录失败', {icon:2}); return; }
		window.location.href = './';
	});
	return false;
}

var connecting = false;
function connect(type){
    //快捷登录接口要请求上游服务，慢或超时都可能发生；没有超时和失败处理会一直转圈，
    //用户反复点击还会堆积请求把站点拖慢，这里加锁 + 超时 + 失败提示
    if(connecting) return;
    connecting = true;
    var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : "POST",
		url : "login.php?act=connect",
		data : {type:type},
		dataType : 'json',
		timeout : 20000,
		success : function(data) {
			layer.close(ii);
			if(data && data.code == 0){
				window.location.href = data.url;
			}else{
				connecting = false;
				layer.alert((data && data.msg) ? data.msg : '登录接口返回异常，请稍后重试', {icon: 7});
			}
		},
		error : function(xhr, status) {
			layer.close(ii);
			connecting = false;
			layer.alert(status === 'timeout' ? '登录接口响应超时，请稍后重试' : '登录接口请求失败，请稍后重试', {icon: 2});
		}
	});
}
</script>
</body>
</html>
