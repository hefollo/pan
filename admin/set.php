<?php
/**
 * 系统设置
**/
define('IN_ADMIN', true);
include("../includes/common.php");
$title='系统设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
  <div class="container" style="padding-top:70px;">
    <div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">
<?php
$mod=isset($_GET['mod'])?$_GET['mod']:null;
?>
<?php
if($mod=='site'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">网站信息设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站标题</label>
	  <div class="col-sm-10"><input type="text" name="title" value="<?php echo $conf['title']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">关键字</label>
	  <div class="col-sm-10"><input type="text" name="keywords" value="<?php echo $conf['keywords']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">网站描述</label>
	  <div class="col-sm-10"><input type="text" name="description" value="<?php echo $conf['description']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">禁止访问IP</label>
	  <div class="col-sm-10"><textarea class="form-control" name="blackip" rows="2" placeholder="多个IP用|隔开"><?php echo $conf['blackip']?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">首页公告</label>
	  <div class="col-sm-10"><textarea class="form-control" name="gonggao" rows="3" placeholder="不填写则不显示首页公告"><?php echo htmlspecialchars($conf['gonggao'])?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文件查看页公告</label>
	  <div class="col-sm-10"><textarea class="form-control" name="gg_file" rows="3" placeholder="不填写则不显示"><?php echo htmlspecialchars($conf['gg_file'])?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">违规文件公示</label>
	  <div class="col-sm-10"><select class="form-control" name="violation_open" default="<?php echo isset($conf['violation_open'])?$conf['violation_open']:1?>"><option value="0">关闭</option><option value="1">开启</option></select><span class="help-block">开启后前台显示“违规公示”页，公示内容在<a href="./set_violation.php">违规公示管理</a>里维护</span></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">公示页说明</label>
	  <div class="col-sm-10"><textarea class="form-control" name="violation_notice" rows="3" placeholder="显示在违规公示页顶部的说明文字"><?php echo htmlspecialchars(isset($conf['violation_notice'])?$conf['violation_notice']:'')?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">统计代码</label>
	  <div class="col-sm-10"><textarea class="form-control" name="tongji" rows="3" placeholder="不填写则不显示统计代码"><?php echo htmlspecialchars($conf['tongji'])?></textarea></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文件搜索功能</label>
	  <div class="col-sm-10"><select class="form-control" name="filesearch" default="<?php echo $conf['filesearch']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='appearance'){
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : default_site_theme();
if(!in_array($site_theme, site_theme_keys(), true)){
	$site_theme = default_site_theme();
}
//顺手同步一次静态 404 页的外观：已经设置好外观的站点不用再点一次保存
sync_404_theme($site_theme);
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">外观设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" role="form">
	<div class="appearance-group">
	  <div class="appearance-group-head">
	    <strong>布局型外观</strong>
	    <small>会改变页面结构：导航位置、内容排版都不一样，前台和后台同时生效。</small>
	  </div>
	<div class="appearance-options">
	  <label class="appearance-card <?php echo $site_theme === 'dashboard' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="dashboard" <?php echo $site_theme === 'dashboard' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-dashboard">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>控制台侧栏风</strong>
	    <small>顶部导航变为左侧固定侧栏，内容区改为圆角卡片布局，更接近后台管理系统的观感。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'console' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="console" <?php echo $site_theme === 'console' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-console">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>数据控制台风</strong>
	    <small>更紧凑的左侧侧栏，标题与搜索独立成顶栏，列表改为白色卡片，信息密度最高。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'portal' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="portal" <?php echo $site_theme === 'portal' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-portal">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>上传门户风</strong>
	    <small>居中的门户式顶部导航，首页多一块大号上传引导区，绿色配色，适合面向访客的公开分享站。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'workspace' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="workspace" <?php echo $site_theme === 'workspace' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-workspace">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>深色工作台风</strong>
	    <small>深色底 + 左侧图标导航条（鼠标移上去展开文字），内容为深色圆角面板，适合长时间浏览管理。</small>
	  </label>
	</div>
	</div>
	<div class="appearance-group">
	  <div class="appearance-group-head">
	    <strong>配色型外观</strong>
	    <small>只改变颜色、背景纹理和质感，页面结构保持默认的顶部导航布局。</small>
	  </div>
	<div class="appearance-options">
	  <label class="appearance-card <?php echo $site_theme === 'cloud' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="cloud" <?php echo $site_theme === 'cloud' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-cloud">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>蓝白清爽</strong>
	    <small>蓝白清爽风格，适合默认展示。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'night' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="night" <?php echo $site_theme === 'night' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-night">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>黑夜风格</strong>
	    <small>深色背景、蓝色高亮，适合夜间浏览。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'neon' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="neon" <?php echo $site_theme === 'neon' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-neon">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>霓虹科技黑夜</strong>
	    <small>蓝紫霓虹、科技感边框，适合深色展示。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'aurora' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="aurora" <?php echo $site_theme === 'aurora' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-aurora">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>蓝紫渐变玻璃</strong>
	    <small>渐变背景、半透明玻璃卡片，适合展示型页面。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'onefour' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="onefour" <?php echo $site_theme === 'onefour' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-onefour">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>暗黑科技后台风</strong>
	    <small>深色点阵背景、半透明暗色面板、圆角按钮与标签，整体偏科技感、数据管理感，适合文件列表、管理后台、资源站页面。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'celadon' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="celadon" <?php echo $site_theme === 'celadon' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-celadon">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>青瓷微澜</strong>
	    <small>青瓷色同心波纹、留白通透，冷静耐看，适合作品集、图床和长时间浏览的列表页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'lilac' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="lilac" <?php echo $site_theme === 'lilac' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-lilac">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>淡紫点阵</strong>
	    <small>薰衣草底色配细密点阵，柔和不刺眼，适合内容站、文档站和图片分享。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'paper' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="paper" <?php echo $site_theme === 'paper' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-paper">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>米白纸张</strong>
	    <small>极简纸张质感，横线纸纹配墨黑标题，几乎无色相干扰，适合以文件名和文字为主的列表。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'blush' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="blush" <?php echo $site_theme === 'blush' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-blush">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>淡粉暖调</strong>
	    <small>浅粉底色配玫瑰色高亮，温和轻盈，适合相册、素材站和面向大众的分享页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'sky' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="sky" <?php echo $site_theme === 'sky' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-sky">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>天蓝细纹</strong>
	    <small>青蓝色调配斜向细纹，比默认的蓝白更冷更透，适合工具站和资源下载页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'mint' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="mint" <?php echo $site_theme === 'mint' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-mint">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>薄荷蜂巢</strong>
	    <small>薄荷绿蜂巢暗纹，清爽有生气，适合图床首页和面向年轻用户的站点。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'sunset' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="sunset" <?php echo $site_theme === 'sunset' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-sunset">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>落日熔金</strong>
	    <small>珊瑚橙到品红的落日渐变，叠磨砂玻璃卡片与暖色辉光，浓烈张扬，适合首页和活动页。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'abyss' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="abyss" <?php echo $site_theme === 'abyss' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-abyss">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>深海玻璃</strong>
	    <small>深海蓝绿渐变配青色辉光，通透安静的磨砂玻璃，适合长时间浏览的资源站。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'emerald' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="emerald" <?php echo $site_theme === 'emerald' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-emerald">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>翡翠流光</strong>
	    <small>翡翠绿到松石色的流光渐变，磨砂玻璃配柔亮描边，清透有质感，适合作品集和图床。</small>
	  </label>
	  <label class="appearance-card <?php echo $site_theme === 'sakura' ? 'active' : null;?>">
	    <input type="radio" name="site_theme" value="sakura" <?php echo $site_theme === 'sakura' ? 'checked' : null;?>>
	    <span class="appearance-preview appearance-preview-sakura">
	      <span class="appearance-nav"></span>
	      <span class="appearance-panel">
	        <span></span><span></span><span></span>
	      </span>
	    </span>
	    <strong>樱雾玻璃</strong>
	    <small>粉紫到天青的浅色雾面渐变，白色磨砂卡片，轻盈明亮，适合相册和展示型页面。</small>
	  </label>
	</div>
	</div>
	<div class="form-group appearance-submit">
	  <input type="submit" name="submit" value="保存外观" class="btn btn-primary form-control"/>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
保存后前台页面会立即使用选中的外观。布局型外观会同时改变后台：“控制台侧栏风”“数据控制台风”“深色工作台风”把后台顶部导航变成左侧侧栏，“上传门户风”只换后台配色、保留顶部导航；配色型外观不影响后台布局。
</div>
</div>
<?php
}elseif($mod=='api'){
$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$admin_path = substr($sitepath, strrpos($sitepath, '/'));
$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].str_replace($admin_path,'',$sitepath).'/';
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">上传API设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">上传API开关</label>
	  <div class="col-sm-9"><select class="form-control" name="api_open" default="<?php echo $conf['api_open']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">来源域名白名单</label>
	  <div class="col-sm-9"><input type="text" name="api_referer" value="<?php echo $conf['api_referer']; ?>" class="form-control" placeholder="多个域名用|隔开"/><font color="green">多个域名用|隔开，不填写则不限制来源域名</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<div class="panel panel-info">
