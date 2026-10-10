<?php
define('IN_ADMIN', true);
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'getcount':
	$thtime=date("Y-m-d").' 00:00:00';
	$lastday=date("Y-m-d",strtotime("-1 day")).' 00:00:00';
	$count1=$DB->getColumn("SELECT count(*) from pre_file");
	$count2=$DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$thtime' AND copied=0");
	$count3=$DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$lastday' AND addtime<'$thtime' AND copied=0");
	$count4=$DB->getColumn("SELECT count(*) from pre_user");

	/*
	 * 首页第二行的按类型统计和全站占用空间。
	 * 分组口径直接复用前台 layout_blocks.php 里的那张扩展名表，后台不再自己抄一份，
	 * 否则站长在「文件设置」里改了图片/视频格式，前后台就会给出两套数字。
	 * 该文件只有函数定义，没有输出，admin 下 include 是安全的。
	 *
	 * 查询用一次 GROUP BY type 带 SUM(size) 全查回来，在 PHP 里归组：
	 * pre_file 上没有 type 索引，按五个分组各查一次就是五次全表扫描。
	 */
	include_once SYSTEM_ROOT.'layout_blocks.php';
	$result=["code"=>0,"count1"=>$count1,"count2"=>$count2,"count3"=>$count3,"count4"=>$count4];
	//只传了部分文件、layout_blocks.php 还是旧版时，分组表取不到就跳过类型统计：
	//上面四个数字照常返回，首页那六张卡留占位符，不会整个接口报错
	if(function_exists('layout_type_group_lookup')){
		$type_count = ["image"=>0,"video"=>0,"audio"=>0,"doc"=>0,"archive"=>0,"other"=>0];
		$type_bytes = $type_count;
		$total_bytes = 0;
		$group_lookup = layout_type_group_lookup();
		$type_rs = $DB->query("SELECT type, count(*) AS num, COALESCE(SUM(size),0) AS bytes FROM pre_file GROUP BY type");
		if($type_rs){
			while($type_row = $type_rs->fetch()){
				$ext = strtolower((string)$type_row['type']);
				$g = isset($group_lookup[$ext]) ? $group_lookup[$ext] : 'other';
				$type_count[$g] += intval($type_row['num']);
				$type_bytes[$g] += floatval($type_row['bytes']);
				$total_bytes += floatval($type_row['bytes']);
			}
		}
		$type_size = [];
		foreach($type_bytes as $g => $bytes){
			$type_size[$g] = size_format($bytes);
		}
		$result["types"] = $type_count;      //各分组文件数
		$result["sizes"] = $type_size;       //各分组占用空间（已带单位，直接显示）
		$result["bytes"] = $type_bytes;      //各分组原始字节数，前端算「按占用」的占比要用
		$result["totalsize"] = size_format($total_bytes);
	}
	exit(json_encode($result));
break;
case 'checkupdate':
	/*
	 * 后台首页「版本信息」里的更新检查。
	 * 结果在服务端缓存半小时（失败缓存 5 分钟），所以这里不加节流也不会反复打 GitHub；
	 * 首页是异步调的，就算服务器连不上 GitHub 卡满超时，也只是这一行显示失败，不挡页面。
	 * 查的是站长在「程序更新日志」页选的那个更新源（默认 GitHub，DEC-20261005-002）。
	 */
	include_once SYSTEM_ROOT.'update_check.php';
	/*
	 * 先把会话写回并解锁：PHP 的文件会话是独占锁，这一步要去访问 GitHub，
	 * 慢的时候十几秒，锁不放开的话同一个管理员的其它请求（首页那几个统计数字）
	 * 会一直排队等着。后面只读 $conf 和写 pre_config，不再动 $_SESSION。
	 */
	if(function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE)session_write_close();
	$u = update_status(false);
	$latest = (!empty($u['commits']) && isset($u['commits'][0])) ? $u['commits'][0] : null;
	//查失败时顺手提一句可以换源：首页这一行只有鼠标悬停的提示，站长不一定知道更新日志页里能换
	if($u['state'] === 'error' && count(update_sources()) > 1)$u['error'] .= '。可以到「更新日志」页换一个主更新源再试';
	exit(json_encode([
		'code'    => 0,
		'state'   => $u['state'],
		'text'    => $u['text'],
		'local'   => $u['local_version'],
		'remote'  => $u['remote_version'],
		'checked' => $u['checked_text'],
		'error'   => $u['error'],
		'needdb'  => !empty($u['need_db_update']),
		'latest'  => $latest ? ($latest['title'].'（'.update_time_ago($latest['date']).'）') : '',
	], JSON_UNESCAPED_UNICODE));
