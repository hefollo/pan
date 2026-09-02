<?php
/**
 * 存储类型设置
**/
define('IN_ADMIN', true);
include("../includes/common.php");
$title='存储类型设置';

$onedrive_china = isset($conf['onedrive_type']) && $conf['onedrive_type'] === 'china';
//授权回调地址：要和 Azure 应用里登记的重定向 URI 一字不差
$onedrive_redirect = $siteurl.'set_stor.php';

/*
 * OneDrive 授权跳转、授权回调和「连接测试」都要在 head.php 之前处理：
 * 前两个要发 Location 头，后一个返回 JSON，输出了后台页面的 HTML 就都废了。
 */
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
if($islogin != 1){
	if($is_ajax)exit('{"code":-1,"msg":"登录状态已失效，请重新登录后台"}');
}elseif(isset($_GET['act']) && $_GET['act'] === 'onedrive_auth'){
	//跳到微软登录页。state 存在会话里，回调时比对，防止别人拿构造好的 code 找上门
	if(empty($conf['onedrive_client_id']) || empty($conf['onedrive_client_secret'])){
		header('Location: ./set_stor.php?onedrive=noapp');
		exit;
	}
	$state = md5(uniqid('', true));
	$_SESSION['onedrive_state'] = $state;
	header('Location: '.\lib\Storage\Onedrive::authUrl($conf['onedrive_client_id'], $onedrive_redirect, $state, $onedrive_china));
	exit;
}elseif(isset($_GET['code']) && isset($_GET['state'])){
	//微软带着授权码回来了，换成 refresh_token 存下来
	if(empty($_SESSION['onedrive_state']) || $_SESSION['onedrive_state'] !== $_GET['state']){
		header('Location: ./set_stor.php?onedrive=state');
		exit;
	}
	unset($_SESSION['onedrive_state']);
	$res = \lib\Storage\Onedrive::exchangeCode($conf['onedrive_client_id'], $conf['onedrive_client_secret'], $_GET['code'], $onedrive_redirect, $onedrive_china);
	if(empty($res['refresh_token'])){
		$_SESSION['onedrive_err'] = isset($res['error']) ? $res['error'] : '微软没有返回 refresh_token，请确认应用勾选了 offline_access 权限';
		header('Location: ./set_stor.php?onedrive=fail');
		exit;
	}
	saveSetting('onedrive_refresh_token', $res['refresh_token']);
	saveSetting('onedrive_access_token', isset($res['access_token']) ? $res['access_token'] : '');
	saveSetting('onedrive_token_expire', time() + (isset($res['expires_in']) ? intval($res['expires_in']) : 3600));
	header('Location: ./set_stor.php?onedrive=ok');
	exit;
}elseif($is_ajax && isset($_POST['do'])){
	if(!checkRefererHost())exit('{"code":-1,"msg":"来源校验失败"}');
	@header('Content-Type: application/json; charset=UTF-8');
	//后台页面不走 common.php 里加载存储模块那一段，各家 SDK 的 autoload 得自己引一次
	include_once(SYSTEM_ROOT.'vendor/autoload.php');
	$do = $_POST['do'];
	if($do === 'unauth'){
		//解除授权：本站这边把令牌清掉，微软那边可以在账号的「应用权限」里撤销
		saveSetting('onedrive_refresh_token', '');
		saveSetting('onedrive_access_token', '');
		saveSetting('onedrive_token_expire', '0');
		exit(json_encode(['code'=>0, 'msg'=>'已解除本站保存的 OneDrive 授权']));
	}
	if($do === 'onedrive_info'){
		$model = \lib\StorHelper::getModel('onedrive');
		$drive = $model ? $model->drive() : false;
		if($drive === false || !is_array($drive)){
			exit(json_encode(['code'=>-1, 'msg'=>$model ? $model->errmsg() : '存储模块加载失败'], JSON_UNESCAPED_UNICODE));
		}
		exit(json_encode([
			'code' => 0,
			'owner' => isset($drive['owner']['user']['displayName']) ? $drive['owner']['user']['displayName'] : '',
			'total' => isset($drive['quota']['total']) ? size_format($drive['quota']['total']) : '',
			'used' => isset($drive['quota']['used']) ? size_format($drive['quota']['used']) : '',
			'remaining' => isset($drive['quota']['remaining']) ? size_format($drive['quota']['remaining']) : '',
		], JSON_UNESCAPED_UNICODE));
	}
	if($do === 'test'){
		$storage = isset($_POST['storage']) ? $_POST['storage'] : '';
		$model = \lib\StorHelper::getModel($storage);
		if(!$model)exit(json_encode(['code'=>-1, 'msg'=>'不认识的存储类型：'.htmlspecialchars($storage, ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE));
		if(method_exists($model, 'test')){
			$ok = $model->test();
			exit(json_encode(['code'=>$ok ? 0 : -1, 'msg'=>$ok ? '连接正常，读写测试通过' : $model->errmsg()], JSON_UNESCAPED_UNICODE));
		}
		//通用测试：写一个小文件再读回来删掉，一次跑通写、读、删三个权限
		$name = 'pantest_'.substr(md5(uniqid('', true)), 0, 8);
		$tmp = sys_get_temp_dir().'/'.$name;
		$content = 'pan storage test '.date('Y-m-d H:i:s');
		if(@file_put_contents($tmp, $content) === false){
			exit(json_encode(['code'=>-1, 'msg'=>'本地临时目录不可写，无法测试'], JSON_UNESCAPED_UNICODE));
		}
		//用 savefile 而不是 upload：upload 在本地存储那边走的是 move_uploaded_file，只认真正的上传文件
		if(!$model->savefile($name, $tmp, 'text/plain')){
			@unlink($tmp);
			exit(json_encode(['code'=>-1, 'msg'=>'写入失败：'.$model->errmsg()], JSON_UNESCAPED_UNICODE));
		}
		$read = $model->get($name);
		$model->delete($name);
		exit(json_encode($read === $content
			? ['code'=>0, 'msg'=>'连接正常，读写测试通过']
			: ['code'=>-1, 'msg'=>'写入成功但读回的内容不一致：'.$model->errmsg()], JSON_UNESCAPED_UNICODE));
	}
	exit('{"code":-1,"msg":"未知操作"}');
}

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$storage = isset($conf['storage']) ? $conf['storage'] : 'local';
$has_sae = defined('SAE_ACCESSKEY');
$onedrive_authed = !empty($conf['onedrive_refresh_token']);

/*
 * 存储清单：卡片上的名字、图标、简介和开通地址。
 * 能力标签（直传/直链/断点续传）直接问 StorHelper，避免这里和实际逻辑对不上。
 */
$stor_list = [
	'local' => ['name'=>'本地存储', 'icon'=>'fa-hdd-o', 'desc'=>'存在网站服务器自己的磁盘上，不依赖第三方，受服务器硬盘和带宽限制。'],
	'oss' => ['name'=>'阿里云 OSS', 'icon'=>'fa-cloud', 'desc'=>'国内访问快、生态成熟，按存储量和流量计费。', 'link'=>'https://www.aliyun.com/product/oss?userCode=1cyrqim7'],
	'qcloud' => ['name'=>'腾讯云 COS', 'icon'=>'fa-cloud', 'desc'=>'腾讯云对象存储，有新用户免费额度。', 'link'=>'https://cloud.tencent.com/act/cps/redirect?redirect=10042&cps_key=11eaac2f518cd09a6288f4b1912228b8'],
	'obs' => ['name'=>'华为云 OBS', 'icon'=>'fa-cloud', 'desc'=>'华为云对象存储，多用于政企场景。', 'link'=>'https://www.huaweicloud.com/product/obs.html?fromacct=b70162c8-fbde-42ca-9f3d-5d99dc1951ba&utm_source=bmV0MjAy=&utm_medium=cps&utm_campaign=201905'],
	'upyun' => ['name'=>'又拍云', 'icon'=>'fa-cloud-upload', 'desc'=>'自带 CDN，做图床很合适，下载必须绑定域名。', 'link'=>'https://console.upyun.com/register/?invite=jUSQy3jyE'],
	'qiniu' => ['name'=>'七牛云', 'icon'=>'fa-cloud-upload', 'desc'=>'有免费额度，下载必须绑定域名。', 'link'=>'https://s.qiniu.com/j6zy63'],
	's3' => ['name'=>'通用 S3 兼容', 'icon'=>'fa-server', 'desc'=>'AWS S3、MinIO、Cloudflare R2、Backblaze B2 等所有兼容 S3 协议的服务。'],
	'webdav' => ['name'=>'WebDAV', 'icon'=>'fa-folder-open-o', 'desc'=>'坚果云、Nextcloud、Alist、群晖等标准 WebDAV 服务，上传下载走本站中转。'],
	'onedrive' => ['name'=>'OneDrive', 'icon'=>'fa-cloud-download', 'desc'=>'微软 OneDrive，个人版 / 商业版 / 世纪互联版都支持，下载可用官方直链。'],
];
if($has_sae)$stor_list['sae'] = ['name'=>'SaeStorage', 'icon'=>'fa-archive', 'desc'=>'新浪 SAE 平台自带的存储服务。'];
//当前选的存储被删掉过配置也要能在卡片里选中，兜底回本地
if(!isset($stor_list[$storage]))$storage = 'local';

$stor_caps = [];
foreach($stor_list as $k=>$v){
	$stor_caps[$k] = [
		'name' => $v['name'],
		'cloud' => \lib\StorHelper::is_cloud($k),
		'up' => \lib\StorHelper::is_direct_upload($k),
		'down' => \lib\StorHelper::is_direct_down($k),
		'range' => \lib\StorHelper::is_range($k),
		//只有对象存储那几家的直链要自己填绑定域名，OneDrive 的直链是微软临时地址
		'domain' => \lib\StorHelper::is_direct_down($k) && $k !== 'onedrive',
	];
}

/*
 * 各存储的参数表单，统一由 stor_field() 渲染，省得每加一种存储就复制一大段 HTML。
 * type：text 普通输入框、secret 带显示开关的密码框、select 下拉框。
 */
$stor_fields = [
	'local' => [
		['name'=>'filepath', 'label'=>'本地存储路径', 'placeholder'=>'默认存储在网站 file 目录', 'tip'=>'不填就存在网站 <b>file</b> 目录下。要改的话只能填以 / 开头的服务器绝对路径，并保证 PHP 对该目录有写权限。'],
	],
	'oss' => [
		['name'=>'oss_ak', 'label'=>'AccessKey Id'],
		['name'=>'oss_sk', 'label'=>'AccessKey Secret', 'type'=>'secret'],
		['name'=>'oss_endpoint', 'label'=>'EndPoint', 'placeholder'=>'oss-cn-hangzhou.aliyuncs.com', 'tip'=>'Bucket 概览页里的「外网访问」域名，本站和 OSS 在同一地域时可以填内网 EndPoint 省流量。'],
		['name'=>'oss_bucket', 'label'=>'Bucket 名称'],
	],
	'qcloud' => [
		['name'=>'qcloud_id', 'label'=>'SecretId'],
		['name'=>'qcloud_key', 'label'=>'SecretKey', 'type'=>'secret'],
		['name'=>'qcloud_region', 'label'=>'存储桶地域', 'placeholder'=>'ap-shanghai', 'tip'=>'填英文名称，例如 ap-shanghai、ap-guangzhou。'],
		['name'=>'qcloud_bucket', 'label'=>'存储桶名称', 'placeholder'=>'BucketName-APPID', 'tip'=>'注意要带上后面的 APPID，格式是 <b>BucketName-APPID</b>。'],
	],
	'obs' => [
		['name'=>'obs_ak', 'label'=>'AccessKeyId'],
		['name'=>'obs_sk', 'label'=>'SecretAccessKey', 'type'=>'secret'],
		['name'=>'obs_endpoint', 'label'=>'EndPoint', 'placeholder'=>'obs.cn-north-4.myhuaweicloud.com'],
		['name'=>'obs_bucket', 'label'=>'桶名称'],
	],
	'upyun' => [
		['name'=>'upyun_name', 'label'=>'云存储服务名称'],
		['name'=>'upyun_user', 'label'=>'操作员名称'],
		['name'=>'upyun_pwd', 'label'=>'操作员密码', 'type'=>'secret'],
	],
	'qiniu' => [
		['name'=>'qiniu_ak', 'label'=>'AccessKey'],
		['name'=>'qiniu_sk', 'label'=>'SecretKey', 'type'=>'secret'],
		['name'=>'qiniu_bucket', 'label'=>'存储空间名称'],
		['name'=>'qiniu_domain', 'label'=>'空间绑定域名', 'tip'=>'七牛的下载必须走绑定域名，这里不填的话文件下不下来。'],
	],
	's3' => [
		['name'=>'s3_ak', 'label'=>'Access Key'],
		['name'=>'s3_sk', 'label'=>'Secret Key', 'type'=>'secret'],
		['name'=>'s3_endpoint', 'label'=>'Endpoint', 'placeholder'=>'https://s3.example.com', 'tip'=>'请填完整地址，支持 AWS S3、MinIO、Cloudflare R2、Backblaze B2 等兼容服务。'],
		['name'=>'s3_region', 'label'=>'Region', 'placeholder'=>'us-east-1', 'default'=>'us-east-1'],
		['name'=>'s3_bucket', 'label'=>'Bucket'],
		['name'=>'s3_prefix', 'label'=>'对象前缀', 'placeholder'=>'file/', 'default'=>'file/', 'tip'=>'留空表示直接存到 Bucket 根目录。'],
		['name'=>'s3_path_style', 'label'=>'地址风格', 'type'=>'select', 'options'=>['0'=>'虚拟主机风格（bucket.endpoint）', '1'=>'路径风格（endpoint/bucket）'], 'tip'=>'MinIO，或者 Bucket 名里带点号的，通常要选路径风格。'],
	],
	'webdav' => [
		['name'=>'webdav_url', 'label'=>'WebDAV 地址', 'placeholder'=>'https://dav.jianguoyun.com/dav/', 'tip'=>'服务商给的 WebDAV 根地址。坚果云是 <b>https://dav.jianguoyun.com/dav/</b>，Nextcloud 一般是 <b>https://域名/remote.php/dav/files/用户名/</b>。'],
		['name'=>'webdav_user', 'label'=>'账号'],
		['name'=>'webdav_pass', 'label'=>'密码', 'type'=>'secret', 'tip'=>'坚果云要用「安全选项」里生成的<b>应用密码</b>，不是登录密码。'],
		['name'=>'webdav_path', 'label'=>'存储目录', 'placeholder'=>'file', 'tip'=>'相对 WebDAV 根地址的子目录，留空默认为 <b>file</b>，目录不存在会自动创建。'],
	],
	'onedrive' => [
		['name'=>'onedrive_type', 'label'=>'账号类型', 'type'=>'select', 'options'=>['common'=>'国际版（个人版 / 商业版）', 'china'=>'世纪互联版（中国区）'], 'tip'=>'改了账号类型要重新走一次授权。'],
		['name'=>'onedrive_client_id', 'label'=>'应用 ID', 'placeholder'=>'Azure 应用的 Application (client) ID'],
		['name'=>'onedrive_client_secret', 'label'=>'应用机密', 'type'=>'secret', 'tip'=>'Azure 里「证书和密码」生成的客户端密码<b>值（Value）</b>，不是密码 ID。'],
		['name'=>'onedrive_path', 'label'=>'存储目录', 'placeholder'=>'pan/file', 'tip'=>'网盘里的目录，留空默认为 <b>pan/file</b>，目录不存在会自动创建。'],
	],
];
if($has_sae){
	$stor_fields['sae'] = [
		['name'=>'storagename', 'label'=>'SAE Storage 名称'],
	];
}

function stor_field($f){
	global $conf;
	$name = $f['name'];
	$type = isset($f['type']) ? $f['type'] : 'text';
	$value = isset($conf[$name]) ? $conf[$name] : (isset($f['default']) ? $f['default'] : '');
	$ph = isset($f['placeholder']) ? ' placeholder="'.htmlspecialchars($f['placeholder'], ENT_QUOTES, 'UTF-8').'"' : '';
	$html = '<div class="form-group"><label class="col-sm-3 control-label">'.htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8').'</label><div class="col-sm-9">';
	if($type === 'select'){
		$html .= '<select class="form-control" name="'.$name.'">';
		foreach($f['options'] as $ov=>$ol){
			$sel = ((string)$ov === (string)$value) ? ' selected' : '';
			$html .= '<option value="'.htmlspecialchars($ov, ENT_QUOTES, 'UTF-8').'"'.$sel.'>'.htmlspecialchars($ol, ENT_QUOTES, 'UTF-8').'</option>';
		}
		$html .= '</select>';
	}elseif($type === 'secret'){
		//密钥默认是圆点，点右边的眼睛才显示出来，免得后台一开就被旁边的人看走
		$html .= '<div class="input-group"><input type="password" class="form-control" name="'.$name.'" value="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'.$ph.' autocomplete="new-password"/>'
			.'<span class="input-group-btn"><button class="btn btn-default stor-eye" type="button" title="显示 / 隐藏"><i class="fa fa-eye"></i></button></span></div>';
	}else{
		$html .= '<input type="text" class="form-control" name="'.$name.'" value="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'.$ph.'/>';
	}
	if(!empty($f['tip']))$html .= '<p class="help-block">'.$f['tip'].'</p>';
	$html .= '</div></div>';
	return $html;
}
?>
<div class="container stor-page" style="padding-top:70px;">

<?php if(isset($_GET['onedrive'])){
	$od = $_GET['onedrive'];
	$od_map = [
		'ok' => ['success', 'OneDrive 授权成功，已经可以启用了。'],
		'noapp' => ['danger', '请先填写并保存 OneDrive 的应用 ID 和应用机密，再点获取授权。'],
		'state' => ['danger', '授权校验失败（state 不匹配），请重新点一次获取授权。'],
		'fail' => ['danger', '授权失败：'.(isset($_SESSION['onedrive_err']) ? htmlspecialchars($_SESSION['onedrive_err'], ENT_QUOTES, 'UTF-8') : '未知错误')],
	];
	unset($_SESSION['onedrive_err']);
	if(isset($od_map[$od])){?>
<div class="alert alert-<?php echo $od_map[$od][0]?>"><?php echo $od_map[$od][1]?></div>
<?php }}?>

<div class="panel panel-primary">
	<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-database"></i> 存储类型</h3></div>
	<div class="panel-body">
		<form onsubmit="return saveSetting(this)" method="post" role="form">
			<div class="stor-grid">
			<?php foreach($stor_list as $k=>$v){
				$cap = $stor_caps[$k];
				$tags = [];
				if($cap['up'])$tags[] = '直传';
				if($cap['down'])$tags[] = '直链下载';
				if($cap['range'])$tags[] = '断点续传';
				if(!$cap['cloud'])$tags[] = '本机磁盘';
			?>
				<label class="stor-card<?php echo $k === $storage ? ' active' : ''?>" data-stor="<?php echo $k?>">
					<input type="radio" name="storage" value="<?php echo $k?>"<?php echo $k === $storage ? ' checked' : ''?>/>
					<span class="stor-card-head">
						<i class="fa <?php echo $v['icon']?>"></i>
						<b><?php echo $v['name']?></b>
						<?php if($k === $storage){?><em class="stor-badge">使用中</em><?php }?>
					</span>
					<span class="stor-card-desc"><?php echo $v['desc']?></span>
					<span class="stor-card-tags"><?php foreach($tags as $t){?><i><?php echo $t?></i><?php }?></span>
				</label>
			<?php }?>
			</div>
			<div class="stor-submit">
				<p class="stor-warn"><i class="fa fa-exclamation-triangle"></i> 已经有文件的情况下不要随意切换，切换后之前上传的文件都会下不下来。切换前请先在下面填好对应存储的参数并测试通过。</p>
				<button type="submit" class="btn btn-primary">保存并启用</button>
			</div>
		</form>
	</div>
</div>

<div class="panel panel-info">
	<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-sliders"></i> 参数配置<small class="stor-config-name"></small></h3></div>
	<div class="panel-body stor-config-body">
	<?php foreach($stor_fields as $k=>$fields){?>
		<div class="stor-config" data-stor="<?php echo $k?>"<?php echo $k === $storage ? '' : ' style="display:none"'?>>
		<?php if($k === 'onedrive'){?>
			<div class="stor-oauth">
				<div class="stor-oauth-status">
					<span class="stor-dot <?php echo $onedrive_authed ? 'on' : 'off'?>"></span>
					<b><?php echo $onedrive_authed ? '已授权' : '未授权'?></b>
					<span id="onedrive_info" class="stor-oauth-info"><?php echo $onedrive_authed ? '正在读取账号信息…' : '填好下面的应用 ID 和应用机密并保存后，点右边的按钮完成授权。'?></span>
				</div>
				<div class="stor-oauth-btns">
					<a href="./set_stor.php?act=onedrive_auth" class="btn btn-primary btn-sm"><i class="fa fa-windows"></i> <?php echo $onedrive_authed ? '重新授权' : '获取授权'?></a>
					<?php if($onedrive_authed){?><button type="button" class="btn btn-default btn-sm" id="onedrive_unauth">解除授权</button><?php }?>
				</div>
			</div>
			<div class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-3 control-label">回调地址</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" value="<?php echo htmlspecialchars($onedrive_redirect, ENT_QUOTES, 'UTF-8')?>" readonly onclick="this.select()"/>
					<p class="help-block">在 <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noreferrer">Azure 应用注册</a>里新建应用，把这个地址<b>原样</b>填进「重定向 URI（Web）」，再到「API 权限」里加上 <b>Files.ReadWrite.All</b> 和 <b>offline_access</b>。</p>
				</div>
			</div>
			</div>
		<?php }?>
			<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
			<?php foreach($fields as $f){echo stor_field($f);}?>
				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9 stor-form-btns">
						<button type="submit" class="btn btn-primary">保存参数</button>
						<button type="button" class="btn btn-default stor-test" data-stor="<?php echo $k?>"><i class="fa fa-plug"></i> 连接测试</button>
						<?php if(!empty($stor_list[$k]['link'])){?>
						<a href="<?php echo $stor_list[$k]['link']?>" rel="noreferrer" target="_blank" class="btn btn-default"><i class="fa fa-external-link"></i> 开通地址</a>
						<?php }?>
						<span class="stor-test-result"></span>
					</div>
				</div>
			</form>
			<?php if(!$stor_caps[$k]['up'] && $stor_caps[$k]['cloud']){?>
			<div class="alert alert-info stor-note"><?php echo $stor_list[$k]['name']?> 的上传只能走本站中转（它的直传要用 PUT 上传会话，跟浏览器直传的表单方式对不上），大文件会占用本站带宽<?php echo $stor_caps[$k]['down'] ? '；下载可以在下面选直接链接，由存储自己扛流量。' : '，下载同理。'?></div>
			<?php }?>
			<div class="alert alert-warning stor-note">连接测试用的是<b>已经保存</b>的参数，改完参数请先点保存参数再测试。测试会往存储里写一个几十字节的小文件，读回来核对后再删掉。</div>
		</div>
	<?php }?>
	</div>
</div>

<?php
//先按当前存储的能力把面板和行的显示状态渲染好，避免页面加载时闪一下再被 JS 收起来
$cap_now = $stor_caps[$storage];
?>
<div class="panel panel-info" id="transfer_panel"<?php echo ($cap_now['up'] || $cap_now['down']) ? '' : ' style="display:none"'?>>
	<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-exchange"></i> 传输方式</h3></div>
	<div class="panel-body">
		<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
			<div class="form-group" id="row_upload"<?php echo $cap_now['up'] ? '' : ' style="display:none"'?>>
				<label class="col-sm-3 control-label">文件上传方式</label>
				<div class="col-sm-9"><select class="form-control" name="uploadfile_type">
					<option value="0"<?php echo $conf['uploadfile_type']!='1'?' selected':''?>>网站中转</option>
					<option value="1"<?php echo $conf['uploadfile_type']=='1'?' selected':''?>>直接链接</option>
				</select>
				<p class="help-block"><b>网站中转：</b>先传到本站服务器再转存到云存储，慢一些，但不用配跨域。<br/><b>直接链接：</b>浏览器直接把文件传到云存储，不占本站带宽，能传更大的文件，<b>需要先在云存储控制台设置跨域（CORS）</b>。</p></div>
			</div>
			<div class="form-group" id="row_down"<?php echo $cap_now['down'] ? '' : ' style="display:none"'?>>
				<label class="col-sm-3 control-label">文件下载方式</label>
				<div class="col-sm-9"><select class="form-control" name="downfile_type">
					<option value="0"<?php echo $conf['downfile_type']!='1'?' selected':''?>>网站中转</option>
					<option value="1"<?php echo $conf['downfile_type']=='1'?' selected':''?>>直接链接</option>
				</select>
				<p class="help-block"><b>网站中转：</b>下载经过本站服务器，本机和云存储内网互通的话不消耗云存储流量。<br/><b>直接链接：</b>直接从云存储下载，速度更快，但要付云存储的流量费。</p></div>
			</div>
			<div class="form-group" id="row_domain"<?php echo ($conf['downfile_type']=='1' && $cap_now['domain']) ? '' : ' style="display:none"'?>>
				<label class="col-sm-3 control-label">文件下载域名</label>
				<div class="col-sm-9">
					<div class="row">
						<div class="col-xs-4 col-md-3" style="padding-right:0">
							<select class="form-control" name="downfile_protocol">
								<option value="0"<?php echo $conf['downfile_protocol']!='1'?' selected':''?>>http://</option>
								<option value="1"<?php echo $conf['downfile_protocol']=='1'?' selected':''?>>https://</option>
							</select>
						</div>
						<div class="col-xs-8 col-md-9" style="padding-left:0">
							<input type="text" class="form-control" name="downfile_domain" value="<?php echo htmlspecialchars(isset($conf['downfile_domain']) ? $conf['downfile_domain'] : '', ENT_QUOTES, 'UTF-8')?>" placeholder="留空则使用云存储默认域名"/>
						</div>
					</div>
					<p class="help-block">填 Bucket 绑定的域名，也可以填 CDN 域名。<b>又拍云和七牛云必须填</b>，其余的留空就用云存储自带域名。</p>
				</div>
			</div>
			<div class="alert alert-info stor-note" id="row_onedrive_note"<?php echo $storage === 'onedrive' ? '' : ' style="display:none"'?>>OneDrive 的直链是微软给的一小时临时地址，本站会 302 过去，不消耗本站流量；代价是下载下来的文件名会变成站内的存储名（没有扩展名）。在意文件名就选网站中转。</div>
			<div class="form-group">
				<div class="col-sm-offset-3 col-sm-9"><button type="submit" class="btn btn-primary">保存传输方式</button></div>
			</div>
		</form>
	</div>
</div>

</div>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/2.3/skin/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var storCaps = <?php echo json_encode($stor_caps, JSON_UNESCAPED_UNICODE)?>;
var currentStor = <?php echo json_encode($storage)?>;

//点卡片就切到那个存储的参数表单，传输方式面板也跟着按能力显示
function showStor(k){
	if(!storCaps[k])return;
	$('.stor-card').removeClass('active');
	$('.stor-card[data-stor="'+k+'"]').addClass('active');
	$('.stor-config').hide();
	$('.stor-config[data-stor="'+k+'"]').show();
	$('.stor-config-name').text('· ' + storCaps[k].name);
	var cap = storCaps[k];
	$('#transfer_panel').toggle(cap.up || cap.down);
	$('#row_upload').toggle(cap.up);
	$('#row_down').toggle(cap.down);
	$('#row_onedrive_note').toggle(k === 'onedrive');
	$('#row_domain').toggle(cap.domain && $("select[name='downfile_type']").val() === '1');
}
$('.stor-card input[type=radio]').change(function(){ showStor($(this).val()); });
$("select[name='downfile_type']").change(function(){
	var k = $('.stor-card.active').data('stor');
	$('#row_domain').toggle($(this).val() === '1' && storCaps[k] && storCaps[k].domain);
});
showStor(currentStor);

$('.stor-eye').click(function(){
	var input = $(this).closest('.input-group').find('input');
	var show = input.attr('type') === 'password';
	input.attr('type', show ? 'text' : 'password');
	$(this).find('i').toggleClass('fa-eye', !show).toggleClass('fa-eye-slash', show);
});

$('.stor-test').click(function(){
	var btn = $(this), box = btn.closest('.stor-form-btns').find('.stor-test-result');
	box.removeClass('ok fail').text('测试中…');
	btn.prop('disabled', true);
	$.post('./set_stor.php', {ajax:'1', 'do':'test', storage:btn.data('stor')}, function(res){
		btn.prop('disabled', false);
		box.addClass(res.code === 0 ? 'ok' : 'fail').text(res.msg || '');
	}, 'json').fail(function(){
		btn.prop('disabled', false);
		box.addClass('fail').text('服务器错误，请查看 PHP 错误日志');
	});
});

<?php if($onedrive_authed){?>
$.post('./set_stor.php', {ajax:'1', 'do':'onedrive_info'}, function(res){
	if(res.code === 0){
		$('#onedrive_info').text('账号：' + (res.owner || '未知') + '　容量：已用 ' + res.used + ' / 共 ' + res.total + '（剩余 ' + res.remaining + '）');
	}else{
		$('#onedrive_info').addClass('fail').text('授权可能已失效：' + (res.msg || '') );
	}
}, 'json').fail(function(){ $('#onedrive_info').text('账号信息读取失败'); });
$('#onedrive_unauth').click(function(){
	if(!confirm('确定要解除本站保存的 OneDrive 授权吗？解除后 OneDrive 上已有的文件都会下不下来。'))return;
	$.post('./set_stor.php', {ajax:'1', 'do':'unauth'}, function(res){
		layer.alert(res.msg, {icon: res.code === 0 ? 1 : 2}, function(){ window.location.href = './set_stor.php'; });
	}, 'json');
});
<?php }?>

function checkURL(obj){
	var url = $(obj).val();
	url = url.replace(/ /g, '').replace(/，/g, ',');
	if(url.toLowerCase().indexOf('http://') == 0)url = url.slice(7);
	if(url.toLowerCase().indexOf('https://') == 0)url = url.slice(8);
	if(url.slice(url.length-1) == '/')url = url.slice(0, url.length-1);
	$(obj).val(url);
}
function saveSetting(obj){
	if($(obj).find("input[name='downfile_domain']").length > 0 && $(obj).find("input[name='downfile_domain']").val() != ''){
		checkURL($(obj).find("input[name='downfile_domain']"));
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {icon: 1, closeBtn: false}, function(){ window.location.reload(); });
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
			return false;
		}
	});
	return false;
}
</script>
</body>
</html>
