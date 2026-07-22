<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='用户登录设置';
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
                <h3 class="block-title"> 用户登录设置 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
                    <div class="mb-4">
                        <label class="form-label" for="userlogin">用户登录开关</label>
                        <select name="userlogin" class="form-select" default="<?php echo $conf['userlogin']?>"><option value="0">关闭</option><option value="1">开启</option></select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="login_apiurl"><b>聚合登录接口地址</b></label>
                        <input type="text" class="form-control form-control-lg" name="login_apiurl" value="<?php echo $conf['login_apiurl']; ?>" placeholder="接口地址要以http://或https://开头，以/结尾：例如https://u.suyanw.cn/">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="login_appid"><b>应用APPID</b></label>
                        <input type="text" class="form-control form-control-lg" name="login_appid" value="<?php echo $conf['login_appid']; ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="login_appkey"><b>应用APPKEY</b></label>
                        <input type="text" class="form-control form-control-lg" name="login_appkey" value="<?php echo $conf['login_appkey']; ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><b>开启的登录方式</b></label>
                        <input type="hidden" name="login_qq" value="0"/>
                        <input type="hidden" name="login_wx" value="0"/>
                        <label class="checkbox-inline"><input type="checkbox" name="login_qq" value="1" <?php echo $conf['login_qq']?'checked':null;?>> QQ</label>
                        <label class="checkbox-inline"><input type="checkbox" name="login_wx" value="1" <?php echo $conf['login_wx']?'checked':null;?>> 微信</label>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check opacity-50 me-1"></i> <b>保存设置</b>
                        </button>
                    </div>
                </form>
                <div class="panel-footer">
                    <span class="glyphicon glyphicon-info-sign"></span>
                    开启后请勿随意更换登录接口站点，否则会导致之前注册的用户全部无法登录。
                </div>
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