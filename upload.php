<?php
include("./includes/common.php");

$title = '上传文件 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';

$csrf_token = bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf_token;
//打赏码跟 file.php 用同一套后台配置，后台关掉或改图这里同步生效
$file_reward_enable = isset($conf['file_reward_enable']) ? ((int)$conf['file_reward_enable'] === 1) : true;
$file_reward_title = isset($conf['file_reward_title']) && $conf['file_reward_title'] !== '' ? $conf['file_reward_title'] : '&#25195;&#30721;&#39046;&#32418;&#21253;';
$file_reward_image = isset($conf['file_reward_image']) && $conf['file_reward_image'] !== '' ? $conf['file_reward_image'] : 'includes/sponsor/images/zhifubaohb.jpg';
$effective_upload_size = get_effective_upload_size_limit();
$effective_upload_limit = get_effective_upload_count_limit();
$effective_upload_used = 0;
$effective_upload_remaining = -1;
$permission_expire_text = '';
if($islogin2 && (intval($userrow['level']) > 0 || intval($userrow['upload_size']) >= 0 || intval($userrow['upload_limit']) >= 0 || !empty($userrow['expiretime']))){
    if(empty($userrow['expiretime'])){
        $permission_expire_text = '永久有效';
    }elseif(is_user_permission_active()){
        $permission_expire_text = $userrow['expiretime'].' 到期';
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
        $effective_upload_used = intval($DB->getColumn("SELECT count(*) from pre_file WHERE uid='$uid' AND addtime>='".$thisday."'"));
    }else{
        $effective_upload_used = intval($DB->getColumn("SELECT count(*) from pre_file WHERE ip='$clientip' AND addtime>='".$thisday."'"));
    }
    $effective_upload_remaining = max(0, $effective_upload_limit - $effective_upload_used);
}
?>
<?php //上传界面的进度条、结果提示和队列样式原来写在这里，首页的上传区要用同一套，已经搬进 assets/css/style.css ?>
<div class="container" id="app" v-cloak>
    <div class="row">
    
      <div class="col-sm-9">
        <div class="well infobox" align="center" id="fileInput" :style="{background: background}">
        <div style="min-height:50px;">
            <div id="progressBar" class="upload-main-progress" v-if="showtype==1">
                <div class="progress"><div class="progress-bar" style="width: 0%" :style="{ width: totalProgress + '%' }">{{progress_tip}}</div></div><div class="row"><div class="col-xs-3" style="text-align:left;" id="percentage"><span v-if="totalProgress>0">{{totalProgress}}%</span></div><div class="col-xs-6 filename">{{filename}}</div><div class="col-xs-3" style="text-align:right;" id="uploadspeed">{{uploadspeed}}</div></div>
            </div>
            <div class="upload-message" :class="'upload-message-'+alert.type" v-if="showtype==2">
                <div class="upload-message-icon">
                    <i class="fa" :class="alertIconClass"></i>
                </div>
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

         <br><br>
         <h1 style="color:#8d8b8b;" id="uploadTitle">{{uploadTitle}}</h1>

         <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $csrf_token?>">
         <input type="file" id="file" name="myfile[]" @change="selectFile" style="display:none" multiple/>
         

<?php
//没有侧栏的外观在这里显示权限条，让用户上传前就知道自己今天还能传多少
include_once SYSTEM_ROOT.'layout_blocks.php';
echo render_permission_bar($DB, 'upload');
?>
         <div id="upload_frame">
