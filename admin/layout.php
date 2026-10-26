<?php
define('IN_ADMIN', true);
define('LAYOUT_EDITOR', true);
include("../includes/common.php");
$title = '页面布局';

/*
 * 后台布局编辑器（DEC-20261025-003）：每个主题、每个页面一份布局，站长在这里拖动卡片、
 * 增删隐藏复制、按卡片的配置项改参数，右边是带着草稿的实时预览。
 * 页面列表、读写都在 ajax.php（layoutGet / layoutSave / layoutPreview），后端函数在 includes/theme_engine.php 的「后台布局编辑器」一节。
 * 前台页面能放的卡片来自 includes/blocks/，后台页面能放的是页面自己捕获的面板（预览框加载后报上来）加通用容器。
 * 这一页本身不在可编辑页面里：编辑器坏了就没法修了。
 */

include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$le_themes = [];
foreach(theme_registry() as $id => $m)$le_themes[] = ['id'=>$id, 'name'=>$m['name'].($m['_source'] === 'upload' ? '（上传）' : ''), 'active'=>$id === $site_theme];
$le_boot = ['themes'=>$le_themes, 'pages'=>layout_editor_page_list(), 'theme'=>$site_theme, 'db_ready'=>theme_db_ready(), 'site_base'=>'../', 'home'=>home_page_slug(), 'opts'=>layout_opts_schema()];
?>
<?php theme_panel_begin('editor', '布局编辑器');?>
<link rel="stylesheet" href="../assets/css/layout-editor.css?v=<?php echo asset_ver('assets/css/layout-editor.css')?>">
<div class="panel panel-primary layout-editor" id="layoutEditor" data-boot="<?php echo htmlspecialchars(json_encode($le_boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')?>">
  <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-th-large fa-fw"></i> 页面布局</h3></div>
  <div class="panel-body">
<?php if(!theme_db_ready()){?>
    <div class="alert alert-warning">数据库还没升级到 1034，布局没地方存。请先打开 <a href="../install/update.php" target="_blank">/install/update.php</a> 升级数据库。</div>
<?php }?>
    <div class="le-bar form-inline">
      <label>主题 <select class="form-control input-sm" id="leTheme"></select></label>
      <label>页面 <select class="form-control input-sm" id="lePage"></select></label>
      <span class="le-page-ops">
        <button type="button" class="btn btn-xs btn-default" id="lePageNew" title="新建一个自定义页面（访问地址 page.php?p=地址名）"><i class="fa fa-plus"></i> 新建页面</button>
        <button type="button" class="btn btn-xs btn-default" id="lePageEdit" style="display:none"><i class="fa fa-cog"></i> 页面设置</button>
        <button type="button" class="btn btn-xs btn-default" id="lePageDel" style="display:none"><i class="fa fa-trash-o"></i> 删除页面</button>
      </span>
      <span class="le-page-hint" id="lePageHint" style="display:none">这个页面所有外观共用一份内容，上面选的主题只决定预览用哪套外观</span>
      <span class="le-status" id="leStatus"></span>
    </div>
    <div class="row">
      <div class="col-lg-5 le-side">
        <div class="le-actions">
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><i class="fa fa-plus"></i> 添加卡片 <span class="caret"></span></button>
            <ul class="dropdown-menu le-palette" id="lePalette"></ul>
          </div>
          <button type="button" class="btn btn-sm btn-primary" id="leDraft"><i class="fa fa-save"></i> 保存草稿并预览</button>
          <button type="button" class="btn btn-sm btn-success" id="lePublish"><i class="fa fa-check"></i> 发布</button>
          <label class="le-live" title="改动后自动保存草稿并刷新右边的预览；访客要等「发布」才看得到"><input type="checkbox" id="leLive" checked> 实时预览</label>
          <label class="le-live" title="在右边预览里点带虚线框的文字，就地修改；回车或点别处完成，Esc 放弃"><input type="checkbox" id="leEditText" checked> 点文字直接改</label>
          <button type="button" class="btn btn-sm btn-default" id="leWide" title="左边编辑区放宽（写代码时用），再点一次恢复"><i class="fa fa-arrows-h"></i> 宽屏编辑</button>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">更多 <span class="caret"></span></button>
            <ul class="dropdown-menu le-more-menu">
              <li><a href="javascript:void(0)" id="leDiscard"><i class="fa fa-undo fa-fw"></i> 丢弃草稿</a></li>
              <li><a href="javascript:void(0)" id="leReset"><i class="fa fa-refresh fa-fw"></i> 恢复主题默认布局</a></li>
              <li class="divider"></li>
              <li><a href="javascript:void(0)" id="leExport"><i class="fa fa-download fa-fw"></i> 导出这一页的布局（JSON）</a></li>
              <li><a href="javascript:void(0)" id="leImport"><i class="fa fa-upload fa-fw"></i> 导入布局（JSON）</a></li>
              <li class="divider"></li>
              <li><a href="javascript:void(0)" id="lePreviewOff"><i class="fa fa-eye-slash fa-fw"></i> 退出预览模式</a></li>
            </ul>
          </div>
          <input type="file" id="leImportFile" accept=".json,application/json" style="display:none">
        </div>
        <p class="help-block le-tip">拖动 <i class="fa fa-bars"></i> 调整顺序，可以拖进带 <i class="fa fa-folder-o"></i> 的容器里。点卡片名字在下面改参数；在右边预览里按住 <kbd>Alt</kbd> 点页面，选中对应的卡片；勾着「点文字直接改」时，预览里带虚线框的文字点一下就能改。勾着「实时预览」时每次改动都会自动存成草稿、右边马上刷新；满意了再「发布」，发布前访客看不到变化。</p>
        <div class="le-tree" id="leTree"></div>
        <div class="le-config" id="leConfig"></div>
      </div>
      <div class="col-lg-7 le-main">
        <div class="le-preview-bar">
          <span id="lePreviewUrl" class="le-preview-url"></span>
          <a href="javascript:void(0)" id="lePreviewReload" class="btn btn-xs btn-default"><i class="fa fa-refresh"></i> 刷新预览</a>
          <a href="javascript:void(0)" id="lePreviewOpen" class="btn btn-xs btn-default" target="_blank"><i class="fa fa-external-link"></i> 新窗口打开</a>
        </div>
        <div class="le-preview" id="lePreviewWrap">
          <iframe id="lePreview" title="预览" src="about:blank"></iframe>
          <div class="le-preview-empty" id="lePreviewEmpty" style="display:none"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php theme_panel_end();?>
<?php //页面内容是布局 admin/layout（includes/layouts/admin/layout.json），见 DEC-20261025-003
echo theme_render_layout('admin/layout', [], 'admin', $site_theme);?>
<script src="https://s4.zstatic.net/ajax/libs/Sortable/1.15.6/Sortable.min.js"></script>
<?php //「代码」卡片的代码框：CodeMirror 5（高亮、行号、自动缩进、补全闭合标签），加载失败时退回普通文本框 ?>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<script src="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/addon/edit/closetag.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="../assets/js/layout-editor.js?v=<?php echo asset_ver('assets/js/layout-editor.js')?>"></script>
<?php //初始化放在内联脚本里：后台动态换页时外部脚本只执行一次，内联脚本每次都执行（见 layout-editor.js 开头） ?>
<script>if(window.panLayoutEditor)window.panLayoutEditor();</script>
</body>
</html>
