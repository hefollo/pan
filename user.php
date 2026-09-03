<?php
/**
 * 用户个人中心。
 *
 * 原来用户只有 index.php?m=mine 一个入口，那是首页文件列表复用了同一套模板，
 * 除了下载/查看/编辑/覆盖之外没有任何管理能力，账号资料、额度、订单也全都看不到。
 * 这里集中成四个页签：概览 / 我的文件 / 订单记录 / 账号设置。
 *
 * 页面本身和下面的 act 接口都只对已登录用户开放。游客的 ?m=mine 走的是
 * $_SESSION['fileids'] 那套浏览器缓存记录，跟账号无关，仍然留在 index.php 里。
 */
include("./includes/common.php");

if($conf['userlogin'] != 1){
	sysmsg('本站未开放用户登录功能。');
}
if(!$islogin2){
	header('Location: ./login.php');
	exit;
}

include_once SYSTEM_ROOT.'layout_blocks.php';

$uid = intval($uid);

/* ---------------- 下面是 act=xxx 的 JSON 接口 ---------------- */

function uc_json($code, $msg, $extra = []){
	@header('Content-Type: application/json; charset=UTF-8');
	exit(json_encode(array_merge(['code'=>$code, 'msg'=>$msg], $extra), JSON_UNESCAPED_UNICODE));
}

/*
 * 取一条属于当前用户的文件记录。
 * 这里不用 can_manage_file()：那个函数对游客还认 $_SESSION['fileids']，
 * 而个人中心的所有写操作都必须严格限定在"登录用户本人的文件"上。
 */
function uc_own_file($id){
	global $DB, $uid;
	$id = intval($id);
	if($id <= 0) uc_json(-1, '参数错误');
	$row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$id]);
	if(!$row) uc_json(-1, '文件不存在');
	if(intval($row['uid']) !== $uid) uc_json(-1, '无权限操作该文件');
	return $row;
}

/*
 * 文件名清洗，规则必须和 ajax.php 的 pre_upload 完全一致：
 * 先 htmlspecialchars（带 ENT_QUOTES，文件名会被拼进播放器的 JS 字符串），
 * 再去掉路径分隔符和 Windows 非法字符。库里存的就是转义后的形式，
 * 列表页是直接 echo 出来的，这里要是漏了同样的处理就成了存储型 XSS。
 */
