<?php
if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    die('require PHP >= 7.1 !');
}
include("./includes/common.php");

$csrf_token = bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;

//老的 ?m=mine 链接（书签、外部引用）继续可用：已登录的转去个人中心，
//那边才有重命名/删除/公开私密这些管理操作；游客没有账号，留在这里看浏览器缓存记录
if(isset($_GET['m']) && $_GET['m']=='mine' && $islogin2){
    header('Location: ./user.php?tab=files');
    exit;
}
if(isset($_GET['m']) && $_GET['m']=='mine'){
    $title = '我的文件 - ' . $conf['title'];
    $htext = '我上传的文件';
    if($islogin2){
        $sql = " uid='{$uid}'";
    }else{
        if($conf['userlogin']==1){
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录，<a href="login.php">登录</a>后可永久保留记录）</span>';
        }else{
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录）</span>';
        }
        if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
            $ids = array_reverse($_SESSION['fileids']);
            if(count($ids) > 60){
                $ids = array_splice($ids, 0, 60);
            }
            $ids = implode(',',$ids);
            $sql = " id IN ($ids)";
        }else{
            $sql = " 1=2";
        }
    }
    $link = '&m=mine';
}else{
    $title = $conf['title'];
    $htext = '文件列表';
    $sql = " hide=0";
    $link = '';
}
//搜索词要分三种用途保存：入SQL的转义版、进URL的编码版、进HTML的实体版，混用会出漏洞
$kw = (isset($_GET['kw']) && is_string($_GET['kw']))?trim(strip_tags($_GET['kw'])):null;
if($conf['filesearch']==1 && $kw){
    $kw_sql = daddslashes($kw);
    $sql.=" AND name LIKE '%{$kw_sql}%'";
    $link .= '&kw='.urlencode($kw);
}

include_once SYSTEM_ROOT.'layout_blocks.php';
//类型筛选（数据控制台风/深色工作台风的筛选标签）；$sql_base 不带类型条件，给标签上的计数用
$sql_base = $sql;
$ft = (isset($_GET['ft']) && is_string($_GET['ft']) && array_key_exists($_GET['ft'], layout_type_filters())) ? $_GET['ft'] : '';
if($ft !== ''){
    $sql .= layout_type_filter_sql($ft);
    $link .= '&ft='.urlencode($ft);
}
//排序：蓝白工作台风的列表上方有排序下拉，其它外观没有入口但参数照样认。
//只认白名单里的四种，直接拼进 ORDER BY 的字符串全部是常量
$sort_map = ['new'=>'id DESC', 'old'=>'id ASC', 'big'=>'size DESC', 'small'=>'size ASC'];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sort_map[$_GET['sort']])) ? $_GET['sort'] : 'new';
$order_sql = $sort_map[$sort];
if($sort !== 'new'){
    $link .= '&sort='.$sort;
}

