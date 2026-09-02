<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '图片检测记录';

/*
 * 每张走过图片检测的图都会在这里留一条，包括放行的。
 * 放行的也记，是为了能回头判断阈值定高了还是低了——只看被拦下的那些，
 * 永远不知道有多少该拦的漏了过去。
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
	//只清放行的旧记录：被拦和待审的要留着追溯
	$DB->exec("DELETE FROM pre_greenlog WHERE verdict='pass' AND addtime < DATE_SUB(NOW(), INTERVAL 7 DAY)");
	$msg = '7 天前的放行记录已清理，拦截和待审记录保留';
}

$verdict_text = [
	'block' => '<span class="label label-danger">已拦截</span>',
	'review' => '<span class="label label-warning">待人工</span>',
	'pass' => '<span class="label label-default">放行</span>',
	'error' => '<span class="label label-info">检测失败</span>',
];
$engine_text = ['aliyun'=>'阿里云', 'qcloud'=>'腾讯云', 'self'=>'自建模型'];

/*
 * 顶部概况。整页渲染和 AJAX 刷新共用，保证两边一致
 */
function render_greenlog_stats(){
	global $DB, $conf;
	$day = "addtime > DATE_SUB(NOW(), INTERVAL 1 DAY)";
	$stat = [
		'day' => intval($DB->getColumn("SELECT count(*) FROM pre_greenlog WHERE {$day}")),
		'block' => intval($DB->getColumn("SELECT count(*) FROM pre_greenlog WHERE verdict='block' AND {$day}")),
		'review' => intval($DB->getColumn("SELECT count(*) FROM pre_greenlog WHERE verdict='review' AND {$day}")),
		'error' => intval($DB->getColumn("SELECT count(*) FROM pre_greenlog WHERE verdict='error' AND {$day}")),
		'avgms' => intval($DB->getColumn("SELECT AVG(ms) FROM pre_greenlog WHERE {$day}")),
		'pending' => intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE block=2")),
	];
	$block_line = isset($conf['green_self_block']) && $conf['green_self_block'] !== '' ? $conf['green_self_block'] : '0.85';
	$review_line = isset($conf['green_self_review']) && $conf['green_self_review'] !== '' ? $conf['green_self_review'] : '0.6';
	$is_self = isset($conf['green_check']) && $conf['green_check'] == 3;
	ob_start();
?>
  <div class="row greenlog-stats">
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="greenlog-stat"><strong><?php echo $stat['day']?></strong><span>24 小时内检测</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="greenlog-stat<?php echo $stat['block'] > 0 ? ' is-bad' : ''?>"><strong><?php echo $stat['block']?></strong><span>24 小时内拦截</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="greenlog-stat<?php echo $stat['review'] > 0 ? ' is-warn' : ''?>"><strong><?php echo $stat['review']?></strong><span>24 小时内转人工</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="greenlog-stat<?php echo $stat['pending'] > 0 ? ' is-warn' : ''?>"><strong><?php echo $stat['pending']?></strong><span>当前待审核文件</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="greenlog-stat<?php echo $stat['error'] > 0 ? ' is-bad' : ''?>"><strong><?php echo $stat['error']?></strong><span>24 小时内检测失败</span></div></div>
    <div class="col-xs-6 col-sm-4 col-md-2"><div class="greenlog-stat"><strong><?php echo $stat['avgms']?><small> ms</small></strong><span>平均耗时</span></div></div>
  </div>
<?php if($is_self){?>
  <p class="greenlog-line">当前阈值：评分 <b><?php echo htmlspecialchars($block_line, ENT_QUOTES, 'UTF-8')?></b> 以上直接拦截，<b><?php echo htmlspecialchars($review_line, ENT_QUOTES, 'UTF-8')?></b> 以上转人工。改阈值在<a href="./set.php?mod=green">图片检测设置</a>。</p>
<?php }
	return ob_get_clean();
}

/*
 * 筛选条：选中态跟着结果一起返回，不然点了没反应
 */