function uc_clean_filename($name){
	$name = trim(htmlspecialchars((string)$name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	$name = str_replace(['/','\\',':','*','"','<','>','|','?'], '', $name);
	//控制字符在下载时会被拼进 Content-Disposition 头，必须清掉
	$name = preg_replace('/[\x00-\x1f\x7f]/', '', $name);
	return trim($name);
}

$act = isset($_GET['act']) ? $_GET['act'] : '';
if($act !== ''){
	if(!checkRefererHost()) uc_json(-1, '来源校验失败');
	if(!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
		uc_json(-1, 'CSRF TOKEN ERROR');
	}

	switch($act){

	//批量删除（单个删除也走这里，就是只传一个 id）
	case 'deleteFiles':
		$ids = isset($_POST['ids']) ? $_POST['ids'] : [];
		if(!is_array($ids)) $ids = explode(',', (string)$ids);
		$ids = array_slice(array_unique(array_filter(array_map('intval', $ids))), 0, 100);
		if(!$ids) uc_json(-1, '请先选择要删除的文件');

		$ok = 0; $fail = 0; $blocked = 0;
		foreach($ids as $id){
			$row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$id]);
			//这里不能用 uc_own_file()：它遇到问题会直接 exit，批量删到一半就断了
			if(!$row || intval($row['uid']) !== $uid){ $fail++; continue; }
			//被后台冻结的文件不许用户自己删掉，得留着给管理员查
			if(intval($row['block']) === 1){ $blocked++; continue; }
			//同一份内容可能被多条记录共享（秒传），只有最后一条引用被删时才清理物理文件
			delete_file_blob_if_orphaned($row['hash'], $row['id']);
			if($DB->exec("DELETE FROM pre_file WHERE id=:id AND uid=:uid", [':id'=>$row['id'], ':uid'=>$uid])) $ok++;
			else $fail++;
		}
		$msg = '已删除 '.$ok.' 个文件';
		if($blocked) $msg .= '，'.$blocked.' 个已被冻结跳过';
		if($fail) $msg .= '，'.$fail.' 个失败';
		uc_json($ok > 0 ? 0 : -1, $msg, ['ok'=>$ok, 'blocked'=>$blocked, 'fail'=>$fail]);
	break;

	//公开 / 私密：hide=1 的文件不出现在首页公共列表里，但外链本身照样能访问
	case 'setHide':
		$row = uc_own_file(isset($_POST['id']) ? $_POST['id'] : 0);
		$hide = (isset($_POST['hide']) && $_POST['hide'] == 1) ? 1 : 0;
		if(!$DB->exec("UPDATE pre_file SET hide=:hide WHERE id=:id AND uid=:uid",
			[':hide'=>$hide, ':id'=>$row['id'], ':uid'=>$uid])){
			uc_json(-1, '修改失败['.$DB->error().']');
		}
		uc_json(0, $hide ? '已设为私密，不再显示在首页列表' : '已设为公开', ['hide'=>$hide]);
	break;

	//设置 / 清除访问密码。留空即清除
	case 'setPwd':
		$row = uc_own_file(isset($_POST['id']) ? $_POST['id'] : 0);
		$pwd = trim(isset($_POST['pwd']) ? (string)$_POST['pwd'] : '');
		if($pwd === ''){
			if(!$DB->exec("UPDATE pre_file SET pwd=NULL WHERE id=:id AND uid=:uid", [':id'=>$row['id'], ':uid'=>$uid])){
				uc_json(-1, '修改失败['.$DB->error().']');
			}
			uc_json(0, '已取消该文件的访问密码', ['haspwd'=>0]);
		}
		//和上传时同一套规则：只允许字母数字，避免密码里出现需要转义的字符
		if(!preg_match('/^[a-zA-Z0-9]{1,32}$/', $pwd)) uc_json(-1, '文件密码只能是 1-32 位字母或数字');
		if(!$DB->exec("UPDATE pre_file SET pwd=:pwd WHERE id=:id AND uid=:uid",
			[':pwd'=>$pwd, ':id'=>$row['id'], ':uid'=>$uid])){
			uc_json(-1, '修改失败['.$DB->error().']');
		}
		uc_json(0, '访问密码已设置', ['haspwd'=>1]);
	break;

	//重命名。只改显示用的文件名，token 和外链地址都不动
	case 'rename':
		$row = uc_own_file(isset($_POST['id']) ? $_POST['id'] : 0);
		if(intval($row['block']) === 1) uc_json(-1, '文件已被冻结，无法重命名');
		$name = uc_clean_filename(isset($_POST['name']) ? $_POST['name'] : '');
		if($name === '') uc_json(-1, '文件名不能为空');
		if(mb_strlen($name, 'UTF-8') > 120) uc_json(-1, '文件名不能超过 120 个字');
		//后台配置的违禁文件名同样要拦，不然改个名就绕过去了
		if(!empty($conf['name_block'])){
			foreach(explode('|', $conf['name_block']) as $bad){
				if($bad !== '' && strpos($name, $bad) !== false) uc_json(-1, '文件名包含不允许的内容');
			}
		}
		//扩展名跟着原文件的 type 走：外链是 down.php/{token}.{type}，
		//让用户把 a.png 改成 a.jpg 只会造成"下载下来打不开"的困惑
		$ext = $row['type'] ? strtolower($row['type']) : '';
		if($ext !== ''){
			$cur = strtolower(get_file_ext($name));
			if($cur !== $ext) $name = preg_replace('/\.[^.]*$/', '', $name).'.'.$ext;
		}
		if(!$DB->exec("UPDATE pre_file SET name=:name WHERE id=:id AND uid=:uid",
			[':name'=>$name, ':id'=>$row['id'], ':uid'=>$uid])){
			uc_json(-1, '重命名失败['.$DB->error().']');
		}
		uc_json(0, '重命名成功', ['name'=>$name]);
	break;

	//修改昵称
	case 'profile':
		$nickname = trim(htmlspecialchars(isset($_POST['nickname']) ? $_POST['nickname'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
		if($nickname === '') uc_json(-1, '昵称不能为空');
		if(mb_strlen($nickname, 'UTF-8') > 20) uc_json(-1, '昵称不能超过 20 个字');
		if(!$DB->exec("UPDATE pre_user SET nickname=:n WHERE uid=:uid", [':n'=>$nickname, ':uid'=>$uid])){
			uc_json(-1, '保存失败['.$DB->error().']');
		}
		uc_json(0, '昵称已更新', ['nickname'=>$nickname]);
	break;

	//修改密码。判断条件是"设过密码没有"而不是"是不是邮箱账号"：
	//快捷登录的账号绑定邮箱时也会设密码，之后同样要能改
	case 'chpwd':
		if(empty($userrow['password'])) uc_json(-1, '当前账号还没有设置密码，请先绑定邮箱');
		$old = isset($_POST['oldpwd']) ? (string)$_POST['oldpwd'] : '';
		$new = isset($_POST['newpwd']) ? (string)$_POST['newpwd'] : '';
		if($old === '' || $new === '') uc_json(-1, '请填写原密码和新密码');
		if(empty($userrow['password']) || !password_verify($old, $userrow['password'])) uc_json(-1, '原密码不正确');
		$err = check_password_rule($new);
		if($err !== '') uc_json(-1, $err);
		if($old === $new) uc_json(-1, '新密码不能和原密码相同');

		$hash = password_hash($new, PASSWORD_DEFAULT);
		if(!$DB->exec("UPDATE pre_user SET password=:p WHERE uid=:uid", [':p'=>$hash, ':uid'=>$uid])){
			uc_json(-1, '保存失败['.$DB->error().']');
		}
		/*
		 * user_session_hash() 把密码哈希也算了进去，改完密码所有旧 cookie 立刻失效——
		 * 包括当前这台设备的。这里拿改完之后的记录重新签一次，本机保持登录，
		 * 其它设备该掉线还是掉线，正是我们想要的效果。
		 */
		$userrow['password'] = $hash;
		user_login_session($uid, $userrow);
		uc_json(0, '密码修改成功，其它设备上的登录已失效');
	break;

	//绑定邮箱前先发验证码。走和注册同一套发信与限流，purpose 用 bindmail 区分开
	case 'sendbindcode':
		if(user_has_login_type($userrow, 'mail')) uc_json(-1, '当前账号已经有邮箱了');
		if(!is_mail_ready()) uc_json(-1, '站点还没有配置发信，暂时无法绑定邮箱');
		$email = normalize_email(isset($_POST['email']) ? $_POST['email'] : '');
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) uc_json(-1, '邮箱格式不正确');
		if(mail_domain_denied($email)) uc_json(-1, '不支持这个邮箱域名，请换一个');
		//这一步就挡掉已被占用的邮箱，别等用户收完验证码填完密码才告诉他不行
		if(identity_owner_uid('mail', $email)) uc_json(-1, '该邮箱已经被其它账号使用');
		$res = send_mail_code($email, 'bindmail', $uid);
		uc_json($res['code'] === 0 ? 0 : -1, $res['msg']);
	break;

	//绑定邮箱 + 设置密码，一步完成
	case 'bindmail':
		if(user_has_login_type($userrow, 'mail')) uc_json(-1, '当前账号已经有邮箱了');
		$email = normalize_email(isset($_POST['email']) ? $_POST['email'] : '');
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) uc_json(-1, '邮箱格式不正确');
		$pwd = isset($_POST['password']) ? (string)$_POST['password'] : '';
		$err = check_password_rule($pwd);
		if($err !== '') uc_json(-1, $err);
		$check = verify_mail_code($email, isset($_POST['code']) ? $_POST['code'] : '', 'bindmail');
		if($check['code'] !== 0) uc_json(-1, $check['msg']);
		//验证码是几分钟前发的，这中间可能已经被别人抢注，落库前再查一次
		if(identity_owner_uid('mail', $email)) uc_json(-1, '该邮箱已经被其它账号使用');

		$hash = password_hash($pwd, PASSWORD_DEFAULT);
		if(!$DB->insert('user_bind', ['uid'=>$uid, 'type'=>'mail', 'openid'=>$email, 'addtime'=>'NOW()'])){
			uc_json(-1, '绑定失败['.$DB->error().']');
		}
		if(!$DB->exec("UPDATE pre_user SET password=:p WHERE uid=:uid", [':p'=>$hash, ':uid'=>$uid])){
			//密码没写进去的话这条绑定就是个只能登不能验的半成品，回滚掉
			$DB->exec("DELETE FROM pre_user_bind WHERE uid=:uid AND type='mail'", [':uid'=>$uid]);
			uc_json(-1, '设置密码失败['.$DB->error().']');
		}
		/*
		 * user_session_hash() 把密码算进去了，刚设完密码本机的 cookie 也会失效，
		 * 拿更新后的记录重签一次，保持当前设备登录（和改密码那支一个道理）
		 */
		$userrow['password'] = $hash;
		user_login_session($uid, $userrow);
		uc_json(0, '邮箱绑定成功，以后可以用邮箱和密码登录');
	break;

	//解绑。只动 pre_user_bind，主身份（注册时用的那种方式）永远解不掉，
	//"至少留一种登录方式"这条约束因此天然成立
	case 'unbind':
		$type = isset($_POST['type']) ? (string)$_POST['type'] : '';
		if(!in_array($type, ['qq', 'wx', 'mail'], true)) uc_json(-1, '参数错误');
		if($userrow['type'] === $type) uc_json(-1, '这是注册时使用的登录方式，不能解绑');
		$bind = $DB->getRow("SELECT * FROM pre_user_bind WHERE uid=:uid AND type=:t LIMIT 1",
			[':uid'=>$uid, ':t'=>$type]);
		if(!$bind) uc_json(-1, '没有绑定过'.login_type_name($type));
		if(!$DB->exec("DELETE FROM pre_user_bind WHERE id=:id AND uid=:uid", [':id'=>$bind['id'], ':uid'=>$uid])){
			uc_json(-1, '解绑失败['.$DB->error().']');
		}
		if($type === 'mail'){
			//邮箱解绑后密码没有任何入口能用到了，一起清掉，免得留个僵尸凭据
			$DB->exec("UPDATE pre_user SET password='' WHERE uid=:uid", [':uid'=>$uid]);
			$userrow['password'] = '';
			user_login_session($uid, $userrow);
		}
		uc_json(0, login_type_name($type).'已解绑');
	break;

	default:
		uc_json(-1, '未知操作');
	break;
	}
}

/* ---------------- 页面渲染 ---------------- */

$csrf_token = bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;

$tabs = [
	'overview' => ['概览', 'fa-dashboard'],
	'files'    => ['我的文件', 'fa-folder-open'],
	'orders'   => ['订单记录', 'fa-shopping-cart'],
	'account'  => ['账号设置', 'fa-user-circle'],
];
//购买功能没开的话就不显示订单页签，免得点进去是一片空白
if(!function_exists('is_buy_open') || !is_buy_open()) unset($tabs['orders']);

$tab = (isset($_GET['tab']) && isset($tabs[$_GET['tab']])) ? $_GET['tab'] : 'overview';

$title = '个人中心 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
?>
<div class="container">
    <div class="well bs-component usercenter">
        <h2>个人中心</h2>
        <div class="uc-head">
            <span class="uc-avatar"><?php
            if(!empty($userrow['faceimg'])){
                echo '<img src="'.htmlspecialchars($userrow['faceimg'], ENT_QUOTES, 'UTF-8').'" alt="">';
            }else{
                echo '<i class="fa fa-'.($userrow['type']=='qq'?'qq':($userrow['type']=='mail'?'envelope':'wechat')).'" aria-hidden="true"></i>';
            }
            ?></span>
            <div class="uc-head-main">
                <strong id="ucNickname"><?php echo $userrow['nickname']?></strong>
                <small><?php
                echo $userrow['type']=='mail' ? htmlspecialchars($userrow['openid'], ENT_QUOTES, 'UTF-8') : ($userrow['type']=='qq' ? 'QQ 快捷登录' : '微信快捷登录');
                ?> · UID <?php echo $uid?></small>
            </div>
<?php
$uc_active = is_user_permission_active();
$uc_expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
if(empty($uc_expire)){
    $uc_state = '永久有效'; $uc_state_cls = 'ok';
}elseif(!$uc_active){
    $uc_state = '权限已过期'; $uc_state_cls = 'expired';
}else{
    $uc_left = max(1, ceil((strtotime($uc_expire) - time()) / 86400));
    $uc_state = '剩 '.$uc_left.' 天'; $uc_state_cls = $uc_left <= 7 ? 'warn' : 'ok';
}
?>
            <span class="uc-tag uc-tag-<?php echo $uc_state_cls?>"><?php echo $uc_state?></span>
        </div>

        <div class="uc-tabs">
<?php foreach($tabs as $key => $t){?>
            <a class="uc-tab<?php echo $tab === $key ? ' active' : ''?>" href="./user.php?tab=<?php echo $key?>"><i class="fa <?php echo $t[1]?>" aria-hidden="true"></i> <?php echo $t[0]?></a>
<?php }?>
        </div>

<?php
/* ===================== 概览 ===================== */
if($tab === 'overview'){
    $uc_limit = get_effective_upload_count_limit();
    $uc_size  = get_effective_upload_size_limit();
    $uc_today = layout_today_upload_count($DB);
    //uid 上有索引，一次把总数和总占用查出来
    $uc_stat = $DB->getRow("SELECT count(*) AS num, COALESCE(SUM(size),0) AS bytes FROM pre_file WHERE uid=:uid", [':uid'=>$uid]);
    $uc_num = $uc_stat ? intval($uc_stat['num']) : 0;
    $uc_bytes = $uc_stat ? floatval($uc_stat['bytes']) : 0;
    $uc_plan = layout_user_plan($DB);
?>
        <div class="uc-cards">
            <div class="uc-card"><span class="uc-card-icon"><i class="fa fa-files-o" aria-hidden="true"></i></span>
                <div><strong><?php echo number_format($uc_num)?></strong><span>我的文件</span></div></div>
            <div class="uc-card"><span class="uc-card-icon"><i class="fa fa-database" aria-hidden="true"></i></span>
                <div><strong><?php echo size_format($uc_bytes)?></strong><span>占用空间</span></div></div>
            <div class="uc-card"><span class="uc-card-icon"><i class="fa fa-cloud-upload" aria-hidden="true"></i></span>
                <div><strong><?php echo $uc_limit > 0 ? $uc_today.' / '.$uc_limit : $uc_today?></strong><span>今日上传<?php echo $uc_limit > 0 ? '' : '（不限）'?></span></div></div>
            <div class="uc-card"><span class="uc-card-icon"><i class="fa fa-file-o" aria-hidden="true"></i></span>
                <div><strong><?php echo limit_number_text($uc_size, 'MB')?></strong><span>单文件上限</span></div></div>
        </div>

        <div class="uc-section">
            <div class="uc-section-title"><span>权限与额度</span></div>
            <dl class="uc-kv">
                <div><dt>当前套餐</dt><dd><?php echo ($uc_plan && $uc_plan['bought']) ? htmlspecialchars($uc_plan['plan_name'], ENT_QUOTES, 'UTF-8') : '未购买（使用站点默认额度）'?></dd></div>
                <div><dt>每日上传</dt><dd><?php echo limit_number_text($uc_limit, '个/天')?><?php if(!empty($userrow['bonus_limit']) && $uc_active && $uc_limit > 0){?>（含加量包 +<?php echo intval($userrow['bonus_limit'])?>）<?php }?></dd></div>
                <div><dt>单文件大小</dt><dd><?php echo limit_number_text($uc_size, 'MB')?></dd></div>
                <div><dt>有效期</dt><dd><?php echo empty($uc_expire) ? '永久有效' : htmlspecialchars($uc_expire, ENT_QUOTES, 'UTF-8').($uc_active ? ' 到期' : ' 已过期')?></dd></div>
            </dl>
<?php if(function_exists('is_buy_open') && is_buy_open()){?>
            <a class="uc-btn uc-btn-primary" href="./buy.php"><i class="fa fa-shopping-cart" aria-hidden="true"></i> <?php echo ($uc_plan && $uc_plan['bought']) ? '续费 / 升级权限' : '购买权限'?></a>
<?php }?>
        </div>

<?php
    //按类型分布：GROUP BY type 走 uid 索引后数据量很小，直接查
    $uc_groups = ['image'=>0, 'video'=>0, 'audio'=>0, 'doc'=>0, 'archive'=>0, 'other'=>0];
    $uc_rs = $DB->query("SELECT type, count(*) AS num FROM pre_file WHERE uid=".$uid." GROUP BY type");
    if($uc_rs){
        while($r = $uc_rs->fetch()){
            $g = layout_type_group($r['type']);
            if(!isset($uc_groups[$g])) $g = 'other';
            $uc_groups[$g] += intval($r['num']);
        }
    }
    $uc_labels = ['image'=>'图片', 'video'=>'视频', 'audio'=>'音频', 'doc'=>'文档', 'archive'=>'压缩包', 'other'=>'其它'];
    if($uc_num > 0){
?>
        <div class="uc-section">
            <div class="uc-section-title"><span>文件类型分布</span></div>
            <div class="uc-bars">
<?php foreach($uc_groups as $g => $n){ if($n <= 0) continue; ?>
                <div class="uc-bar-row">
                    <em><?php echo $uc_labels[$g]?></em>
                    <span class="uc-bar"><i class="uc-bar-<?php echo $g?>" style="width:<?php echo max(2, round($n / $uc_num * 100))?>%"></i></span>
                    <b><?php echo $n?></b>
                </div>
<?php }?>
            </div>
        </div>
<?php }?>

<?php
/* ===================== 我的文件 ===================== */
}elseif($tab === 'files'){
    $uc_kw = (isset($_GET['kw']) && is_string($_GET['kw'])) ? trim(strip_tags($_GET['kw'])) : '';
    $uc_ft = (isset($_GET['ft']) && is_string($_GET['ft']) && array_key_exists($_GET['ft'], layout_type_filters())) ? $_GET['ft'] : '';

    $where = " uid=".$uid;
    $qs = 'tab=files';
    if($uc_kw !== ''){
        $where .= " AND name LIKE '%".daddslashes($uc_kw)."%'";
        $qs .= '&kw='.urlencode($uc_kw);
    }
    $where_base = $where;
    if($uc_ft !== ''){
        $where .= layout_type_filter_sql($uc_ft);
        $qs .= '&ft='.urlencode($uc_ft);
    }

    //筛选标签上的计数：只统计当前用户，数据量小，不用像首页那样走缓存
    $uc_counts = ['' => 0, 'image'=>0, 'video'=>0, 'doc'=>0, 'archive'=>0];
    $uc_crs = $DB->query("SELECT type, count(*) AS num FROM pre_file WHERE{$where_base} GROUP BY type");
    if($uc_crs){
        while($r = $uc_crs->fetch()){
            $n = intval($r['num']);
            $uc_counts[''] += $n;
            $g = layout_type_group($r['type']);
            if(isset($uc_counts[$g])) $uc_counts[$g] += $n;
        }
    }

    $numrows = intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE{$where}"));
    $pagesize = 20;
    $pages = max(1, ceil($numrows / $pagesize));
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    if($page > $pages) $page = $pages;
    $offset = $pagesize * ($page - 1);
?>
        <form class="uc-toolbar" method="get" action="./user.php">
            <input type="hidden" name="tab" value="files">
<?php if($uc_ft !== ''){?><input type="hidden" name="ft" value="<?php echo htmlspecialchars($uc_ft, ENT_QUOTES, 'UTF-8')?>"><?php }?>
            <input class="uc-search" type="search" name="kw" value="<?php echo htmlspecialchars($uc_kw, ENT_QUOTES, 'UTF-8')?>" placeholder="搜索我的文件名">
            <button class="uc-btn" type="submit"><i class="fa fa-search" aria-hidden="true"></i> 搜索</button>
<?php if($uc_kw !== ''){?><a class="uc-btn" href="./user.php?tab=files">清除</a><?php }?>
            <a class="uc-btn uc-btn-primary" href="./upload.php"><i class="fa fa-plus" aria-hidden="true"></i> 上传文件</a>
        </form>

        <div class="uc-filters">
<?php foreach(layout_type_filters() as $fk => $flabel){
        $fq = 'tab=files';
        if($uc_kw !== '') $fq .= '&kw='.urlencode($uc_kw);
        if($fk !== '') $fq .= '&ft='.$fk;
?>
            <a class="uc-filter<?php echo $uc_ft === $fk ? ' active' : ''?>" href="./user.php?<?php echo $fq?>"><?php echo $flabel?> <em><?php echo isset($uc_counts[$fk]) ? intval($uc_counts[$fk]) : 0?></em></a>
<?php }?>
        </div>

        <p class="uc-tip uc-hint"><i class="fa fa-info-circle" aria-hidden="true"></i> 「私密」只是不在首页公共列表里出现，外链本身照样能打开；真要限制访问，请给文件设置访问密码。</p>

        <div class="uc-batchbar" id="ucBatchBar" hidden>
            <span>已选中 <b id="ucSelCount">0</b> 个文件</span>
            <button type="button" class="uc-btn uc-btn-danger" id="ucBatchDelete"><i class="fa fa-trash" aria-hidden="true"></i> 批量删除</button>
            <button type="button" class="uc-btn" id="ucSelClear">取消选择</button>
        </div>

        <div class="table-responsive">
        <table class="table table-hover uc-filelist">
            <thead>
                <tr>
                    <th class="uc-col-check"><input type="checkbox" id="ucCheckAll" title="全选本页"></th>
                    <th>文件名</th>
                    <th class="uc-col-size">大小</th>
                    <th class="uc-col-state">状态</th>
                    <th class="uc-col-time">上传时间</th>
                    <th class="uc-col-act">操作</th>
                </tr>
            </thead>
            <tbody>
<?php
    $rs = $DB->query("SELECT * FROM pre_file WHERE{$where} ORDER BY id DESC LIMIT {$offset},{$pagesize}");
    $rowcount = 0;
    while($rs && $res = $rs->fetch()){
        $rowcount++;
        $fileurl = './down.php/'.$res['token'].'.'.($res['type'] ? $res['type'] : 'file');
        $viewurl = './file.php?hash='.$res['token'];
        $blocked = intval($res['block']) === 1;
        $haspwd = !empty($res['pwd']);
        $hidden = intval($res['hide']) === 1;
?>
                <tr data-id="<?php echo intval($res['id'])?>" data-name="<?php echo $res['name']?>" data-hide="<?php echo $hidden ? 1 : 0?>" data-haspwd="<?php echo $haspwd ? 1 : 0?>" data-view="<?php echo htmlspecialchars($viewurl, ENT_QUOTES, 'UTF-8')?>" data-down="<?php echo htmlspecialchars($fileurl, ENT_QUOTES, 'UTF-8')?>"<?php echo $blocked ? ' class="is-blocked"' : ''?>>
                    <td class="uc-col-check"><input type="checkbox" class="uc-check"<?php echo $blocked ? ' disabled title="已冻结的文件不能删除"' : ''?>></td>
                    <td class="uc-col-name"><i class="fa <?php echo type_to_icon($res['type'])?> fa-fw"></i><span class="uc-name"><?php echo $res['name']?></span></td>
                    <td class="uc-col-size"><?php echo size_format($res['size'])?></td>
                    <td class="uc-col-state">
<?php if($blocked){?><span class="uc-badge uc-badge-danger">已冻结</span><?php }?>
<?php if($hidden){?><span class="uc-badge">私密</span><?php }else{?><span class="uc-badge uc-badge-ok">公开</span><?php }?>
<?php if($haspwd){?><span class="uc-badge uc-badge-warn"><i class="fa fa-lock" aria-hidden="true"></i> 有密码</span><?php }?>
                    </td>
                    <td class="uc-col-time"><?php echo $res['addtime']?></td>
                    <td class="uc-col-act">
                        <div class="uc-acts">
                            <a class="uc-act" href="<?php echo htmlspecialchars($fileurl, ENT_QUOTES, 'UTF-8')?>" title="下载"><i class="fa fa-download" aria-hidden="true"></i></a>
                            <a class="uc-act" href="<?php echo htmlspecialchars($viewurl, ENT_QUOTES, 'UTF-8')?>" title="查看"><i class="fa fa-eye" aria-hidden="true"></i></a>
                            <button type="button" class="uc-act" data-uc="copy" title="复制外链"><i class="fa fa-link" aria-hidden="true"></i></button>
<?php if(!$blocked){?>
                            <button type="button" class="uc-act" data-uc="rename" title="重命名"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                            <button type="button" class="uc-act" data-uc="pwd" title="访问密码"><i class="fa fa-key" aria-hidden="true"></i></button>
                            <button type="button" class="uc-act" data-uc="hide" title="<?php echo $hidden ? '设为公开' : '设为私密'?>"><i class="fa fa-<?php echo $hidden ? 'eye-slash' : 'globe'?>" aria-hidden="true"></i></button>
<?php if(can_edit_file_online($res)){?>
                            <a class="uc-act" href="./edit.php?id=<?php echo intval($res['id'])?>" title="在线编辑"><i class="fa fa-code" aria-hidden="true"></i></a>
<?php }?>
                            <button type="button" class="uc-act uc-act-danger" data-uc="del" title="删除"><i class="fa fa-trash" aria-hidden="true"></i></button>
<?php }?>
                        </div>
                    </td>
                </tr>
<?php }
    if($rowcount === 0){
        echo '<tr><td colspan="6" class="uc-empty">'.($uc_kw !== '' || $uc_ft !== '' ? '没有符合条件的文件' : '还没有上传过文件').'</td></tr>';
    }
?>
            </tbody>
        </table>
        </div>

        <div class="filelist-footer">
            <div class="filelist-summary">共有 <?php echo $numrows?> 个文件　当前第 <?php echo $page?> 页，共 <?php echo $pages?> 页</div>
<?php if($pages > 1){?>
            <nav class="filelist-pager"><ul class="pagination pagination-sm">
<?php
        if($page > 1){
            echo '<li><a href="./user.php?'.$qs.'&page=1">首页</a></li>';
            echo '<li><a href="./user.php?'.$qs.'&page='.($page-1).'">&laquo;</a></li>';
        }
        $start = max(1, $page - 4);
        $end = min($pages, $start + 9);
        for($i = $start; $i <= $end; $i++){
            if($i == $page) echo '<li class="disabled"><a>'.$i.'</a></li>';
            else echo '<li><a href="./user.php?'.$qs.'&page='.$i.'">'.$i.'</a></li>';
        }
        if($page < $pages){
            echo '<li><a href="./user.php?'.$qs.'&page='.($page+1).'">&raquo;</a></li>';
            echo '<li><a href="./user.php?'.$qs.'&page='.$pages.'">尾页</a></li>';
        }
?>
            </ul></nav>
<?php }?>
        </div>

<?php
/* ===================== 订单记录 ===================== */
}elseif($tab === 'orders'){
    //当面付没有回调，靠打开页面时补查漏单，和 buy.php 一个路子；60 秒内只查一次
    if(!isset($_SESSION['buy_rescue_time']) || $_SESSION['buy_rescue_time'] + 60 < time()){
        $_SESSION['buy_rescue_time'] = time();
        if(rescue_pending_orders($uid, 1) > 0){
            $userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid=:uid LIMIT 1", [':uid'=>$uid]);
        }
    }
    $o_num = intval($DB->getColumn("SELECT count(*) FROM pre_order WHERE uid=".$uid));
    $o_pagesize = 20;
    $o_pages = max(1, ceil($o_num / $o_pagesize));
    $o_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    if($o_page > $o_pages) $o_page = $o_pages;
    $o_offset = $o_pagesize * ($o_page - 1);
    $o_status = [0=>['待支付','warn'], 1=>['已支付','ok'], 2=>['已关闭','muted']];
?>
        <div class="table-responsive">
        <table class="table table-hover uc-orderlist">
            <thead><tr><th>订单号</th><th>套餐</th><th>金额</th><th>支付方式</th><th>状态</th><th>下单时间</th><th>支付时间</th></tr></thead>
            <tbody>
<?php
    $ors = $DB->query("SELECT * FROM pre_order WHERE uid=".$uid." ORDER BY id DESC LIMIT {$o_offset},{$o_pagesize}");
    $ocount = 0;
    while($ors && $o = $ors->fetch()){
        $ocount++;
        $st = isset($o_status[intval($o['status'])]) ? $o_status[intval($o['status'])] : ['未知','muted'];
?>
                <tr>
                    <td class="uc-order-no"><?php echo htmlspecialchars($o['trade_no'], ENT_QUOTES, 'UTF-8')?></td>
                    <td><?php echo htmlspecialchars($o['plan_name'], ENT_QUOTES, 'UTF-8')?></td>
                    <td class="uc-order-price">￥<?php echo htmlspecialchars($o['price'], ENT_QUOTES, 'UTF-8')?></td>
                    <td><?php echo $o['pay_type'] === 'epay' ? '易支付' : '支付宝'?></td>
                    <td><span class="uc-badge uc-badge-<?php echo $st[1]?>"><?php echo $st[0]?></span></td>
                    <td><?php echo htmlspecialchars($o['addtime'], ENT_QUOTES, 'UTF-8')?></td>
                    <td><?php echo $o['paytime'] ? htmlspecialchars($o['paytime'], ENT_QUOTES, 'UTF-8') : '—'?></td>
                </tr>
<?php }
    if($ocount === 0) echo '<tr><td colspan="7" class="uc-empty">还没有购买记录</td></tr>';
?>
            </tbody>
        </table>
        </div>
<?php if($o_pages > 1){?>
        <div class="filelist-footer">
            <div class="filelist-summary">共 <?php echo $o_num?> 笔订单　第 <?php echo $o_page?> / <?php echo $o_pages?> 页</div>
            <nav class="filelist-pager"><ul class="pagination pagination-sm">
<?php
        for($i = 1; $i <= $o_pages; $i++){
            if($i == $o_page) echo '<li class="disabled"><a>'.$i.'</a></li>';
            else echo '<li><a href="./user.php?tab=orders&page='.$i.'">'.$i.'</a></li>';
        }
?>
            </ul></nav>
        </div>
<?php }?>

<?php
/* ===================== 账号设置 ===================== */
}else{
$uc_ids = user_identities($userrow);
$uc_has = [];
foreach($uc_ids as $one) $uc_has[$one['type']] = $one;
$uc_oauth_types = bindable_login_types();
?>
        <!-- 三栏并排：容器在侧栏型外观下能有 1600px 宽，单栏铺开的话右边会空掉一大半。
             auto-fit + minmax 让它宽屏三栏、中屏两栏、窄屏一栏，不用手写断点 -->
        <div class="uc-cols">
        <div class="uc-col">
        <div class="uc-section">
            <div class="uc-section-title"><span>账号资料</span></div>
            <dl class="uc-kv">
                <div><dt>注册方式</dt><dd><?php echo login_type_name($userrow['type'])?><?php echo $userrow['type']=='mail' ? '' : ' 快捷登录'?></dd></div>
                <div><dt>注册时间</dt><dd><?php echo htmlspecialchars($userrow['addtime'], ENT_QUOTES, 'UTF-8')?></dd></div>
                <div><dt>最后登录</dt><dd><?php echo htmlspecialchars($userrow['lasttime'], ENT_QUOTES, 'UTF-8')?><?php if(!empty($userrow['loginip'])){?>　<span class="uc-dim"><?php echo htmlspecialchars(preg_replace('/\d+$/', '*', $userrow['loginip']), ENT_QUOTES, 'UTF-8')?></span><?php }?></dd></div>
            </dl>
        </div>

        <div class="uc-section">
            <div class="uc-section-title"><span>修改昵称</span></div>
            <div class="uc-form">
                <input class="uc-input" type="text" id="ucNickInput" maxlength="20" value="<?php echo $userrow['nickname']?>" placeholder="昵称，最多 20 个字">
                <button type="button" class="uc-btn uc-btn-primary" id="ucNickSave">保存</button>
            </div>
        </div>

        </div>

        <div class="uc-col">
        <div class="uc-section">
            <div class="uc-section-title"><span>登录方式</span></div>
            <div class="uc-binds">
<?php foreach($uc_ids as $one){?>
                <div class="uc-bind">
                    <span class="uc-bind-icon uc-bind-<?php echo $one['type']?>"><i class="fa fa-<?php echo $one['type']=='qq'?'qq':($one['type']=='mail'?'envelope':'wechat')?>" aria-hidden="true"></i></span>
                    <div class="uc-bind-main">
                        <strong><?php echo login_type_name($one['type'])?><?php if($one['primary']){?><em class="uc-bind-tag">注册方式</em><?php }?></strong>
                        <small><?php echo $one['type']=='mail' ? htmlspecialchars($one['openid'], ENT_QUOTES, 'UTF-8') : '已绑定'?></small>
                    </div>
<?php if($one['primary']){?>
                    <span class="uc-dim">不可解绑</span>
<?php }else{?>
                    <button type="button" class="uc-btn uc-btn-sm" data-uc-unbind="<?php echo $one['type']?>">解绑</button>
<?php }?>
                </div>
<?php }?>
<?php foreach($uc_oauth_types as $tk => $tname){ if(isset($uc_has[$tk])) continue; ?>
                <div class="uc-bind is-empty">
                    <span class="uc-bind-icon uc-bind-<?php echo $tk?>"><i class="fa fa-<?php echo $tk=='qq'?'qq':'wechat'?>" aria-hidden="true"></i></span>
                    <div class="uc-bind-main">
                        <strong><?php echo $tname?></strong>
                        <small>未绑定，绑定后可以直接用<?php echo $tname?>登录</small>
                    </div>
                    <button type="button" class="uc-btn uc-btn-primary uc-btn-sm" data-uc-bind="<?php echo $tk?>">绑定</button>
                </div>
<?php }?>
<?php if(!isset($uc_has['mail'])){?>
                <div class="uc-bind is-empty">
                    <span class="uc-bind-icon uc-bind-mail"><i class="fa fa-envelope" aria-hidden="true"></i></span>
                    <div class="uc-bind-main">
                        <strong>邮箱</strong>
                        <small><?php echo is_mail_ready() ? '未绑定，绑定后可以用邮箱和密码登录' : '站点还没有配置发信，暂时无法绑定'?></small>
                    </div>
<?php if(is_mail_ready()){?>
                    <button type="button" class="uc-btn uc-btn-primary uc-btn-sm" id="ucBindMailBtn">绑定邮箱</button>
<?php }?>
                </div>
<?php }?>
            </div>
<?php if(!isset($uc_has['mail']) && is_mail_ready()){?>
            <div class="uc-form uc-form-col uc-bindmail-form" id="ucBindMailForm" hidden>
                <input class="uc-input" type="email" id="ucBindEmail" placeholder="邮箱地址" autocomplete="email">
                <div class="uc-form-row">
                    <input class="uc-input" type="text" id="ucBindCode" maxlength="6" placeholder="6 位验证码" autocomplete="one-time-code">
                    <button type="button" class="uc-btn" id="ucBindSendCode">获取验证码</button>
                </div>
                <input class="uc-input" type="password" id="ucBindPwd" autocomplete="new-password" placeholder="设置登录密码，6-32 位，需含字母和数字">
                <button type="button" class="uc-btn uc-btn-primary" id="ucBindSubmit">确认绑定</button>
            </div>
<?php }?>
            <p class="uc-tip">注册时使用的那种登录方式不能解绑，账号至少要保留一个能登进来的入口。</p>
        </div>
        </div>

        <div class="uc-col">
<?php if(!empty($userrow['password'])){?>
        <div class="uc-section">
            <div class="uc-section-title"><span>修改密码</span></div>
            <div class="uc-form uc-form-col">
                <input class="uc-input" type="password" id="ucOldPwd" autocomplete="current-password" placeholder="原密码">
                <input class="uc-input" type="password" id="ucNewPwd" autocomplete="new-password" placeholder="新密码，6-32 位，需含字母和数字">
                <input class="uc-input" type="password" id="ucNewPwd2" autocomplete="new-password" placeholder="再输一次新密码">
                <button type="button" class="uc-btn uc-btn-primary" id="ucPwdSave">修改密码</button>
            </div>
            <p class="uc-tip">修改成功后，其它设备上的登录状态会立即失效，当前设备保持登录。</p>
        </div>
<?php }else{?>
        <div class="uc-section">
            <div class="uc-section-title"><span>密码</span></div>
            <p class="uc-tip">当前账号还没有设置密码。上面绑定邮箱时会一并设置密码，之后就能用邮箱 + 密码登录。</p>
        </div>
<?php }?>
        </div>
        </div>
<?php }?>
    </div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script>
var uc_csrf = '<?php echo $csrf_token?>';
</script>
<script src="./assets/js/usercenter.js?v=<?php echo VERSION?>"></script>
</body>
</html>