<div class="panel-heading"><h3 class="panel-title">上传API文档</h3></div>
<div class="panel-body">
<pre>
API接口地址：<?php echo $siteurl?>api.php

当前API支持JSON、JSONP、FORM 3种返回方式，支持Web跨域调用，也支持程序中直接调用。

请求方式：POST  multipart/form-data

请求参数说明：
<table class="table table-bordered table-hover">
  <thead><tr><th>字段名</th><th>变量名</th><th>是否必填</th><th>示例值</th><th>描述</th></tr></thead>
  <tbody>
  <tr><td>文件</td><td>file</td><td>是</td><td></td><td>multipart格式文件</td></tr>
  <tr><td>是否首页显示</td><td>show</td><td>否</td><td>1</td><td>默认为是</td></tr>
  <tr><td>是否设置密码</td><td>ispwd</td><td>否</td><td>0</td><td>默认为否</td></tr>
  <tr><td>下载密码</td><td>pwd</td><td>否</td><td>123456</td><td>默认留空</td></tr>
  <tr><td>返回格式</td><td>format</td><td>否</td><td>json</td><td>有json、jsonp、form三种选择
默认为json</td></tr>
  <tr><td>跳转页面url</td><td>backurl</td><td>否</td><td>http://...</td><td>上传成功后的跳转地址