break;
case 'updatesource':
	/*
	 * 「程序更新日志」页切换更新源（DEC-20261005-002）。
	 * 只能在 update_check.php 里写死的清单中选，不接受自填的地址；
	 * 换源等于换了在线更新的下载来源，所以和在线更新一样要求 POST 并带上页面令牌。
	 */
	include_once SYSTEM_ROOT.'online_update.php';
	if($_SERVER['REQUEST_METHOD'] !== 'POST' || !oupd_check_token(isset($_POST['token']) ? $_POST['token'] : ''))exit('{"code":-1,"msg":"令牌无效，请刷新页面后再试"}');
	$update_source = isset($_POST['source']) ? (string)$_POST['source'] : '';
	$update_sources = update_sources();
	if(!isset($update_sources[$update_source]))exit(json_encode(['code'=>-1, 'msg'=>'没有这个更新源'], JSON_UNESCAPED_UNICODE));
	saveSetting('update_source', $update_source);
	exit('{"code":0}');
break;
case 'updateaccel':
	/*
	 * 保存 / 清除站长自己填的加速源地址（DEC-20261005-004）。
	 * 这是唯一一个可以在页面上填的更新源地址，只能当加速源用。经它下载的更新包
	 * 和内置加速站一样不做核对（DEC-20261005-006），填谁的地址就等于信谁给的代码，页面上有说明。
	 * 地址只收 https 的域名（update_accel_custom_clean）；和换源一样要求 POST 并带页面令牌。
	 */
	include_once SYSTEM_ROOT.'online_update.php';
	if($_SERVER['REQUEST_METHOD'] !== 'POST' || !oupd_check_token(isset($_POST['token']) ? $_POST['token'] : ''))exit('{"code":-1,"msg":"令牌无效，请刷新页面后再试"}');
	$accel_raw = isset($_POST['url']) ? trim((string)$_POST['url']) : '';
	$accel_old = isset($conf['update_accel_custom']) ? (string)$conf['update_accel_custom'] : '';
	if($accel_raw === ''){
		//清空 = 删除这个源；正选着它的话退回 GitHub
		saveSetting('update_accel_custom', '');
		saveSetting('update_cache_custom', '');
		if(isset($conf['update_source']) && $conf['update_source'] === 'custom')saveSetting('update_source', 'github');
		exit(json_encode(['code'=>0, 'msg'=>'已删除自定义加速源'], JSON_UNESCAPED_UNICODE));
	}
	$accel_base = update_accel_custom_clean($accel_raw);
	if($accel_base === '')exit(json_encode(['code'=>-1, 'msg'=>'地址不合规：要填 https:// 开头的域名，不带参数，长度不超过 200，例如 https://gh.example.com'], JSON_UNESCAPED_UNICODE));
	//换了地址，上一个地址查到的结果就不能再用了
	if($accel_base !== $accel_old)saveSetting('update_cache_custom', '');
	saveSetting('update_accel_custom', $accel_base);
	if(!empty($_POST['use']))saveSetting('update_source', 'custom');
	exit(json_encode(['code'=>0, 'msg'=>'已保存'], JSON_UNESCAPED_UNICODE));
