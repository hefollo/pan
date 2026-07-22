<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='文件上传设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<div id="pjax-container">
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center py-2">
                <div class="flex-grow-1">
                    <h1 class="h3 fw-bold mb-2">
                        <?php echo $title ?>
                    </h1>
                </div>
                <nav class="flex-shrink-0 mt-3 mt-sm-0 ms-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-alt">
                        <li class="breadcrumb-item">
                            <a class="link-fx" href="./index.php">后台首页</a>
                        </li>
                        <li class="breadcrumb-item" aria-current="page">
                            系统设置
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <div class="content animated fadeIn">
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title"> 文件上传设置 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
                    <div class="mb-4">
                        <label class="form-label" for="type_image"><b>图片文件类型</b></label>
                        <input type="text" class="form-control form-control-lg" name="type_image" value="<?php echo $conf['type_image']; ?>" placeholder="多个文件类型用|隔开"><font color="green">在文件预览页面，以上文件类型将以图片的形式展示</font>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="type_audio"><b>音频文件类型</b></label>
                        <input type="text" class="form-control form-control-lg" name="type_audio" value="<?php echo $conf['type_audio']; ?>" placeholder="多个文件类型用|隔开"><font color="green">在文件预览页面，以上文件类型将以音频的形式展示</font>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="type_video"><b>视频文件类型</b></label>
                        <input type="text" class="form-control form-control-lg" name="type_video" value="<?php echo $conf['type_video']; ?>" placeholder="多个文件类型用|隔开"><font color="green">在文件预览页面，以上文件类型将以视频的形式展示</font>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="type_block"><b>禁止上传的文件类型</b></label>
                        <input type="text" class="form-control form-control-lg" name="type_block" value="<?php echo $conf['type_block']; ?>" placeholder="多个文件类型用|隔开">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="name_block"><b>文件名屏蔽关键词</b></label>
                        <input type="text" class="form-control form-control-lg" name="name_block" value="<?php echo $conf['name_block']; ?>" placeholder="多个关键词用|隔开">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="upload_limit"><b>每IP每天限制上传数量</b></label>
                        <input type="text" class="form-control form-control-lg" name="upload_limit" value="<?php echo $conf['upload_limit']; ?>" placeholder="0或留空为不限制">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="videoreview">视频文件需要审核</label>
                        <select name="videoreview" class="form-select" default="<?php echo $conf['videoreview']?>"><option value="0">关闭</option><option value="1">开启</option></select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="upload_size"><b>上传大小限制</b></label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg" name="upload_size" value="<?php echo $conf['upload_size']; ?>" placeholder="不填写则不限制大小">
                            <span class="input-group-text">MB</span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="forcelogin">仅限登录用户上传</label>
                        <select name="forcelogin" class="form-select" default="<?php echo $conf['forcelogin']?>"><option value="0">0_否</option><option value="1">1_是</option></select>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check opacity-50 me-1"></i> <b>保存设置</b>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'foot.php';?>
<script>
    var items = $("select[default]");
    for (i = 0; i < items.length; i++) {
        $(items[i]).val($(items[i]).attr("default")||0);
    }
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