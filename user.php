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

$act = isset($_GET['act']) ? $_GET['act'] : '';
if($act !== ''){
	if(!checkRefererHost()) uc_json(-1, '来源校验失败');
	if(!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
		uc_json(-1, 'CSRF TOKEN ERROR');
	}

	switch($act){
	case 'createApiKey':
		if(empty($conf['api_open']))uc_json(-1, '上传 API 已关闭');
		if(!level_can('api'))uc_json(-1, '当前账号的会员等级不能使用上传接口');
		$name = isset($_POST['name']) ? $_POST['name'] : '';
		$allow_ip = isset($_POST['allow_ip']) ? $_POST['allow_ip'] : '';
		$expire_days = isset($_POST['expire_days']) ? intval($_POST['expire_days']) : api_key_default_expire_days();
		$result = create_user_api_key($uid, $name, $allow_ip, $expire_days);
		if(empty($result['ok']))uc_json(-1, $result['msg']);
		uc_json(0, 'API 密钥创建成功，请立即复制保存', ['key'=>$result['key'], 'id'=>intval($result['id']), 'expiretime'=>$result['expiretime']]);
	break;

	case 'toggleApiKey':
		if(empty($conf['api_open']))uc_json(-1, '上传 API 已关闭');
		$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
		$row = $DB->getRow("SELECT * FROM pre_api_key WHERE id=:id AND uid=:uid LIMIT 1", [':id'=>$id, ':uid'=>$uid]);
		if(!$row)uc_json(-1, 'API 密钥不存在');
		$enable = intval($row['enable']) === 1 ? 0 : 1;
		if($enable && !level_can('api'))uc_json(-1, '当前账号的会员等级不能使用上传接口');
		if($DB->exec("UPDATE pre_api_key SET enable=:enable WHERE id=:id AND uid=:uid", [':enable'=>$enable, ':id'=>$id, ':uid'=>$uid]) === false){
			uc_json(-1, '修改密钥状态失败['.$DB->error().']');
		}
		uc_json(0, $enable ? 'API 密钥已启用' : 'API 密钥已停用');
	break;

	case 'deleteApiKey':
		if(empty($conf['api_open']))uc_json(-1, '上传 API 已关闭');
		$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
		$row = $DB->getRow("SELECT id FROM pre_api_key WHERE id=:id AND uid=:uid LIMIT 1", [':id'=>$id, ':uid'=>$uid]);
		if(!$row)uc_json(-1, 'API 密钥不存在');
		if($DB->exec("DELETE FROM pre_api_key WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]) === false){
			uc_json(-1, '删除密钥失败['.$DB->error().']');
		}
		uc_json(0, 'API 密钥已删除');
	break;

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
			if(!lock_file_blobs([$row['hash']])){ $fail++; continue; }
			//冻结的、以及内容检测转人工还没复核完的，都不许用户自己删掉，得留着给管理员查
			if(file_delete_locked_reason($row) !== ''){ $blocked++; continue; }
			if(delete_file_record($row)) $ok++;
			else $fail++;
		}
		$msg = '已删除 '.$ok.' 个文件';
		if($blocked) $msg .= '，'.$blocked.' 个已冻结或待人工审核，已跳过';
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
		layout_cache_bump();
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

	//重命名。只改显示用的文件名，token 和外链地址都不动。
	//清洗、违禁词、扩展名跟随 type 这些规则在 file_rename_record()，游客「我的文件」的改名也走它
	case 'rename':
		$row = uc_own_file(isset($_POST['id']) ? $_POST['id'] : 0);
		list($err, $name) = file_rename_record($row, isset($_POST['name']) ? $_POST['name'] : '');
		if($err !== '') uc_json(-1, $err);
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

	//上传头像。类型、尺寸的校验和裁剪、落盘都在 user_avatar_save() 里
	case 'avatar':
		if(empty($_FILES['file']) || !is_array($_FILES['file']) || is_array($_FILES['file']['error'])) uc_json(-1, '请选择一张图片');
		$up = $_FILES['file'];
		if($up['error'] === UPLOAD_ERR_INI_SIZE || $up['error'] === UPLOAD_ERR_FORM_SIZE) uc_json(-1, '图片太大，服务器不接收，请换一张小一点的');
		if($up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) uc_json(-1, '图片上传失败，请重试');
		$face = user_avatar_save($uid, $up['tmp_name'], $err);
		if($face === false) uc_json(-1, $err);
		uc_json(0, '头像已更新', ['url'=>user_avatar_url($face), 'custom'=>1]);
	break;

	//恢复默认头像：只去掉自己上传的那张，快捷登录带来的头像不动
	case 'avatarReset':
		if(!user_avatar_reset($uid)) uc_json(-1, '操作失败['.$DB->error().']');
		uc_json(0, '已恢复默认头像', ['url'=>'', 'custom'=>0]);
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

//「我的文件」局部刷新取页面时沿用现有令牌，见 page_csrf_token()
$csrf_token = page_csrf_token();

$tabs = [
	'overview' => ['概览', 'fa-dashboard'],
	'files'    => ['我的文件', 'fa-folder-open'],
	'orders'   => ['订单记录', 'fa-shopping-cart'],
	'api'      => ['上传 API 密钥', 'fa-key'],
	'account'  => ['账号设置', 'fa-user-circle'],
];
//购买功能没开的话就不显示订单页签，免得点进去是一片空白
if(!function_exists('is_buy_open') || !is_buy_open()) unset($tabs['orders']);
//站点关闭 API 或当前等级无权限时隐藏入口；已有密钥保留，恢复权限后可继续管理。
if(empty($conf['api_open']) || !level_can('api')) unset($tabs['api']);

$tab = (isset($_GET['tab']) && isset($tabs[$_GET['tab']])) ? $_GET['tab'] : 'overview';

$title = '个人中心 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
/*
 * 页面内容是布局 user（DEC-20261025-003）：系统默认在 includes/layouts/user.json，
 * 卡片在 includes/blocks/user/，共用数据在 includes/usercenter.php。
 * 每个页签是一个 user.tab 容器，只有当前页签的那一个会输出。
 * 页面级的脚本（layer、令牌、usercenter.js）由这里登记，「我的文件」的脚本由列表卡片登记。
 */
theme_defer('user.base', theme_tpl_render('blocks/user/base_scripts', ['csrf_html'=>$csrf_token], $site_theme), 10);
theme_defer('user.js', theme_tpl_render('blocks/user/uc_script', ['version'=>VERSION], $site_theme), 30);
echo theme_render_layout('user', [
	'tab' => $tab,
	'tabs' => $tabs,
	'csrf_token' => $csrf_token,
], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
echo theme_deferred_html();
?></body>
</html>
