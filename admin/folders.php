<?php
define('IN_ADMIN', true);
include '../includes/common.php';
if(empty($islogin) || intval($islogin) !== 1){ header('Location: ./login.php'); exit; }
header('Cache-Control: private, no-store');
require_once SYSTEM_ROOT.'admin_folders.php';
$title = '用户文件夹';
$error = '';
try{ $listing = admin_folder_listing($_GET); }
catch(InvalidArgumentException $e){ $error = '文件夹不存在或不属于指定用户。'; http_response_code(404); }
catch(RuntimeException $e){ $error = '暂时无法读取用户文件夹，请检查数据库升级是否完成。'; http_response_code(503); }
include './head.php';
?>
<style>
.admin-folder-browser{--folder-text:var(--admin-text,#172033);color:var(--folder-text);padding-bottom:28px}
body.admin-theme-sky .admin-folder-browser,body.admin-theme-celadon .admin-folder-browser,body.admin-theme-lilac .admin-folder-browser,body.admin-theme-paper .admin-folder-browser,body.admin-theme-blush .admin-folder-browser,body.admin-theme-mint .admin-folder-browser{--folder-text:#243249}
.admin-folder-browser h3,.admin-folder-browser label,.admin-folder-browser .breadcrumb [aria-current],.admin-folder-browser .folder-count{color:var(--folder-text)}
body.admin-body .admin-folder-browser a:not(.btn){color:var(--admin-primary-dark,#1d4ed8)}.admin-folder-browser .btn-default{color:var(--folder-text);background:var(--admin-soft,#f8fafc);border-color:var(--admin-line,#e5eaf1)}body.admin-body .admin-folder-browser a.btn-default{color:var(--folder-text)!important}.admin-folder-browser .btn-default:hover,.admin-folder-browser .btn-default:focus{border-color:var(--admin-primary,#2563eb);color:var(--folder-text)}.admin-folder-browser .folder-share-detail.text-success{color:var(--admin-green,#059669)}
.folder-browser-heading{margin-bottom:18px}.folder-browser-heading h3{margin:0 0 8px;line-height:1.3}.folder-browser-heading p{margin:0;line-height:1.7}
.folder-browser-location{display:flex;align-items:center;gap:16px;margin-bottom:18px;padding:12px 16px;border:1px solid var(--admin-line,#e5eaf1);border-radius:10px;background:var(--admin-surface,#fff)}
.folder-browser-location nav{flex:1;min-width:0}.admin-folder-browser .folder-browser-location .breadcrumb{margin:0;padding:0;background:transparent;line-height:1.8;overflow-wrap:anywhere}.folder-browser-location>.btn{flex-shrink:0;white-space:nowrap;margin:0}
.admin-folder-browser .folder-browser-context{margin-bottom:18px;line-height:1.8}
.folder-filter-panel{margin:0 0 16px;padding:18px;border:1px solid var(--admin-line,#e5eaf1);border-radius:12px;background:var(--admin-surface,#fff)}
.folder-browser-toolbar{display:grid;grid-template-columns:160px minmax(220px,420px) 180px max-content;gap:16px;align-items:end;margin:0}
.folder-filter-field{min-width:0}.admin-folder-browser .folder-filter-field label{display:block;margin:0 0 8px;font-size:13px;line-height:18px}
.admin-folder-browser .folder-browser-toolbar .form-control{display:block;width:100%;height:40px;min-height:40px;max-width:100%;margin:0;box-sizing:border-box}
.folder-filter-actions{display:flex;gap:8px;align-items:center;align-self:end}
.admin-folder-browser .folder-browser-toolbar .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:40px;min-height:40px;margin:0;padding:9px 14px;line-height:20px;box-sizing:border-box;white-space:nowrap}
.folder-browser-summary{display:flex;justify-content:space-between;align-items:baseline;gap:8px 20px;flex-wrap:wrap;margin:0 0 16px}.folder-browser-summary p{margin:0;line-height:1.7}.folder-browser-summary .text-muted{font-size:12px}
.admin-folder-browser .panel{margin:0 0 18px}.admin-folder-browser .panel-heading{padding:14px 18px}.admin-folder-browser .table{margin:0;width:100%;table-layout:fixed}.admin-folder-browser .table th,.admin-folder-browser .table td{padding:12px 14px;vertical-align:middle;overflow-wrap:anywhere;line-height:1.6}
.admin-folder-browser .folder-table{min-width:920px}.admin-folder-browser .folder-table.is-user{min-width:760px}.admin-folder-browser .folder-file-table{min-width:850px}
.folder-col-id{width:64px}.folder-col-user{width:170px}.admin-folder-browser .folder-col-count{width:96px;text-align:center}.folder-col-share{width:230px}.folder-col-time{width:180px;white-space:nowrap}.folder-col-size{width:110px}.folder-col-type{width:76px}.folder-col-state{width:140px}.folder-col-action{width:100px;white-space:nowrap}
.folder-entry{display:inline-flex;align-items:baseline;gap:8px;max-width:100%;overflow-wrap:anywhere}.folder-entry>.fa{flex-shrink:0}.folder-entry strong{min-width:0;font-weight:600}.folder-user-name,.folder-share-detail{display:block;margin-top:4px;font-size:12px;line-height:1.6}
.admin-folder-browser .folder-share-badge{display:inline-block;padding:3px 8px;border-radius:6px;font-size:12px;line-height:18px;font-weight:500}.folder-browser-pagination{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin:0 0 20px}.folder-browser-pagination>span{margin-left:6px;font-size:13px}.folder-empty{padding:38px 20px;border:1px dashed var(--admin-line,#e5eaf1);border-radius:12px;background:var(--admin-surface,#fff);text-align:center;line-height:1.8}
@media(max-width:1199px){.folder-browser-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}.folder-filter-actions{justify-content:flex-start}}
@media(max-width:640px){.admin-folder-browser{padding-top:36px}.folder-browser-location{align-items:flex-start;flex-direction:column;gap:10px;padding:12px}.folder-filter-panel{padding:14px}.folder-browser-toolbar{grid-template-columns:minmax(0,1fr);gap:14px}.folder-filter-actions>.btn{flex:1}.folder-browser-summary{align-items:flex-start;flex-direction:column}.folder-browser-pagination{justify-content:flex-start}.admin-folder-browser .table th,.admin-folder-browser .table td{padding:10px 12px}}
</style>
<div class="container-fluid"><div class="admin-page-wide admin-folder-browser">
    <header class="folder-browser-heading"><h3><?php echo !$error && $listing['current'] ? folder_h($listing['current']['name']) : '用户文件夹'?></h3>
    <p class="text-muted">按目录查看用户文件与分享状态，不会修改用户内容。</p></header>
<?php if($error){ ?>
    <div class="alert alert-danger" role="alert"><?php echo folder_h($error)?></div>
<?php }else{ ?>
    <div class="folder-browser-location"><nav aria-label="目录路径"><ol class="breadcrumb">
        <li><a href="<?php echo folder_h(admin_folder_ancestor_link($listing, 0, 0))?>">全部用户顶层文件夹</a></li>
        <?php if($listing['uid']){ ?><li><a href="<?php echo folder_h(admin_folder_ancestor_link($listing, 0, $listing['uid']))?>">UID <?php echo $listing['uid']?> 根目录</a></li><?php } ?>
        <?php foreach($listing['breadcrumbs'] as $crumb){ ?><li<?php echo intval($crumb['id']) === $listing['folder'] ? ' aria-current="page"' : ''?>><?php if(intval($crumb['id']) !== $listing['folder']){ ?><a href="<?php echo folder_h(admin_folder_ancestor_link($listing, intval($crumb['id']), $listing['uid']))?>"><?php echo folder_h($crumb['name'])?></a><?php }else echo folder_h($crumb['name']); ?></li><?php } ?>
    </ol></nav>
    <?php if(admin_folder_up_link($listing)){ ?><a class="btn btn-default" data-folder-up href="<?php echo folder_h(admin_folder_up_link($listing))?>"><i class="fa fa-arrow-up"></i> <?php echo $listing['current'] ? '返回上一级' : '返回全部用户'?></a><?php } ?></div>
    <?php if($listing['current']){ $current=$listing['current']; ?><div class="alert alert-info folder-browser-context">
        当前目录：<strong><?php echo folder_h($current['name'])?></strong> · <?php echo admin_folder_state_labels()[$current['share_state']]?>
        <?php if($current['share_id']){ ?> · <?php echo $current['has_password'] ? '有访问密码' : '无访问密码'?> · <?php echo $current['expiretime'] ? '有效期至 '.folder_h($current['expiretime']) : '永久有效'?><?php } ?>
        <?php if($current['inherited']){ ?><br>已被父目录分享包含：<?php echo folder_h(implode('、', $current['inherited']))?><?php } ?>
    </div><?php } ?>
    <section class="folder-filter-panel" aria-label="筛选当前层级"><form method="get" action="./folders.php" class="folder-browser-toolbar">
        <input type="hidden" name="folder" value="<?php echo $listing['folder']?>">
        <?php if($listing['trail']){ ?><input type="hidden" name="nav" value="<?php echo folder_h(admin_folder_nav_value($listing['trail']))?>"><?php } ?>
        <div class="folder-filter-field"><label for="folder-user">用户UID</label> <input class="form-control" id="folder-user" type="number" min="1" name="uid" placeholder="全部用户" value="<?php echo $listing['uid'] ?: ''?>"<?php echo $listing['folder'] ? ' readonly' : ''?>></div>
        <div class="folder-filter-field"><label for="folder-name">名称</label> <input class="form-control" id="folder-name" name="q" maxlength="100" value="<?php echo folder_h($listing['q'])?>" placeholder="搜索本层文件或文件夹"></div>
        <div class="folder-filter-field"><label for="folder-share-state">子文件夹分享</label> <select class="form-control" id="folder-share-state" name="status"><option value="">全部状态</option><?php foreach(admin_folder_state_labels() as $key=>$label){ ?><option value="<?php echo $key?>"<?php echo $listing['status'] === $key ? ' selected' : ''?>><?php echo $label?></option><?php } ?></select></div>
        <div class="folder-filter-actions"><button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> 筛选本层</button> <a class="btn btn-default" href="<?php echo folder_h(admin_folder_link($listing, ['q'=>'', 'status'=>'', 'page'=>1, 'file_page'=>1]))?>">清除本层筛选</a></div>
    </form></section>
    <div class="folder-browser-summary"><p class="folder-count">本层<?php echo $listing['q'] !== '' || $listing['status'] !== '' ? '筛选结果：' : '：'?><?php echo $listing['total']?> 个文件夹<?php if($listing['uid']){ ?>、<?php echo $listing['file_total']?> 个文件<?php } ?>。</p>
    <p class="text-muted"><?php echo !$listing['uid'] ? '此处列出全部用户的顶层文件夹。点击用户UID可查看该用户根目录文件。' : '分享筛选只作用于子文件夹；文件数仅统计当前层。'?></p></div>
    <?php if(empty($conf['folder_open'])){ ?><div class="alert alert-warning">用户文件夹功能已关闭，已开启的分享暂不可访问；仍可查看历史目录和分享记录。</div><?php } ?>
    <?php if($listing['rows']){ ?>
    <section class="panel panel-default"><div class="panel-heading"><strong>文件夹</strong> <span class="text-muted"><?php echo $listing['total']?> 个</span></div>
    <div class="table-responsive"><table class="table table-striped table-hover folder-table<?php echo $listing['uid'] ? ' is-user' : ''?>">
        <thead><tr><th class="folder-col-id">ID</th><th>文件夹名称</th><?php if(!$listing['uid']){ ?><th class="folder-col-user">所属用户</th><?php } ?><th class="folder-col-count">本层文件</th><th class="folder-col-share">分享状态</th><th class="folder-col-time">创建时间</th></tr></thead><tbody>
    <?php foreach($listing['rows'] as $row){ ?>
        <tr><td><?php echo intval($row['id'])?></td>
            <td><a class="folder-entry" href="<?php echo folder_h(admin_folder_child_link($listing, $row))?>"><i class="fa fa-folder" aria-hidden="true"></i> <strong><?php echo folder_h($row['name'])?></strong></a></td>
            <?php if(!$listing['uid']){ ?><td><a href="<?php echo folder_h(admin_folder_owner_link($listing, intval($row['uid'])))?>">UID <?php echo intval($row['uid'])?></a><span class="folder-user-name text-muted"><?php echo folder_h($row['nickname'] ?: ($row['user_enable'] === null ? '用户不存在' : '未设置昵称'))?></span><?php if(empty($row['user_enable'])){ ?><span class="text-danger">账号不可用</span><?php } ?></td><?php } ?>
            <td class="folder-col-count"><?php echo intval($row['file_count'])?></td>
            <td><span class="label folder-share-badge label-<?php echo $row['share_state'] === 'active' ? 'success' : 'default'?>"><?php echo admin_folder_state_labels()[$row['share_state']]?></span>
                <?php if($row['share_id']){ ?><span class="folder-share-detail text-muted"><?php echo $row['has_password'] ? '有访问密码' : '无访问密码'?> · <?php echo $row['expiretime'] ? '有效期至 '.folder_h($row['expiretime']) : '永久有效'?></span><?php } ?>
                <?php if($row['share_state'] === 'active'){ ?><a class="folder-share-detail" href="<?php echo folder_h(site_root_url().'share.php?token='.$row['token'])?>" target="_blank" rel="noopener noreferrer">查看分享</a><?php } ?>
                <?php if($row['inherited']){ ?><span class="folder-share-detail text-success">父目录分享包含：<?php echo folder_h(implode('、', $row['inherited']))?></span><?php } ?></td>
            <td><?php echo folder_h($row['addtime'])?></td></tr>
    <?php } ?>
        </tbody></table></div></section>
    <?php } ?>
    <?php if($listing['pages'] > 1){ ?>
    <nav class="folder-browser-pagination" aria-label="文件夹分页">
        <?php foreach(['上一页'=>-1,'下一页'=>1] as $label=>$offset){ $page = $listing['page']+$offset; if($page < 1 || $page > $listing['pages'])continue; ?>
        <a class="btn btn-default" href="<?php echo folder_h(admin_folder_link($listing, ['page'=>$page]))?>"><?php echo $label?></a>
        <?php } ?><span>第 <?php echo $listing['page']?> / <?php echo $listing['pages']?> 页</span>
    </nav><?php } ?>
    <?php if($listing['files']){ ?>
    <section class="panel panel-default"><div class="panel-heading"><strong>文件</strong> <span class="text-muted"><?php echo $listing['file_total']?> 个</span></div>
    <div class="table-responsive"><table class="table table-striped folder-file-table">
        <thead><tr><th class="folder-col-id">ID</th><th>文件名</th><th class="folder-col-size">大小</th><th class="folder-col-type">格式</th><th class="folder-col-state">状态</th><th class="folder-col-time">上传时间</th><th class="folder-col-action">操作</th></tr></thead><tbody>
        <?php foreach($listing['files'] as $file){ ?>
        <tr><td><?php echo intval($file['id'])?></td><td><a href="./file-view.php?id=<?php echo intval($file['id'])?>" target="_blank" rel="noopener"><?php echo folder_h(html_entity_decode($file['name'], ENT_QUOTES, 'UTF-8'))?></a></td>
            <td><?php echo folder_h(size_format($file['size']))?></td><td><?php echo folder_h($file['type'])?></td>
            <td><?php echo intval($file['block']) === 1 ? '已冻结' : (intval($file['block']) === 2 ? '待审核' : '正常')?> · <?php echo $file['hide'] ? '私密' : '公开'?></td>
            <td><?php echo folder_h($file['addtime'])?></td><td><a href="./file-view.php?id=<?php echo intval($file['id'])?>" target="_blank" rel="noopener">查看文件</a></td></tr>
        <?php } ?>
        </tbody></table></div></section>
        <?php if($listing['file_pages'] > 1){ ?>
        <nav class="folder-browser-pagination" aria-label="文件分页"><?php foreach(['上一页文件'=>-1,'下一页文件'=>1] as $label=>$offset){ $page=$listing['file_page']+$offset; if($page<1 || $page>$listing['file_pages'])continue; ?>
            <a class="btn btn-default" href="<?php echo folder_h(admin_folder_link($listing, ['file_page'=>$page]))?>"><?php echo $label?></a>
        <?php } ?><span>文件第 <?php echo $listing['file_page']?> / <?php echo $listing['file_pages']?> 页</span></nav>
    <?php } } ?>
    <?php if(!$listing['rows'] && !$listing['files']){ ?><div class="folder-empty text-muted" role="status"><?php echo $listing['q'] !== '' || $listing['status'] !== '' ? '本层没有匹配的文件或文件夹，可清除筛选后重试。' : ($listing['uid'] ? '此目录为空。' : '暂无用户创建的顶层文件夹。')?></div><?php } ?>
<?php } ?>
</div></div>
</body></html>