<?php if($conf['forcelogin']==1 && !$islogin2){?>
         <button id="uploadFile" class="btn btn-raised btn-primary" style="height:50px;font-size:20px;" onclick="window.location.href='./login.php'"><i class="fa fa-sign-in"></i> 请先登录<div class="ripple-container"></div></button>
         <script>var forbid = true;</script>
<?php }else{?>
         <button id="uploadFile" class="btn btn-raised btn-primary" style="height:50px;font-size:20px;" @click="clickUpload"><i class="fa fa-upload"></i> 选择文件/批量上传<div class="ripple-container"></div></button>
<?php }?>
<?php if($upload_storage_options){?>
<div class="form-group upload-storage">
<label for="storage_select">存储位置</label>
<div class="upload-storage-select">
<select class="form-control" id="storage_select" v-model="input.storage">
<?php foreach($upload_storage_options as $o){?>
<option value="<?php echo htmlspecialchars($o['key'], ENT_QUOTES, 'UTF-8')?>"><?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8')?></option>
<?php }?>
</select>
</div>
</div>
<?php }?>
<div class="form-group">
<div class="checkbox">
<label>
<input type="checkbox" id="show" v-model="input.show"> 在首页文件列表显示
</label>
</div>
</div>
<div class="form-group upload-pwd" id="pwd_frame">
<input type="text" class="form-control" id="pwd" placeholder="请输入密码（留空则不设置）" autocomplete="off" maxlength="32" v-model="input.pwd">
<p class="help-block" v-if="input.pwd">密码只能为字母或数字</p>
</div>
         </div>
         
        <br><br><br><br>
        </div>
      </div>
      <div class="col-sm-3">
      <div class="panel panel-primary">
<div class="panel-heading">
<h3 class="panel-title"><i class="fa fa-exclamation-circle"></i> 上传提示</h3>
</div>
<div class="list-group-item">
**您的IP是<?php echo $clientip?>，请不要上传违规文件！
</div>
<?php if($effective_upload_size>0){?>
<div class="list-group-item">**上传无格式限制，当前服务器单个文件上传最大支持<b><?php echo $effective_upload_size?>MB</b>！
</div>
<?php }else{?>
<div class="list-group-item">**上传无格式限制，无大小限制</b>！
</div>
<?php }?>
<?php if($effective_upload_limit>0){?>
<div class="list-group-item">**当前账号每天最多可上传 <b><?php echo $effective_upload_limit?></b> 个文件，今日剩余 <b><?php echo $effective_upload_remaining?></b> 个。
</div>
<?php }?>
<?php if($permission_expire_text){?>
<div class="list-group-item">**当前账号权限：<b><?php echo htmlspecialchars($permission_expire_text)?></b>。
</div>
<?php }?>
<?php if($conf['videoreview']==1){?>
<div class="list-group-item">**当前网站已开启视频文件审核，如果上传的是视频文件，需要等待审核通过后才能下载和播放。
</div>
<?php }?>
</div>


<?php if($file_reward_enable && !empty($file_reward_image)){ ?>
<div class="panel panel-default hidden-xs">
<div class="panel-heading" style="background-color:#009688">
<h3 class="panel-title"><i class="fa fa-qrcode"></i> <?php echo $file_reward_title?></h3>
</div>
<div class="panel-body text-center">
<img alt="<?php echo htmlspecialchars(strip_tags($file_reward_title), ENT_QUOTES, 'UTF-8');?>" src="<?php echo htmlspecialchars($file_reward_image, ENT_QUOTES, 'UTF-8');?>" style="width: 200px; height: 325px; max-width: 100%; object-fit: contain; border-radius: 8px;">
</div>
</div>
<?php } ?>


      </div>
    </div>
  </div>
<div class="colorful_loading_frame">
  <div class="colorful_loading"><i class="rect1"></i><i class="rect2"></i><i class="rect3"></i><i class="rect4"></i><i class="rect5"></i></div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/vue/2.6.14/vue.min.js"></script>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/layer/3.1.1/theme/default/layer.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>
var upload_max_filesize = '<?php echo $effective_upload_size?>';
var upload_count_limit = <?php echo intval($effective_upload_limit)?>;
var upload_count_used = <?php echo intval($effective_upload_used)?>;
var upload_count_remaining = <?php echo intval($effective_upload_remaining)?>;
/*
 * 存储下拉的默认选中项。必须在这儿给出，不能等 Vue 挂载后再去读 select 的值：
 * v-model 会在首次渲染时按数据（空串）把 select 刷成「未选中」，那时再读就是 null 了。
 * 没开多存储时这里是空串，服务端会落到默认存储。
 */
var upload_storage_default = <?php echo json_encode($upload_storage_options ? $upload_storage_options[0]['key'] : '')?>;
</script>
<script src="./assets/js/uploadnew.js?v=<?php echo VERSION?>"></script>
</body>
</html>
