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
.admin-folder-browser{--folder-text:var(--admin-text,#172033);color:var(--folder-text)}
body.admin-theme-sky .admin-folder-browser,body.admin-theme-celadon .admin-folder-browser,body.admin-theme-lilac .admin-folder-browser,body.admin-theme-paper .admin-folder-browser,body.admin-theme-blush .admin-folder-browser,body.admin-theme-mint .admin-folder-browser{--folder-text:#243249}
.admin-folder-browser h3,.admin-folder-browser label,.admin-folder-browser .breadcrumb [aria-current],.admin-folder-browser>.folder-count{color:var(--folder-text)}
.folder-browser-toolbar{display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px;margin:18px 0}.folder-browser-toolbar label{display:block;margin-bottom:5px}.folder-browser-toolbar .form-control{max-width:100%}
.admin-folder-browser .panel{margin-top:18px}.admin-folder-browser .table{margin-bottom:0}.admin-folder-browser .table td{vertical-align:middle;overflow-wrap:anywhere}.admin-folder-browser nav[aria-label$="分页"]{margin-bottom:18px}
@media(max-width:640px){.admin-folder-browser{padding-top:36px}.folder-browser-toolbar .form-group{flex:1 1 100%;margin-bottom:0}.folder-browser-toolbar .form-control{width:100%}.admin-folder-browser .table{min-width:620px}}
</style>
<div class="container-fluid"><div class="admin-page-wide admin-folder-browser">
    <h3><?php echo !$error && $listing['current'] ? folder_h($listing['current']['name']) : '用户文件夹'?></h3>
    <p class="text-muted">按目录查看用户文件与分享状态，不会修改用户内容。</p>
<?php if($error){ ?>
    <div class="alert alert-danger" role="alert"><?php echo folder_h($error)?></div>
<?php }else{ ?>
    <nav aria-label="目录路径"><ol class="breadcrumb">
        <li><a href="<?php echo folder_h(admin_folder_ancestor_link($listing, 0, 0))?>">全部用户顶层文件夹</a></li>
        <?php if($listing['uid']){ ?><li><a href="<?php echo folder_h(admin_folder_ancestor_link($listing, 0, $listing['uid']))?>">UID <?php echo $listing['uid']?> 根目录</a></li><?php } ?>
        <?php foreach($listing['breadcrumbs'] as $crumb){ ?><li<?php echo intval($crumb['id']) === $listing['folder'] ? ' aria-current="page"' : ''?>><?php if(intval($crumb['id']) !== $listing['folder']){ ?><a href="<?php echo folder_h(admin_folder_ancestor_link($listing, intval($crumb['id']), $listing['uid']))?>"><?php echo folder_h($crumb['name'])?></a><?php }else echo folder_h($crumb['name']); ?></li><?php } ?>
    </ol></nav>
    <?php if(admin_folder_up_link($listing)){ ?><p><a class="btn btn-default" data-folder-up href="<?php echo folder_h(admin_folder_up_link($listing))?>"><i class="fa fa-arrow-up"></i> <?php echo $listing['current'] ? '返回上一级' : '返回全部用户'?></a></p><?php } ?>
    <?php if($listing['current']){ $current=$listing['current']; ?><div class="alert alert-info">
        当前目录：<strong><?php echo folder_h($current['name'])?></strong> · <?php echo admin_folder_state_labels()[$current['share_state']]?>
        <?php if($current['share_id']){ ?> · <?php echo $current['has_password'] ? '有访问密码' : '无访问密码'?> · <?php echo $current['expiretime'] ? '有效期至 '.folder_h($current['expiretime']) : '永久有效'?><?php } ?>
        <?php if($current['inherited']){ ?><br>已被父目录分享包含：<?php echo folder_h(implode('、', $current['inherited']))?><?php } ?>
    </div><?php } ?>
    <form method="get" action="./folders.php" class="form-inline folder-browser-toolbar">
        <input type="hidden" name="folder" value="<?php echo $listing['folder']?>">
        <?php if($listing['trail']){ ?><input type="hidden" name="nav" value="<?php echo folder_h(admin_folder_nav_value($listing['trail']))?>"><?php } ?>
        <div class="form-group"><label for="folder-user">用户UID</label> <input class="form-control" id="folder-user" type="number" min="1" name="uid" placeholder="全部用户" value="<?php echo $listing['uid'] ?: ''?>"<?php echo $listing['folder'] ? ' readonly' : ''?>></div>
        <div class="form-group"><label for="folder-name">名称</label> <input class="form-control" id="folder-name" name="q" maxlength="100" value="<?php echo folder_h($listing['q'])?>" placeholder="搜索本层文件或文件夹"></div>
        <div class="form-group"><label for="folder-share-state">子文件夹分享</label> <select class="form-control" id="folder-share-state" name="status"><option value="">全部状态</option><?php foreach(admin_folder_state_labels() as $key=>$label){ ?><option value="<?php echo $key?>"<?php echo $listing['status'] === $key ? ' selected' : ''?>><?php echo $label?></option><?php } ?></select></div>
        <button class="btn btn-primary" type="submit">筛选本层</button> <a class="btn btn-default" href="<?php echo folder_h(admin_folder_link($listing, ['q'=>'', 'status'=>'', 'page'=>1, 'file_page'=>1]))?>">清除本层筛选</a>
    </form>
    <p class="folder-count">本层<?php echo $listing['q'] !== '' || $listing['status'] !== '' ? '筛选结果：' : '：'?><?php echo $listing['total']?> 个文件夹<?php if($listing['uid']){ ?>、<?php echo $listing['file_total']?> 个文件<?php } ?>。</p>
    <?php if(!$listing['uid']){ ?><p class="text-muted">此处列出全部用户的顶层文件夹。点击用户UID可查看该用户根目录文件。</p><?php } ?>
    <?php if(empty($conf['folder_open'])){ ?><div class="alert alert-warning">用户文件夹功能已关闭，已开启的分享暂不可访问；仍可查看历史目录和分享记录。</div><?php } ?>
    <?php if($listing['rows']){ ?>
    <section class="panel panel-default"><div class="panel-heading"><strong>文件夹</strong> <span class="text-muted"><?php echo $listing['total']?> 个</span></div>
    <div class="table-responsive"><table class="table table-striped table-hover">
        <thead><tr><th>ID</th><th>文件夹名称</th><?php if(!$listing['uid']){ ?><th>所属用户</th><?php } ?><th>本层文件</th><th>分享状态</th><th>创建时间</th></tr></thead><tbody>
    <?php foreach($listing['rows'] as $row){ ?>
        <tr><td><?php echo intval($row['id'])?></td>
            <td><a href="<?php echo folder_h(admin_folder_child_link($listing, $row))?>"><i class="fa fa-folder"></i> <strong><?php echo folder_h($row['name'])?></strong></a></td>
            <?php if(!$listing['uid']){ ?><td><a href="<?php echo folder_h(admin_folder_owner_link($listing, intval($row['uid'])))?>">UID <?php echo intval($row['uid'])?></a><br><?php echo folder_h($row['nickname'] ?: ($row['user_enable'] === null ? '用户不存在' : '未设置昵称'))?><?php if(empty($row['user_enable'])){ ?><br><span class="text-danger">账号不可用</span><?php } ?></td><?php } ?>
            <td><?php echo intval($row['file_count'])?></td>
            <td><span class="label label-<?php echo $row['share_state'] === 'active' ? 'success' : 'default'?>"><?php echo admin_folder_state_labels()[$row['share_state']]?></span>
                <?php if($row['share_id']){ ?><br><small><?php echo $row['has_password'] ? '有访问密码' : '无访问密码'?> · <?php echo $row['expiretime'] ? '有效期至 '.folder_h($row['expiretime']) : '永久有效'?></small><?php } ?>
                <?php if($row['share_state'] === 'active'){ ?><br><a href="<?php echo folder_h(site_root_url().'share.php?token='.$row['token'])?>" target="_blank" rel="noopener noreferrer">查看分享</a><?php } ?>
                <?php if($row['inherited']){ ?><br><span class="text-success">父目录分享包含：<?php echo folder_h(implode('、', $row['inherited']))?></span><?php } ?></td>
            <td><?php echo folder_h($row['addtime'])?></td></tr>
    <?php } ?>
        </tbody></table></div></section>
    <?php } ?>
    <?php if($listing['pages'] > 1){ ?>
    <nav aria-label="文件夹分页">
        <?php foreach(['上一页'=>-1,'下一页'=>1] as $label=>$offset){ $page = $listing['page']+$offset; if($page < 1 || $page > $listing['pages'])continue; ?>
        <a class="btn btn-default" href="<?php echo folder_h(admin_folder_link($listing, ['page'=>$page]))?>"><?php echo $label?></a>
        <?php } ?><span>第 <?php echo $listing['page']?> / <?php echo $listing['pages']?> 页</span>
    </nav><?php } ?>
    <?php if($listing['files']){ ?>
    <section class="panel panel-default"><div class="panel-heading"><strong>文件</strong> <span class="text-muted"><?php echo $listing['file_total']?> 个</span></div>
    <div class="table-responsive"><table class="table table-striped table-bordered">
        <thead><tr><th>ID</th><th>文件名</th><th>大小</th><th>格式</th><th>状态</th><th>上传时间</th><th>操作</th></tr></thead><tbody>
        <?php foreach($listing['files'] as $file){ ?>
        <tr><td><?php echo intval($file['id'])?></td><td><a href="./file-view.php?id=<?php echo intval($file['id'])?>" target="_blank" rel="noopener"><?php echo folder_h(html_entity_decode($file['name'], ENT_QUOTES, 'UTF-8'))?></a></td>
            <td><?php echo folder_h(size_format($file['size']))?></td><td><?php echo folder_h($file['type'])?></td>
            <td><?php echo intval($file['block']) === 1 ? '已冻结' : (intval($file['block']) === 2 ? '待审核' : '正常')?> · <?php echo $file['hide'] ? '私密' : '公开'?></td>
            <td><?php echo folder_h($file['addtime'])?></td><td><a href="./file-view.php?id=<?php echo intval($file['id'])?>" target="_blank" rel="noopener">查看文件</a></td></tr>
        <?php } ?>
        </tbody></table></div></section>
        <?php if($listing['file_pages'] > 1){ ?>
        <nav aria-label="文件分页"><?php foreach(['上一页文件'=>-1,'下一页文件'=>1] as $label=>$offset){ $page=$listing['file_page']+$offset; if($page<1 || $page>$listing['file_pages'])continue; ?>
            <a class="btn btn-default" href="<?php echo folder_h(admin_folder_link($listing, ['file_page'=>$page]))?>"><?php echo $label?></a>
        <?php } ?><span>文件第 <?php echo $listing['file_page']?> / <?php echo $listing['file_pages']?> 页</span></nav>
    <?php } } ?>
    <?php if(!$listing['rows'] && !$listing['files']){ ?><div class="well text-center text-muted" role="status"><?php echo $listing['q'] !== '' || $listing['status'] !== '' ? '本层没有匹配的文件或文件夹，可清除筛选后重试。' : ($listing['uid'] ? '此目录为空。' : '暂无用户创建的顶层文件夹。')?></div><?php } ?>
<?php } ?>
</div></div>
</body></html>
