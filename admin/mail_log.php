<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '发信记录';

/*
 * 每一次"获取验证码"都会在这里留一条，包括发送失败的。
 * 判断有没有被刷主要看顶部那几个数：短时间内不同邮箱、不同来源特别多就是被刷了。
 *
 * 筛选、搜索、翻页、清理都走 ajax=1 的 JSON 分支，服务端把几块 HTML 渲染好返回，
 * 前端替换对应区域，整页不刷新。处理逻辑必须放在 head.php 之前，否则 JSON 前面会混进 HTML。
 */
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
$msg = '';

if($islogin != 1){
	if($is_ajax)exit('{"code":-1,"msg":"登录状态已失效，请重新登录后台"}');
}elseif(isset($_POST['do']) && $_POST['do'] === 'clean'){
	if(!checkRefererHost())exit($is_ajax ? '{"code":-1,"msg":"来源校验失败"}' : '来源校验失败');
	//只清 7 天前的，最近的记录要留着排查
	$DB->exec("DELETE FROM pre_mailcode WHERE addtime < DATE_SUB(NOW(), INTERVAL 7 DAY)");
	$msg = '7 天前的记录已清理';
}

$status_text = [
	0 => '<span class="label label-info">已发送</span>',
	1 => '<span class="label label-success">已验证</span>',
	2 => '<span class="label label-danger">发送失败</span>',
	3 => '<span class="label label-default">已作废</span>',
];
$purpose_text = ['register'=>'注册', 'reset'=>'找回密码', 'changemail'=>'换邮箱', 'bindmail'=>'绑定邮箱'];

/*
 * 顶部概况。整页渲染和 AJAX 刷新共用，保证两边一致
 */
function render_maillog_stats(){
	global $DB, $conf;
	$stat = [
		'hour' => intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE addtime > DATE_SUB(NOW(), INTERVAL 1 HOUR)")),
		'day' => intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)")),
		'email' => intval($DB->getColumn("SELECT count(DISTINCT email) FROM pre_mailcode WHERE addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)")),
		'ip' => intval($DB->getColumn("SELECT count(DISTINCT ip) FROM pre_mailcode WHERE addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)")),
		'done' => intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE status=1 AND addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)")),
		'fail' => intval($DB->getColumn("SELECT count(*) FROM pre_mailcode WHERE status=2 AND addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)")),
	];
	$hour_limit = isset($conf['mail_hour_limit']) ? intval($conf['mail_hour_limit']) : 50;
	$site_daily = isset($conf['mail_site_daily']) ? intval($conf['mail_site_daily']) : 200;
	ob_start();
?>
  <div class="row maillog-stats">
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="maillog-stat"><strong><?php echo $stat['hour']?><small> / <?php echo $hour_limit > 0 ? $hour_limit : '不限'?></small></strong><span>最近 1 小时</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="maillog-stat"><strong><?php echo $stat['day']?><small> / <?php echo $site_daily > 0 ? $site_daily : '不限'?></small></strong><span>最近 24 小时</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="maillog-stat"><strong><?php echo $stat['email']?></strong><span>24 小时内不同邮箱</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="maillog-stat"><strong><?php echo $stat['ip']?></strong><span>24 小时内不同来源</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="maillog-stat"><strong><?php echo $stat['done']?></strong><span>24 小时内完成注册验证</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="maillog-stat<?php echo $stat['fail'] > 0 ? ' is-bad' : ''?>"><strong><?php echo $stat['fail']?></strong><span>24 小时内发送失败</span></div></div>
  </div>
<?php
	return ob_get_clean();
}

/*
 * 筛选条：状态按钮的选中态跟着结果一起返回，不然点了没反应
 */
