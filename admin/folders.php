<?php
define('IN_ADMIN', true);
include '../includes/common.php';
if(empty($islogin) || intval($islogin) !== 1){ header('Location: ./login.php'); exit; }
header('Cache-Control: private, no-store');
require_once SYSTEM_ROOT.'admin_folders.php';
$title = '用户文件夹';
$error = '';
try{ $listing = admin_folder_listing($_GET); }
catch(RuntimeException $e){ $error = '暂时无法读取用户文件夹，请检查数据库升级是否完成。'; http_response_code(503); }
include './head.php';
?>
<div class="container-fluid"><div class="admin-page-wide">
    <h3>用户文件夹</h3>
    <p class="text-muted">查看登录用户创建的虚拟目录。文件数仅统计本层；子目录可能通过父目录被分享，即使没有单独分享链接。此页为只读查看。</p>
<?php if($error){ ?>
    <div class="alert alert-danger" role="alert"><?php echo folder_h($error)?></div>
<?php }else{ ?>
    <form method="get" action="./folders.php" class="form-inline">
        <div class="form-group"><label for="folder-user">用户UID</label> <input class="form-control" id="folder-user" type="number" min="1" name="uid" placeholder="全部用户" value="<?php echo $listing['uid'] ?: ''?>"></div>
        <div class="form-group"><label for="folder-name">文件夹名</label> <input class="form-control" id="folder-name" name="q" maxlength="100" value="<?php echo folder_h($listing['q'])?>" placeholder="搜索文件夹名称"></div>
        <div class="form-group"><label for="folder-share-state">本目录分享</label> <select class="form-control" id="folder-share-state" name="status"><option value="">全部状态</option><?php foreach(admin_folder_state_labels() as $key=>$label){ ?><option value="<?php echo $key?>"<?php echo $listing['status'] === $key ? ' selected' : ''?>><?php echo $label?></option><?php } ?></select></div>
        <button class="btn btn-primary" type="submit">筛选</button> <a class="btn btn-default" href="./folders.php">重置</a>
    </form>
    <p>共 <?php echo $listing['total']?> 个文件夹</p>
    <?php if(empty($conf['folder_open'])){ ?><div class="alert alert-warning">用户文件夹功能已关闭，已开启的分享暂不可访问；仍可查看历史目录和分享记录。</div><?php } ?>
    <div class="table-responsive"><table class="table table-striped table-bordered table-hover">
        <thead><tr><th>ID</th><th>文件夹 / 路径</th><th>所属用户</th><th>本层文件</th><th>本目录分享</th><th>父目录分享</th><th>创建时间</th></tr></thead><tbody>
    <?php foreach($listing['rows'] as $row){ ?>
        <tr><td><?php echo intval($row['id'])?></td>
            <td><strong><?php echo folder_h($row['name'])?></strong><br><small class="text-muted"><?php echo folder_h($row['path'])?></small></td>
            <td>UID <?php echo intval($row['uid'])?><br><?php echo folder_h($row['nickname'] ?: ($row['user_enable'] === null ? '用户不存在' : '未设置昵称'))?><?php if(empty($row['user_enable'])){ ?><br><span class="text-danger">账号不可用</span><?php } ?></td>
            <td><?php echo intval($row['file_count'])?></td>
            <td><span class="label label-<?php echo $row['share_state'] === 'active' ? 'success' : 'default'?>"><?php echo admin_folder_state_labels()[$row['share_state']]?></span>
                <?php if($row['share_id']){ ?><br><small><?php echo $row['has_password'] ? '有访问密码' : '无访问密码'?> · <?php echo $row['expiretime'] ? '有效期至 '.folder_h($row['expiretime']) : '永久有效'?></small><?php } ?>
                <?php if($row['share_state'] === 'active'){ ?><br><a href="<?php echo folder_h(site_root_url().'share.php?token='.$row['token'])?>" target="_blank" rel="noopener noreferrer">查看分享</a><?php } ?></td>
            <td><?php echo $row['inherited'] ? '<span class="text-success">已被父目录分享包含</span><br>'.folder_h(implode('、', $row['inherited'])) : '<span class="text-muted">无有效父目录分享</span>'?></td>
            <td><?php echo folder_h($row['addtime'])?></td></tr>
    <?php } ?>
    <?php if(!$listing['rows']){ ?><tr><td colspan="7" class="text-center text-muted">没有符合条件的文件夹</td></tr><?php } ?>
        </tbody></table></div>
    <nav aria-label="文件夹分页">
        <?php foreach(['上一页'=>-1,'下一页'=>1] as $label=>$offset){ $page = $listing['page']+$offset; if($page < 1 || $page > $listing['pages'])continue; ?>
        <a class="btn btn-default" href="<?php echo folder_h('./folders.php?'.http_build_query(['uid'=>$listing['uid'], 'q'=>$listing['q'], 'status'=>$listing['status'], 'page'=>$page]))?>"><?php echo $label?></a>
        <?php } ?><span>第 <?php echo $listing['page']?> / <?php echo $listing['pages']?> 页</span>
    </nav>
<?php } ?>
</div></div>
</body></html>