只在form格式有效</td></tr>
  <tr><td>callback</td><td>callback</td><td>否</td><td>callback</td><td>只在jsonp格式有效</td></tr>
  </tbody>
</table>
返回参数说明：
<table class="table table-bordered table-hover">
  <thead><tr><th>字段名</th><th>变量名</th><th>类型</th><th>示例值</th><th>描述</th></tr></thead>
  <tbody>
  <tr><td>上传状态</td><td>code</td><td>Int</td><td>0</td><td>0为成功，其他为失败</td></tr>
  <tr><td>提示信息</td><td>msg</td><td>String</td><td>上传成功！</td><td>如果上传失败会有错误提示</td></tr>
  <tr><td>文件MD5</td><td>hash</td><td>String</td><td>f1e807cb0d6ba52d71bdb02864e6bda8</td><td></td></tr>
  <tr><td>文件名称</td><td>name</td><td>String</td><td>exapmle1.jpg</td><td></td></tr>
  <tr><td>文件大小</td><td>size</td><td>Int</td><td>58937</td><td>单位：字节</td></tr>
  <tr><td>文件格式</td><td>type</td><td>String</td><td>jpg</td><td></td></tr>
  <tr><td>下载地址</td><td>downurl</td><td>String</td><td>http://.....</td><td></td></tr>
  <tr><td>预览地址</td><td>viewurl</td><td>String</td><td>http://.....</td><td>只有图片、音乐、视频文件才有</td></tr>
  </tbody>