function render_maillog_filter($status, $kw){
	ob_start();
	$tabs = [-1=>'全部', 0=>'已发送', 1=>'已验证', 2=>'发送失败', 3=>'已作废'];
?>
  <form class="form-inline maillog-filter" onsubmit="return doSearch()">
<?php foreach($tabs as $v => $name){?>
    <a class="btn btn-xs <?php echo $status === $v ? 'btn-primary' : 'btn-default'?> maillog-tab" href="javascript:void(0)" data-status="<?php echo $v?>"><?php echo $name?></a>
<?php }?>
    <input type="text" id="maillogKw" value="<?php echo htmlspecialchars($kw, ENT_QUOTES, 'UTF-8')?>" class="form-control input-sm maillog-search" placeholder="搜邮箱或来源地址"/>
    <button type="submit" class="btn btn-xs btn-default">搜索</button>
  </form>
<?php
	return ob_get_clean();
}

/*
 * 表格内容
 */
function render_maillog_rows($rows){
	global $status_text, $purpose_text;
	ob_start();
	if(!$rows){
		echo '<tr><td colspan="7" align="center">暂无记录</td></tr>';
	}
	foreach($rows as $r){
		$st = intval($r['status']);
?>
    <tr>
      <td><small><?php echo htmlspecialchars($r['addtime'])?></small></td>
      <td><?php echo htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8')?></td>
      <td><small><?php echo isset($purpose_text[$r['purpose']]) ? $purpose_text[$r['purpose']] : htmlspecialchars($r['purpose'], ENT_QUOTES, 'UTF-8')?></small></td>
      <td><?php echo isset($status_text[$st]) ? $status_text[$st] : ''?></td>
      <td><small><?php
		if($st === 2){
			echo '<span class="text-danger">'.htmlspecialchars($r['errmsg'] !== '' ? $r['errmsg'] : '发送失败', ENT_QUOTES, 'UTF-8').'</span>';
		}else{
			echo htmlspecialchars($r['sender'] !== '' ? $r['sender'] : '-', ENT_QUOTES, 'UTF-8');
		}
      ?></small></td>
      <td><small><?php echo htmlspecialchars($r['ip'], ENT_QUOTES, 'UTF-8')?></small></td>
      <td><?php echo intval($r['trycount']) > 0 ? '<span class="text-warning">'.intval($r['trycount']).'</span>' : '0'?></td>
    </tr>
<?php
	}
	return ob_get_clean();
}

/*
 * 分页。翻页也走 AJAX，所以按钮上带的是页码而不是链接
 */
function render_maillog_pager($page, $pages, $numrows){
	ob_start();
?>
  共 <?php echo $numrows?> 条，第 <?php echo $page?> / <?php echo $pages?> 页
<?php if($pages > 1){?>
  <span class="pull-right">
<?php if($page > 1){?><a class="btn btn-xs btn-default maillog-page" href="javascript:void(0)" data-page="<?php echo $page-1?>">上一页</a><?php }?>
<?php if($page < $pages){?><a class="btn btn-xs btn-default maillog-page" href="javascript:void(0)" data-page="<?php echo $page+1?>">下一页</a><?php }?>
  </span>
<?php }
	return ob_get_clean();
}

//筛选条件：AJAX 走 POST，整页打开走 GET
$src = $is_ajax ? $_POST : $_GET;
$status_filter = isset($src['status']) && $src['status'] !== '' ? intval($src['status']) : -1;
if($status_filter < -1 || $status_filter > 3)$status_filter = -1;
$kw = isset($src['kw']) ? trim($src['kw']) : '';
if(mb_strlen($kw, 'UTF-8') > 60)$kw = mb_substr($kw, 0, 60, 'UTF-8');

$where = ' WHERE 1=1';
$params = [];
if($status_filter >= 0){
	$where .= ' AND status=:st';
	$params[':st'] = $status_filter;
}
if($kw !== ''){
	//邮箱和来源地址都能搜，排查时按其中一个顺藤摸瓜
	$where .= ' AND (email LIKE :kw OR ip LIKE :kw)';
	$params[':kw'] = '%'.$kw.'%';
}

