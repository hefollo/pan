<?php
/*
 * 视频检测结果回调 / 轮询入口。
 *
 * 检测服务抽帧打分是异步跑的，跑完把结果 POST 到这里。地址是提交任务时连同一把
 * 一次性钥匙（k）一起给它的，这里认钥匙不认来源 IP——检测服务不一定在本机。
 * 没有这把钥匙，任何人都能往这里打一个「合规」结果，把待审的视频放出来。
 *
 * 同一个文件兼作轮询入口：
 *     curl -s 'https://你的站点/green_cb.php?poll=1&k=后台给的那串'
 * 挂到 crontab 上每分钟跑一次。回调丢失、检测服务重启、任务超时都靠它收尾——
 * 站点是先挂起再放行，回调要是丢了，文件会永远卡在待审。
 *
 * $nosecu 必须是 true：includes/txprotect.php 会拿 UA 和 Accept 头挡掉一批请求，
 * 其中就有「UA 含 python」和「没有 Accept 头」，检测服务的回调正好两条都占。
 */
$nosession = true;
$nosecu = true;
include("./includes/common.php");

@header('Content-Type: application/json; charset=utf-8');

$key = isset($_GET['k']) ? (string)$_GET['k'] : '';

//——————— 轮询入口 ———————
if(isset($_GET['poll'])){
	if($key === '' || !hash_equals(green_poll_key(), $key)){
		exit(json_encode(['code'=>-1, 'msg'=>'key 不正确']));
	}
	$done = green_video_poll(20);
	exit(json_encode(['code'=>1, 'msg'=>'ok', 'done'=>$done]));
}

//——————— 结果回调 ———————
if(!preg_match('/^[a-f0-9]{32}$/', $key)){
	exit(json_encode(['code'=>-1, 'msg'=>'缺少 k']));
}
//设了 token 的话再多认一道，纵深防御
if(!empty($conf['green_self_token'])){
	$sent = isset($_SERVER['HTTP_X_AUTH_TOKEN']) ? (string)$_SERVER['HTTP_X_AUTH_TOKEN'] : '';
	if(!hash_equals((string)$conf['green_self_token'], $sent)){
		exit(json_encode(['code'=>-1, 'msg'=>'token 不正确']));
	}
}

$job = $DB->getRow("SELECT * FROM `pre_greenjob` WHERE `cbkey`=:k LIMIT 1", [':k'=>$key]);
if(!$job){
	exit(json_encode(['code'=>-1, 'msg'=>'任务不存在']));
}
if(intval($job['status']) != 0){
	//回调重试和轮询可能撞在一起，已经处理过的直接回成功，让对面别再重试
	exit(json_encode(['code'=>1, 'msg'=>'已处理']));
}

$res = json_decode(file_get_contents('php://input'), true);
if(!is_array($res)){
	exit(json_encode(['code'=>-1, 'msg'=>'请求体不是 JSON']));
}
//钥匙对得上但任务号对不上，说明串了，不处理
if($job['job'] !== '' && isset($res['job']) && (string)$res['job'] !== $job['job']){
	exit(json_encode(['code'=>-1, 'msg'=>'任务号对不上']));
}

if(isset($res['status']) && $res['status'] === 'error'){
	//检测失败不在这里判死：记一次失败，交给轮询按重试次数决定是重试还是转人工
	$DB->exec("UPDATE `pre_greenjob` SET `tries`=`tries`+1,`updatetime`=NOW() WHERE `id`=:id LIMIT 1", [':id'=>$job['id']]);
	writeLog('视频检测失败（job '.$job['id'].'）：'.(isset($res['msg']) ? substr((string)$res['msg'], 0, 200) : ''));
	exit(json_encode(['code'=>1, 'msg'=>'已记录失败']));
}

apply_video_verdict($job, $res);
$DB->exec("UPDATE `pre_greenjob` SET `status`=1,`updatetime`=NOW() WHERE `id`=:id LIMIT 1", [':id'=>$job['id']]);
exit(json_encode(['code'=>1, 'msg'=>'ok']));