</table>
</pre>
</div>
</div>
<?php
}elseif($mod=='account_n' && $_POST['do']=='submit'){
	if(!checkRefererHost())exit;
	$user=$_POST['user'];
	$oldpwd=$_POST['oldpwd'];
	$newpwd=$_POST['newpwd'];
	$newpwd2=$_POST['newpwd2'];
	if($user==null)showmsg('用户名不能为空！',3);
	saveSetting('admin_user',$user);
	if(!empty($newpwd) && !empty($newpwd2)){
		if(!hash_equals((string)$conf['admin_pwd'], (string)$oldpwd))showmsg('旧密码不正确！',3);
		if($newpwd!=$newpwd2)showmsg('两次输入的密码不一致！',3);
		saveSetting('admin_pwd',$newpwd);
	}
	showmsg('修改成功！请重新登录',1);
}elseif($mod=='account'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">管理员账号设置</h3></div>
<div class="panel-body">
  <form action="./set.php?mod=account_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">用户名</label>
	  <div class="col-sm-10"><input type="text" name="user" value="<?php echo $conf['admin_user']; ?>" class="form-control" required/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">旧密码</label>
	  <div class="col-sm-10"><input type="password" name="oldpwd" value="" class="form-control" placeholder="请输入当前的管理员密码"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">新密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-2 control-label">重输密码</label>
	  <div class="col-sm-10"><input type="password" name="newpwd2" value="" class="form-control" placeholder="不修改请留空"/></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='iptype'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户IP地址获取设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-2 control-label">用户IP地址获取方式</label>
	  <div class="col-sm-10"><select class="form-control" name="ip_type" default="<?php echo $conf['ip_type']?>"><option value="0">0_X_FORWARDED_FOR</option><option value="1">1_X_REAL_IP</option><option value="2">2_REMOTE_ADDR</option><option value="3">3_Cloudflare（自动校验来源）</option></select></div>
	</div>
	<div class="form-group">
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
此功能设置用于防止用户伪造IP请求。<br/>
X_FORWARDED_FOR：之前的获取真实IP方式，极易被伪造IP<br/>
X_REAL_IP：在网站使用CDN的情况下选择此项，在不使用CDN的情况下也会被伪造<br/>
REMOTE_ADDR：直接获取真实请求IP，无法被伪造，但可能获取到的是CDN节点IP<br/>
<b>你可以从中选择一个能显示你真实地址的IP，优先选下方的选项。</b>
</div>
</div>
<script>
$(document).ready(function(){
	$.ajax({
		type : "GET",
		url : "ajax.php?act=iptype",
		dataType : 'json',
		async: true,
		success : function(data) {
			$("select[name='ip_type']").empty();
			var defaultv = $("select[name='ip_type']").attr('default');
			$.each(data, function(k, item){
				$("select[name='ip_type']").append('<option value="'+k+'" '+(defaultv==k?'selected':'')+'>'+ item.name +' - '+ item.ip +' '+ item.city +'</option>');
			})
		}
	});
})
</script>
<?php
}elseif($mod=='file'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">文件上传设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_image" value="<?php echo $conf['type_image']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以图片的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">音频文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_audio" value="<?php echo $conf['type_audio']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以音频的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">视频文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_video" value="<?php echo $conf['type_video']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/><font color="green">在文件预览页面，以上文件类型将以视频的形式展示</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">禁止上传的文件类型</label>
	  <div class="col-sm-9"><input type="text" name="type_block" value="<?php echo $conf['type_block']; ?>" class="form-control" placeholder="多个文件类型用|隔开"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">文件名屏蔽关键词</label>
	  <div class="col-sm-9"><input type="text" name="name_block" value="<?php echo $conf['name_block']; ?>" class="form-control" placeholder="多个关键词用|隔开"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每IP每天限制上传数量</label>
	  <div class="col-sm-9"><input type="text" name="upload_limit" value="<?php echo $conf['upload_limit']; ?>" class="form-control" placeholder="0或留空为不限制"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">每分钟上传次数</label>
	  <div class="col-sm-9"><input type="text" name="upload_per_minute" value="<?php echo isset($conf['upload_per_minute'])?$conf['upload_per_minute']:10; ?>" class="form-control" placeholder="默认10，0为不限制"/>
	  <p class="help-block">只有"每天多少个"的话，脚本几秒钟就能刷满额度。这一条按分钟卡，登录用户按账号算，游客按来源地址算（IPv6 归并到 /64 前缀）。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">视频文件需要审核</label>
	  <div class="col-sm-9"><select class="form-control" name="videoreview" default="<?php echo $conf['videoreview']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">上传大小限制</label>
	  <div class="col-sm-9"><div class="input-group"><input type="text" name="upload_size" value="<?php echo $conf['upload_size']; ?>" class="form-control" placeholder="不填写则不限制大小"/><span class="input-group-addon">MB</span></div></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">仅限登录用户上传</label>
	  <div class="col-sm-9"><select class="form-control" name="forcelogin" default="<?php echo $conf['forcelogin']?>"><option value="0">0_否</option><option value="1">1_是</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">在线编辑权限</label>
	  <div class="col-sm-9"><select class="form-control" name="online_edit_mode" id="online_edit_mode" default="<?php echo isset($conf['online_edit_mode']) ? $conf['online_edit_mode'] : 'all'?>"><option value="all">所有用户都可用</option><option value="login">仅登录用户可用</option><option value="uid">仅指定UID可用</option></select><font color="green">这里只控制在线编辑功能入口与保存权限，文件本身是否属于当前用户，仍按原来的文件管理规则判断。</font></div>
	</div><br/>
	<div class="form-group" id="online_edit_uids_group" style="<?php echo (isset($conf['online_edit_mode']) && $conf['online_edit_mode'] === 'uid') ? '' : 'display:none;'; ?>">
	  <label class="col-sm-3 control-label">可用UID</label>
	  <div class="col-sm-9"><input type="text" name="online_edit_uids" value="<?php echo isset($conf['online_edit_uids']) ? htmlspecialchars($conf['online_edit_uids']) : ''; ?>" class="form-control" placeholder="例如：1,2,1001"/><font color="green">多个UID用英文逗号分隔，只有这些登录用户可以使用在线编辑。</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
</div>
<?php
}elseif($mod=='user'){
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">用户登录设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
  	<div class="form-group">
	  <label class="col-sm-3 control-label">用户登录开关</label>
	  <div class="col-sm-9"><select class="form-control" name="userlogin" default="<?php echo $conf['userlogin']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">聚合登录接口地址</label>
	  <div class="col-sm-9"><input type="text" name="login_apiurl" value="<?php echo $conf['login_apiurl']; ?>" class="form-control" placeholder="接口地址要以http://或https://开头，以/结尾"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">应用APPID</label>
	  <div class="col-sm-9"><input type="text" name="login_appid" value="<?php echo $conf['login_appid']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">应用APPKEY</label>
	  <div class="col-sm-9"><input type="text" name="login_appkey" value="<?php echo $conf['login_appkey']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">开启的登录方式</label>
	  <div class="col-sm-9">
	  <input type="hidden" name="login_qq" value="0"/>
	  <input type="hidden" name="login_wx" value="0"/>
	  <label class="checkbox-inline"><input type="checkbox" name="login_qq" value="1" <?php echo $conf['login_qq']?'checked':null;?>> QQ</label>
	  <label class="checkbox-inline"><input type="checkbox" name="login_wx" value="1" <?php echo $conf['login_wx']?'checked':null;?>> 微信</label>
	  </div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
聚合登录接口是使用<a href="https://www.clogin.cc/recommend.php" target="_blank">彩虹聚合登录系统搭建的站点</a>。<br/>
开启后请勿随意更换登录接口站点，否则会导致之前注册的用户全部无法登录。
</div>
</div>
<script>
</script>
<?php
}elseif($mod=='green'){
	$green_label_porn = explode(',', $conf['green_label_porn']);
	$green_label_terrorism = explode(',', $conf['green_label_terrorism']);
?>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">图片检测设置</h3></div>
<div class="panel-body">
  <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-3 control-label">图片违规检测</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check" default="<?php echo $conf['green_check']?>"><option value="0">关闭</option><option value="1">阿里云内容安全接口</option><option value="2">腾讯云内容安全接口</option><option value="3">自建检测服务（本机模型）</option></select>
	  <p class="help-block">自建检测不产生调用费，图片也不出服务器，但要在服务器上另跑一个 Python 服务，部署说明见 <b>tools/nsfw/README.md</b>。</p></div>
	</div><br/>
	<div id="green_aliyun" style="<?php echo $conf['green_check']!='1'?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKey Id</label>
	  <div class="col-sm-9"><input type="text" name="aliyun_ak" value="<?php echo $conf['aliyun_ak']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">阿里云AccessKey Secret</label>
	  <div class="col-sm-9"><input type="text" name="aliyun_sk" value="<?php echo $conf['aliyun_sk']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片检测接入区域</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_region" default="<?php echo $conf['green_check_region']?>"><option value="cn-beijing">华北2（北京）</option><option value="cn-shanghai">华东2（上海）</option><option value="cn-shenzhen">华南1（深圳）</option><option value="ap-southeast-1">新加坡</option><option value="us-west-1">美西</option></select><font color="green">你可以选择一个离本站服务器最近的以提升检测速度</font></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片智能鉴黄</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_porn" default="<?php echo $conf['green_check_porn']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group" id="green_check_porn_" style="<?php echo $conf['green_check_porn']!=1?'display:none;':null; ?>">
	  <label class="col-sm-3 control-label">图片智能鉴黄屏蔽类型</label>
	  <div class="col-sm-9">
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_porn[]" value="porn" <?php echo in_array('porn',$green_label_porn)?'checked':null;?>> 色情图片（porn）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_porn[]" value="sexy" <?php echo in_array('sexy',$green_label_porn)?'checked':null;?>> 性感图片（sexy）</label>
	  </div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片暴恐涉政识别</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_terrorism" default="<?php echo $conf['green_check_terrorism']?>"><option value="0">关闭</option><option value="1">开启</option></select></div>
	</div><br/>
	<div class="form-group" id="green_check_terrorism_" style="<?php echo $conf['green_check_terrorism']!=1?'display:none;':null; ?>">
	  <label class="col-sm-3 control-label">图片暴恐涉政识别屏蔽类型</label>
	  <div class="col-sm-9">
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="bloody" <?php echo in_array('bloody',$green_label_terrorism)?'checked':null;?>> 血腥（bloody）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="explosion" <?php echo in_array('explosion',$green_label_terrorism)?'checked':null;?>> 爆炸烟光（explosion）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="outfit" <?php echo in_array('outfit',$green_label_terrorism)?'checked':null;?>> 特殊装束（outfit）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="logo" <?php echo in_array('logo',$green_label_terrorism)?'checked':null;?>> 特殊标识（logo）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="weapon" <?php echo in_array('weapon',$green_label_terrorism)?'checked':null;?>> 武器（weapon）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="politics" <?php echo in_array('politics',$green_label_terrorism)?'checked':null;?>> 涉政（politics）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="violence" <?php echo in_array('violence',$green_label_terrorism)?'checked':null;?>> 打斗（violence）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="crowd" <?php echo in_array('crowd',$green_label_terrorism)?'checked':null;?>> 聚众（crowd）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="parade" <?php echo in_array('parade',$green_label_terrorism)?'checked':null;?>> 游行（parade）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="carcrash" <?php echo in_array('carcrash',$green_label_terrorism)?'checked':null;?>> 车祸现场（carcrash）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="flag" <?php echo in_array('flag',$green_label_terrorism)?'checked':null;?>> 旗帜（flag）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="location" <?php echo in_array('location',$green_label_terrorism)?'checked':null;?>> 地标（location）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="drug" <?php echo in_array('drug',$green_label_terrorism)?'checked':null;?>> 涉毒（drug）</label>
	  <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="gamble" <?php echo in_array('gamble',$green_label_terrorism)?'checked':null;?>> 赌博（gamble）</label>
	  </div>
	</div><br/>
	</div>
	<div id="green_self" style="<?php echo $conf['green_check']!='3'?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">检测服务地址</label>
	  <div class="col-sm-9"><input type="text" name="green_self_api" value="<?php echo htmlspecialchars(isset($conf['green_self_api'])?$conf['green_self_api']:'', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="http://127.0.0.1:9012/check"/>
	  <p class="help-block">留空就用默认的 <b>http://127.0.0.1:9012/check</b>。服务只监听本机回环地址，不要暴露到公网。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">访问令牌</label>
	  <div class="col-sm-9"><input type="text" name="green_self_token" value="<?php echo htmlspecialchars(isset($conf['green_self_token'])?$conf['green_self_token']:'', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="选填，与 config.json 里的 token 保持一致"/>
	  <p class="help-block">同一台机器上还跑着别人的程序时才需要设，填了要和检测服务的 <b>token</b> 一样。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">直接封禁阈值</label>
	  <div class="col-sm-9"><input type="text" name="green_self_block" value="<?php echo htmlspecialchars(isset($conf['green_self_block']) && $conf['green_self_block']!==''?$conf['green_self_block']:'0.85', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="0.85"/>
	  <p class="help-block">0~1 之间。评分达到这个值直接屏蔽并记入违规公示。调低会更严，误伤也更多。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">转人工阈值</label>
	  <div class="col-sm-9"><input type="text" name="green_self_review" value="<?php echo htmlspecialchars(isset($conf['green_self_review']) && $conf['green_self_review']!==''?$conf['green_self_review']:'0.6', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="0.6"/>
	  <p class="help-block">评分在这个值和封禁阈值之间的，标成<b>待审核</b>（前台下载不了），等你在文件管理里筛「待审核文件」逐个确认。自建模型误判率比云接口高，留这一档比一刀切稳妥。设成和封禁阈值一样就等于不用中间档。</p></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">超时时间</label>
	  <div class="col-sm-9"><input type="text" name="green_self_timeout" value="<?php echo htmlspecialchars(isset($conf['green_self_timeout']) && $conf['green_self_timeout']!==''?$conf['green_self_timeout']:'5', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="5"/>
	  <p class="help-block">单位秒。检测服务没起来或者超时，一律<b>放行</b>不拦，不会因为它挂了就让用户传不了图，失败原因写在网站日志里。</p></div>
	</div><br/>
	</div>
	<div id="green_qcloud" style="<?php echo $conf['green_check']!='2'?'display:none;':null; ?>">
	<div class="form-group">
	  <label class="col-sm-3 control-label">腾讯云SecretId</label>
	  <div class="col-sm-9"><input type="text" name="qcloud_green_id" value="<?php echo $conf['qcloud_green_id']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">腾讯云SecretKey</label>
	  <div class="col-sm-9"><input type="text" name="qcloud_green_key" value="<?php echo $conf['qcloud_green_key']; ?>" class="form-control"/></div>
	</div><br/>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片检测接入区域</label>
	  <div class="col-sm-9"><select class="form-control" name="green_check_region" default="<?php echo $conf['green_check_region']?>"><option value="ap-beijing">华北地区(北京)</option><option value="ap-shanghai">华东地区(上海)</option><option value="ap-guangzhou">华南地区(广州)</option><option value="ap-mumbai">亚太南部(孟买)</option><option value="ap-singapore">亚太东南(新加坡)</option><option value="eu-frankfurt">欧洲地区(法兰克福)</option><option value="na-ashburn">美国东部(弗吉尼亚)</option><option value="na-siliconvalley">美国西部(硅谷)</option></select><font color="green">你可以选择一个离本站服务器最近的以提升检测速度</font></div>
	</div><br/>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">图片检测访问网址</label>
	  <div class="col-sm-9"><input type="text" name="apiurl" value="<?php echo $conf['apiurl']; ?>" class="form-control" placeholder="不填写则默认使用当前网址"/><font color="green">此处是图片检测的时候阿里云访问本站的网址，不填写则默认使用当前网址，如果填写必需以http://开头，以/结尾</font></div>
	</div><br/>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary form-control"/><br/>
	 </div>
	</div>
  </form>
</div>
<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>
阿里云内容安全接口：<a href="https://yundun.console.aliyun.com/?p=cts#/api/statistics" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://usercenter.console.aliyun.com/#/manage/ak" target="_blank" rel="noreferrer">获取密钥</a><br/>
腾讯云内容安全接口：<a href="https://cloud.tencent.com/product/ims" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://console.cloud.tencent.com/cam/capi" target="_blank" rel="noreferrer">获取密钥</a><br/>
屏蔽类型选不选都可以，会同时根据返回的建议结果进行屏蔽
</div>
</div>
<script>
$("select[name='green_check']").change(function(){
	var v = $(this).val();
	$("#green_aliyun").toggle(v == 1);
	$("#green_qcloud").toggle(v == 2);
	$("#green_self").toggle(v == 3);
});
$("select[name='green_check_porn']").change(function(){
	if($(this).val() == 1){
		$("#green_check_porn_").show();
	}else{
		$("#green_check_porn_").hide();
	}
});
$("select[name='green_check_terrorism']").change(function(){
	if($(this).val() == 1){
		$("#green_check_terrorism_").show();
	}else{
		$("#green_check_terrorism_").hide();
	}
});
</script>
<?php
}
?>
    </div>
  </div>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/2.3/skin/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
$('.appearance-card input[type="radio"]').on('change', function(){
	$('.appearance-card').removeClass('active');
	$(this).closest('.appearance-card').addClass('active');
});
$("#online_edit_mode").on('change', function(){
	if($(this).val() === 'uid'){
		$("#online_edit_uids_group").show();
	}else{
		$("#online_edit_uids_group").hide();
	}
});

function saveSetting(obj){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {
					icon: 1,
					closeBtn: false
				}, function(){
				  window.location.reload()
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
	return false;
}
</script>
