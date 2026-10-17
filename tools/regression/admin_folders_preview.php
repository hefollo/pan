<?php
// 本地浏览器验收：php -S 127.0.0.1:8197 -t . tools/regression/admin_folders_preview.php
// 仅合成数据与内存SQLite，不读取config.php、不写业务库；不部署此开发脚本。
if(PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true))exit;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if(strpos($path, '/assets/') === 0 || $path === '/favicon.ico')return false;
if($path === '/admin/login.php'){ echo '本地测试：需要管理员登录'; return; }
if($path !== '/admin/folders.php'){ http_response_code(404); echo '本地测试只提供用户文件夹页面'; return; }
define('IN_CRONLITE', true);
define('ROOT', dirname(__DIR__, 2).'/');
define('SYSTEM_ROOT', ROOT.'includes/');
define('VERSION', '1640');
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ERROR | E_PARSE);
require SYSTEM_ROOT.'functions.php';
require SYSTEM_ROOT.'folders.php';
require SYSTEM_ROOT.'folder_shares.php';
require SYSTEM_ROOT.'theme_recolor.php';
$theme = isset($_GET['preview_theme']) && is_string($_GET['preview_theme']) ? $_GET['preview_theme'] : ($_COOKIE['pan_preview_theme'] ?? 'console');
if(!in_array($theme, site_theme_keys(), true))$theme = 'console';
setcookie('pan_preview_theme', $theme, ['path'=>'/', 'httponly'=>true, 'samesite'=>'Lax']);
$conf = ['title'=>'本地验收 · 用户文件夹', 'site_theme'=>$theme, 'folder_open'=>1, 'apiurl'=>'http://'.$_SERVER['HTTP_HOST'].'/'];
$siteurl = 'http://'.$_SERVER['HTTP_HOST'].'/admin/';
$islogin = isset($_GET['fixture_auth']) && $_GET['fixture_auth'] === '0' ? 0 : 1;
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('NOW', function(){ return date('Y-m-d H:i:s'); });
$pdo->exec("CREATE TABLE pre_folder(id INTEGER PRIMARY KEY,uid INTEGER,gkey TEXT DEFAULT '',parent_id INTEGER,name TEXT,addtime TEXT);
CREATE TABLE pre_user(uid INTEGER PRIMARY KEY,nickname TEXT,enable INTEGER);
CREATE TABLE pre_folder_share(id INTEGER PRIMARY KEY,folder_id INTEGER,uid INTEGER,enabled INTEGER,expiretime TEXT,token TEXT,password_hash TEXT);
CREATE TABLE pre_file(id INTEGER PRIMARY KEY,folder_id INTEGER,uid INTEGER,name TEXT,type TEXT,size INTEGER,block INTEGER,hide INTEGER,addtime TEXT);
INSERT INTO pre_user VALUES(1,'测试用户A',1),(2,'测试用户B',1),(3,'停用测试账号',0);
INSERT INTO pre_folder VALUES(1,1,'',0,'项目资料','2026-10-17 09:00:00'),(2,1,'',1,'图片','2026-10-17 09:01:00'),(3,1,'',2,'空目录','2026-10-17 09:02:00'),(4,2,'',0,'另一个用户','2026-10-17 09:00:00'),(5,1,'',0,'已取消分享','2026-10-17 09:00:00'),(6,1,'',0,'已过期分享','2026-10-17 09:00:00'),(7,3,'',0,'账号停用的分享','2026-10-17 09:00:00');
INSERT INTO pre_folder_share VALUES(1,1,1,1,NULL,'preview-only','hash'),(2,5,1,0,NULL,'cancelled',''),(3,6,1,1,'2000-01-01','expired',''),(4,7,3,1,NULL,'disabled','');
INSERT INTO pre_file VALUES(1,2,1,'风景 &amp; 夜色.jpg','jpg',269000,0,1,'2026-10-17 09:00:00'),(2,0,1,'根目录说明.txt','txt',1024,0,0,'2026-10-17 09:00:00'),(3,2,1,'待复核文件.txt','txt',1024,2,0,'2026-10-17 09:00:00')");
$pdo->exec("INSERT INTO pre_folder VALUES(8,1,'',2,'需要换行展示的长文件夹名称_abcdefghijklmnopqrstuvwx_资料归档','2026-10-17 09:02:00');
INSERT INTO pre_file VALUES(4,2,1,'需要换行展示的长文件名称_abcdefghijklmnopqrstuvwx_附件说明.txt','txt',1024,0,1,'2026-10-17 09:00:00')");
for($i=10;$i<95;$i++)$pdo->exec("INSERT INTO pre_folder VALUES($i,2,'',0,'分页目录 $i','2026-10-17 09:00:00')");
for($i=10;$i<55;$i++)$pdo->exec("INSERT INTO pre_file VALUES($i,1,1,'文件 $i.txt','txt',1024,0,0,'2026-10-17 09:00:00')");
$DB = new class($pdo){
    private $pdo;
    function __construct($pdo){ $this->pdo=$pdo; }
    function getAll($sql,$args=[]){ $s=$this->pdo->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); }
    function getColumn($sql,$args=[]){ $s=$this->pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
    function getRow($sql,$args=[]){ $s=$this->pdo->prepare($sql); $s->execute($args); return $s->fetch(PDO::FETCH_ASSOC); }
};
chdir(ROOT.'admin');
$_SERVER['SCRIPT_NAME'] = '/admin/folders.php';
require ROOT.'admin/folders.php';