include_once SYSTEM_ROOT.'script_manager.php';
include SYSTEM_ROOT.'header.php';
?>
<?php echo mpimg_render_notice_html($conf);?>
<?php echo mpimg_render_ads_html($conf);?>
<?php
//上传门户风的首屏大上传区：只有这套外观会输出，其它外观保持原来的纯列表首页
$portal_hero = ($site_theme === 'portal' && !$kw && (!isset($_GET['m']) || $_GET['m'] !== 'mine'));
if($portal_hero){
    $hero_size = get_effective_upload_size_limit();
    $hero_size_text = $hero_size > 0 ? ('单个文件最大 '.$hero_size.' MB，支持任意格式') : '不限制文件大小，支持任意格式';
    /*
     * 首屏这块原来只是个跳到 upload.php 的链接，拖文件进来没反应、点一下也是跳页。
     * 现在直接把上传页那套 Vue 组件挂在这儿（同一个 assets/js/uploadnew.js），
     * 需要的 DOM 就四个：#app、#fileInput（拖拽区）、#file（隐藏的文件框）、#csrf_token。
     *
     * 外层仍然保留 <a href="./upload.php">：Vue 或 CDN 没加载成功时，@click.prevent 不会生效，
     * 点击就退回原来的跳转行为，不会变成一个点了没反应的死区。
     *
     * 首屏只做「拖进来就传」，密码、存储位置这些选项仍然在上传页设置。
     */
    $hero_limit = get_effective_upload_count_limit();
    //今日已传数量走会话缓存（2 分钟），只用于前端预检，真正的限额由服务端再判一次
    $hero_used = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
    $hero_remaining = $hero_limit > 0 ? max(0, $hero_limit - $hero_used) : -1;
    //开了多存储时跟上传页口径一致：默认落到第一个可用存储；没开就留空，由服务端用默认存储
    $hero_storage_default = '';
    if(function_exists('storage_multi_open') && storage_multi_open()){
        $hero_storage_list = storage_allowed_list();
        if(count($hero_storage_list) > 1)$hero_storage_default = $hero_storage_list[0];
    }
    $hero_forbid = (isset($conf['forcelogin']) && $conf['forcelogin'] == 1 && empty($islogin2));
?>
<div class="portal-hero" id="app">
  <div class="portal-hero-inner">
    <div class="portal-hero-copy">
      <span class="portal-kicker">快速 · 安全 · 长期可用</span>
      <h1>把文件放上来，<br>链接带去任何地方。</h1>
      <p>上传图片、视频、文档或压缩包，即刻生成可分享的外链。无需安装客户端，打开浏览器就能用。</p>
      <div class="portal-trust">
        <span><i class="fa fa-check" aria-hidden="true"></i> 支持批量上传</span>
        <span><i class="fa fa-check" aria-hidden="true"></i> 自动生成外链</span>
        <span><i class="fa fa-check" aria-hidden="true"></i> 多种存储可选</span>
      </div>
    </div>
    <div class="portal-drop-wrap">
<?php if($hero_forbid){
      //站点要求登录才能上传：这里不挂上传组件，整块就是一个去登录的入口
      ?>
      <a class="portal-drop" href="./login.php">
        <span class="portal-drop-icon"><i class="fa fa-cloud-upload" aria-hidden="true"></i></span>
        <strong>登录后即可上传</strong>
        <small>本站要求登录后才能上传文件</small>
        <span class="portal-drop-btn"><i class="fa fa-sign-in" aria-hidden="true"></i> 去登录</span>
      </a>
<?php }else{?>
      <a class="portal-drop" id="fileInput" href="./upload.php" @click.prevent="clickUpload" :class="{'is-dragover':dragging, 'is-busy':isBlock}">
        <span class="portal-drop-icon"><i class="fa fa-cloud-upload" aria-hidden="true"></i></span>
        <strong v-text="dragging ? '释放鼠标立即上传' : '把文件拖到这里上传'">把文件拖到这里上传</strong>
        <small><?php echo htmlspecialchars($hero_size_text, ENT_QUOTES, 'UTF-8')?>，也可以 Ctrl+V 粘贴</small>
        <span class="portal-drop-btn"><i class="fa fa-upload" aria-hidden="true"></i> 选择本地文件</span>
      </a>
<?php }?>
      <?php //进度、结果和队列：Vue 没挂上时整块靠 v-cloak 藏着，不会露出没渲染的模板 ?>
      <div class="portal-upload-panel" v-cloak v-if="showtype>0 || batchQueue.length>0">
        <div class="upload-main-progress" v-if="showtype==1">
          <div class="progress"><div class="progress-bar" :style="{ width: totalProgress + '%' }">{{progress_tip}}</div></div>
          <div class="portal-upload-meta"><span class="portal-upload-name">{{filename}}</span><span>{{uploadspeed}}</span></div>
        </div>
        <div class="upload-message" :class="'upload-message-'+alert.type" v-if="showtype==2">
          <div class="upload-message-icon"><i class="fa" :class="alertIconClass"></i></div>
          <div class="upload-message-body" v-html="alert.msg"></div>
          <button type="button" class="upload-message-close" @click="showtype=0">×</button>
        </div>
        <div class="upload-result-actions" v-if="showtype==2 && successfulQueueItems().length>1">
          <button type="button" class="upload-link-btn upload-link-btn-secondary" @click="copyAllViewLinks"><i class="fa fa-copy"></i> 全部查看链接</button>
          <button type="button" class="upload-link-btn" @click="copyAllDownloadLinks"><i class="fa fa-copy"></i> 全部下载链接</button>
        </div>
        <div class="upload-queue" v-if="batchQueue.length>0">
          <div class="upload-queue-item" v-for="(item, index) in batchQueue" :key="item.id">
            <div class="upload-queue-index">{{index + 1}}</div>
            <div class="upload-queue-main">
              <div class="upload-queue-name" :title="item.name">{{item.name}}</div>
              <div class="upload-queue-meta">{{item.sizeText}}<span v-if="item.msg"> · {{item.msg}}</span></div>
              <div class="upload-queue-progress" v-if="item.status==='reading' || item.status==='uploading' || item.status==='saving'">
                <span :style="{width: item.progress + '%'}"></span>
              </div>
            </div>
            <div class="upload-queue-actions" v-if="item.status==='success' && item.downloadUrl && item.viewUrl">
              <button type="button" class="upload-link-btn upload-link-btn-secondary" @click.stop="copyText(item.viewUrl)"><i class="fa fa-eye"></i> 查看链接</button>
              <button type="button" class="upload-link-btn" @click.stop="copyText(item.downloadUrl)"><i class="fa fa-download"></i> 下载链接</button>
            </div>
            <div class="upload-queue-status">
              <span class="label" :class="queueStatusClass(item.status)">{{queueStatusText(item.status)}}</span>
            </div>
          </div>
        </div>
      </div>
      <p class="portal-drop-tip">要设访问密码、选存储位置，或者查看完整上传说明，去 <a href="./upload.php">上传页</a>。</p>
    </div>
  </div>
  <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8')?>">
  <input type="file" id="file" name="myfile[]" @change="selectFile" multiple style="display:none">
</div>
<?php }?>
<?php
//布局型外观的额外结构：统计卡、类型筛选、右侧预览，只在对应外观下输出
$layout_key = (isset($layout_themes) && in_array($site_theme, $layout_themes, true)) ? $site_theme : '';
$layout_is_mine = isset($_GET['m']) && $_GET['m'] === 'mine';
//游客的“我的文件”也使用个人中心的文件表格排版。登录用户在文件开头已经跳转到 user.php，
//所以这里的 guest mine 只可能来自当前浏览器会话保存的上传记录。
$guest_mine = $layout_is_mine && !$islogin2;
$layout_counts = null;
if($layout_key === 'console' || $layout_key === 'workspace' || $layout_key === 'cockpit' || in_array($layout_key, studio_family_keys(), true)){
    $layout_counts = layout_type_counts($DB, $sql_base);
}
//渐变仪表盘风的问候栏、额度卡、统计卡和右侧栏都要用这两个数，先算一次传下去
$cockpit_today = 0;
if($layout_key === 'cockpit'){
    $cockpit_today = layout_today_total($DB, $sql_base);
}
$layout_base_query = '';
if($layout_is_mine) $layout_base_query .= 'm=mine';
if($kw) $layout_base_query .= ($layout_base_query === '' ? '' : '&').'kw='.urlencode($kw);
?>
<div class="container">
<?php if($layout_key === 'cockpit'){echo layout_render_cockpit_head($DB, $layout_counts['']);}?>
<?php if($layout_key === 'workspace' || $layout_key === 'cockpit' || in_array($layout_key, studio_family_keys(), true)){?><div class="layout-shell"><?php }?>
<?php if($layout_key === 'cockpit'){?><div class="cockpit-main">
<?php echo layout_render_cockpit_quota($DB, layout_storage_used($DB, $sql_base), $layout_counts[''], $cockpit_today);?>
<?php echo layout_render_stats($layout_counts, $cockpit_today);?>
<?php }?>
<?php //蓝白工作台风：主视觉横幅 + 四张统计卡，都在左边这一列里
if(in_array($layout_key, studio_family_keys(), true)){?><div class="studio-main">
<?php echo layout_render_studio_hero($layout_counts[''], $layout_is_mine);?>
<?php //清爽极简风和蓝天白云风的原型是五张统计卡，各自多一张文档 / 音频
$studio_extra = in_array($layout_key, ['crisp', 'neo'], true) ? 'doc' : (($layout_key === 'azure') ? 'audio' : '');
echo layout_render_stats($layout_counts, layout_today_total($DB, $sql_base), $studio_extra);?>
<?php }?>
    <div class="well bs-component">
