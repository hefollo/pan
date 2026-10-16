<?php
// 独立 MySQL 集成回归：只创建/删除随机测试库，不读取站点 config.php。
// PAN_TEST_MYSQL_HOST / PORT / USER / PASSWORD 指定专用测试实例。
// php tools/regression/folder_shares.php（需要建库权限、pdo_mysql、curl、proc_open）
if(PHP_SAPI !== 'cli')exit;
$root = dirname(__DIR__, 2).'/';
define('ROOT', $root);
define('SYSTEM_ROOT', $root.'includes/');
define('SYS_KEY', 'folder-share-regression-only');
date_default_timezone_set('Asia/Shanghai');
require SYSTEM_ROOT.'autoloader.php';
Autoloader::register();
require SYSTEM_ROOT.'functions.php';
require SYSTEM_ROOT.'folders.php';
require SYSTEM_ROOT.'folder_shares.php';
require SYSTEM_ROOT.'layout_blocks.php';
$config = ['host'=>getenv('PAN_TEST_MYSQL_HOST') ?: '127.0.0.1', 'port'=>getenv('PAN_TEST_MYSQL_PORT') ?: '3306', 'user'=>getenv('PAN_TEST_MYSQL_USER') ?: 'root', 'pwd'=>getenv('PAN_TEST_MYSQL_PASSWORD') ?: ''];
$admin = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4', $config['user'], $config['pwd'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$config['dbname'] = 'pan_share_test_'.bin2hex(random_bytes(8));
$checks = 0;
$server = null;
$temp = sys_get_temp_dir().'/'.$config['dbname'];
function verify($ok, $message){
    global $checks;
    if(!$ok)throw new RuntimeException($message);
    $checks++;
    echo "PASS $message\n";
}
function query_ok($sql, $args = []){
    global $DB;
    if($DB->exec($sql, $args) === false)throw new RuntimeException($DB->error());
}
function identity($id){
    global $DB, $uid, $islogin2, $userrow;
    $uid = $id; $islogin2 = $id > 0;
    $userrow = $id ? $DB->getRow('SELECT * FROM pre_user WHERE uid=:u', [':u'=>$id]) : null;
}
function share_token($id = 11){
    global $DB;
    return $DB->getColumn('SELECT token FROM pre_folder_share WHERE folder_id=:f', [':f'=>$id]);
}
function request_test($path, $post = null, $headers = []){
    global $base, $temp;
    $c = curl_init($base.$path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_COOKIEFILE=>$temp.'/cookies', CURLOPT_COOKIEJAR=>$temp.'/cookies', CURLOPT_REFERER=>$base.'/', CURLOPT_HTTPHEADER=>$headers]);
    if($post !== null)curl_setopt_array($c, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $start = microtime(true); $response = curl_exec($c);
    if($response === false)throw new RuntimeException(curl_error($c));
    $r = ['status'=>curl_getinfo($c, CURLINFO_HTTP_CODE), 'header'=>substr($response, 0, curl_getinfo($c, CURLINFO_HEADER_SIZE)), 'body'=>substr($response, curl_getinfo($c, CURLINFO_HEADER_SIZE)), 'seconds'=>microtime(true)-$start];
    curl_close($c);
    return $r;
}
function csrf_from($html){
    preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}
