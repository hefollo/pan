<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title = '内容检测记录';

/*
 * 每个走过内容检测的文件都会在这里留一条，图片和视频都算，包括放行的。
 * 放行的也记，是为了能回头判断阈值定高了还是低了——只看被拦下的那些，
 * 永远不知道有多少该拦的漏了过去。
 *
 * 图片是上传时同步检测的，视频是抽帧异步跑的：上传时先挂起，结果由回调或轮询
 * 回来才落到这张表上，所以视频记录的时间会比上传时间晚一截。
 *
 * 筛选、搜索、翻页、清理都走 ajax=1 的 JSON 分支，服务端把几块 HTML 渲染好返回，
 * 前端替换对应区域，整页不刷新。处理逻辑必须放在 head.php 之前，否则 JSON 前面会混进 HTML。
 */
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
$msg = '';

/*
 * 证据帧的唯一出口。图存在 data/greenshot/，那个目录用 .htaccess 挡掉了直接访问，
 * 只有登录后台才能从这里取。文件名只认我们自己生成的那种格式，堵住 ../ 读别的文件。
 */
if(isset($_GET['shot'])){
	if($islogin != 1)exit('请先登录后台');
	$shot = (string)$_GET['shot'];
	if(!preg_match('/^\d{8}_[a-f0-9]{32}\.jpg$/', $shot))exit('文件名不合法');
	$shot_path = ROOT.'data/greenshot/'.$shot;
	if(!is_file($shot_path))exit('证据帧不存在（可能已被清理）');
	@header('Content-Type: image/jpeg');
	@header('Content-Length: '.filesize($shot_path));
	@header('Cache-Control: private, max-age=600');
	readfile($shot_path);
	exit;
}

//打开这一页顺手推一下积压的视频任务。回调要是丢了，这里也是一条能收尾的路。
//只取几条：检测服务万一没响应，每条都要等一次连接超时，别把这页拖死
if($islogin == 1)green_video_poll(3);

if($islogin != 1){
	if($is_ajax)exit('{"code":-1,"msg":"登录状态已失效，请重新登录后台"}');
}elseif(isset($_POST['do']) && $_POST['do'] === 'clean'){
	if(!checkRefererHost())exit($is_ajax ? '{"code":-1,"msg":"来源校验失败"}' : '来源校验失败');
	//只清放行的旧记录：被拦和待审的要留着追溯
	$DB->exec("DELETE FROM pre_greenlog WHERE verdict='pass' AND addtime < DATE_SUB(NOW(), INTERVAL 7 DAY)");
	$msg = '7 天前的放行记录已清理，拦截和待审记录保留';
}

/*
 * 检测结果标签。和右边「状态」那一列用同一套 .admin-tag 样式和同一组颜色，
 * 两列并排读起来才是一套东西——原来这里用的是 bootstrap 的 .label，圆角字号都不一样。
 * 颜色对齐语义之后还有个好处：两列颜色不一致时一眼就能看出来
 * （比如「已拦截」是红的、状态却是绿的「正常」，说明这条被人工放出来过）。
 */
$verdict_text = [
	'block' => '<span class="admin-tag t-bad">已拦截</span>',
	'review' => '<span class="admin-tag t-warn">待人工</span>',
	'pass' => '<span class="admin-tag t-ok">放行</span>',
	'error' => '<span class="admin-tag t-mute">检测失败</span>',
];
$engine_text = ['aliyun'=>'阿里云', 'qcloud'=>'腾讯云', 'self'=>'自建模型', 'self-video'=>'自建模型<br/>视频抽帧'];
//这两个引擎会给出 0~1 的分数，云接口只有命中/不命中，分数列对它们没意义
$scored_engines = ['self', 'self-video'];

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
	$video_on = $is_self && !empty($conf['green_video']);
	//还没出结果的视频任务。这个数长期不降，基本就是回调没通、检测服务挂了
	$queued = $video_on ? intval($DB->getColumn("SELECT count(*) FROM pre_greenjob WHERE status=0")) : 0;
	$v_block = isset($conf['green_video_block']) && $conf['green_video_block'] !== '' ? $conf['green_video_block'] : '0.85';
	$v_hit = isset($conf['green_video_hit']) && $conf['green_video_hit'] !== '' ? intval($conf['green_video_hit']) : 2;
	$v_timeout = isset($conf['green_video_timeout']) && $conf['green_video_timeout'] !== '' ? intval($conf['green_video_timeout']) : 30;
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
  <p class="greenlog-line">图片阈值：评分 <b><?php echo htmlspecialchars($block_line, ENT_QUOTES, 'UTF-8')?></b> 以上直接拦截，<b><?php echo htmlspecialchars($review_line, ENT_QUOTES, 'UTF-8')?></b> 以上转人工。改阈值在<a href="./set.php?mod=green">内容检测设置</a>。</p>
