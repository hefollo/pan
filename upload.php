<?php
include("./includes/common.php");

$title = '上传文件 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';

$csrf_token = bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;
$effective_upload_size = get_effective_upload_size_limit();
$effective_upload_limit = get_effective_upload_count_limit();
$effective_upload_used = 0;
$effective_upload_remaining = -1;
$permission_expire_text = '';
//有会员等级、或者后台单独调过额度的用户，说明一下现在是什么等级、到什么时候
if($islogin2 && (intval($userrow['level_id']) > 0 || intval($userrow['upload_size']) >= 0 || intval($userrow['upload_limit']) >= 0 || !empty($userrow['expiretime']))){
    $upload_level = current_level();
    if(empty($userrow['expiretime'])){
        $permission_expire_text = $upload_level['name'].'，永久有效';
    }elseif(is_user_permission_active()){
        $permission_expire_text = $upload_level['name'].'，'.$userrow['expiretime'].' 到期';
    }else{
        $permission_expire_text = $userrow['expiretime'].' 已过期，当前按普通用户权限生效';
    }
}
/*
 * 多存储上传：只有后台开了、而且当前访客够格用的存储不止一个时，才显示存储下拉框。
 * 只有一个的时候连框都不出，普通站点的上传页跟以前一模一样。
 * 这里只管显示，真正写哪儿由 ajax.php 的 storage_pick() 再校验一次——
 * 前端能改的东西一律不能当数。
 */
$upload_storages = storage_allowed_list();
$upload_storage_options = [];
if(storage_multi_open() && count($upload_storages) > 1){
    foreach($upload_storages as $k){
        $upload_storage_options[] = ['key'=>$k, 'name'=>\lib\StorHelper::name($k)];
    }
}
if($effective_upload_limit > 0){
    $thisday = date("Y-m-d 00:00:00");
    if($islogin2){
        $effective_upload_used = intval($DB->getColumn("SELECT count(*) from pre_file WHERE uid='$uid' AND addtime>='".$thisday."' AND copied=0"));
    }else{
        $effective_upload_used = intval($DB->getColumn("SELECT count(*) from pre_file WHERE ip='$clientip' AND addtime>='".$thisday."' AND copied=0"));
    }
    $effective_upload_remaining = max(0, $effective_upload_limit - $effective_upload_used);
}
/*
 * 从「我的文件」某个文件夹点「上传到这里」进来的：页面上标出目标位置，并把 id 交给上传脚本。
 * 这里只管显示，真正放哪儿由 ajax.php 的 folder_upload_target() 再校验一次归属。
 */
$upload_folder_id = 0;
$upload_folder_path = [];
if(isset($_GET['folder']) && can_use_folders()){
    $upload_folder_all = folder_all(folder_owner(false));
    $upload_folder_id = folder_current($upload_folder_all);
    if($upload_folder_id > 0) $upload_folder_path = folder_path($upload_folder_all, $upload_folder_id);
}
$upload_folder_back = $islogin2 ? './user.php?tab=files' : './?m=mine';
/*
 * 页面内容是布局 upload（DEC-20261025-003）：系统默认在 includes/layouts/upload.json，
 * 卡片在 includes/blocks/upload/（上传面板、提示、目标文件夹、加载动画）和 common/（权限条、打赏码）。
 */
echo theme_render_layout('upload', [
    'csrf_token' => $csrf_token,
    'size' => $effective_upload_size,
    'limit' => $effective_upload_limit,
    'used' => $effective_upload_used,
    'remaining' => $effective_upload_remaining,
    'expire_text' => $permission_expire_text,
    'storage_options' => $upload_storage_options,
    'folder_id' => $upload_folder_id,
    'folder_path' => $upload_folder_path,
    'folder_back' => $upload_folder_back,
], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
//上传面板登记的页面末尾脚本（Vue、layer、uploadnew.js 和它要的几个变量）
echo theme_deferred_html();
?></body>
</html>