function render_greenlog_filter($verdict, $kw){
	ob_start();
	$tabs = [''=>'全部', 'block'=>'已拦截', 'review'=>'待人工', 'pass'=>'放行', 'error'=>'检测失败'];
?>
  <form class="form-inline greenlog-filter" onsubmit="return doSearch()">
<?php foreach($tabs as $v => $name){?>
    <a class="btn btn-xs <?php echo $verdict === $v ? 'btn-primary' : 'btn-default'?> greenlog-tab" href="javascript:void(0)" data-verdict="<?php echo $v?>"><?php echo $name?></a>
<?php }?>
    <input type="text" id="greenlogKw" value="<?php echo htmlspecialchars($kw, ENT_QUOTES, 'UTF-8')?>" class="form-control input-sm greenlog-search" placeholder="搜文件名或上传地址"/>
    <button type="submit" class="btn btn-xs btn-default">搜索</button>
  </form>
<?php
	return ob_get_clean();
}

/*
 * 表格内容
 */
function render_greenlog_rows($rows){
	global $verdict_text, $engine_text;
	ob_start();
	if(!$rows){
		echo '<tr><td colspan="7" align="center">暂无记录</td></tr>';
	}
	foreach($rows as $r){
		$v = $r['verdict'];
		$score = floatval($r['score']);
		//分数越高条越红，扫一眼就知道这批图整体什么水平
		$bar = min(100, max(0, $score * 100));
?>
    <tr>
      <td><small><?php echo htmlspecialchars($r['addtime'])?></small></td>
      <td>
        <span class="greenlog-name" title="<?php echo htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8')?>"><?php echo htmlspecialchars($r['name'] !== '' ? $r['name'] : '-', ENT_QUOTES, 'UTF-8')?></span>
        <?php if(!empty($r['ftoken'])){?><a href="javascript:void(0)" class="greenlog-jump js-image-preview" data-src="./view.php/<?php echo htmlspecialchars($r['ftoken'], ENT_QUOTES, 'UTF-8').'.'.htmlspecialchars($r['type'] ? $r['type'] : 'png', ENT_QUOTES, 'UTF-8')?>">查看</a><?php }else{?><span class="greenlog-gone" title="文件已删除">已删除</span><?php }?>
      </td>
      <td><?php echo isset($verdict_text[$v]) ? $verdict_text[$v] : htmlspecialchars($v, ENT_QUOTES, 'UTF-8')?></td>
      <td>
        <div class="greenlog-score">
          <b><?php echo $r['engine'] === 'self' ? number_format($score, 4) : '-'?></b>
          <?php if($r['engine'] === 'self'){?><i style="width:<?php echo $bar?>%"></i><?php }?>
        </div>
      </td>
      <td><small class="text-muted"><?php echo htmlspecialchars($r['detail'] !== '' ? $r['detail'] : '-', ENT_QUOTES, 'UTF-8')?></small></td>
      <td><small><?php echo isset($engine_text[$r['engine']]) ? $engine_text[$r['engine']] : htmlspecialchars($r['engine'], ENT_QUOTES, 'UTF-8')?><br/><?php echo intval($r['ms'])?>ms</small></td>
      <td><small><?php echo htmlspecialchars($r['ip'], ENT_QUOTES, 'UTF-8')?><?php echo intval($r['uid']) > 0 ? '<br/>UID '.intval($r['uid']) : ''?></small></td>
    </tr>
<?php
	}
	return ob_get_clean();
}

/*
 * 分页。翻页也走 AJAX，所以按钮上带的是页码而不是链接
 */
function render_greenlog_pager($page, $pages, $numrows){
	ob_start();
?>
  共 <?php echo $numrows?> 条，第 <?php echo $page?> / <?php echo $pages?> 页
<?php if($pages > 1){?>
  <span class="pull-right">
<?php if($page > 1){?><a class="btn btn-xs btn-default greenlog-page" href="javascript:void(0)" data-page="<?php echo $page-1?>">上一页</a><?php }?>
<?php if($page < $pages){?><a class="btn btn-xs btn-default greenlog-page" href="javascript:void(0)" data-page="<?php echo $page+1?>">下一页</a><?php }?>
  </span>
<?php }
	return ob_get_clean();
}

//筛选条件：AJAX 走 POST，整页打开走 GET
$src = $is_ajax ? $_POST : $_GET;
$verdict_filter = isset($src['verdict']) ? trim($src['verdict']) : '';
if(!in_array($verdict_filter, ['block', 'review', 'pass', 'error'], true))$verdict_filter = '';
$kw = isset($src['kw']) ? trim($src['kw']) : '';
if(mb_strlen($kw, 'UTF-8') > 60)$kw = mb_substr($kw, 0, 60, 'UTF-8');

