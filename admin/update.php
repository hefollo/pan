<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '程序更新日志';

/*
 * 这一页做两件事：
 *   ① 把本地装的版本号和 GitHub 上 main 分支的版本号摆在一起，告诉站长有没有新版本；
 *   ② 列出 main 分支最近的提交，等于把 GitHub 的 commits 页面搬进后台，能看到改了什么；
 *   ③ 在线更新：下载仓库最新提交的 zip 覆盖站点文件，带备份和还原（includes/online_update.php，DEC-20261003-001）。
 * 上面三样默认都从 GitHub 取；服务器连不上 GitHub 时，站长可以用页面上的「更新源」下拉框
 * 换成作者自建的 Gitea 镜像（清单写死在 update_check.php，DEC-20261005-002），三样跟着一起换。
 *
 * 数据全部在服务端取（includes/update_check.php），结果缓存半小时。
 * GitHub 未登录时每个 IP 每小时只有 60 次额度，所以不要在页面里做轮询，
 * 「重新检查」也有 60 秒的最小间隔。
 */
include_once SYSTEM_ROOT.'update_check.php';
include_once SYSTEM_ROOT.'online_update.php';

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

//在线更新的令牌存在会话里，必须在下面关会话之前取
$oupd_token = oupd_token();

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

$oupd_errs = oupd_requirements();
$oupd_target = (!empty($u['commits']) && isset($u['commits'][0]['sha']) && preg_match('/^[0-9a-f]{40}$/', $u['commits'][0]['sha'])) ? $u['commits'][0] : null;
$oupd_installed = isset($u['installed_sha']) ? $u['installed_sha'] : '';
$oupd_backups = $oupd_errs ? [] : oupd_backups();
$oupd_last = json_decode((string)getSetting('update_last'), true);

$update_sources = update_sources();
$update_source = update_source();
$update_source_name = update_source_name();
//换源的提示里要说「换成哪个」：当前是 GitHub 就指备用源，反过来指 GitHub
$update_other_name = '';
foreach($update_sources as $k => $s){
	if($k !== $update_source){ $update_other_name = $s['name']; break; }
}
?>
<div class="container">
<div class="admin-page">

<div class="panel panel-primary">
  <div class="panel-heading update-head">
    <h3 class="panel-title"><i class="fa fa-cloud-download" aria-hidden="true"></i> 版本检查</h3>
    <div class="update-head-btns">
      <a class="btn btn-xs btn-default" href="./update.php?force=1"><i class="fa fa-refresh" aria-hidden="true"></i> 重新检查</a>
      <a class="btn btn-xs btn-default" href="<?php echo htmlspecialchars(update_commits_url(), ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer"><i class="fa <?php echo $update_source === 'github' ? 'fa-github' : 'fa-git'?>" aria-hidden="true"></i> 去<?php echo $update_source === 'github' ? ' GitHub ' : htmlspecialchars($update_source_name, ENT_QUOTES, 'UTF-8')?>看</a>
    </div>
  </div>
  <div class="panel-body">
<?php if(count($update_sources) > 1){?>
    <div class="update-source">
      <label for="update-source">更新源</label>
      <select id="update-source" class="form-control input-sm">
<?php   foreach($update_sources as $k => $s){?>
        <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8')?>"<?php echo $k === $update_source ? ' selected' : ''?>><?php echo htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8')?></option>
<?php   }?>
      </select>
      <span class="update-source-note"><?php echo $update_source === 'github' ? '服务器连不上 GitHub 时可以换成备用源，版本检查、提交列表和在线更新都会跟着换。' : '备用源定时从 GitHub 同步，可能比 GitHub 晚几个小时才看到新提交。'?></span>
    </div>