<?php if($layout_key === 'workspace'){?>
        <div class="layout-crumb"><i class="fa fa-folder-o" aria-hidden="true"></i> <span>工作空间</span> <i class="fa fa-angle-right" aria-hidden="true"></i> <strong><?php echo $layout_is_mine ? '我的文件' : '全部文件'?></strong></div>
<?php }?>
        <h2><?php echo $htext?>
        <?php if($conf['filesearch']==1){?><span class="searchbox">
            <form class="form-inline" action="./" method="GET">
                <?php if(isset($_GET['m']) && is_string($_GET['m'])){?><input name="m" type="hidden" value="<?php echo htmlspecialchars($_GET['m'], ENT_QUOTES, 'UTF-8')?>"><?php }?>
				<input name="kw" class="form-control" type="search" placeholder="请输入搜索关键字" value="<?php echo htmlspecialchars((string)$kw, ENT_QUOTES, 'UTF-8')?>" required="">
				<button class="btn btn-default btn-raised btn-sm" type="submit"><i class="fa fa-search" aria-hidden="true"></i> 搜索</button>
			</form>
        </span><?php }?><?php if($layout_key === 'mac'){echo layout_render_mac_viewtoggle();}?><?php if($layout_key === 'console' || $layout_key === 'workspace' || $layout_key === 'mac'){?><a class="layout-cta" href="./upload.php"><i class="fa fa-plus" aria-hidden="true"></i> <span><?php echo $layout_key === 'mac' ? '上传文件' : '上传新文件'?></span></a><?php }?></h2>