try{
    $admin->exec('CREATE DATABASE `'.$config['dbname'].'` CHARACTER SET utf8mb4');
    $DB = new lib\PdoHelper($config);
    query_ok(file_get_contents(ROOT.'install/install.sql'));
    $schema = $DB->getRow('SHOW CREATE TABLE pre_folder_share')['Create Table'];
    query_ok(file_get_contents(ROOT.'install/update_1028.sql'));
    query_ok(file_get_contents(ROOT.'install/update_1028.sql'));
    verify($schema === $DB->getRow('SHOW CREATE TABLE pre_folder_share')['Create Table'], '新装结构与重复升级一致');
    query_ok('DROP TABLE pre_folder_share');
    query_ok(file_get_contents(ROOT.'install/update_1028.sql'));
    verify($schema === $DB->getRow('SHOW CREATE TABLE pre_folder_share')['Create Table'], '旧库新增分享表结构一致');
    mkdir($temp); mkdir($temp.'/files'); mkdir($temp.'/sessions');
    $conf = ['folder_open'=>1, 'folder_uids'=>'', 'storage'=>'local', 'filepath'=>$temp.'/files/', 'downfile_type'=>1, 'down_speed_user'=>0, 'down_speed_guest'=>0];
    $siteurl = 'http://127.0.0.1/'; $_SESSION = [];
    query_ok("INSERT INTO pre_user(uid,type,openid,nickname,enable,level_id,addtime,lasttime) VALUES (1001,'mail','one','one',1,4,NOW(),NOW()),(1002,'mail','two','two',1,4,NOW(),NOW())");
    query_ok("INSERT INTO pre_folder(id,uid,gkey,parent_id,name,addtime) VALUES (10,1001,'',0,'PRIVATE PARENT',NOW()),(11,1001,'',10,'分享 <root>',NOW()),(12,1001,'',11,'子目录',NOW()),(13,1001,'',0,'兄弟目录',NOW()),(14,1002,'',0,'别人目录',NOW())");
    $blob = str_repeat('0123456789', 1639); $hash = md5($blob); file_put_contents($temp.'/files/'.$hash, $blob);
    $insert = 'INSERT INTO pre_file(id,uid,folder_id,name,type,size,hash,storage,token,addtime,ip,hide,pwd,block) VALUES (:id,:uid,:folder,:name,:type,:size,:hash,\'local\',:token,NOW(),\'127.0.0.1\',:hide,:pwd,:block)';
    for($i=1;$i<=212;$i++){
        query_ok($insert, [':id'=>$i, ':uid'=>$i===212 ? 1002 : 1001, ':folder'=>$i===211 ? 13 : 11+($i%2), ':name'=>htmlspecialchars($i===1 ? 'special_%= & <name>.txt' : 'file-'.$i.'.txt', ENT_QUOTES, 'UTF-8'), ':type'=>$i===2 ? 'png' : 'txt', ':size'=>strlen($blob), ':hash'=>$hash, ':token'=>md5('file-'.$i), ':hide'=>$i===3 ? 1 : 0, ':pwd'=>$i===4 ? '0e123456' : '', ':block'=>$i===209 ? 1 : ($i===210 ? 2 : 0)]);
    }
    identity(0); verify(folder_share_manage('save',11)['code'] !== 0, '游客不能创建分享');
    identity(1002); verify(folder_share_manage('save',11)['code'] !== 0, '跨用户创建拒绝');
    identity(1001); verify(folder_share_manage('save',0)['code'] !== 0, '虚拟根不能分享');
    verify(folder_share_manage('save',11)['code'] === 0, '所有者创建分享');
    $token = share_token(); $ctx = folder_share_resolve($token);
    verify($ctx && $ctx['all'][11]['parent_id'] === 0 && !isset($ctx['all'][10],$ctx['all'][13]), '分享子树裁剪私人父目录和兄弟');
    verify(folder_share_listing($ctx,['folder'=>13]) === null, '伪造列表目录拒绝');
    verify(!folder_share_file($ctx,211) && !folder_share_file($ctx,212), '兄弟文件和跨账号文件拒绝');
    verify(!folder_share_file($ctx,209) && !folder_share_file($ctx,210), '冻结和待审文件拒绝');
    verify((bool)folder_share_file($ctx,3), '正常私密文件包含');
    $ids=[];
    for($page=1;$page<=6;$page++){
        $list=folder_share_listing($ctx,['mode'=>'all','page'=>$page]);
        verify($list['total']===208 && $list['pages']===6, '超过200文件分页计数 '.$page);
        $ids=array_merge($ids,array_column($list['files'],'id'));
    }
    verify(count($ids)===208 && count(array_unique($ids))===208, '全量分页不遗漏不重复');
    verify(folder_share_listing($ctx,['q'=>'_%= & <name>'])['total']===1, '搜索实体和LIKE特殊字符准确');
    verify(folder_share_listing($ctx,['type'=>'image'])['total']===1, '共享类型表筛选');
    foreach(['name','size','oldest','bad SQL'] as $sort)verify(folder_share_listing($ctx,['mode'=>'all','sort'=>$sort])['total']===208, '排序白名单 '.$sort);
    query_ok('UPDATE pre_file SET folder_id=13 WHERE id=1');
    verify(!folder_share_file(folder_share_resolve($token),1), '文件移出立即拒绝');
    query_ok('UPDATE pre_file SET folder_id=12 WHERE id=1');
    verify((bool)folder_share_file(folder_share_resolve($token),1), '文件移入立即包含');
    query_ok('UPDATE pre_folder SET parent_id=13 WHERE id=12');
    verify(!folder_share_file(folder_share_resolve($token),1), '子目录移出立即拒绝');
    query_ok("UPDATE pre_folder SET parent_id=11,name='改名目录' WHERE id=12");
    verify(folder_share_resolve($token)['all'][12]['name']==='改名目录', '目录移入和改名实时生效');
    verify(folder_share_manage('save',11,['password_mode'=>'set','password'=>"nul\0password"])['code']!==0, 'NUL密码拒绝');
    verify(folder_share_manage('save',11,['expiretime'=>'2000-01-01T00:00'])['code']!==0, '过去有效期拒绝');
    verify(folder_share_manage('save',11,['password_mode'=>'set','password'=>'share-pass'])['code']===0, '设置分享密码');
    $ctx=folder_share_resolve($token);
    verify(!folder_share_unlocked($ctx) && !folder_share_unlock($ctx,'wrong'), '未授权和错误密码拒绝');
    verify(folder_share_unlock($ctx,'share-pass') && folder_share_unlocked($ctx), '正确分享密码授权');
    $file=folder_share_file($ctx,4);
    verify(!folder_share_unlocked($ctx,$file) && !folder_share_unlock($ctx,'0e999999',$file), '单文件密码独立且拒绝科学计数法绕过');
    verify(folder_share_unlock($ctx,'0e123456',$file), '单文件密码正确授权');
    query_ok("UPDATE pre_file SET pwd='new-file-pass' WHERE id=4");
    verify(!folder_share_unlocked($ctx,folder_share_file($ctx,4)), '修改文件密码立即失效');
    folder_share_manage('save',11,['password_mode'=>'set','password'=>'new-share-pass']);
    verify(!folder_share_unlocked(folder_share_resolve($token)), '修改分享密码立即失效');
    folder_share_manage('reset',11); $newToken=share_token();
    verify($newToken!==$token && !folder_share_resolve($token), '重置链接旧地址失效');
    folder_share_manage('revoke',11); verify(!folder_share_resolve($newToken), '取消分享失效');
    folder_share_manage('save',11,['password_mode'=>'clear']); $token=share_token();
    verify($token!==$newToken && !folder_share_resolve($newToken), '重开分享不复活旧链接');
    query_ok("UPDATE pre_folder_share SET expiretime='2000-01-01' WHERE folder_id=11");
    verify(!folder_share_resolve($token), '过期拒绝');
    query_ok('UPDATE pre_folder_share SET expiretime=NULL WHERE folder_id=11');
    query_ok('UPDATE pre_user SET enable=0 WHERE uid=1001');
    verify(!folder_share_resolve($token) && folder_share_manage('save',11)['code']!==0, '禁用账号拒绝访问与管理');
    query_ok('UPDATE pre_user SET enable=1,level_id=0 WHERE uid=1001'); identity(1001);
    verify(folder_share_manage('save',11)['code']!==0 && folder_share_manage('revoke',11)['code']===0, '失去等级权限不能修改仍可撤销');
    query_ok('UPDATE pre_user SET level_id=4 WHERE uid=1001'); identity(1001);
    folder_share_manage('save',11); $token=share_token();
    $conf['folder_open']=0; verify(!folder_share_resolve($token), '站点总开关关闭拒绝'); $conf['folder_open']=1;
    query_ok('DELETE FROM pre_folder WHERE id=11'); verify(!folder_share_resolve($token), '删除分享根立即失效');
    verify(folder_share_manage('list',0)['shares'][0]['missing'] && folder_share_manage('revoke',11)['code']===0, '根删除后清单可见仍可撤销');
    query_ok("INSERT INTO pre_folder(id,uid,gkey,parent_id,name,addtime) VALUES(11,1001,'',10,'分享 <root>',NOW())");
    folder_share_manage('save',11,['password_mode'=>'set','password'=>'http-share-pass']); $token=share_token();

    // 临时本机router注入测试配置后执行实际公开页/AJAX，跳过用户common初始化。
    $router= <<<'PHP'
<?php
$root=getenv('PAN_TEST_ROOT');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(strpos($path,'/assets/')===0)return false;
define('IN_CRONLITE',true); define('ROOT',$root); define('SYSTEM_ROOT',$root.'includes/'); define('SYS_KEY','folder-share-regression-only'); define('VERSION','1638');
date_default_timezone_set('Asia/Shanghai');
ini_set('session.save_path',getenv('PAN_TEST_TEMP').'/sessions'); session_start();
require SYSTEM_ROOT.'autoloader.php'; Autoloader::register();
require SYSTEM_ROOT.'functions.php'; require SYSTEM_ROOT.'folders.php'; require SYSTEM_ROOT.'folder_shares.php';
$DB=new lib\PdoHelper(json_decode(getenv('PAN_TEST_DB'),true));
$conf=['folder_open'=>1,'storage'=>'local','filepath'=>getenv('PAN_TEST_TEMP').'/files/','downfile_type'=>1,'down_speed_user'=>0,'down_speed_guest'=>0];
$siteurl='http://'.$_SERVER['HTTP_HOST'].'/';
$uid=intval($_SERVER['HTTP_X_TEST_UID']??0); $islogin2=$uid>0; $userrow=$uid ? $DB->getRow('SELECT * FROM pre_user WHERE uid=:u',[':u'=>$uid]) : null;
$_SESSION['csrf_token']='test-csrf';
chdir(ROOT);
if($path==='/ajax.php'){require ROOT.'ajax.php';return;}
require ROOT.'share.php';
PHP;
    file_put_contents($temp.'/router.php',$router);
    putenv('PAN_TEST_ROOT='.ROOT); putenv('PAN_TEST_TEMP='.$temp); putenv('PAN_TEST_DB='.json_encode($config));
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr); $address=stream_socket_get_name($socket,false); fclose($socket);
    $base='http://'.$address;
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',ROOT,$temp.'/router.php'],[0=>['pipe','r'],1=>['file',$temp.'/http.log','a'],2=>['file',$temp.'/http.log','a']],$pipes,ROOT);
    if(!is_resource($server))throw new RuntimeException('测试服务器启动失败');
    for($i=0;$i<30;$i++){ $ready=@stream_socket_client('tcp://'.$address,$errno,$errstr,.1); if($ready){fclose($ready);break;} usleep(100000); }
    $path='/share.php?token='.$token;
    $r=request_test($path); verify($r['status']===200 && strpos($r['body'],'请输入分享密码')!==false, 'HTTP访客需要分享密码');
    $csrf=csrf_from($r['body']);
    verify(request_test($path,['password'=>'http-share-pass','csrf_token'=>'bad'])['status']===403, 'HTTP密码提交校验CSRF');
    verify(request_test($path.'&file=3&action=down')['status']===403, 'HTTP未解锁禁止下载');
    verify(request_test($path,['password'=>'http-share-pass','csrf_token'=>$csrf])['status']===303, 'HTTP分享解锁跳转');
    $r=request_test($path.'&mode=all');
    verify($r['status']===200 && strpos($r['body'],'PRIVATE PARENT')===false && strpos($r['body'],'&lt;root&gt;')!==false, 'HTTP公开列表转义且不泄漏父目录');
    $r=request_test($path.'&mode=all&page=6',null,['X-Pan-Share: 1']);
    verify(strpos($r['body'],'id="share-main"')!==false && strpos($r['body'],'<!doctype')===false, 'HTTP局部分页片段');
    verify(request_test($path.'&folder=13')['status']===404, 'HTTP越界目录拒绝');
    foreach([209,210,211,212] as $id)verify(request_test($path.'&file='.$id.'&action=down')['status']===404, 'HTTP受限文件拒绝 '.$id);
    $r=request_test($path.'&file=3&action=down');
    verify($r['status']===200 && $r['body']===$blob && stripos($r['header'],'private, no-store')!==false && stripos($r['header'],'Location:')===false, 'HTTP私密文件下载完整且不缓存不跳直链');
    $r=request_test($path.'&file=3&action=view',null,['Range: bytes=10-19']);
    verify($r['status']===206 && $r['body']===substr($blob,10,10), 'HTTP Range媒体分段');
    verify(request_test($path.'&file=3&action=view',null,['If-None-Match: "'.$hash.'"'])['status']===200, 'HTTP保护响应不走304复用');
    $r=request_test($path.'&file=3&action=text'); verify($r['status']===200 && $r['body']===$blob, 'HTTP文本预览');
    verify(request_test($path.'&file=4&action=down')['status']===403, 'HTTP单文件密码不能绕过');
    $csrf=csrf_from(request_test($path.'&file=4')['body']);
    verify(request_test($path.'&file=4',['password'=>'new-file-pass','csrf_token'=>$csrf])['status']===303, 'HTTP单文件解锁');
    verify(request_test($path.'&file=4&action=down')['body']===$blob, 'HTTP单文件解锁后下载');
    query_ok('UPDATE pre_file SET folder_id=13 WHERE id=4');
    verify(request_test($path.'&file=4&action=down')['status']===404, 'HTTP已解锁文件移出仍立即拒绝');
    query_ok('UPDATE pre_user SET down_speed=16 WHERE uid=1001');
    $r=request_test($path.'&file=3&action=down'); verify($r['seconds']>.75 && $r['body']===$blob, 'HTTP游客下载沿用上传者16KB限速');
    $r=json_decode(request_test('/ajax.php?act=folderShareSave',['id'=>11,'csrf_token'=>'bad'],['X-Test-Uid: 1001'])['body'],true);
    verify($r['code']!==0, 'AJAX管理CSRF拒绝');
    $r=json_decode(request_test('/ajax.php?act=folderShareRevoke',['id'=>11,'csrf_token'=>'test-csrf'],['X-Test-Uid: 1002'])['body'],true);
    verify($r['code']!==0, 'AJAX跨账号撤销拒绝');
    $r=json_decode(request_test('/ajax.php?act=folderShareRevoke',['id'=>11,'csrf_token'=>'test-csrf'],['X-Test-Uid: 1001'])['body'],true);
    verify($r['code']===0 && request_test($path.'&file=3&action=down')['status']===404, 'AJAX撤销后已授权HTTP请求立即失效');
    echo "OK $checks checks\n";
}finally{
    if(is_resource($server)){proc_terminate($server); proc_close($server);}
    $DB=null;
    $admin->exec('DROP DATABASE IF EXISTS `'.$config['dbname'].'`');
    // 只清理本脚本创建的随机临时目录，无递归shell删除。
    if(is_dir($temp)){
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}
        rmdir($temp);
    }
}