$numrows = intval($DB->getColumn("SELECT count(*) FROM pre_mailcode".$where, $params));
$pagesize = 30;
$pages = max(1, ceil($numrows / $pagesize));
$page = isset($src['page']) ? max(1, intval($src['page'])) : 1;
if($page > $pages)$page = $pages;
$offset = $pagesize * ($page - 1);
$rows = $DB->getAll("SELECT * FROM pre_mailcode".$where." ORDER BY id DESC LIMIT {$offset},{$pagesize}", $params);
if(!is_array($rows))$rows = [];

if($is_ajax){
	@header('Content-Type: application/json; charset=UTF-8');
	exit(json_encode([
		'code' => 0,
		'msg' => $msg,
		'stats' => render_maillog_stats(),
		'filter' => render_maillog_filter($status_filter, $kw),
		'rows' => render_maillog_rows($rows),
		'pager' => render_maillog_pager($page, $pages, $numrows),
		'page' => $page,
		'status' => $status_filter,
		'kw' => $kw,
	], JSON_UNESCAPED_UNICODE));
}

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<div class="container">
<div class="admin-page-wide">

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">发信概况</h3></div>
<div class="panel-body">
  <div id="maillogStats"><?php echo render_maillog_stats();?></div>
  <div class="alert alert-info" style="margin:14px 0 0">
    每一次「获取验证码」都会留一条记录，<b>包括没发出去的</b>。
    正常情况下「不同邮箱」和「已验证」这两个数应该接近；
    如果<b>不同邮箱数很大、已验证却很少</b>，或者<b>同一个来源短时间里换了很多邮箱</b>，基本就是有人在刷接口——
    这时去「邮件发信设置」把每 IP 上限和全站上限调小即可。
  </div>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">发信记录
  <button type="button" class="btn btn-xs btn-default pull-right" onclick="cleanLog()">清理 7 天前</button>
</h3></div>
<div class="panel-body">
  <div id="maillogFilter"><?php echo render_maillog_filter($status_filter, $kw);?></div>
</div>
<div class="table-responsive">
<table class="table table-striped table-hover">
  <thead><tr><th>时间</th><th>邮箱</th><th>用途</th><th>状态</th><th>通道 / 失败原因</th><th>来源地址</th><th>错误次数</th></tr></thead>
  <tbody id="maillogTbody"><?php echo render_maillog_rows($rows);?></tbody>
</table>
</div>
<div class="panel-footer" id="maillogPager"><?php echo render_maillog_pager($page, $pages, $numrows);?></div>
</div>