<?php echo render_permission_bar($DB, 'list');?>
<?php if($layout_key === 'console'){?>
        <p class="layout-page-sub">管理、预览并分享你上传的所有内容。</p>
        <?php echo layout_render_stats($layout_counts, layout_today_total($DB, $sql_base));?>
<?php }elseif($layout_key === 'portal'){?>
        <p class="layout-page-sub">浏览大家刚刚分享的文件，点文件名即可查看或下载。</p>
<?php }elseif($layout_key === 'cockpit'){?>
        <p class="layout-page-sub">按上传时间排序，点文件名即可查看、下载或复制外链。</p>
<?php }elseif($layout_key === 'mac'){
        //macOS 窗口风：列表上方放一块拖拽提示区。搜索/筛选状态下不显示，
        //免得把用户刚查出来的结果顶到屏幕外面去
        if(!$kw && $ft === ''){echo layout_render_mac_drop();}
}?>
<?php if(($layout_key === 'console' || $layout_key === 'workspace' || $layout_key === 'cockpit' || in_array($layout_key, studio_family_keys(), true)) && $layout_counts){?>
        <div class="studio-filterbar">
        <?php echo layout_render_filters($layout_counts, $ft, $layout_base_query);?>
        <?php if(in_array($layout_key, studio_family_keys(), true)){echo layout_render_studio_tools($sort, $layout_base_query);}?>
        </div>
<?php }?>
        <?php if(isset($_GET['m']) && $_GET['m']=='mine'){?>
        <input type="file" id="replaceFileInput" style="display:none">
        <?php }?>
<?php if($guest_mine){?>
        <div class="uc-batchbar" id="guestBatchBar" hidden>
            <span>已选中 <b id="guestSelCount">0</b> 个文件</span>
            <button type="button" class="uc-btn uc-btn-danger" id="guestBatchDelete"><i class="fa fa-trash" aria-hidden="true"></i> 批量删除</button>
            <button type="button" class="uc-btn" id="guestSelClear">取消选择</button>
        </div>
<?php }?>
        <div class="table-responsive">
       <table class="table table-hover <?php echo $guest_mine ? 'uc-filelist guest-filelist' : 'table-striped filelist filelist-main'?>">
            <thead>
                <tr>
<?php if($guest_mine){?>
                    <th class="uc-col-check"><input type="checkbox" id="guestCheckAll" title="全选本页"></th>
                    <th>文件名</th>
                    <th class="uc-col-size">大小</th>
                    <th class="uc-col-state">状态</th>
                    <th class="uc-col-time">上传时间</th>
                    <th class="uc-col-act">操作</th>
<?php }else{?>
                    <th>#</th>
                    <?php //工作台家族把操作列放到最右边，和这几套外观的原型一致；其余外观保持原来的第二列
                    if(!in_array($layout_key, studio_family_keys(), true)){?><th>操作</th><?php }?>
                    <th>文件名</th>
                    <th>文件大小</th>
                    <th>文件格式</th>
                    <th>上传时间</th>
                    <th>上传者IP</th>
                    <?php if(in_array($layout_key, studio_family_keys(), true)){?><th>操作</th><?php }?>
<?php }?>
                </tr>
            </thead>
            <tbody>
<?php
$numrows=$DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
$pagesize=15;
$pages=ceil($numrows/$pagesize);
$page=isset($_GET['page'])?intval($_GET['page']):1;
$offset=$pagesize*($page - 1);

