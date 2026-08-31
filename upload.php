<?php
include("./includes/common.php");

$title = '上传文件 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';

$csrf_token = md5(mt_rand(0,999).time());
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
<style>
[v-cloak]{display:none!important}
.upload-queue{margin-top:15px;text-align:left;max-height:260px;overflow:auto;border:1px solid var(--line);border-radius:12px;background:var(--surface-soft);box-shadow:0 12px 28px rgba(15,23,42,.06)}
.upload-queue-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-bottom:1px solid var(--line);background:transparent}
.upload-queue-item:last-child{border-bottom:0}
.upload-queue-index{width:38px;color:var(--muted);font-weight:700;text-align:center}
.upload-queue-main{flex:1;min-width:0}
.upload-queue-name{font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.upload-queue-meta{font-size:12px;color:var(--muted);margin-top:2px}
.upload-queue-actions{display:flex;align-items:center;gap:6px;flex:0 0 auto}
.upload-queue-status{min-width:78px;text-align:right}
.upload-queue-progress{height:4px;margin-top:6px;background:var(--line);border-radius:999px;overflow:hidden}
.upload-queue-progress span{display:block;height:100%;background:var(--primary);transition:width .2s ease}
.upload-link-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:28px;border:1px solid rgba(59,130,246,.28);border-radius:999px;background:rgba(59,130,246,.1);color:var(--primary);padding:5px 10px;font-size:12px;font-weight:800;line-height:1;white-space:nowrap;transition:border-color .18s ease,background .18s ease,color .18s ease,transform .18s ease}
.upload-link-btn:hover{border-color:var(--primary);background:var(--primary);color:#fff;transform:translateY(-1px)}
.upload-link-btn-secondary{border-color:rgba(16,185,129,.28);background:rgba(16,185,129,.1);color:var(--accent)}
.upload-link-btn-secondary:hover{border-color:var(--accent);background:var(--accent);color:#fff}
.upload-result-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;margin:-4px 0 14px}
.upload-result-actions .upload-link-btn{min-height:34px;padding:7px 13px;font-size:13px}
@media (max-width:640px){.upload-queue-item{align-items:flex-start;flex-wrap:wrap}.upload-queue-actions{width:100%;padding-left:48px;justify-content:flex-start}.upload-queue-status{margin-left:auto}.upload-result-actions{justify-content:flex-start}}
.upload-main-progress .progress{height:18px;border-radius:999px;background:var(--surface-soft);border:1px solid var(--line);box-shadow:none;overflow:hidden}
.upload-main-progress .progress-bar{background:linear-gradient(90deg,var(--primary),var(--accent));line-height:18px;box-shadow:none}
.upload-message{position:relative;display:flex;align-items:flex-start;gap:12px;margin:0 0 12px;padding:14px 44px 14px 16px;border:1px solid var(--line);border-left:4px solid var(--primary);border-radius:12px;background:var(--surface-soft);color:var(--text);box-shadow:0 12px 28px rgba(15,23,42,.06);text-align:left}
.upload-message-icon{flex:0 0 28px;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--primary);color:#fff;font-size:14px}
.upload-message-body{flex:1;min-width:0;padding-top:3px;font-weight:700;line-height:1.7;word-break:break-word}
.upload-message-close{position:absolute;top:10px;right:12px;border:0;background:transparent;color:var(--muted);font-size:22px;line-height:1;opacity:.75}
.upload-message-close:hover{opacity:1;color:var(--text)}
.upload-message-success{border-left-color:var(--primary)}
.upload-message-success .upload-message-icon{background:var(--primary)}
.upload-message-warning{border-left-color:var(--accent)}
.upload-message-warning .upload-message-icon{background:var(--accent)}
.upload-message-danger{border-left-color:var(--danger)}
.upload-message-danger .upload-message-icon{background:var(--danger)}
body.theme-night .upload-message,body.theme-neon .upload-message,body.theme-onefour .upload-message{box-shadow:0 18px 40px rgba(0,0,0,.22)}
body.theme-aurora .upload-message{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.18);color:#fff;backdrop-filter:blur(12px)}
body.theme-night .upload-queue,body.theme-neon .upload-queue,body.theme-onefour .upload-queue{background:var(--surface-soft);box-shadow:0 18px 40px rgba(0,0,0,.22)}
body.theme-aurora .upload-queue{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.18);box-shadow:0 18px 42px rgba(30,20,93,.18);backdrop-filter:blur(12px)}
body.theme-aurora .upload-queue-item{border-bottom-color:rgba(255,255,255,.16)}
body.theme-aurora .upload-queue-name{color:#fff}
body.theme-aurora .upload-queue-meta,body.theme-aurora .upload-queue-index{color:#dbe8ff}
body.theme-aurora .upload-queue-progress{background:rgba(255,255,255,.18)}
body.theme-night .upload-link-btn,body.theme-neon .upload-link-btn,body.theme-onefour .upload-link-btn{background:rgba(255,255,255,.08)}
body.theme-aurora .upload-link-btn{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.24);color:#fff}
body.theme-aurora .upload-link-btn:hover{border-color:#fff;background:rgba(255,255,255,.22);color:#fff}
</style>
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
</script>
<script src="./assets/js/uploadnew.js?v=<?php echo VERSION?>"></script>
</body>
</html>