//列名要带 g. 前缀：下面 JOIN 了文件表，两张表都有 name/type/hash/ip/uid，不写前缀会有歧义
$where = ' WHERE 1=1';
$params = [];
if($verdict_filter !== ''){
	$where .= ' AND g.verdict=:v';
	$params[':v'] = $verdict_filter;
}
if($kw !== ''){
	$where .= ' AND (g.name LIKE :kw OR g.ip LIKE :kw OR g.hash LIKE :kw)';
	$params[':kw'] = '%'.$kw.'%';
}

$numrows = intval($DB->getColumn("SELECT count(*) FROM pre_greenlog g".$where, $params));
$pagesize = 30;
$pages = max(1, ceil($numrows / $pagesize));
$page = isset($src['page']) ? max(1, intval($src['page'])) : 1;
if($page > $pages)$page = $pages;
$offset = $pagesize * ($page - 1);
//token 从文件表带出来，预览地址要用它；文件已经删掉的行 ftoken 会是 NULL
$rows = $DB->getAll("SELECT g.*, f.token AS ftoken FROM pre_greenlog g LEFT JOIN pre_file f ON f.id=g.file_id".$where." ORDER BY g.id DESC LIMIT {$offset},{$pagesize}", $params);
if(!is_array($rows))$rows = [];

if($is_ajax){
	@header('Content-Type: application/json; charset=UTF-8');
	exit(json_encode([
		'code' => 0,
		'msg' => $msg,
		'stats' => render_greenlog_stats(),
		'filter' => render_greenlog_filter($verdict_filter, $kw),
		'rows' => render_greenlog_rows($rows),
		'pager' => render_greenlog_pager($page, $pages, $numrows),
		'page' => $page,
		'verdict' => $verdict_filter,
		'kw' => $kw,
	], JSON_UNESCAPED_UNICODE));
}

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$green_off = !isset($conf['green_check']) || $conf['green_check'] == 0;
?>
<div class="container" style="padding-top:70px;">

<?php if($green_off){?>
<div class="alert alert-warning">图片检测当前是<b>关闭</b>状态，不会产生新记录。要开启请去 <a href="./set.php?mod=green">图片检测设置</a>。</div>
<?php }?>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">检测概况</h3></div>
<div class="panel-body">
  <div id="greenlogStats"><?php echo render_greenlog_stats();?></div>
  <div class="alert alert-info" style="margin:14px 0 0">
    每张图检测完都会留一条记录，<b>包括放行的</b>——只看被拦下的，永远不知道有多少该拦的漏了过去。<br/>
    <b>「待人工」的图前台下载不了</b>，去<a href="./file.php?dstatus=3">文件管理筛「待审核文件」</a>逐个确认，误判的点「正常」放出来。<br/>
    如果「转人工」长期是 0 而你知道站上有该拦的图，说明阈值定高了；反过来待审核列表天天几十条全是正常图，就是定低了。
  </div>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">检测记录
  <button type="button" class="btn btn-xs btn-default pull-right" onclick="cleanLog()">清理 7 天前的放行记录</button>
</h3></div>
<div class="panel-body">
  <div id="greenlogFilter"><?php echo render_greenlog_filter($verdict_filter, $kw);?></div>
</div>
<div class="table-responsive">
<table class="table table-striped table-hover">
  <thead><tr><th>时间</th><th>文件</th><th>结果</th><th>评分</th><th>模型明细</th><th>引擎 / 耗时</th><th>来源</th></tr></thead>
  <tbody id="greenlogTbody"><?php echo render_greenlog_rows($rows);?></tbody>
</table>
</div>
<div class="panel-footer" id="greenlogPager"><?php echo render_greenlog_pager($page, $pages, $numrows);?></div>
</div>

</div>