$rs=$DB->query("SELECT * FROM pre_file WHERE{$sql} ORDER BY {$order_sql} LIMIT $offset,$pagesize");
$i=1;
while($res = $rs->fetch())
{
	$fileurl = './down.php/'.$res['token'].'.'.($res['type']?$res['type']:'file');
	$viewurl = './file.php?hash='.$res['token'];
	$blocked = intval($res['block']) === 1;
	$pending = intval($res['block']) === 2;
	$hidden = intval($res['hide']) === 1;
	$haspwd = !empty($res['pwd']);
	$can_manage = can_manage_file($res);
	$delete_reason = file_delete_locked_reason($res);
	if($delete_reason === '' && !$can_manage) $delete_reason = '游客只能管理本浏览器七天内上传的文件';
	$actions = '<div class="file-actions"><a class="file-action file-action-down" href="'.$fileurl.'" title="下载"><i class="fa fa-download" aria-hidden="true"></i> <span class="file-action-label">下载</span></a><a class="file-action file-action-view" href="'.$viewurl.'" title="查看"><i class="fa fa-eye" aria-hidden="true"></i> <span class="file-action-label">查看</span></a>';
	if(isset($_GET['m']) && $_GET['m']=='mine' && can_edit_file_online($res)){
		$actions .= '<a class="file-action file-action-edit" href="./edit.php?id='.$res['id'].'" title="编辑"><i class="fa fa-pencil" aria-hidden="true"></i> <span class="file-action-label">编辑</span></a>';
	}
	if(isset($_GET['m']) && $_GET['m']=='mine' && can_manage_file($res)){
		$actions .= '<a class="file-action file-action-replace" href="javascript:void(0)" onclick="replace_upload_click('.intval($res['id']).')" title="覆盖"><i class="fa fa-refresh" aria-hidden="true"></i> <span class="file-action-label">覆盖</span></a>';
	}
	$actions .= '</div>';
	$type_text = $res['type']?$res['type']:'未知';
	//设了访问密码的文件，在文件名后面挂个锁，列表里一眼能看出来。
	//不用 fa-fw，免得被各外观给类型图标定的颜色带跑
	$lock_icon = !empty($res['pwd']) ? ' <i class="fa fa-lock filelist-lock" title="该文件需要密码才能查看" aria-hidden="true"></i>' : '';
	$row_ip = preg_replace('/\d+$/','*',$res['ip']);
	//深色工作台风的预览面板要直接显示图片/视频/文本：没有设密码、没被封禁、且是可在线预览的
	//类型时才给出预览地址，其余情况面板里还是显示文件类型图标
	$layout_text_max = defined('LAYOUT_TEXT_PREVIEW_MAX') ? LAYOUT_TEXT_PREVIEW_MAX : 256 * 1024;
	$preview_url = '';
	$preview_kind = '';
	if(empty($res['pwd']) && intval($res['block']) === 0){
		if(is_view($res['type'])){
			$preview_kind = get_view_type($res['type']);
			if($preview_kind === 'image' || $preview_kind === 'video' || $preview_kind === 'audio'){
				$preview_url = './view.php/'.$res['token'].'.'.$res['type'].'?preview=1';
			}else{
				$preview_kind = '';
			}
		//常量定义在 layout_blocks.php 里，万一只传了部分文件也要能正常降级，不能静默失效
		}elseif(is_editable_file_type($res['type']) && intval($res['size']) <= $layout_text_max){
			//txt/json/js 这类文本文件走 text.php 取内容；太大的不自动拉，免得点一下列表就下几 MB
			$preview_kind = 'text';
			$preview_url = './text.php?hash='.$res['token'].'&preview=1';
		}
	}
	//data-* 给布局型外观用：深色工作台风的右侧预览面板直接读这几个值
	$row_attr = ' data-group="'.layout_type_group($res['type']).'"'
		.' data-preview="'.htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8').'"'
		.' data-preview-kind="'.htmlspecialchars($preview_kind, ENT_QUOTES, 'UTF-8').'"'
		.' data-name="'.htmlspecialchars($res['name'], ENT_QUOTES, 'UTF-8').'"'
		.' data-size="'.htmlspecialchars(size_format($res['size']), ENT_QUOTES, 'UTF-8').'"'
		.' data-type="'.htmlspecialchars($type_text, ENT_QUOTES, 'UTF-8').'"'
		.' data-time="'.htmlspecialchars($res['addtime'], ENT_QUOTES, 'UTF-8').'"'
		.' data-ip="'.htmlspecialchars($row_ip, ENT_QUOTES, 'UTF-8').'"'
		.' data-down="'.htmlspecialchars($fileurl, ENT_QUOTES, 'UTF-8').'"'
		.' data-view="'.htmlspecialchars($viewurl, ENT_QUOTES, 'UTF-8').'"'
		.' data-icon="'.type_to_icon($res['type']).'"'
		.' data-lock="'.(!empty($res['pwd']) ? '1' : '').'"';
if($guest_mine){
	$guest_actions = '<div class="uc-acts">'
		.'<a class="uc-act" href="'.htmlspecialchars($fileurl, ENT_QUOTES, 'UTF-8').'" title="下载"><i class="fa fa-download" aria-hidden="true"></i></a>'
		.'<a class="uc-act" href="'.htmlspecialchars($viewurl, ENT_QUOTES, 'UTF-8').'" title="查看"><i class="fa fa-eye" aria-hidden="true"></i></a>';
	if(can_edit_file_online($res)){
		$guest_actions .= '<a class="uc-act" href="./edit.php?id='.intval($res['id']).'" title="在线编辑"><i class="fa fa-code" aria-hidden="true"></i></a>';
	}
	if($can_manage){
		$guest_actions .= '<button type="button" class="uc-act" data-guest="replace" title="重新上传替换"><i class="fa fa-refresh" aria-hidden="true"></i></button>';
	}
	if($delete_reason === ''){
		$guest_actions .= '<button type="button" class="uc-act uc-act-danger" data-guest="delete" title="删除"><i class="fa fa-trash" aria-hidden="true"></i></button>';
	}
	$guest_actions .= '</div>';
	$check_attr = $delete_reason !== '' ? ' disabled title="'.htmlspecialchars($delete_reason, ENT_QUOTES, 'UTF-8').'"' : '';
	$state = '';
	if($blocked) $state .= '<span class="uc-badge uc-badge-danger">已冻结</span>';
	if($pending) $state .= '<span class="uc-badge uc-badge-warn">待人工审核</span>';
	$state .= $hidden ? '<span class="uc-badge">私密</span>' : '<span class="uc-badge uc-badge-ok">公开</span>';
	if($haspwd) $state .= '<span class="uc-badge uc-badge-warn"><i class="fa fa-lock" aria-hidden="true"></i> 有密码</span>';
	echo '<tr data-id="'.intval($res['id']).'" data-token="'.htmlspecialchars($res['token'], ENT_QUOTES, 'UTF-8').'"'.($blocked ? ' class="is-blocked"' : '').'>'
		.'<td class="uc-col-check"><input type="checkbox" class="guest-check"'.$check_attr.'></td>'
		.'<td class="uc-col-name"><i class="fa '.type_to_icon($res['type']).' fa-fw"></i><span class="uc-name">'.$res['name'].'</span></td>'
		.'<td class="uc-col-size">'.size_format($res['size']).'</td>'
		.'<td class="uc-col-state">'.$state.'</td>'
		.'<td class="uc-col-time">'.$res['addtime'].'</td>'
		.'<td class="uc-col-act">'.$guest_actions.'</td></tr>';
	continue;
}
$cell_action = '<td class="filelist-actions-cell">'.$actions.'</td>';
$cell_rest = '<td><i class="fa '.type_to_icon($res['type']).' fa-fw"></i>'.$res['name'].$lock_icon.'</td><td>'.size_format($res['size']).'</td><td><span class="file-type-badge">'.htmlspecialchars($type_text).'</span></td><td>'.$res['addtime'].'</td><td>'.$row_ip.'</td>';
//操作列的位置跟着表头走：蓝白工作台风在最右，其余外观在第二列
echo '<tr'.$row_attr.'><td><b>'.$i++.'</b></td>'
	.(in_array($layout_key, studio_family_keys(), true) ? $cell_rest.$cell_action : $cell_action.$cell_rest).'</tr>';
}
if($numrows == 0) echo $guest_mine
	? '<tr><td colspan="6" class="uc-empty">还没上传过任何文件</td></tr>'
	: '<tr><td colspan="7" align="center">还没上传过任何文件</td></tr>';
