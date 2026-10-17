<?php
// 离线执行真实身份判定、按钮渲染和个人中心归属门禁，不连接业务数据库。
if(PHP_SAPI !== 'cli')exit;
require dirname(__DIR__, 2).'/includes/folder_shares.php';
$source = file_get_contents(dirname(__DIR__, 2).'/share.php');
$begin = strpos($source, 'function share_url(');
$end = strpos($source, '$token = folder_share_input', $begin);
eval(substr($source, $begin, $end - $begin));
function folder_h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function verify($ok, $label){ if(!$ok)throw new RuntimeException($label); echo "PASS $label\n"; }
$token = 'test-share-token';
$ctx = ['share'=>['uid'=>1001]];
$file = ['id'=>3, 'name'=>'&lt;unsafe&gt;.jpg', 'hide'=>1];
foreach([[false,0,false],[true,1002,false],[true,1001,true],[true,0,false]] as $case){
    list($islogin2,$uid,$expected)=$case;
    $owner=folder_share_is_owner($ctx);
    verify($owner===$expected, '仅真实登录所有者可管理 '.$uid);
    ob_start(); share_actions($file); $html=ob_get_clean();
    verify(substr_count($html,'data-share-action=')===($expected ? 6 : 1), '管理按钮按身份显示 '.$uid);
    verify(strpos($html,'action=down')!==false && strpos($html,'data-share-nav')!==false && strpos($html,'data-share-action="copy"')!==false, '所有访问者都有下载查看复制');
    verify(strpos($html,'down.php')===false && strpos($html,'test-share-token')!==false, '复制下载保持分享授权范围');
    verify(strpos($html,'<unsafe>')===false && strpos($html,'&lt;unsafe&gt;')!==false, '文件名安全转义');
}
verify(!folder_share_is_owner(null), '失效分享没有管理身份');
// 执行真实写接口使用的归属助手，以异常代替 JSON exit，断言跨账号与游客均被拒。
$source=file_get_contents(dirname(__DIR__, 2).'/user.php');
$begin=strpos($source,'function uc_own_file('); $end=strpos($source,'$act = isset', $begin);
eval(substr($source,$begin,$end-$begin));
function uc_json($code,$msg){ throw new RuntimeException($msg); }
$DB=new class { function getRow($sql,$args){ return ['id'=>3,'uid'=>1001]; } };
foreach([0,1002] as $uid){
    $denied=false; try{ uc_own_file(3); }catch(RuntimeException $e){ $denied=true; }
    verify($denied,'实际服务端归属门禁拒绝 '.$uid);
}
$uid=1001; verify(uc_own_file(3)['id']===3,'所有者可通过实际归属门禁');
echo "OK\n";
