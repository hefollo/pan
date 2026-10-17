<?php
// 内存数据库运行真实查询和模板，不读取站点配置或更改业务数据。
if(PHP_SAPI !== 'cli')exit;
require dirname(__DIR__, 2).'/includes/folders.php';
require dirname(__DIR__, 2).'/includes/folder_shares.php';
require dirname(__DIR__, 2).'/includes/admin_folders.php';
date_default_timezone_set('Asia/Shanghai');
$checks=0;
function check($ok,$label){ global $checks; if(!$ok)throw new RuntimeException($label); $checks++; echo "PASS $label\n"; }
function site_root_url(){ return 'https://example.test/'; }
function size_format($size){ return $size.' B'; }
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('NOW',function(){ return date('Y-m-d H:i:s'); });
$pdo->exec('CREATE TABLE pre_folder (id INTEGER PRIMARY KEY,uid INTEGER,gkey TEXT,parent_id INTEGER,name TEXT,addtime TEXT);
CREATE TABLE pre_user (uid INTEGER PRIMARY KEY,nickname TEXT,enable INTEGER);
CREATE TABLE pre_folder_share (id INTEGER PRIMARY KEY,folder_id INTEGER,uid INTEGER,enabled INTEGER,expiretime TEXT,token TEXT,password_hash TEXT);
CREATE TABLE pre_file (id INTEGER PRIMARY KEY,folder_id INTEGER,uid INTEGER,name TEXT DEFAULT \'\',type TEXT DEFAULT \'\',size INTEGER DEFAULT 0,block INTEGER DEFAULT 0,hide INTEGER DEFAULT 0,addtime TEXT DEFAULT \'\')');
$DB=new class($pdo){
    private $pdo;
    function __construct($pdo){ $this->pdo=$pdo; }
    function getAll($sql,$args=[]){ $s=$this->pdo->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC); }
    function getColumn($sql,$args=[]){ $s=$this->pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
    function getRow($sql,$args=[]){ $s=$this->pdo->prepare($sql); $s->execute($args); return $s->fetch(PDO::FETCH_ASSOC); }
};
$pdo->exec("INSERT INTO pre_user VALUES(1,'<script>nick</script>',1),(2,'second',1),(3,'disabled',0);
INSERT INTO pre_folder VALUES(1,1,'',0,'root','2026-10-17'),(2,1,'',1,'<unsafe>','2026-10-17'),(3,2,'',0,'other','2026-10-17'),(4,3,'',0,'blocked user','2026-10-17'),(5,1,'',0,'100%_literal','2026-10-17'),(6,1,'',0,'expired','2026-10-17'),(7,1,'',0,'cancelled','2026-10-17'),(8,0,'guest-key',0,'guest','2026-10-17');
INSERT INTO pre_folder_share VALUES(1,1,1,1,NULL,'active-token','hash'),(2,3,2,1,NULL,'other-token',''),(3,4,3,1,NULL,'disabled-token',''),(4,6,1,1,'2000-01-01','old-token',''),(5,7,1,0,NULL,'cancel-token','');
INSERT INTO pre_file(id,folder_id,uid,name,type,size,block) VALUES(1,2,1,'&lt;file&gt;.txt','txt',100,2),(2,2,2,'wrong owner','txt',1,0),(3,0,1,'root file','txt',1,0)");
$conf=['folder_open'=>1];
foreach([0,2] as $islogin){
    $denied=false; try{ admin_folder_listing([]); }catch(RuntimeException $e){ $denied=true; }
    check($denied,'非管理员身份拒绝 '.$islogin);
}
$islogin=1;
$listing=admin_folder_listing([]);
check($listing['total']===6,'管理员只看顶层目录，排除游客与后代目录');
$rows=array_column($listing['rows'],null,'id');
$inside=admin_folder_listing(['uid'=>1,'folder'=>1]);
$rows+=array_column($inside['rows'],null,'id');
check($rows[2]['path']==='root / <unsafe>','完整目录路径');
check($rows[2]['inherited']===['root'] && $rows[2]['share_state']==='none','未单独分享的子目录识别有效父分享');
check(intval($rows[2]['file_count'])===1,'本层文件计数严格按归属');
check($rows[1]['share_state']==='active' && $rows[6]['share_state']==='expired' && $rows[7]['share_state']==='revoked' && $rows[4]['share_state']==='unavailable','分享有效/过期/取消/账号禁用');
check(admin_folder_listing(['uid'=>2])['total']===1,'按UID筛选隔离');
check(admin_folder_listing(['q'=>'%_'])['total']===1,'名称LIKE特殊字符按字面匹配');
check(admin_folder_listing(['q'=>"' OR 1=1 --"])['total']===0,'搜索参数绑定');
foreach(['none'=>1,'active'=>2,'expired'=>1,'revoked'=>1,'unavailable'=>1] as $state=>$count){
    check(admin_folder_listing(['status'=>$state])['total']===$count,'状态筛选 '.$state);
}
$conf['folder_open']=0;
$off=admin_folder_listing(['uid'=>1]); $offrows=array_column($off['rows'],null,'id');
$offrows+=array_column(admin_folder_listing(['folder'=>1])['rows'],null,'id');
check($offrows[1]['share_state']==='unavailable' && !$offrows[2]['inherited'],'功能关闭后不误报有效分享');
$conf['folder_open']=1;
$bad=admin_folder_listing(['uid'=>[], 'q'=>[], 'status'=>[], 'page'=>[]]);
check($bad['page']===1 && $bad['total']===6,'非标量参数安全回退');
// 渲染真实模板，昵称与目录名不允许成为后台HTML。
$error=''; $listing=$inside; $source=file_get_contents(dirname(__DIR__, 2).'/admin/folders.php');
$template=substr($source,strpos($source,'?>')+2);
ob_start(); eval('?>'.$template); $html=ob_get_clean();
check(strpos($html,'<unsafe>')===false && strpos($html,'<script>nick')===false && strpos($html,'&lt;unsafe&gt;')!==false,'后台模板转义用户输入');
check(strpos($html,'folder=2')!==false && strpos($html,'返回上一级')!==false && strpos($html,'目录路径')!==false,'子目录可点击并具备面包屑和返回');
check(strpos($html,'hash')===false && strpos($html,'guest-key')===false,'不展示密码哈希和游客会话标识');
for($i=10;$i<100;$i++)$pdo->exec("INSERT INTO pre_folder VALUES($i,2,'',0,'extra $i','2026-10-17')");
$a=admin_folder_listing(['page'=>1]); $b=admin_folder_listing(['page'=>2]); $c=admin_folder_listing(['page'=>999]);
check(count($a['rows'])===40 && count($b['rows'])===40 && $c['page']===3 && count($c['rows'])===16,'顶层分页40条与末页边界');
check(!array_intersect(array_column($a['rows'],'id'),array_column($b['rows'],'id')),'相邻页无重复');
$inside=admin_folder_listing(['folder'=>1]);
check($inside['uid']===1 && count($inside['rows'])===1 && intval($inside['rows'][0]['id'])===2,'进入目录仅显示直属子目录并推导所属UID');
$leaf=admin_folder_listing(['folder'=>2]);
check(!$leaf['rows'] && count($leaf['files'])===1 && intval($leaf['files'][0]['id'])===1,'叶子目录只显示本层正确归属的文件');
check($leaf['files'][0]['block']==2 && $leaf['files'][0]['name']==='&lt;file&gt;.txt','后台保留待审文件元数据');
check($leaf['current']['inherited']===['root'] && $leaf['current']['share_state']==='none','进入叶子仍显示父分享覆盖和本目录状态');
check(array_column($leaf['breadcrumbs'],'id')===[1,2],'面包屑按层级排列');
check(admin_folder_listing(['uid'=>1])['file_total']===1,'用户根目录显示根文件');
check(!admin_folder_listing([])['files'],'全用户总入口不混列各用户文件');
foreach([['uid'=>2,'folder'=>2],['folder'=>999],['folder'=>8]] as $input){
    $denied=false; try{ admin_folder_listing($input); }catch(InvalidArgumentException $e){ $denied=true; }
    check($denied,'拒绝错误归属/不存在/游客目录 '.json_encode($input));
}
check(admin_folder_listing(['folder'=>1,'q'=>'other'])['total']===0,'搜索只在当前层级内');
for($i=10;$i<95;$i++)$pdo->exec("INSERT INTO pre_file(id,folder_id,uid,name,type,size) VALUES($i,2,1,'file $i','txt',1)");
$files1=admin_folder_listing(['folder'=>2]); $files2=admin_folder_listing(['folder'=>2,'file_page'=>2]); $files3=admin_folder_listing(['folder'=>2,'file_page'=>999]);
check(count($files1['files'])===40 && count($files2['files'])===40 && count($files3['files'])===6 && $files3['file_page']===3,'目录文件独立分页与边界');
check(!array_intersect(array_column($files1['files'],'id'),array_column($files2['files'],'id')),'文件相邻页无重复');
$listing=$leaf; ob_start(); eval('?>'.$template); $html=ob_get_clean();
check(strpos($html,'&lt;file&gt;.txt')!==false && strpos($html,'<file>')===false && strpos($html,'file-view.php?id=1')!==false,'文件名安全显示且可查看文件');
// 跟随真实页面链接：总入口 -> 顶层目录 -> 返回上一级，不得悄悄切成单用户列表。
$listing=admin_folder_listing(['q'=>'extra','page'=>2]);
$start=$listing;
ob_start(); eval('?>'.$template); $html=ob_get_clean();
$dom=new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
$xpath=new DOMXPath($dom);
$link=$xpath->query('//table//td/a[strong]')->item(0)->getAttribute('href');
parse_str(parse_url($link,PHP_URL_QUERY),$child_input);
$listing=admin_folder_listing($child_input);
ob_start(); eval('?>'.$template); $html=ob_get_clean();
$dom=new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
$xpath=new DOMXPath($dom);
$back=$xpath->query('//a[contains(.,"返回上一级")]')->item(0)->getAttribute('href');
parse_str(parse_url($back,PHP_URL_QUERY),$parent_input);
$returned=admin_folder_listing($parent_input);
check($returned['uid']===$start['uid'] && $returned['q']===$start['q'] && $returned['page']===$start['page'] && $returned['total']===$start['total'],'真实链接往返保留全用户范围、筛选和分页');
$root=admin_folder_listing(['q'=>'root']);
parse_str(parse_url(admin_folder_child_link($root,['uid'=>1,'id'=>1]),PHP_URL_QUERY),$args);
$middle=admin_folder_listing($args);
$middle['q']='<unsafe>';
parse_str(parse_url(admin_folder_child_link($middle,['uid'=>1,'id'=>2]),PHP_URL_QUERY),$args);
$leafnav=admin_folder_listing($args);
parse_str(parse_url(admin_folder_up_link($leafnav),PHP_URL_QUERY),$args);
$backmiddle=admin_folder_listing($args);
check($backmiddle['folder']===1 && $backmiddle['q']==='<unsafe>','多层返回恢复直属父目录筛选');
parse_str(parse_url(admin_folder_up_link($backmiddle),PHP_URL_QUERY),$args);
check(admin_folder_listing($args)['uid']===0 && admin_folder_listing($args)['q']==='root','连续返回保持原全用户入口');
parse_str(parse_url(admin_folder_ancestor_link($leafnav,1,1),PHP_URL_QUERY),$args);
check(admin_folder_listing($args)['q']==='<unsafe>','面包屑恢复对应祖先列表上下文');
$userroot=admin_folder_listing(['uid'=>1,'status'=>'active']);
parse_str(parse_url(admin_folder_child_link($userroot,['uid'=>1,'id'=>1]),PHP_URL_QUERY),$args);
$userchild=admin_folder_listing($args);
parse_str(parse_url(admin_folder_up_link($userchild),PHP_URL_QUERY),$args);
check($args['uid']==1 && $args['folder']==0 && $args['status']==='active','单用户入口返回仍为单用户根并恢复分享筛选');
check(admin_folder_up_link($userroot)==='./folders.php','用户根目录可返回全部用户');
$direct=admin_folder_listing(['folder'=>2]);
parse_str(parse_url(admin_folder_up_link($direct),PHP_URL_QUERY),$args);
check($args['uid']==1 && $args['folder']==1,'旧直达链接无来源时按真实父目录返回');
foreach(['not-base64',admin_folder_nav_value([['uid'=>2,'folder'=>1]]),admin_folder_nav_value([['uid'=>1,'folder'=>999]]),str_repeat('a',13000)] as $badnav){
    check(!admin_folder_listing(['folder'=>2,'nav'=>$badnav])['trail'],'异常或跨用户导航上下文被丢弃');
}
parse_str(parse_url(admin_folder_link($leafnav,['file_page'=>2]),PHP_URL_QUERY),$args);
check($args['folder']==2 && !empty($args['nav']),'文件分页保留当前目录和返回链');
$listing=$leaf; ob_start(); eval('?>'.$template); $html=ob_get_clean();
check(strpos($html,'文件夹名称')===false && strpos($html,'文件夹分页')===false && strpos($html,'文件分页')===false,'叶子目录不渲染空目录表和单页分页');
$listing=admin_folder_listing(['folder'=>1,'q'=>'absent']); ob_start(); eval('?>'.$template); $html=ob_get_clean();
check(substr_count($html,'role="status"')===1 && strpos($html,'清除本层筛选')!==false,'无匹配结果显示统一空态并可清除筛选');
$global=admin_folder_listing(['q'=>'extra','page'=>2]);
parse_str(parse_url(admin_folder_owner_link($global,2),PHP_URL_QUERY),$args);
$ownerroot=admin_folder_listing($args);
parse_str(parse_url(admin_folder_child_link($ownerroot,['uid'=>2,'id'=>99]),PHP_URL_QUERY),$args);
$ownerchild=admin_folder_listing($args);
parse_str(parse_url(admin_folder_up_link($ownerchild),PHP_URL_QUERY),$args);
$ownerback=admin_folder_listing($args);
check($ownerback['uid']===2 && $ownerback['folder']===0 && count($ownerback['trail'])===1,'全用户到用户根再到目录可逐级返回');
parse_str(parse_url(admin_folder_up_link($ownerback),PHP_URL_QUERY),$args);
$globalback=admin_folder_listing($args);
check($globalback['uid']===0 && $globalback['q']==='extra' && $globalback['page']===2,'用户根返回总览也保留原筛选和页码');
echo "OK $checks checks\n";