?>
            </tbody>
        </table>
        </div>
        <div class="filelist-footer">
        <div class="filelist-summary">共有 <?php echo $numrows?> 个文件&nbsp;&nbsp;当前第 <?php echo $page?> 页，共 <?php echo $pages?> 页</div>
        <nav class="filelist-pager">
  <ul class="pagination pagination-sm">
<?php
$first=1;
$prev=$page-1;
$next=$page+1;
$last=$pages;
if ($page>1)
{
echo '<li><a href="index.php?page='.$first.$link.'">首页</a></li>';
echo '<li><a href="index.php?page='.$prev.$link.'">&laquo;</a></li>';
} else {
echo '<li class="disabled"><a>首页</a></li>';
echo '<li class="disabled"><a>&laquo;</a></li>';
}
$pagegroup = 10;
$start = $page < $pagegroup ? 1 : floor($page / $pagegroup) * $pagegroup;
$end = min($start + $pagegroup, $pages);
for ($i=$start;$i<=$end;$i++){
	if($i == $page){
		echo '<li class="disabled"><a>'.$i.'</a></li>';
	}else{
		echo '<li><a href="index.php?page='.$i.$link.'">'.$i.'</a></li>';
	}
}
echo '';
if ($page<$pages)
{
echo '<li><a href="index.php?page='.$next.$link.'">&raquo;</a></li>';
echo '<li><a href="index.php?page='.$last.$link.'">尾页</a></li>';
} else {
echo '<li class="disabled"><a>&raquo;</a></li>';
echo '<li class="disabled"><a>尾页</a></li>';
}
?>
  </ul>
</nav>
</div>
    </div>