<?php }?>
    <div class="update-state">
      <span class="label <?php echo $badge?>"><?php echo htmlspecialchars($u['text'], ENT_QUOTES, 'UTF-8')?></span>
      <span class="update-state-kv">本地版本 <b><?php echo intval($u['local_version'])?></b>（数据库 <?php echo intval($u['local_db'])?>）</span>
      <span class="update-state-kv">仓库版本 <b><?php echo $u['remote_version'] > 0 ? intval($u['remote_version']) : '—'?></b><?php echo $u['remote_db'] > 0 ? '（数据库 '.intval($u['remote_db']).'）' : ''?></span>
      <span class="update-state-kv">检查时间 <?php echo htmlspecialchars($u['checked_text'], ENT_QUOTES, 'UTF-8')?></span>
    </div>

<?php if($u['state'] === 'error'){?>
    <div class="alert alert-warning" style="margin:14px 0 0">
      <b>没查到版本信息。</b><?php echo htmlspecialchars($u['error'], ENT_QUOTES, 'UTF-8')?><br/>
<?php   if($update_source === 'github'){?>
      国内服务器连不上 <code>github.com</code> 是常见情况<?php echo $update_other_name !== '' ? '，可以把上面的「更新源」换成'.htmlspecialchars($update_other_name, ENT_QUOTES, 'UTF-8').'再试' : ''?>；也可以直接点右上角「去 GitHub 看」在自己电脑上看。
<?php   }else{?>
      <?php echo htmlspecialchars($update_source_name, ENT_QUOTES, 'UTF-8')?>暂时连不上，可以过一会儿再试，或者把上面的「更新源」换回 GitHub。
<?php   }?>
    </div>
<?php }elseif($u['state'] === 'unknown'){?>
    <div class="alert alert-info" style="margin:14px 0 0">
      <b>提交列表取到了，版本号没取到，所以这次没法下「有没有新版本」的结论。</b><?php echo htmlspecialchars($u['error'], ENT_QUOTES, 'UTF-8')?><br/>
      下面的提交列表照常能看。这种情况多半是一时的网络问题，过几分钟会自动再试一次。
    </div>
<?php }elseif($u['state'] === 'new'){?>
    <div class="alert alert-warning" style="margin:14px 0 0">
      <b>仓库里有比当前站点更新的代码。</b>可以用下面的「在线更新」，或者照老办法拿新的全量包覆盖上传（<b>不要只传改动的几个文件</b>）。
<?php if(!empty($u['need_db_update'])){?>
      <br/><b style="color:#b45309">这次动过数据库</b>（仓库 <?php echo intval($u['remote_db'])?> &gt; 本地 <?php echo intval($u['local_db'])?>）：先更新程序文件，再升级数据库。用下面的「在线更新」时，更新完成会直接出现「立即升级数据库」按钮；手工上传的话，传完打开后台会看到同样的按钮，也可以访问 <code>/install/update.php</code>。
<?php }?>
    </div>
<?php }elseif(!empty($u['source_behind'])){?>
    <div class="alert alert-info" style="margin:14px 0 0">
      <b><?php echo $update_source === 'github' ? 'GitHub ' : htmlspecialchars($update_source_name, ENT_QUOTES, 'UTF-8')?>还没同步到站点现在装的提交 <code><?php echo substr($u['installed_sha'], 0, 7)?></code>。</b>站点比这个更新源新，不需要更新；它同步之后这里会自动恢复正常。想马上看有没有更新的提交，可以把上面的「更新源」换成<?php echo $update_other_name === 'GitHub' ? ' GitHub' : htmlspecialchars($update_other_name, ENT_QUOTES, 'UTF-8')?>。
    </div>
<?php }elseif($u['state'] === 'ahead'){?>
    <div class="alert alert-info" style="margin:14px 0 0">
      当前站点的版本号比仓库还高，一般是本地改完还没推到 GitHub<?php echo $update_source === 'github' ? '' : '，或者'.htmlspecialchars($update_source_name, ENT_QUOTES, 'UTF-8').'还没同步过来'?>。
    </div>
<?php }elseif(isset($u['behind']) && $u['behind'] === 0){?>
    <div class="alert alert-success" style="margin:14px 0 0">
      站点装的就是仓库最新的提交 <code><?php echo substr($u['installed_sha'], 0, 7)?></code>（上次在线更新时记下的）。
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
    <h3 class="panel-title"><i class="fa fa-download" aria-hidden="true"></i> 在线更新</h3>
  </div>
  <div class="panel-body">
<?php if($oupd_errs){?>
    <div class="alert alert-warning" style="margin:0">
      <b>这台服务器暂时用不了在线更新：</b><br/><?php echo implode('<br/>', array_map(function($e){ return htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); }, $oupd_errs))?><br/>
      还是可以照老办法上传全量包。
    </div>
<?php }elseif(!$oupd_target){?>
    <div class="alert alert-warning" style="margin:0">
      没取到仓库的提交列表，不知道该更新到哪个提交。等上面的版本检查恢复正常后再来<?php echo $update_other_name !== '' ? '，或者换一个更新源' : ''?>。
    </div>
<?php }elseif(!empty($u['source_behind'])){?>
    <div class="alert alert-info" style="margin:0">
      当前更新源比站点旧，用它更新会把站点改回旧代码，所以这里不提供更新。等它同步后再来，或者换一个更新源。
    </div>
<?php }else{?>
    <div class="oupd-kv">
      <div><span>站点当前</span><?php if($oupd_installed !== ''){?><code><?php echo substr($oupd_installed, 0, 7)?></code><?php }else{?><em>未知（之前是手工上传的包）</em><?php }?></div>
      <div><span>更新到</span><code><?php echo substr($oupd_target['sha'], 0, 7)?></code> <?php echo htmlspecialchars($oupd_target['title'], ENT_QUOTES, 'UTF-8')?></div>
      <div><span>下载来源</span><?php echo htmlspecialchars($update_sources[$update_source]['label'], ENT_QUOTES, 'UTF-8')?></div>
<?php   if(is_array($oupd_last) && !empty($oupd_last['time'])){?>
      <div><span>上次在线更新</span><?php echo date('Y-m-d H:i', intval($oupd_last['time']))?>，改 <?php echo intval(isset($oupd_last['changed']) ? $oupd_last['changed'] : 0)?> 个、新增 <?php echo intval(isset($oupd_last['added']) ? $oupd_last['added'] : 0)?> 个文件</div>
<?php   }?>
    </div>
    <div class="oupd-btns">
      <button type="button" class="btn btn-primary btn-sm" id="oupd-check" data-sha="<?php echo $oupd_target['sha']?>"><i class="fa fa-search" aria-hidden="true"></i> <?php echo $oupd_installed === $oupd_target['sha'] ? '重新核对站点文件' : '检查要更新哪些文件'?></button>
    </div>
    <div id="oupd-result"></div>
    <ul class="oupd-notes">
      <li>先「检查」只下载和比对，不改任何文件；确认后才覆盖。</li>
      <li>覆盖前会把要被替换的旧文件打成备份，下方可以一键还原；写到一半出错会自动退回原样。</li>
      <li>不会动：<code>config.php</code>、<code>install/install.lock</code>、<code>includes/vendor/</code>、上传的文件和自定义配色；仓库里删掉的文件也不会从站点删除。</li>
      <li>站点上直接改过的程序文件会被仓库版本覆盖（备份里有原件）。</li>
    </ul>
<?php }?>
<?php if($oupd_backups){?>
    <div class="oupd-backups">
      <div class="oupd-sub">更新备份（保留最近 <?php echo OUPD_KEEP_BACKUPS?> 份）</div>
<?php   foreach($oupd_backups as $b){?>
      <div class="oupd-backup">
        <div>
          <b><?php echo date('Y-m-d H:i', intval($b['time']))?></b>
          <span class="oupd-muted"><?php echo $b['from_sha'] ? substr($b['from_sha'], 0, 7) : '未知'?> → <?php echo $b['to_sha'] ? substr($b['to_sha'], 0, 7) : '?'?> · 覆盖 <?php echo intval($b['changed'])?> 个、新增 <?php echo intval($b['added'])?> 个 · <?php echo size_format($b['size'])?></span>
        </div>
        <button type="button" class="btn btn-default btn-xs oupd-restore" data-name="<?php echo htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8')?>"><i class="fa fa-undo" aria-hidden="true"></i> 还原到这次更新之前</button>
      </div>
<?php   }?>
    </div>
<?php }?>
  </div>