break;
case 'onlineupdate_prepare':
case 'onlineupdate_apply':
case 'onlineupdate_restore':
	/*
	 * 后台在线更新（DEC-20261003-001），逻辑都在 includes/online_update.php。
	 * 这三个动作会下载代码、覆盖站点文件，除了上面的 Referer 校验，
	 * 还要求 POST 并带上「程序更新日志」页面给的令牌。
	 */
	include_once SYSTEM_ROOT.'online_update.php';
	if($_SERVER['REQUEST_METHOD'] !== 'POST' || !oupd_check_token(isset($_POST['token']) ? $_POST['token'] : ''))exit('{"code":-1,"msg":"令牌无效，请刷新页面后再试"}');
	//下载和覆盖可能要几十秒，先放开会话锁，别把同一管理员的其它标签页堵住
	if(function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE)session_write_close();
	if($act == 'onlineupdate_restore'){
		$result = oupd_restore(isset($_POST['name']) ? (string)$_POST['name'] : '');
	}else{
		$sha = isset($_POST['sha']) ? strtolower(trim((string)$_POST['sha'])) : '';
		$result = $act == 'onlineupdate_prepare' ? oupd_prepare($sha) : oupd_apply($sha);
	}
	exit(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
break;
case 'set':
	if(isset($_POST['green_label_porn'])){
		$_POST['green_label_porn'] = implode(',',$_POST['green_label_porn']);
	}
	if(isset($_POST['green_label_terrorism'])){
		$_POST['green_label_terrorism'] = implode(',',$_POST['green_label_terrorism']);
	}
	//外观渐变是前端拼好的 JSON，落库前先按主题表洗一遍：认识的外观、合法的颜色角度才留
	if(isset($_POST['theme_gradient'])){
		$_POST['theme_gradient'] = normalize_theme_gradient($_POST['theme_gradient']);
	}
	//页脚代码列表是后台表格汇总出来的 JSON，落库前洗一遍；解析不了就不存，保留原值
	if(isset($_POST['footer_codes'])){
		$footer_codes = json_decode($_POST['footer_codes'], true);
		if(!is_array($footer_codes)){
			unset($_POST['footer_codes']);
		}else{
			$_POST['footer_codes'] = json_encode(footer_codes_normalize($footer_codes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			//配置值是 TEXT，超长会被静默截断，截断后的 JSON 整个读不出来，所以宁可不存
			if(strlen($_POST['footer_codes']) > 60000)exit(json_encode(['code'=>-1, 'msg'=>'页脚代码总长度超过 60000 字节，请删减后再保存']));
		}
	}
	//禁止访问 IP 列表：校验每一条，有无效的就整份不存并说出是哪几条；存列表的同时重新拼出拦截用的 blackip
	if(isset($_POST['blackip_list'])){
		$blackip_list = json_decode($_POST['blackip_list'], true);
		if(!is_array($blackip_list)){
			unset($_POST['blackip_list']);
		}else{
			$blackip_list = blackip_list_normalize($blackip_list, $blackip_bad);
			if($blackip_bad){
				//提示会被 layer.alert 当 HTML 显示，站长填的内容先转义
				exit(json_encode(['code'=>-1, 'msg'=>'禁止访问 IP 里这些不是有效的 IP 或网段，没有保存：<br>'
					.htmlspecialchars(implode('、', array_slice($blackip_bad, 0, 10)), ENT_QUOTES, 'UTF-8')
					.(count($blackip_bad) > 10 ? ' 等 '.count($blackip_bad).' 条' : '')
					.'<br>单个 IP 如 1.2.3.4，网段如 1.2.3.0/24'], JSON_UNESCAPED_UNICODE));
			}
			$_POST['blackip_list'] = json_encode($blackip_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if(strlen($_POST['blackip_list']) > 60000)exit(json_encode(['code'=>-1, 'msg'=>'禁止访问 IP 列表太长（超过 60000 字节），请删减后再保存'], JSON_UNESCAPED_UNICODE));
			$_POST['blackip'] = blackip_string($blackip_list);
		}
	}
	//页脚运行时间的起算时刻：datetime-local 传来的是 2024-07-28T16:30:59，统一存成 Y-m-d H:i:s；解析不了就不存，保留原值
	if(isset($_POST['runtime_start'])){
		$runtime_ts = strtotime(trim($_POST['runtime_start']));
		if($runtime_ts === false) unset($_POST['runtime_start']);
		else $_POST['runtime_start'] = date('Y-m-d H:i:s', $runtime_ts);
	}
	//下载限速只接受非负数字和固定单位，避免绕过页面校验写入异常配置。
	foreach(['guest', 'user', 'vip'] as $speed_tier){
		$speed_key = 'down_speed_'.$speed_tier;
		$unit_key = $speed_key.'_unit';
		if(isset($_POST[$speed_key])){
			$speed_value = is_numeric($_POST[$speed_key]) ? max(0, floatval($_POST[$speed_key])) : 0;
			if(!is_finite($speed_value))$speed_value = 0;
			$_POST[$speed_key] = rtrim(rtrim(number_format($speed_value, 2, '.', ''), '0'), '.');
		}
		if(isset($_POST[$unit_key])){
			$_POST[$unit_key] = strtoupper(trim($_POST[$unit_key])) === 'MB' ? 'MB' : 'KB';
		}
	}
	if(isset($_POST['api_auth_mode'])){
		$_POST['api_auth_mode'] = in_array($_POST['api_auth_mode'], ['public', 'user', 'vip'], true) ? $_POST['api_auth_mode'] : 'user';
	}
	if(isset($_POST['api_key_limit']))$_POST['api_key_limit'] = max(1, min(20, intval($_POST['api_key_limit'])));
	if(isset($_POST['api_key_expire_days']))$_POST['api_key_expire_days'] = max(0, min(3650, intval($_POST['api_key_expire_days'])));
	//只写白名单里的配置键，其余一律丢弃（admin_user/admin_pwd 走账号页自己的表单）
	//表单里混进来的非配置字段（令牌、按钮之类）不算漏配，别报进 skipped 里干扰判断
	$not_setting = ['csrf_token', 'ajax', 'do', 'fields', 'submit', 'act'];
	$skipped = [];
	foreach($_POST as $k=>$v){
		if(!is_admin_setting_key($k)){
			if(!in_array($k, $not_setting, true))$skipped[] = $k;
			continue;
		}
		saveSetting($k, $v);
		//内存里的 $conf 跟着更新：下面同步 404 页要用刚存进去的渐变配置，不能读旧值
		$conf[$k] = $v;
	}
	//静态的 404.html 读不到数据库配置，外观一改就把主题类名写进去
	if(isset($_POST['site_theme']))sync_404_theme($_POST['site_theme']);
	exit(json_encode(['code'=>0, 'msg'=>'succ', 'skipped'=>$skipped]));
break;
case 'siteicon':
	/*
	 * 上传网站图标（「网站信息设置」页）。图标存在 assets/siteicon/，不随更新包走，更新之后不用重新换。
	 * 不走 act=set：这里收的是文件，存成什么路径由 site_icon_save() 自己定，不接受表单里填的值。
	 */
	if($_SERVER['REQUEST_METHOD'] !== 'POST')exit('{"code":-1,"msg":"请求方式不对"}');
	if(empty($_FILES['file']) || !is_array($_FILES['file']) || is_array($_FILES['file']['error']))exit(json_encode(['code'=>-1, 'msg'=>'请选择一张图片'], JSON_UNESCAPED_UNICODE));
	$icon_up = $_FILES['file'];
	if($icon_up['error'] === UPLOAD_ERR_INI_SIZE || $icon_up['error'] === UPLOAD_ERR_FORM_SIZE)exit(json_encode(['code'=>-1, 'msg'=>'图片太大，服务器不接收，请换一张小一点的'], JSON_UNESCAPED_UNICODE));
	if($icon_up['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($icon_up['tmp_name']))exit(json_encode(['code'=>-1, 'msg'=>'图片上传失败，请重试'], JSON_UNESCAPED_UNICODE));
	if(site_icon_save($icon_up['tmp_name'], $icon_err) === false)exit(json_encode(['code'=>-1, 'msg'=>$icon_err], JSON_UNESCAPED_UNICODE));
	//根目录的 favicon.ico 写不进去不算失败：页面上的图标已经换了，只是直接打开文件直链时还是原来那个
	$icon_msg = site_icon_sync_root() ? '图标已更新' : '图标已更新。但站点根目录的 favicon.ico 写不进去，直接打开文件直链时浏览器标签上还是原来的图标，请检查该文件的写入权限';
	exit(json_encode(['code'=>0, 'msg'=>$icon_msg, 'url'=>site_icon_url('../'), 'custom'=>1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
break;
case 'siteiconReset':
	//恢复默认图标：删掉上传的那张，根目录的 favicon.ico 也还原成程序自带的
	if($_SERVER['REQUEST_METHOD'] !== 'POST')exit('{"code":-1,"msg":"请求方式不对"}');
	if(!site_icon_reset())exit(json_encode(['code'=>-1, 'msg'=>'恢复失败['.$DB->error().']'], JSON_UNESCAPED_UNICODE));
	exit(json_encode(['code'=>0, 'msg'=>'已恢复默认图标', 'url'=>site_icon_url('../'), 'custom'=>0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
break;
case 'iptype':
	$result = [
	['name'=>'0_X_FORWARDED_FOR', 'ip'=>real_ip(0), 'city'=>get_ip_city(real_ip(0))],
	['name'=>'1_X_REAL_IP', 'ip'=>real_ip(1), 'city'=>get_ip_city(real_ip(1))],
	['name'=>'2_REMOTE_ADDR', 'ip'=>real_ip(2), 'city'=>get_ip_city(real_ip(2))],
	['name'=>'3_Cloudflare（自动校验来源）', 'ip'=>real_ip(3), 'city'=>get_ip_city(real_ip(3))]
	];
	exit(json_encode($result));
break;
case 'userList':
	$sql=" 1=1";
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `enable`={$dstatus}";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$type = intval($_POST['type']);
		$kw = trim(daddslashes($_POST['kw']));
		if($type == 1){
			$sql.=" AND `uid`='{$kw}'";
		}elseif($type == 2){
			$sql.=" AND `openid`='{$kw}'";
		}elseif($type == 3){
			$sql.=" AND `nickname` LIKE '%{$kw}%'";
		}elseif($type == 4){
			$sql.=" AND `loginip`='{$kw}'";
		}
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_user WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_user WHERE{$sql} order by uid desc limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		//登录方式的显示名统一走 login_type_name()：原来这里自带一张只有 qq/wx 的表，
		//加了邮箱注册之后 type='mail' 查不到，列表里那一列就是空的
		$row['type'] = login_type_name($row['type']);
		//自己上传的头像存的是站内相对路径，后台页面在下一级目录里，要补 ../；
		//avatar_custom 给列表用来决定要不要出「清除头像」
		$row['avatar_custom'] = user_avatar_is_custom($row['faceimg']) ? 1 : 0;
		//会员等级的名字给列表显示；记的等级被删了的按普通用户（过没过期由页面按到期时间自己标）
		$row_level = intval($row['level_id']) > 0 ? level_get($row['level_id']) : null;
		if(!$row_level || intval($row_level['type']) === 1 || intval($row_level['type']) === 2){
			$row['level_id'] = 0;
			$row_level = level_builtin(2);
		}
		$row['level_name'] = $row_level['name'];
		$row['level_admin'] = level_is_admin($row_level) ? 1 : 0;
		$row['faceimg'] = user_avatar_url($row, '../');
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'setUserEnable':
	$uid=intval($_POST['uid']);
	$enable=intval($_POST['enable']);
	$sql = "UPDATE pre_user SET enable='$enable' WHERE uid='$uid'";
	if($DB->exec($sql)!==false)exit('{"code":0,"msg":"修改用户成功！"}');
	else exit('{"code":-1,"msg":"修改用户失败['.$DB->error().']"}');
break;
case 'resetUserAvatar':
	//只清用户自己上传的头像（文件一起删），快捷登录带来的第三方头像不动
	$uid=intval($_POST['uid']);
	if(user_avatar_reset($uid))exit('{"code":0,"msg":"头像已清除！"}');
	else exit('{"code":-1,"msg":"清除头像失败['.$DB->error().']"}');
break;
case 'saveUserInfo':
	$uid=intval($_POST['uid']);
	//会员等级：0 是普通用户；只能选自己建的等级或管理员，游客、普通用户那两行不算一个可以「给」的等级
	$level_id = isset($_POST['level_id']) ? intval($_POST['level_id']) : 0;
	if($level_id > 0){
		$level_row = level_get($level_id);
		if(!$level_row)exit('{"code":-1,"msg":"选择的会员等级不存在，请刷新页面后重试"}');
		if(!level_is_custom($level_row) && !level_is_admin($level_row))$level_id = 0;
	}
	$upload_size = (isset($_POST['upload_size']) && $_POST['upload_size'] !== '') ? intval($_POST['upload_size']) : -1;
	$upload_limit = (isset($_POST['upload_limit']) && $_POST['upload_limit'] !== '') ? intval($_POST['upload_limit']) : -1;
	$expiretime = null;
	if(isset($_POST['expire_days']) && $_POST['expire_days'] !== ''){
		$expire_days = intval($_POST['expire_days']);
		if($expire_days > 0) $expiretime = date('Y-m-d H:i:s', strtotime('+'.$expire_days.' days'));
	}
	if($expiretime === null && isset($_POST['expiretime']) && trim($_POST['expiretime']) !== ''){
		$expiretime_input = str_replace('T', ' ', trim($_POST['expiretime']));
		$expire_timestamp = strtotime($expiretime_input);
		if($expire_timestamp !== false) $expiretime = date('Y-m-d H:i:s', $expire_timestamp);
	}
	if($upload_size < -1)$upload_size = -1;
	if($upload_limit < -1)$upload_limit = -1;
	//下载限速 KB/s：留空或负数 = -1 按会员等级，0 不限速
	$down_speed = (isset($_POST['down_speed']) && $_POST['down_speed'] !== '') ? intval($_POST['down_speed']) : -1;
	if($down_speed < -1)$down_speed = -1;
	$sql = "UPDATE pre_user SET level_id=:level_id, upload_size=:upload_size, upload_limit=:upload_limit, down_speed=:down_speed, expiretime=:expiretime";
	$params = [':level_id'=>$level_id, ':upload_size'=>$upload_size, ':upload_limit'=>$upload_limit, ':down_speed'=>$down_speed, ':expiretime'=>$expiretime, ':uid'=>$uid];
	//附加包两项各有自己的到期时间（空 = 永久）。表单没带的不动，免得旧页面缓存提交把它清掉
	if(isset($_POST['bonus_limit'])){
		$bonus_limit = max(0, intval($_POST['bonus_limit']));
		$bonus_expire = null;
		if($bonus_limit > 0 && isset($_POST['bonus_expire']) && trim($_POST['bonus_expire']) !== ''){
			$bonus_timestamp = strtotime(str_replace('T', ' ', trim($_POST['bonus_expire'])));
			if($bonus_timestamp !== false) $bonus_expire = date('Y-m-d H:i:s', $bonus_timestamp);
		}
		$sql .= ", bonus_limit=:bonus_limit, bonus_expire=:bonus_expire";
		$params[':bonus_limit'] = $bonus_limit;
		$params[':bonus_expire'] = $bonus_expire;
	}
	if(isset($_POST['online_edit'])){
		$online_edit = intval($_POST['online_edit']) === 1 ? 1 : 0;
		$edit_expire = null;
		if($online_edit === 1 && isset($_POST['edit_expire']) && trim($_POST['edit_expire']) !== ''){
			$edit_timestamp = strtotime(str_replace('T', ' ', trim($_POST['edit_expire'])));
			if($edit_timestamp !== false) $edit_expire = date('Y-m-d H:i:s', $edit_timestamp);
		}
		$sql .= ", online_edit=:online_edit, edit_expire=:edit_expire";
		$params[':online_edit'] = $online_edit;
		$params[':edit_expire'] = $edit_expire;
	}
	$sql .= " WHERE uid=:uid";
	if($DB->exec($sql, $params)!==false)exit('{"code":0,"msg":"修改用户成功！"}');
	else exit('{"code":-1,"msg":"修改用户失败['.$DB->error().']"}');
break;
case 'delUser':
	$uid=intval($_POST['uid']);
	$row=$DB->getRow("select * from pre_user where uid='$uid' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前用户不存在！"}');
	$sql = "DELETE FROM pre_user WHERE uid='$uid'";
	if($DB->exec($sql)){
		//绑定表的 (type,openid) 上是唯一索引，留着孤儿行会让那个 QQ / 邮箱以后再也绑不上任何账号
		delete_user_binds($uid);
		//文件记录本来就不随账号删；他建的文件夹删掉，文件的 folder_id 归零，免得指向不存在的文件夹
		$DB->exec("UPDATE pre_file SET folder_id=0 WHERE uid=:uid", [':uid'=>$uid]);
		$DB->exec("DELETE FROM pre_folder WHERE uid=:uid", [':uid'=>$uid]);
		//他自己上传的头像文件也删掉，不然就成了没人认领的文件
		user_avatar_delete_file($row['faceimg']);
		exit('{"code":0,"msg":"删除用户成功！"}');
	}
	else exit('{"code":-1,"msg":"删除用户失败['.$DB->error().']"}');
break;
case 'sponsorList':
	$sql=" 1=1";
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw = trim(daddslashes($_POST['kw']));
		$sql.=" AND `name` LIKE '%{$kw}%'";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_sponsor WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_sponsor WHERE{$sql} order by id desc limit $offset,$limit");
	exit(json_encode(['total'=>$total, 'rows'=>$list], JSON_UNESCAPED_UNICODE));
break;
case 'saveSponsorInfo':
	$id = intval($_POST['id']);
	$name = trim(htmlspecialchars($_POST['name']));
	$platform = trim(htmlspecialchars($_POST['platform']));
	$amount = trim(htmlspecialchars($_POST['amount']));
	$sponsor_time = trim(htmlspecialchars($_POST['sponsor_time']));
	$platform_allow = ['微信','QQ钱包','支付宝'];
	if(empty($name) || empty($amount) || empty($sponsor_time))exit('{"code":-1,"msg":"昵称、赞助金额、赞助时间均不能为空"}');
	if(!in_array($platform, $platform_allow, true))exit('{"code":-1,"msg":"赞助平台只能是微信、QQ钱包或支付宝"}');
	if($id > 0){
		$sql = "UPDATE `pre_sponsor` SET `name`=:name,`platform`=:platform,`amount`=:amount,`sponsor_time`=:sponsor_time WHERE `id`=:id";
		$data = [':name'=>$name, ':platform'=>$platform, ':amount'=>$amount, ':sponsor_time'=>$sponsor_time, ':id'=>$id];
	}else{
		$sql = "INSERT INTO `pre_sponsor` (`name`,`platform`,`amount`,`sponsor_time`,`addtime`) VALUES (:name,:platform,:amount,:sponsor_time,NOW())";
		$data = [':name'=>$name, ':platform'=>$platform, ':amount'=>$amount, ':sponsor_time'=>$sponsor_time];
	}
	if($DB->exec($sql, $data)!==false)exit('{"code":0,"msg":"保存成功！"}');
	else exit('{"code":-1,"msg":"保存失败['.$DB->error().']"}');
break;
case 'delSponsor':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_sponsor where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"该赞助记录不存在！"}');
	$sql = "DELETE FROM pre_sponsor WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除成功！"}');
	else exit('{"code":-1,"msg":"删除失败['.$DB->error().']"}');
break;
case 'violationList':
	$sql=" 1=1";
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw = trim(daddslashes($_POST['kw']));
		$sql.=" AND (`name` LIKE '%{$kw}%' OR `ip` LIKE '%{$kw}%' OR `hash`='{$kw}')";
	}
	if(isset($_POST['is_show']) && $_POST['is_show']>-1) {
		$is_show = intval($_POST['is_show']);
		$sql.=" AND `is_show`={$is_show}";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_violation WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_violation WHERE{$sql} order by id desc limit $offset,$limit");
	if(!$list)$list = [];
	foreach($list as &$row){
		$row['size_text'] = size_format($row['size']);
		$row['mask_name'] = violation_mask_name($row['name']);
		//违规公示页的“查看”与内容检测记录一致：图片弹层查看，音视频就地播放。
		//手工补录没有关联文件，文件后来被删除时也只保留公示记录，这两种情况返回已删除状态。
		$row['file_exists'] = 0;
		$row['view_type'] = '';
		$row['viewurl'] = '';
		if(intval($row['file_id']) > 0){
			$file = $DB->getRow("SELECT `token`,`type` FROM pre_file WHERE `id`=:id LIMIT 1", [':id'=>intval($row['file_id'])]);
			if($file){
				$row['file_exists'] = 1;
				$row['view_type'] = get_view_type($file['type']);
				$row['viewurl'] = './view.php/'.rawurlencode($file['token']).'.'.rawurlencode($file['type'] ? $file['type'] : 'file');
			}
		}
	}
	unset($row);
	exit(json_encode(['total'=>$total, 'rows'=>$list], JSON_UNESCAPED_UNICODE));
break;
case 'saveViolationInfo':
	$id = intval($_POST['id']);
	$name = trim(htmlspecialchars($_POST['name']));
	$type = trim(htmlspecialchars($_POST['type']));
	$remark = trim(htmlspecialchars($_POST['remark']));
	$is_show = intval($_POST['is_show']) == 1 ? 1 : 0;
	if(empty($name))exit('{"code":-1,"msg":"文件名称不能为空"}');
	if($id > 0){
		$sql = "UPDATE `pre_violation` SET `name`=:name,`type`=:type,`remark`=:remark,`is_show`=:is_show WHERE `id`=:id";
		$data = [':name'=>$name, ':type'=>$type, ':remark'=>$remark, ':is_show'=>$is_show, ':id'=>$id];
	}else{
		//手工补录的公示记录没有对应的文件，file_id 留 0，不参与按文件去重
		$sql = "INSERT INTO `pre_violation` (`file_id`,`name`,`type`,`source`,`remark`,`is_show`,`addtime`) VALUES (0,:name,:type,'manual',:remark,:is_show,NOW())";
		$data = [':name'=>$name, ':type'=>$type, ':remark'=>$remark, ':is_show'=>$is_show];
	}
	if($DB->exec($sql, $data)!==false)exit('{"code":0,"msg":"保存成功！"}');
	else exit('{"code":-1,"msg":"保存失败['.$DB->error().']"}');
break;
case 'importBlockedFiles':
	//把启用公示功能之前就已经封禁的老文件补录进来，已有记录的会被 LEFT JOIN 排除，可以重复执行
	$list = $DB->getAll("SELECT f.* FROM pre_file f LEFT JOIN pre_violation v ON v.`file_id`=f.`id` WHERE f.`block`=1 AND v.`id` IS NULL");
	if(!$list)exit('{"code":0,"msg":"没有需要补录的封禁文件"}');
	$i=0;
	foreach($list as $row){
		if(add_violation_log($row))$i++;
	}
	exit(json_encode(['code'=>0, 'msg'=>'成功补录'.$i.'条封禁记录'], JSON_UNESCAPED_UNICODE));
break;
case 'setViolationShow':
	$id=intval($_POST['id']);
	$is_show=intval($_POST['is_show']) == 1 ? 1 : 0;
	$sql = "UPDATE `pre_violation` SET `is_show`=:is_show WHERE `id`=:id";
	if($DB->exec($sql, [':is_show'=>$is_show, ':id'=>$id])!==false)exit('{"code":0,"msg":"修改成功！"}');
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'delViolation':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_violation where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"该公示记录不存在！"}');
	$sql = "DELETE FROM pre_violation WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除成功！"}');
	else exit('{"code":-1,"msg":"删除失败['.$DB->error().']"}');
break;
case 'replaceList':
	$sql=" 1=1";
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw = trim(daddslashes($_POST['kw']));
		$sql.=" AND (`new_name` LIKE '%{$kw}%' OR `old_name` LIKE '%{$kw}%' OR `ip` LIKE '%{$kw}%' OR `token`='{$kw}')";
	}
	if(isset($_POST['checked']) && $_POST['checked']>-1) {
		$checked = intval($_POST['checked']);
		$sql.=" AND `checked`={$checked}";
	}
	if(isset($_POST['source']) && !empty($_POST['source']) && $_POST['source']!='-1') {
		$source = trim(daddslashes($_POST['source']));
		$sql.=" AND `source`='{$source}'";
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_replace_log WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_replace_log WHERE{$sql} order by id desc limit $offset,$limit");
	if(!$list)$list = [];
	foreach($list as &$row){
		$row['old_size_text'] = size_format($row['old_size']);
		$row['new_size_text'] = size_format($row['new_size']);
		//文件可能已经被删掉了，这时只保留日志，不给查看和封禁入口
		$file = $DB->getRow("SELECT `id`,`block`,`token`,`type`,`pwd` FROM pre_file WHERE `id`=:id LIMIT 1", [':id'=>$row['file_id']]);
		if($file){
			$row['file_exists'] = 1;
			$row['block'] = intval($file['block']);
			$row['pageurl'] = '../file.php?hash='.$file['token'].(!empty($file['pwd'])?'&pwd='.$file['pwd']:'');
			$row['viewurl'] = '../view.php/'.$file['token'].'.'.($file['type']?$file['type']:'file');
			$row['is_image'] = is_view($file['type']) ? 1 : 0;
		}else{
			$row['file_exists'] = 0;
			$row['block'] = -1;
			$row['pageurl'] = '';
			$row['viewurl'] = '';
			$row['is_image'] = 0;
		}
	}
	unset($row);
	exit(json_encode(['total'=>$total, 'rows'=>$list], JSON_UNESCAPED_UNICODE));
break;
case 'setReplaceChecked':
	$id=intval($_POST['id']);
	$checked=intval($_POST['checked']) == 1 ? 1 : 0;
	if($DB->exec("UPDATE `pre_replace_log` SET `checked`=:checked WHERE `id`=:id", [':checked'=>$checked, ':id'=>$id])!==false)exit('{"code":0,"msg":"修改成功！"}');
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'checkAllReplace':
	if($DB->exec("UPDATE `pre_replace_log` SET `checked`=1 WHERE `checked`=0")!==false)exit('{"code":0,"msg":"已全部标记为已复查"}');
	else exit('{"code":-1,"msg":"操作失败['.$DB->error().']"}');
break;
case 'delReplaceLog':
	$id=intval($_POST['id']);
	if($DB->exec("DELETE FROM pre_replace_log WHERE id=:id", [':id'=>$id]))exit('{"code":0,"msg":"删除成功！"}');
	else exit('{"code":-1,"msg":"删除失败['.$DB->error().']"}');
break;
case 'greenhealth':
	/*
	 * 设置页上那行「检测服务：可用 / 不可用」。
	 * 开关打得开、实际永远不工作是最难发现的一种坏法——尤其是视频，它依赖 ffmpeg，
	 * 而 ffmpeg 装没装从网站这边一点都看不出来，所以专门问一次检测服务自己。
	 */
	$health = green_self_request('health', null, 4);
	if(!is_array($health) || empty($health['ok'])){
		exit(json_encode(['code'=>-1, 'msg'=>'连不上检测服务（'.htmlspecialchars(green_self_url('health'), ENT_QUOTES, 'UTF-8').'）']));
	}
	exit(json_encode([
		'code' => 0,
		'models' => isset($health['models']) ? count($health['models']) : 0,
		'video' => !empty($health['video']),
		'ffmpeg' => isset($health['ffmpeg']) ? $health['ffmpeg'] : '',
		'queue' => isset($health['queue']) ? intval($health['queue']) : 0,
	]));
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