</div>
</div>
<style>
.maillog-stats{margin:0 -6px}
.maillog-stats>div{padding:0 6px 12px}
.maillog-stat{padding:14px;background:#f6f8fb;border:1px solid #e6ebf2;border-radius:10px;text-align:center}
.maillog-stat strong{display:block;color:#2f3545;font-size:24px;font-weight:800;line-height:1.2}
.maillog-stat strong small{color:#98a2b3;font-size:14px;font-weight:600}
.maillog-stat span{display:block;margin-top:4px;color:#8a94a6;font-size:12px}
.maillog-stat.is-bad{background:rgba(220,53,69,.07);border-color:rgba(220,53,69,.25)}
.maillog-stat.is-bad strong{color:#c0392b}
.maillog-filter{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.maillog-filter .btn{margin:0}
.maillog-filter .maillog-search{flex:1;min-width:160px;max-width:260px;margin-left:6px}
@media (max-width:767px){
	/* 窄屏上搜索框独占一行，固定宽度会把筛选条撑破 */
	.maillog-filter .maillog-search{flex:1 1 100%;max-width:none;margin-left:0;margin-top:4px}
	.maillog-filter .maillog-search+.btn{flex:1 1 100%}
}
.maillog-loading{opacity:.45;transition:opacity .15s}
</style>
<script>
/*
 * 筛选、搜索、翻页、清理全部就地刷新：服务端把统计、筛选条、表格、分页四块 HTML
 * 渲染好返回，前端替换对应区域，整页不刷新。
 * 表格和筛选条是整块替换的，所以点击统一用事件委托，不用每次重新绑定。
 */
(function(){
	var state = {
		status: <?php echo $status_filter?>,
		kw: <?php echo json_encode($kw, JSON_UNESCAPED_UNICODE)?>,
		page: <?php echo $page?>
	};
	var busy = false;

	function load(patch, push){
		if(busy)return;
		busy = true;
		for(var k in patch){ if(Object.prototype.hasOwnProperty.call(patch, k))state[k] = patch[k]; }
		$('#maillogTbody,#maillogStats').addClass('maillog-loading');
		$.ajax({
			type: 'POST',
			url: './mail_log.php',
			data: { ajax:'1', status:state.status, kw:state.kw, page:state.page },
			dataType: 'json',
			timeout: 30000,
			success: function(res){
				$('#maillogTbody,#maillogStats').removeClass('maillog-loading');
				busy = false;
				if(!res || res.code !== 0){
					layer.msg((res && res.msg) ? res.msg : '加载失败，请刷新页面重试');
					return;
				}
				$('#maillogStats').html(res.stats);
				$('#maillogFilter').html(res.filter);
				$('#maillogTbody').html(res.rows);
				$('#maillogPager').html(res.pager);
				state.page = res.page;
				//地址栏跟着变，刷新或收藏后还是同一份结果
				if(push && window.history && history.pushState){
					var q = [];
					if(res.status >= 0)q.push('status=' + res.status);
					if(res.kw)q.push('kw=' + encodeURIComponent(res.kw));
					if(res.page > 1)q.push('page=' + res.page);
					history.pushState(null, '', './mail_log.php' + (q.length ? '?' + q.join('&') : ''));
				}
				if(res.msg)layer.msg(res.msg);
			},
			error: function(xhr, status){
				$('#maillogTbody,#maillogStats').removeClass('maillog-loading');
				busy = false;
				layer.msg(status === 'timeout' ? '请求超时，请稍后重试' : '请求失败，请稍后重试');
			}
		});
	}

	//筛选条和分页都是整块替换的，用事件委托绑在 document 上
	$(document).on('click', '.maillog-tab', function(){
		load({status: parseInt($(this).attr('data-status'), 10), page: 1}, true);
	});
	$(document).on('click', '.maillog-page', function(){
		load({page: parseInt($(this).attr('data-page'), 10)}, true);
	});

	window.doSearch = function(){
		load({kw: $('#maillogKw').val(), page: 1}, true);
		return false;
	};
	window.cleanLog = function(){
		if(!confirm('会删除 7 天前的全部记录，确定吗？'))return;
		if(busy)return;
		busy = true;
		$.ajax({
			type: 'POST',
			url: './mail_log.php',
			data: { ajax:'1', 'do':'clean', status:state.status, kw:state.kw, page:1 },
			dataType: 'json',
			timeout: 30000,
			success: function(res){
				busy = false;
				if(!res || res.code !== 0){ layer.msg((res && res.msg) ? res.msg : '清理失败'); return; }
				$('#maillogStats').html(res.stats);
				$('#maillogFilter').html(res.filter);
				$('#maillogTbody').html(res.rows);
				$('#maillogPager').html(res.pager);
				state.page = res.page;
				layer.msg(res.msg || '已清理');
			},
			error: function(){ busy = false; layer.msg('请求失败，请稍后重试'); }
		});
	};

	//浏览器前进后退时按地址栏里的条件重新拉一次
	$(window).on('popstate', function(){
		var q = location.search;
		var m = q.match(/[?&]status=(-?\d+)/);
		state.status = m ? parseInt(m[1], 10) : -1;
		m = q.match(/[?&]kw=([^&]*)/);
		state.kw = m ? decodeURIComponent(m[1]) : '';
		m = q.match(/[?&]page=(\d+)/);
		state.page = m ? parseInt(m[1], 10) : 1;
		load({}, false);
	});
})();
</script>
</body>
</html>
