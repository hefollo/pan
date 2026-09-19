<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
if(defined('IN_CRONLITE'))return;
define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');
define('VERSION', '1632');
define('DB_VERSION', '1023');
date_default_timezone_set('Asia/Shanghai');
$date = date("Y-m-d H:i:s");

if(!$nosession){
	//会话 cookie 也必须 HttpOnly：PHPSESSID 被 JS 读走同样等于会话被劫持。
	//这里在 functions.php 之前执行，用不了 is_https()，就地判断一次协议
	$secure_cookie = (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1'))
		|| (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
		|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		|| (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https');
	if(PHP_VERSION_ID >= 70300){
		@session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'httponly'=>true, 'secure'=>$secure_cookie, 'samesite'=>'Lax']);
	}else{
		//7.3 以下没有 samesite 参数，拼在 path 后面浏览器一样认
		@session_set_cookie_params(0, '/; samesite=Lax', '', $secure_cookie, true);
	}
	session_start();
}

include_once(SYSTEM_ROOT.'txprotect.php');
include_once(SYSTEM_ROOT."autoloader.php");
Autoloader::register();

require ROOT.'config.php';

if(!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname'])//检测安装1
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

$DB = new \lib\PdoHelper($dbconfig);

if($DB->query("select * from pre_config where 1")==FALSE)//检测安装2
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

include_once(SYSTEM_ROOT."functions.php");
include_once(SYSTEM_ROOT."theme_recolor.php");

$conf=getAllSetting();
define('SYS_KEY', $conf['syskey']);
$password_hash='!@#%!s!0';

/*
 * 这三行原来排在下面的版本门禁后面，现在提到前面来。
 * 它们只依赖 is_https() 和 $_SERVER，跟 $conf 无关，位置提前没有副作用；
 * 而门禁里的「点此升级」链接要靠 site_root_url() 拼，那个函数读的正是 $siteurl。
 */
$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$sitepath.'/';

if (!$conf['version'] || $conf['version'] < DB_VERSION) {
    if (!$install) {
		header('Content-type:text/html;charset=utf-8');
		/*
		 * 升级地址必须按站点实际位置拼，不能写死 /install/update.php。
		 * 写死的是「域名根目录」下的绝对路径，站点装在子目录里（比如 https://x.com/pan/）
		 * 时那个链接指向 https://x.com/install/update.php，直接 404，而此时整站被门禁拦着，
		 * 前台后台都进不去，用户会以为网站彻底坏了。
		 *
		 * site_root_url() 会把当前脚本所在层级削掉，后台页面（/admin/xxx.php）触发门禁时
		 * 也能得到正确的站点根地址。
		 */
		$update_url = site_root_url().'install/update.php';
		echo '请先完成网站升级！<a href="'.htmlspecialchars($update_url, ENT_QUOTES, 'UTF-8').'"><font color=red>点此升级</font></a>';
		exit;
    }
}
/*
 * 记下后台目录的真实名字。
 *
 * 默认是 admin，但改名几乎是唯一一种后台隐藏手段，不少站长都会改。站内链接都是相对
 * 路径，改名没影响；麻烦的是要发到站外去的链接（比如内容检测的命中通知邮件）——那里
 * 拿不到当前请求路径，写死 /admin/ 就会给出一个打不开的地址。
 *
 * 只有后台目录里的脚本才会走到这段（IN_ADMIN 由各后台文件自己定义），目录名是从脚本在
 * 磁盘上的真实位置推出来的，外部伪造不了。值只在发生变化时才写一次库，平时一次都不写。
 */
if(defined('IN_ADMIN') && !empty($_SERVER['SCRIPT_FILENAME'])){
	$admin_here = @realpath(dirname($_SERVER['SCRIPT_FILENAME']));
	$admin_base = @realpath(ROOT);
	if($admin_here !== false && $admin_base !== false && strpos($admin_here, $admin_base) === 0){
		$admin_dir_now = trim(str_replace(DIRECTORY_SEPARATOR, '/', substr($admin_here, strlen($admin_base))), '/');
		if($admin_dir_now !== '' && $admin_dir_now !== (isset($conf['admin_dir']) ? (string)$conf['admin_dir'] : '')){
			saveSetting('admin_dir', $admin_dir_now);
			$conf['admin_dir'] = $admin_dir_now;
		}
	}
}


$clientip=real_ip($conf['ip_type']?$conf['ip_type']:0);
if(isset($_COOKIE["admin_token"]))
{
	$token=authcode(daddslashes($_COOKIE['admin_token']), 'DECODE', SYS_KEY);
	if($token){
		list($user, $sid, $expiretime) = explode("\t", $token);
		$session=md5($conf['admin_user'].$conf['admin_pwd'].$password_hash);
		if($session==$sid && $expiretime>time()) {
			$islogin=1;
		}
	}
}
if(isset($_COOKIE["user_token"]))
{
	$token=authcode(daddslashes($_COOKIE['user_token']), 'DECODE', SYS_KEY);
	if($token){
		list($uid, $sid, $expiretime) = explode("\t", $token);
		if($userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid='".intval($uid)."' LIMIT 1")){
			//邮箱账号的会话串里含密码哈希，改完密码其它设备的登录态会立刻失效；
			//快捷登录的账号算法不变，老 cookie 不受影响
			$session=user_session_hash($userrow);
			if($session===$sid && $expiretime>time()) {
				if($userrow['enable']==1){
					$islogin2=1;
					unset($_SESSION['user_block']);
				}else{
					$_SESSION['user_block'] = true;
				}
			}
		}
	}
}

if(defined('IN_ADMIN')) return;

$denyip = explode('|',$conf['blackip']);
if(in_array($clientip,$denyip) && !$islogin){
	Header("HTTP/1.1 403 Forbidden");
	exit;
}

include_once(SYSTEM_ROOT."vendor/autoload.php");

/*
 * 腾讯云 COS、华为云 OBS、七牛云这三个驱动要靠 includes/vendor/ 里的 Guzzle 才能工作。
 *
 * 那个目录是 composer 装出来的，按 .gitignore 不进版本库，所以更新包漏传、或者换了台新机器
 * 没跑过 composer install，它就会缺。缺了之后上面那句 include_once 只产生一条被 error_reporting
 * 屏蔽掉的警告，真正的报错要等到下面构造存储驱动时才抛「Class GuzzleHttp\... not found」——
 * COS 和 OBS 是在构造函数里就炸，也就是说每个前台页面都会 500，而线上 display_errors 关着，
 * 站长看到的只有一片白屏，完全没有线索。所以这里提前查一次，把原因和办法直接写出来。
 *
 * 用 class_exists 而不是 file_exists：目录传了一半、或者 autoload 映射坏掉也能一并查出来。
 * 本地存储、阿里云 OSS 等不依赖 Guzzle，缺了也照常跑，不要误伤。
 */
if(in_array($conf['storage'], ['qcloud','obs','qiniu'], true) && !class_exists('GuzzleHttp\Client')){
	sysmsg('<h2>缺少依赖目录 includes/vendor/</h2>'
		.'<p>当前存储方式是<b>'.\lib\StorHelper::name($conf['storage']).'</b>，它需要 <b>includes/vendor/</b> 里的依赖库，但该目录不存在或不完整。</p>'
		.'<p>处理办法（任选其一）：</p><ul>'
		.'<li>把更新包里的 <b>includes/vendor/</b> 整个目录补传到服务器；</li>'
		.'<li>或在服务器的 <b>includes</b> 目录下执行 <code>composer install</code>。</li>'
		.'</ul><p>在恢复之前，可以先到后台把存储方式切回<b>本地存储</b>，让网站继续对外服务。</p>');
	exit;
}

//加载存储模块
$stor = \lib\StorHelper::getModel($conf['storage']);

if (!file_exists(ROOT.'install/install.lock') && file_exists(ROOT.'install/index.php')) {
	sysmsg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="./install/">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是安装保护文件，如果检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装你的网站。<br/><br/>');exit;
}
?>