</div>

<div class="panel panel-primary">
  <div class="panel-heading update-head">
    <h3 class="panel-title"><i class="fa fa-history" aria-hidden="true"></i> 最近提交（<?php echo htmlspecialchars(UPDATE_REPO.' · '.UPDATE_BRANCH, ENT_QUOTES, 'UTF-8')?>）</h3>
    <div class="update-head-btns">
      <span class="update-head-note">共 <?php echo intval($u['commit_count'])?> 条，取自<?php echo $update_source === 'github' ? ' GitHub' : htmlspecialchars($update_source_name, ENT_QUOTES, 'UTF-8')?>，完整历史点右上角去看</span>
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
        <a class="update-sha" href="<?php echo htmlspecialchars(update_commit_url($sha), ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer" title="在<?php echo $update_source === 'github' ? ' GitHub ' : htmlspecialchars($update_source_name, ENT_QUOTES, 'UTF-8')?>上打开这次提交"><?php echo substr($sha, 0, 7)?></a>
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
<script>
(function(){
	var token = <?php echo json_encode($oupd_token)?>;
	var dbUpdateUrl = <?php echo json_encode(site_root_url().'install/update.php', JSON_UNESCAPED_SLASHES)?>;
	//接口返回的东西（文件名、提示）一律转义后再拼进页面
	function esc(s){ return $('<div>').text(s == null ? '' : String(s)).html(); }
	function list(title, arr, total){
		if(!arr || !arr.length)return '';
		var more = total > arr.length ? '<li>…… 共 ' + total + ' 个</li>' : '';
		return '<div class="oupd-sub">' + esc(title) + '（' + total + '）</div><ul class="oupd-files"><li>' + $.map(arr, esc).join('</li><li>') + '</li>' + more + '</ul>';
	}
	//done=true 表示文件已经更新完：这时版本门禁已经生效，提示里换成直接升级的按钮
	function warnings(d, done){
		var w = [];
		if(d.need_db && !done)w.push('这次改过数据库（仓库 ' + esc(d.db_version) + ' &gt; 站点 ' + esc(d.local_db) + '）：文件更新完会出现「立即升级数据库」按钮，点一下就行；升级前整站会被版本门禁拦住。');
		if(d.need_db && done)w.push('这次改过数据库（仓库 ' + esc(d.db_version) + ' &gt; 站点 ' + esc(d.local_db) + '）：<b>现在整站被版本门禁拦着，请马上点下面的按钮升级数据库</b>。'
			+ '<form method="post" action="./db_upgrade.php" style="margin:10px 0 2px"><input type="hidden" name="token" value="' + esc(token) + '"/>'
			+ '<button type="submit" class="btn btn-warning btn-sm"><i class="fa fa-database" aria-hidden="true"></i> 立即升级数据库</button></form>'
			+ '按钮不好用时，也可以打开 <a href="' + esc(dbUpdateUrl) + '" target="_blank">/install/update.php</a> 输入管理员账号密码升级。');
		if(d.composer_changed)w.push('<code>includes/composer.json</code> 有变化：在线更新不碰 <code>includes/vendor/</code>，依赖要另外补（上传全量包里的 vendor，或在 includes 目录执行 composer install）。');
		if(d.admin_dir && d.admin_dir !== 'admin')w.push('仓库里的 <code>admin/</code> 会写到站点实际的后台目录 <code>' + esc(d.admin_dir) + '/</code>。');
		return w.length ? '<div class="alert alert-warning oupd-alert">' + w.join('<br/>') + '</div>' : '';
	}
	function post(act, data, done){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		data.token = token;
		$.ajax({type:'POST', url:'ajax.php?act=' + act, data:data, dataType:'json', timeout:600000,
			success:function(d){ layer.close(ii); done(d || {code:-1, msg:'返回内容为空'}); },
			error:function(xhr, status){ layer.close(ii); done({code:-1, msg: status === 'timeout' ? '请求超时。如果是在更新途中，请刷新页面看结果，必要时用下方备份还原' : '服务器错误（HTTP ' + xhr.status + '）'}); }
		});
	}
	$('#oupd-check').on('click', function(){
		var sha = String($(this).data('sha')), $out = $('#oupd-result');
		post('onlineupdate_prepare', {sha:sha}, function(d){
			if(d.code != 0){
				$out.html('<div class="alert alert-danger oupd-alert">' + esc(d.msg) + '</div>');
				return;
			}
			var total = d.changed_count + d.added_count;
			var html = '<div class="oupd-summary">仓库版本 <b>' + esc(d.version) + '</b>（数据库 ' + esc(d.db_version) + '）：'
				+ '修改 <b>' + d.changed_count + '</b> 个、新增 <b>' + d.added_count + '</b> 个，'
				+ d.unchanged + ' 个和站点一致，' + d.skipped_count + ' 个不随站点发布。</div>';
			if(d.unwritable && d.unwritable.length){
				html += '<div class="alert alert-danger oupd-alert">下面这些文件或目录 PHP 写不了，修好权限前不能更新：</div>' + list('不可写', d.unwritable, d.unwritable.length);
			}else{
				html += warnings(d) + list('将修改', d.changed, d.changed_count) + list('将新增', d.added, d.added_count);
				html += '<div class="oupd-btns"><button type="button" class="btn btn-success btn-sm" id="oupd-apply"><i class="fa fa-check" aria-hidden="true"></i> '
					+ (total ? '确认更新这 ' + total + ' 个文件' : '文件已一致，记为已更新到此提交') + '</button></div>';
			}
			$out.html(html);
			$('#oupd-apply').on('click', function(){
				var short = esc(sha.substr(0, 7));
				layer.confirm(total ? '确定用仓库提交 ' + short + ' 覆盖这 ' + total + ' 个文件？<br/>旧文件会先备份。' : '记为已更新到 ' + short + '？', {icon:3, title:'在线更新'}, function(idx){
					layer.close(idx);
					post('onlineupdate_apply', {sha:sha}, function(r){
						if(r.code != 0){
							var h = '<div class="alert alert-danger oupd-alert">' + esc(r.msg) + '</div>';
							if(r.unwritable && r.unwritable.length)h += list('不可写', r.unwritable, r.unwritable.length);
							$out.html(h);
							return;
						}
						var ok = '<div class="alert alert-success oupd-alert"><b>' + esc(r.msg) + '</b>'
							+ (r.backup ? '：修改 ' + r.changed_count + ' 个、新增 ' + r.added_count + ' 个文件，旧文件已备份。' : '。')
							+ (r.need_db ? '' : ' <a href="./update.php">刷新本页</a>') + '</div>';
						$out.html(ok + warnings(r, true));
						$('#oupd-check').prop('disabled', true);
					});
				});
			});
		});
	});
	//换更新源：存下来后重新打开本页（不带 force，缓存期内直接用这个源上次查到的结果）
	$('#update-source').on('change', function(){
		var $sel = $(this);
		post('updatesource', {source:$sel.val()}, function(r){
			if(r.code == 0){ window.location.href = './update.php'; return; }
			layer.alert(esc(r.msg), {icon:2});
			$sel.val(<?php echo json_encode($update_source)?>);
		});
	});
	$('.oupd-restore').on('click', function(){
		var name = $(this).data('name');
		layer.confirm('把这次更新覆盖掉的文件写回去、删掉这次新增的文件？<br/>数据库不会跟着退回。', {icon:3, title:'还原'}, function(idx){
			layer.close(idx);
			post('onlineupdate_restore', {name:name}, function(r){
				layer.alert(esc(r.msg), {icon: r.code == 0 ? 1 : 2, closeBtn:false}, function(){ window.location.reload(); });
			});
		});
	});
})();
</script>
</body>
</html>