<?php }
if($video_on){?>
  <p class="greenlog-line">视频：抽帧打分，<b><?php echo htmlspecialchars($v_block, ENT_QUOTES, 'UTF-8')?></b> 以上的帧算命中，够 <b><?php echo $v_hit?></b> 帧才封禁，只命中 1 帧转人工。
  当前 <b<?php echo $queued > 0 ? ' class="text-warning"' : ''?>><?php echo $queued?></b> 个任务在等结果<?php if($queued > 0){?>（超过 <?php echo $v_timeout?> 分钟没结果的会自动放行）<?php }?>。
  <?php if($queued > 5){?><span class="text-danger">积压这么多通常是回调没通或者检测服务挂了，去设置页看看「检测服务状态」那行。</span><?php }?></p>
<?php }
	return ob_get_clean();
}

/*
 * 筛选条：选中态跟着结果一起返回，不然点了没反应
 */
function render_greenlog_filter($verdict, $kw, $etype = ''){
	ob_start();
	$tabs = [''=>'全部', 'block'=>'已拦截', 'review'=>'待人工', 'pass'=>'放行', 'error'=>'检测失败'];
	//图片是同步检测的、视频是抽帧异步跑的，两者的分数口径和耗时完全不是一回事，
	//混在一起看容易得出错误结论，所以单给一组按钮分开看
	$types = [''=>'不分类型', 'image'=>'仅图片', 'video'=>'仅视频'];
?>
  <form class="form-inline greenlog-filter" onsubmit="return doSearch()">
<?php foreach($tabs as $v => $name){?>
    <a class="btn btn-xs <?php echo $verdict === $v ? 'btn-primary' : 'btn-default'?> greenlog-tab" href="javascript:void(0)" data-verdict="<?php echo $v?>"><?php echo $name?></a>
<?php }?>
    <span class="greenlog-sep"></span>
<?php foreach($types as $v => $name){?>
    <a class="btn btn-xs <?php echo $etype === $v ? 'btn-info' : 'btn-default'?> greenlog-etype" href="javascript:void(0)" data-etype="<?php echo $v?>"><?php echo $name?></a>
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
	global $verdict_text, $engine_text, $scored_engines;
	ob_start();
	if(!$rows){
		echo '<tr><td colspan="10" align="center">暂无记录</td></tr>';
	}
	foreach($rows as $r){
		$v = $r['verdict'];
		$score = floatval($r['score']);
		//分数越高条越红，扫一眼就知道这批文件整体什么水平
		$bar = min(100, max(0, $score * 100));
		$scored = in_array($r['engine'], $scored_engines, true);
		$is_video = ($r['engine'] === 'self-video');
		//视频多一行「命中几帧 @几分几秒」，这是判断误判的关键信息：
		//只命中 1 帧的多半是转场或泳装剧照，连着命中好几帧才是真有问题
		$frames = isset($r['frames']) ? (string)$r['frames'] : '';
		$hit_at = isset($r['hit_at']) ? intval($r['hit_at']) : 0;
		$hit_text = '';
		if($is_video && $frames !== ''){
			$hit_text = '命中 '.htmlspecialchars($frames, ENT_QUOTES, 'UTF-8').' 帧';
			if($hit_at > 0)$hit_text .= ' @'.sprintf('%02d:%02d', floor($hit_at / 60), $hit_at % 60);
		}
?>
    <tr>
      <td><small><?php echo htmlspecialchars($r['addtime'])?></small></td>
      <td>
        <span class="greenlog-name" title="<?php echo htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8')?>"><?php echo htmlspecialchars($r['name'] !== '' ? $r['name'] : '-', ENT_QUOTES, 'UTF-8')?></span>
      </td>
      <td class="text-center">
        <?php //证据帧只有视频有，而且只在判成待人工或封禁时才存，放行的没必要留
        if(!empty($r['shot'])){?><a href="javascript:void(0)" class="greenlog-jump js-image-preview" data-src="./green_log.php?shot=<?php echo rawurlencode($r['shot'])?>">查看</a><?php }else{?><span class="greenlog-gone">—</span><?php }?>
      </td>
      <td class="text-center">
<?php if(!empty($r['ftoken'])){
	if($is_video){
		//视频走文件管理那套预览弹层，就地播，不跳新标签
?><a href="javascript:void(0)" class="greenlog-jump js-video-preview" data-id="<?php echo intval($r['file_id'])?>">播放</a><?php
	}else{
?><a href="javascript:void(0)" class="greenlog-jump js-image-preview" data-src="./view.php/<?php echo htmlspecialchars($r['ftoken'], ENT_QUOTES, 'UTF-8').'.'.htmlspecialchars($r['type'] ? $r['type'] : 'png', ENT_QUOTES, 'UTF-8')?>">查看</a><?php
	}
}else{?><span class="greenlog-gone" title="文件已删除">已删除</span><?php }?>
      </td>
      <td class="text-center"><?php echo isset($verdict_text[$v]) ? $verdict_text[$v] : htmlspecialchars($v, ENT_QUOTES, 'UTF-8')?></td>
      <td class="text-center">
        <?php if(!empty($r['ftoken'])){
          /*
           * 「检测结果」是当时机器判成什么，是历史，不会变；这一列是文件此刻的状态，
           * 可以改。复核就是在这两列之间做判断，所以放在一起，不用再跳去文件管理。
           * 结构和 admin/head.php 里 adminBlockHtml() 生成的一致，交互也由那边统一处理。
           */
          $fb = intval($r['fblock']);
          $bnames = [0=>'正常', 1=>'封禁', 2=>'待审'];
          if(!isset($bnames[$fb]))$fb = 0;
          //结构和 admin/head.php 里 adminBlockHtml() 生成的一致，交互也由那边统一处理
        ?><div class="admin-block-pick" data-id="<?php echo intval($r['file_id'])?>" data-v="<?php echo $fb?>">
          <a class="admin-block-cur v<?php echo $fb?>"><?php echo $bnames[$fb]?></a>
          <span class="admin-block-opts"><?php foreach($bnames as $bv => $bname){?><a data-v="<?php echo $bv?>"<?php echo $fb === $bv ? ' class="on v'.$bv.'"' : ''?>><?php echo $bname?></a><?php }?></span>
        </div><?php }else{?><span class="greenlog-gone">已删除</span><?php }?>
      </td>
      <td>
        <div class="greenlog-score">
          <b><?php echo $scored ? number_format($score, 4) : '-'?></b>
          <?php if($scored){?><i style="width:<?php echo $bar?>%"></i><?php }?>
        </div>
        <?php if($hit_text !== ''){?><small class="text-muted"><?php echo $hit_text?></small><?php }?>
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
$etype = isset($src['etype']) ? trim($src['etype']) : '';
if(!in_array($etype, ['image', 'video'], true))$etype = '';

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
//视频记录就是 engine='self-video' 这一种，其余都算图片
if($etype === 'video'){
	$where .= " AND g.engine='self-video'";
}elseif($etype === 'image'){
	$where .= " AND g.engine<>'self-video'";
}

/*
 * 这里要区分「查询失败」和「表里就是 0 条」：getColumn 失败返回 false，intval 之后
 * 同样是 0，两种完全不同的故障在页面上长得一模一样，只能靠猜。$count_failed 留着给
 * 下面的空列表提示用。
 */
$count_raw = $DB->getColumn("SELECT count(*) FROM pre_greenlog g".$where, $params);
$count_failed = ($count_raw === false);
$numrows = intval($count_raw);
$pagesize = 30;
$pages = max(1, ceil($numrows / $pagesize));
$page = isset($src['page']) ? max(1, intval($src['page'])) : 1;
if($page > $pages)$page = $pages;
$offset = $pagesize * ($page - 1);
//token 从文件表带出来，预览地址要用它；文件已经删掉的行 ftoken 会是 NULL
$rows = $DB->getAll("SELECT g.*, f.token AS ftoken, f.block AS fblock FROM pre_greenlog g LEFT JOIN pre_file f ON f.id=g.file_id".$where." ORDER BY g.id DESC LIMIT {$offset},{$pagesize}", $params);
if(!is_array($rows))$rows = [];

if($is_ajax){
	@header('Content-Type: application/json; charset=UTF-8');
	exit(json_encode([
		'code' => 0,
		'msg' => $msg,
		'stats' => render_greenlog_stats(),
		'filter' => render_greenlog_filter($verdict_filter, $kw, $etype),
		'rows' => render_greenlog_rows($rows),
		'pager' => render_greenlog_pager($page, $pages, $numrows),
		'page' => $page,
		'verdict' => $verdict_filter,
		'kw' => $kw,
		'etype' => $etype,
	], JSON_UNESCAPED_UNICODE));
}

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$green_off = !isset($conf['green_check']) || $conf['green_check'] == 0;
?>
<div class="container" style="padding-top:70px;">

<?php
$schema_missing = green_schema_missing();
if($schema_missing){?>
<div class="alert alert-danger">
  <b>数据库升级没完成，检测记录写不进去。</b>缺少：<?php echo htmlspecialchars(implode('、', $schema_missing), ENT_QUOTES, 'UTF-8')?>。<br/>
  版本号已经是 <?php echo DB_VERSION?>，但建表/加字段的语句没真正执行——多半是 <code>install/update_1020.sql</code> 没上传到服务器。
  这种状态下检测照常在跑、视频也照常挂起，就是<b>一条记录都存不下来</b>。<br/>
  把 <code>install/</code> 目录补传完整后，重新访问 <a href="../install/update.php">install/update.php</a>；
  如果它提示「已经是最新版本」，先在数据库里把版本号改回上一版：<br/>
  <code>UPDATE pre_config SET v='1019' WHERE k='version';</code> 然后再跑一次升级。
</div>
<?php }?>

<?php
/*
 * 一条记录都没有的时候，把能自动查的都查了摆出来。
 * 「共 0 条」有好几种完全不同的原因：表没建、字段缺、查询失败、被筛选条件挡了、
 * 或者真的一条都没写进去。光看一个 0 只能靠猜，这里一次性说清楚是哪一种。
 */
if(!$schema_missing && $numrows == 0){
	$total_raw = $DB->getColumn("SELECT count(*) FROM pre_greenlog");
	$db_err = $DB->error();
	$filtered = ($verdict_filter !== '' || $kw !== '' || $etype !== '');
?>
<div class="alert alert-warning">
  <b>检测记录是空的。</b>下面是自动查到的情况：<br/>
  · 表 <code>pre_greenlog</code> 里<b>不带任何筛选</b>共 <b><?php echo $total_raw === false ? '查询失败' : intval($total_raw).' 条'?></b><br/>
  · 本次列表查询：<?php echo $count_failed ? '<b style="color:#d33">执行失败</b>' : '执行成功，命中 0 条'?><?php echo $filtered ? '（当前有筛选条件：'.htmlspecialchars(trim(($verdict_filter?'结果='.$verdict_filter.' ':'').($etype?'类型='.$etype.' ':'').($kw?'关键词='.$kw:'')), ENT_QUOTES, 'UTF-8').'，先点「全部」和「不分类型」再看）' : ''?><br/>
<?php if($count_failed || $total_raw === false){?>
  · 数据库报错：<code><?php echo htmlspecialchars(is_array($db_err) && isset($db_err[2]) ? $db_err[2] : '无', ENT_QUOTES, 'UTF-8')?></code><br/>
<?php }?>
  <br/>
<?php if($total_raw !== false && intval($total_raw) > 0){?>
  表里<b>有数据但这一页显示不出来</b>，请把上面这段连同截图发我。
<?php }else{?>
  表里确实一条都没有。检测有没有真的在跑，看 <code>includes/log.txt</code> 末尾：
  写记录失败会留一行「检测记录写入失败：…」；如果连这行都没有，说明检测流程压根没被触发
  （常见原因：上传的文件类型不在「图片/视频文件类型」里、或者检测开关刚打开、之前传的文件不会补检）。
<?php }?>
</div>
<?php }?>

<?php if($green_off){?>
<div class="alert alert-warning">内容检测当前是<b>关闭</b>状态，不会产生新记录。要开启请去 <a href="./set.php?mod=green">内容检测设置</a>。</div>
<?php }?>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">检测概况</h3></div>
<div class="panel-body">
  <div id="greenlogStats"><?php echo render_greenlog_stats();?></div>
  <div class="alert alert-info" style="margin:14px 0 0">
    每个文件检测完都会留一条记录，<b>包括放行的</b>——只看被拦下的，永远不知道有多少该拦的漏了过去。<br/>
    <b>「结果」是当时机器判成什么，不会变；「状态」是文件此刻的状态，点一下就能改</b>——
    正常 / 封禁 / 待审，和文件管理是同一套。复核就是对着这两列做判断，不用再跳来跳去。
    「封禁」会进违规公示，改回正常或退回待审会把公示撤下来（记录保留）。<br/>
    <b>「封禁」和「待审」的文件前台都下载不了</b>，区别是前者公示、后者不公示。
    也可以去<a href="./file.php?dstatus=3">文件管理筛「待审核文件」</a>批量处理。<br/>
    如果「转人工」长期是 0 而你知道站上有该拦的内容，说明阈值定高了；反过来待审核列表天天几十条全是正常文件，就是定低了。<br/>
    <b>视频和图片不一样</b>：图片是上传时同步检测的，视频传完先挂起、抽帧在后台跑，跑完才放行或封禁。
    视频行点「证据帧」直接看命中的那一帧，点「查看原片」就地弹出播放器（后台可以看待审和已封的文件，前台不行）。
  </div>
</div>
</div>

<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">检测记录
  <button type="button" class="btn btn-xs btn-default pull-right" onclick="cleanLog()">清理 7 天前的放行记录</button>
</h3></div>
<div class="panel-body">
  <div id="greenlogFilter"><?php echo render_greenlog_filter($verdict_filter, $kw, $etype);?></div>
</div>
<div class="table-responsive">
<table class="table table-striped table-hover">
  <thead><tr><th>时间</th><th>文件</th><th class="text-center">证据帧</th><th class="text-center">查看</th><th class="text-center">结果</th><th class="text-center">状态(可修改)</th><th>评分</th><th>模型明细</th><th>引擎 / 耗时</th><th>来源</th></tr></thead>
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
.greenlog-sep{display:inline-block;width:1px;height:18px;margin:0 3px;background:var(--admin-line)}
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
var greenlogState = {page: <?php echo $page?>, verdict: <?php echo json_encode($verdict_filter)?>, kw: <?php echo json_encode($kw)?>, etype: <?php echo json_encode($etype)?>};

function loadGreenlog(extra){
	var data = {ajax:'1', page:greenlogState.page, verdict:greenlogState.verdict, kw:greenlogState.kw, etype:greenlogState.etype};
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
		greenlogState.etype = res.etype;
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

/*
 * 视频就地播，用的是文件管理同一个 file-view.php。
 * 复核时要来回对照「证据帧」和原片，跳去新标签再切回来很难受。
 * file-view.php 里的地址解析出来是 admin/view.php，所以待审和已封的文件也能播——
 * 前台的 view.php 对 block>=1 只会返回一张占位图，正好是需要复核的那些看不了。
 */
$(document).on('click', '.js-video-preview', function(){
	var w = $(window).width(), area;
	if(w >= 1200){ area = ['50%', '60%']; }
	else if(w >= 992){ area = ['75%', '70%']; }
	else if(w >= 768){ area = ['95%', '75%']; }
	else{ area = ['100%', '55%']; }
	layer.open({
		type: 2,
		title: '视频预览',
		shadeClose: true,
		area: area,
		content: './file-view.php?id=' + $(this).data('id')
	});
});

//筛选条和分页是 AJAX 换上去的，事件得挂在容器上
$(document).on('click', '.greenlog-tab', function(){
	greenlogState.verdict = $(this).data('verdict') || '';
	greenlogState.page = 1;
	loadGreenlog();
});
/*
 * 直接在这一页改文件状态，走的是文件管理同一个接口（ajax_file.php?act=setBlock），
 * 三个状态的语义、公示的加与撤都由那边统一处理，两个页面不会各写一套。
 * 改完刷新列表：顶部「当前待审核文件」那个数也要跟着变。
 */
/*
 * 状态选择器的交互在 admin/head.php 里（和文件管理共用），这里只接改完之后的回调：
 * 重新拉一次列表，顶部「当前待审核文件」那个数也要跟着变。
 */
function adminBlockDone(id, status, res){
	loadGreenlog();
}
$(document).on('click', '.greenlog-etype', function(){
	greenlogState.etype = $(this).data('etype') || '';
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