<style>
.greenlog-stats{margin:0 -6px}
.greenlog-stats>div{padding:0 6px 12px}
.greenlog-stat{padding:14px;background:var(--admin-soft);border:1px solid var(--admin-line);border-radius:10px;text-align:center}
.greenlog-stat strong{display:block;color:var(--admin-text);font-size:24px;font-weight:800;line-height:1.2}
.greenlog-stat strong small{color:var(--admin-muted);font-size:14px;font-weight:600}
.greenlog-stat span{display:block;margin-top:4px;color:var(--admin-muted);font-size:12px}
.greenlog-stat.is-bad{border-color:var(--admin-red)}
.greenlog-stat.is-bad strong{color:var(--admin-red)}
.greenlog-stat.is-warn{border-color:var(--admin-yellow)}
.greenlog-stat.is-warn strong{color:var(--admin-yellow)}
.greenlog-line{margin:12px 0 0;color:var(--admin-muted);font-size:13px}
.greenlog-filter{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.greenlog-filter .btn{margin:0}
.greenlog-filter .greenlog-search{flex:1;min-width:160px;max-width:260px;margin-left:6px}
@media (max-width:767px){
	/* 窄屏上搜索框独占一行，固定宽度会把筛选条撑破 */
	.greenlog-filter .greenlog-search{flex:1 1 100%;max-width:none;margin-left:0;margin-top:4px}
	.greenlog-filter .greenlog-search+.btn{flex:1 1 100%}
}
.greenlog-name{display:inline-block;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom}
.greenlog-jump{margin-left:6px;font-size:12px;cursor:pointer}
.greenlog-gone{margin-left:6px;font-size:12px;color:var(--admin-muted)}
/* 分数条：数字之外再给个长度，一屏扫下来就知道整体分布 */
.greenlog-score{position:relative;min-width:88px}
.greenlog-score b{font-variant-numeric:tabular-nums;font-weight:600}
.greenlog-score i{display:block;height:3px;margin-top:3px;border-radius:2px;background:var(--admin-primary);opacity:.55}
.greenlog-loading{opacity:.45;transition:opacity .15s}
</style>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/2.3/skin/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var greenlogState = {page: <?php echo $page?>, verdict: <?php echo json_encode($verdict_filter)?>, kw: <?php echo json_encode($kw)?>};

function loadGreenlog(extra){
	var data = {ajax:'1', page:greenlogState.page, verdict:greenlogState.verdict, kw:greenlogState.kw};
	if(extra){ for(var k in extra){ data[k] = extra[k]; } }
	$('#greenlogTbody').addClass('greenlog-loading');
	$.post('./green_log.php', data, function(res){
		$('#greenlogTbody').removeClass('greenlog-loading');
		if(!res || res.code !== 0){
			layer.msg((res && res.msg) ? res.msg : '加载失败');
			return;
		}
		$('#greenlogStats').html(res.stats);
		$('#greenlogFilter').html(res.filter);
		$('#greenlogTbody').html(res.rows);
		$('#greenlogPager').html(res.pager);
		greenlogState.page = res.page;
		greenlogState.verdict = res.verdict;
		greenlogState.kw = res.kw;
		if(res.msg) layer.msg(res.msg);
	}, 'json').fail(function(){
		$('#greenlogTbody').removeClass('greenlog-loading');
		layer.msg('服务器错误');
	});
}

function doSearch(){
	greenlogState.kw = $('#greenlogKw').val();
	greenlogState.page = 1;
	loadGreenlog();
	return false;
}

function cleanLog(){
	if(!confirm('清理 7 天前的「放行」记录？被拦截和待人工的记录会保留。'))return;
	greenlogState.page = 1;
	loadGreenlog({'do':'clean'});
}

/*
 * 就地看图，跟文件管理里的「查看大图」是同一套：先把图加载出来量到真实尺寸，
 * 再等比缩到不超出窗口，最后用 layer 弹出来。直接塞进弹层的话大图会撑破屏幕。
 */
function showimage(resourcesUrl){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	var img = new Image();
	img.onload = function(){
		var max_height = $(window).height() - 200;
		var max_width = $(window).width();
		var rate = Math.min(max_height / img.height, max_width / img.width, 1);
		var imgHeight = img.height * rate;
		var imgWidth = img.width * rate;
		var imgHtml = '<div id="showimg" style="width:'+imgWidth+'px; height:'+imgHeight+'px;"></div>';
		img.style = 'width:100%';
		layer.close(ii);
		layer.open({
			type: 1,
			shade: 0.6,
			title: false,
			area: ['auto', 'auto'],
			shadeClose: true,
			content: imgHtml,
			success: function(){ $('#showimg').append(img); }
		});
	};
	img.onerror = function(){ layer.close(ii); layer.msg('图片加载失败，可能已被删除'); };
	img.src = resourcesUrl;
}
$(document).on('click', '.js-image-preview', function(){
	showimage($(this).data('src'));
});

//筛选条和分页是 AJAX 换上去的，事件得挂在容器上
$(document).on('click', '.greenlog-tab', function(){
	greenlogState.verdict = $(this).data('verdict') || '';
	greenlogState.page = 1;
	loadGreenlog();
});
$(document).on('click', '.greenlog-page', function(){
	greenlogState.page = $(this).data('page');
	loadGreenlog();
});
</script>
</body>
</html>
