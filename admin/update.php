<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '程序更新日志';

/*
 * 这一页做两件事：
 *   ① 把本地装的版本号和 GitHub 上 main 分支的版本号摆在一起，告诉站长有没有新版本；
 *   ② 列出 main 分支最近的提交，等于把 GitHub 的 commits 页面搬进后台，能看到改了什么。
 *
 * 数据全部在服务端取（includes/update_check.php），结果缓存半小时。
 * GitHub 未登录时每个 IP 每小时只有 60 次额度，所以不要在页面里做轮询，
 * 「重新检查」也有 60 秒的最小间隔。
 */
include_once SYSTEM_ROOT.'update_check.php';

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

/*
 * 先把会话写回并解锁：下面要去访问 GitHub，慢的时候十几秒，
 * PHP 的文件会话是独占锁，不放开的话同一个管理员的其它标签页会一直排队等着。
 * 这之后只读配置、写 pre_config，不再动 $_SESSION。
 */
if(function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE)session_write_close();
//?force=1 是「重新检查」按钮，仍然受 UPDATE_FORCE_MIN 保护
$u = update_status(isset($_GET['force']) && $_GET['force'] == '1');
$badge = ['new'=>'label-warning', 'latest'=>'label-success', 'ahead'=>'label-info', 'unknown'=>'label-default', 'error'=>'label-default'];
$badge = isset($badge[$u['state']]) ? $badge[$u['state']] : 'label-default';
?>
<div class="container">
<div class="admin-page">

<div class="panel panel-primary">
  <div class="panel-heading update-head">
    <h3 class="panel-title"><i class="fa fa-cloud-download" aria-hidden="true"></i> 版本检查</h3>
    <div class="update-head-btns">
      <a class="btn btn-xs btn-default" href="./update.php?force=1"><i class="fa fa-refresh" aria-hidden="true"></i> 重新检查</a>
      <a class="btn btn-xs btn-default" href="<?php echo htmlspecialchars(update_commits_url(), ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer"><i class="fa fa-github" aria-hidden="true"></i> 去 GitHub 看</a>
    </div>
  </div>
  <div class="panel-body">
    <div class="update-state">
      <span class="label <?php echo $badge?>"><?php echo htmlspecialchars($u['text'], ENT_QUOTES, 'UTF-8')?></span>
      <span class="update-state-kv">本地版本 <b><?php echo intval($u['local_version'])?></b>（数据库 <?php echo intval($u['local_db'])?>）</span>
      <span class="update-state-kv">仓库版本 <b><?php echo $u['remote_version'] > 0 ? intval($u['remote_version']) : '—'?></b><?php echo $u['remote_db'] > 0 ? '（数据库 '.intval($u['remote_db']).'）' : ''?></span>
      <span class="update-state-kv">检查时间 <?php echo htmlspecialchars($u['checked_text'], ENT_QUOTES, 'UTF-8')?></span>
    </div>

<?php if($u['state'] === 'error'){?>
    <div class="alert alert-warning" style="margin:14px 0 0">
      <b>没查到版本信息。</b><?php echo htmlspecialchars($u['error'], ENT_QUOTES, 'UTF-8')?><br/>
      国内服务器连不上 <code>github.com</code> 是常见情况，可以直接点右上角「去 GitHub 看」在自己电脑上看。
    </div>
<?php }elseif($u['state'] === 'unknown'){?>
    <div class="alert alert-info" style="margin:14px 0 0">
      <b>提交列表取到了，版本号没取到，所以这次没法下「有没有新版本」的结论。</b><?php echo htmlspecialchars($u['error'], ENT_QUOTES, 'UTF-8')?><br/>
      下面的提交列表照常能看。这种情况多半是一时的网络问题，过几分钟会自动再试一次。
    </div>
<?php }elseif($u['state'] === 'new'){?>
    <div class="alert alert-warning" style="margin:14px 0 0">
      <b>仓库里有比当前站点更新的代码。</b>更新方式还是老样子：拿到新的全量包覆盖上传，<b>不要只传改动的几个文件</b>。
<?php if(!empty($u['need_db_update'])){?>
      <br/><b style="color:#b45309">这次动过数据库</b>（仓库 <?php echo intval($u['remote_db'])?> &gt; 本地 <?php echo intval($u['local_db'])?>）：传完文件必须再跑一次 <code>/install/update.php</code>，否则版本门禁会把整站拦住。
<?php }?>
    </div>
<?php }elseif($u['state'] === 'ahead'){?>
    <div class="alert alert-info" style="margin:14px 0 0">
      当前站点的版本号比仓库还高，一般是本地改完还没推到 GitHub。
    </div>
<?php }else{?>
    <div class="alert alert-success" style="margin:14px 0 0">
      版本号和仓库一致。<b>但版本号一样不代表代码一定一样</b>：<code>VERSION</code> 只在改了 <code>assets/js/</code> 这类静态资源时才提，只改 PHP 的提交不会动它。具体改了什么看下面的提交列表。
    </div>
<?php }?>
  </div>
</div>

<div class="panel panel-primary">
  <div class="panel-heading update-head">
    <h3 class="panel-title"><i class="fa fa-history" aria-hidden="true"></i> 最近提交（<?php echo htmlspecialchars(UPDATE_REPO.' · '.UPDATE_BRANCH, ENT_QUOTES, 'UTF-8')?>）</h3>
    <div class="update-head-btns">
      <span class="update-head-note">共 <?php echo intval($u['commit_count'])?> 条，完整历史在 GitHub</span>
    </div>
  </div>
  <div class="panel-body update-log">
<?php if(empty($u['commits'])){?>
    <p class="update-empty">没有取到提交记录。<?php echo !empty($u['error']) ? htmlspecialchars($u['error'], ENT_QUOTES, 'UTF-8') : ''?></p>
<?php }else{ foreach($u['commits'] as $c){
      $sha = preg_match('/^[0-9a-f]{40}$/', $c['sha']) ? $c['sha'] : '';
      if($sha === '')continue;
?>
    <div class="update-item">
      <div class="update-item-top">
        <a class="update-sha" href="<?php echo htmlspecialchars(update_commit_url($sha), ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer" title="在 GitHub 上打开这次提交"><?php echo substr($sha, 0, 7)?></a>
        <span class="update-title"><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8')?></span>
      </div>
      <div class="update-meta">
        <?php echo htmlspecialchars($c['author'], ENT_QUOTES, 'UTF-8')?>
        · <?php echo htmlspecialchars(update_time_ago($c['date']), ENT_QUOTES, 'UTF-8')?>
        <?php echo $c['date'] ? '· '.date('Y-m-d H:i', intval($c['date'])) : ''?>
      </div>
<?php   if(!empty($c['body'])){?>
      <div class="update-body"><?php echo htmlspecialchars($c['body'], ENT_QUOTES, 'UTF-8')?></div>
<?php   }?>
    </div>
<?php } }?>
  </div>
</div>

</div>
</div>
</body>
</html>