<?php if($layout_key === 'workspace'){echo layout_render_preview();?></div><?php }?>
<?php if($layout_key === 'cockpit'){?></div><?php echo layout_render_cockpit_side($DB, $layout_counts, $sql_base);?></div><?php }?>
<?php //工作台家族：列表下面可能还有一条推广横幅，再收掉左列、输出右侧数据列
if(in_array($layout_key, studio_family_keys(), true)){echo layout_render_studio_promo();?></div><?php echo layout_render_studio_side($DB, $sql_base, $layout_counts['']);?></div><?php }?>
<?php include SYSTEM_ROOT.'footer.php';?>
<?php if($layout_key === 'workspace'){?>
<script src="./assets/js/layout-workspace.js?v=<?php echo VERSION?>"></script>
<?php }?>
<?php if($layout_key === 'mac'){?>
<script src="./assets/js/layout-mac.js?v=<?php echo VERSION?>"></script>
<?php }?>
<?php //上传门户风的首屏上传区：用的就是上传页那套脚本，只有这套外观的首页才加载。
//强制登录又没登录时首屏是个去登录的入口，不需要这些脚本
if(!empty($portal_hero) && empty($hero_forbid)){?>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/vue/2.6.14/vue.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>
var upload_max_filesize = '<?php echo intval($hero_size)?>';
var upload_count_limit = <?php echo intval($hero_limit)?>;
var upload_count_used = <?php echo intval($hero_used)?>;
var upload_count_remaining = <?php echo intval($hero_remaining)?>;
var upload_storage_default = <?php echo json_encode($hero_storage_default)?>;
</script>
<script src="./assets/js/uploadnew.js?v=<?php echo VERSION?>"></script>
<?php }?>
<?php if(isset($_GET['m']) && $_GET['m']=='mine'){?>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>
var replace_csrf_token = '<?php echo $csrf_token?>';
var replace_target_id = 0;

//游客文件表格沿用个人中心的勾选体验，但删除仍逐条走 ajax.php 的游客权限校验：
//服务端会按 token 复查当前会话归属、七天期限以及冻结/待审状态。
function guest_selected_rows(){
  return $('.guest-filelist tbody .guest-check:checked').closest('tr');
}
function guest_refresh_batchbar(){
  var $selected = guest_selected_rows();
  var $available = $('.guest-filelist tbody .guest-check:not(:disabled)');
  $('#guestSelCount').text($selected.length);
  $('#guestBatchBar').prop('hidden', $selected.length === 0);
  $('#guestCheckAll').prop('checked', $available.length > 0 && $selected.length === $available.length);
}
$('#guestCheckAll').on('change', function(){
  $('.guest-filelist tbody .guest-check:not(:disabled)').prop('checked', this.checked);
  guest_refresh_batchbar();
});
$('.guest-filelist').on('change', '.guest-check', guest_refresh_batchbar);
$('#guestSelClear').on('click', function(){
  $('.guest-filelist tbody .guest-check').prop('checked', false);
  $('#guestCheckAll').prop('checked', false);
  guest_refresh_batchbar();
});

function guest_delete_files(tokens){
  if(!tokens.length) return;
  var loading = layer.load(2, {shade:[0.2,'#fff']});
  var ok = 0, failed = [];
  $.getJSON('ajax.php?act=csrf_token').done(function(tokenData){
    if(tokenData && tokenData.csrf_token) replace_csrf_token = tokenData.csrf_token;
    var next = function(index){
      if(index >= tokens.length){
        layer.close(loading);
        if(failed.length === 0){
          layer.msg('已删除 '+ok+' 个文件', {icon:1, time:1200}, function(){ window.location.reload(); });
        }else{
          layer.alert('已删除 '+ok+' 个文件，'+failed.length+' 个删除失败：'+failed.join('；'), {icon: ok ? 0 : 2}, function(){ window.location.reload(); });
        }
        return;
      }
      $.ajax({
        type: 'POST',
        url: 'ajax.php?act=deleteFile',
        data: {hash: tokens[index], csrf_token: replace_csrf_token},
        dataType: 'json',
        success: function(res){
          if(res && res.code === 0) ok++;
          else failed.push((res && res.msg) || '服务器返回异常');
          next(index + 1);
        },
        error: function(){ failed.push('网络错误'); next(index + 1); }
      });
    };
    next(0);
  }).fail(function(){
    layer.close(loading);
    layer.msg('无法获取安全令牌，请刷新页面重试', {icon:2});
  });
}

$('#guestBatchDelete').on('click', function(){
  var tokens = guest_selected_rows().map(function(){ return $(this).attr('data-token'); }).get();
  if(!tokens.length){ layer.msg('请先选择文件'); return; }
  layer.confirm('确定删除选中的 '+tokens.length+' 个文件？删除后外链立即失效，且无法恢复。',
    {icon:3, title:'批量删除'}, function(index){
      layer.close(index);
      guest_delete_files(tokens);
    });
});

$('.guest-filelist').on('click', '[data-guest]', function(){
  var $row = $(this).closest('tr');
  if($(this).data('guest') === 'replace'){
    replace_upload_click($row.data('id'));
    return;
  }
  var name = $row.find('.uc-name').text();
  layer.confirm('确定删除《'+name+'》？删除后外链立即失效，且无法恢复。',
    {icon:3, title:'删除文件'}, function(index){
      layer.close(index);
      guest_delete_files([$row.attr('data-token')]);
    });
});

function replace_upload_click(id){
  replace_target_id = id;
  $("#replaceFileInput").val('');
  $("#replaceFileInput").trigger('click');
}
$("#replaceFileInput").on('change', function(){
  var file = this.files && this.files[0];
  if(!file || !replace_target_id) return;
  var fileId = replace_target_id;
  var ii = layer.load(2, {shade:[0.2,'#fff']});

  //本页面可能已经打开一段时间，会话里的csrf_token可能已被同一浏览器其它页面刷新过，先取一次最新值再提交
  $.getJSON('ajax.php?act=csrf_token', function(tokenData){
    if(tokenData && tokenData.csrf_token) replace_csrf_token = tokenData.csrf_token;
    replace_startUpload(file, fileId, ii);
  }).fail(function(){
    replace_startUpload(file, fileId, ii);
  });
});
function replace_startUpload(file, fileId, ii){
  replace_getFileHash(file).then(function(hash){
    $.ajax({
      type: 'POST',
      url: 'ajax.php?act=pre_upload',
      data: {
        csrf_token: replace_csrf_token,
        name: file.name,
        hash: hash,
        size: file.size,
        show: '1',
        ispwd: '0',
        pwd: '',
        replace_id: fileId
      },
      dataType: 'json',
      success: function(data){
        if(data.csrf_token) replace_csrf_token = data.csrf_token;
        if(data.code == 1){
          layer.close(ii);
          layer.alert(data.msg || '替换成功，链接保持不变', {icon:1}, function(){ window.location.reload(); });
        }else if(data.code == 0){
          replace_uploadBody(data, file, ii);
        }else{
          layer.close(ii);
          layer.alert(data.msg || '替换失败', {icon:2});
        }
      },
      error: function(){
        layer.close(ii);
        layer.msg('服务器错误');
      }
    });
  });
}
function replace_getFileHash(file){
  return new Promise(function(resolve){
    var fileReader = new FileReader(),
        blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice,
        chunkSize = 2097152,
        chunks = Math.ceil(file.size / chunkSize),
        currentChunk = 0,
        spark = new SparkMD5();
    if(chunks === 0){
      resolve(SparkMD5.hashBinary(''));
      return;
    }
    loadNext();
    fileReader.onload = function(e){
      spark.appendBinary(e.target.result);
      currentChunk++;
      if(currentChunk < chunks){
        loadNext();
      }else{
        resolve(spark.end());
      }
    };
    function loadNext(){
      var start = currentChunk * chunkSize,
          end = start + chunkSize >= file.size ? file.size : start + chunkSize;
      fileReader.readAsBinaryString(blobSlice.call(file, start, end));
    }
  });
}
function replace_uploadBody(preResult, file, ii){
  if(preResult.third){
    var data = new FormData();
    for(var key in preResult.post){ data.append(key, preResult.post[key]); }
    data.append('file', file);
    $.ajax({
      type: 'POST', url: preResult.url, data: data, processData: false, contentType: false, dataType: 'html',
      success: function(){ replace_completeUpload(preResult.hash, ii); },
      error: function(){ layer.close(ii); layer.msg('上传失败，请稍后再试'); }
    });
    return;
  }
  var chunks = preResult.chunks, chunkSize = preResult.chunksize;
  var blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice;
  function uploadChunk(chunk){
    var start = (chunk - 1) * chunkSize;
    var end = start + chunkSize > file.size ? file.size : start + chunkSize;
    var blob = blobSlice.call(file, start, end);
    var data = new FormData();
    data.append('file', blob);
    data.append('hash', preResult.hash);
    data.append('chunk', chunk);
    data.append('csrf_token', replace_csrf_token);
    $.ajax({
      type: 'POST', url: 'ajax.php?act=upload_part', data: data, processData: false, contentType: false, dataType: 'json',
      success: function(res){
        if(res.csrf_token) replace_csrf_token = res.csrf_token;
        if(res.code == -1){
          layer.close(ii);
          layer.alert(res.msg || '替换失败', {icon:2});
          return;
        }
        if(chunk < chunks){
          uploadChunk(chunk + 1);
        }else if(res.code == 1){
          layer.close(ii);
          layer.alert(res.msg || '替换成功，链接保持不变', {icon:1}, function(){ window.location.reload(); });
        }
      },
      error: function(){ layer.close(ii); layer.msg('上传失败，请稍后再试'); }
    });
  }
  uploadChunk(1);
}
function replace_completeUpload(hash, ii){
  $.ajax({
    type: 'POST', url: 'ajax.php?act=complete_upload', data: {hash: hash, csrf_token: replace_csrf_token}, dataType: 'json',
    success: function(res){
      layer.close(ii);
      if(res.code == 1){
        layer.alert(res.msg || '替换成功，链接保持不变', {icon:1}, function(){ window.location.reload(); });
      }else{
        layer.alert(res.msg || '替换失败', {icon:2});
      }
    },
    error: function(){ layer.close(ii); layer.msg('服务器错误'); }
  });
}
</script>
<?php }?>
<?php if(!empty($conf['gonggao'])){?>
<link href="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.css" rel="stylesheet">
<script src="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<script>
$(function() {
    if(!$.cookie('gonggao')){
        $.snackbar({content: "<?php echo $conf['gonggao']?>", timeout: 10000});
        var cookietime = new Date(); 
        cookietime.setTime(cookietime.getTime() + (60*60*1000));
        $.cookie('gonggao', false, { expires: cookietime });
    }
});
</script>
<?php }?>
</body>
</html>
