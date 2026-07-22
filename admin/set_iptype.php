<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='用户IP地址获取设置';
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
                <h3 class="block-title"> 用户IP地址获取设置 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
                    <div class="mb-4">
                        <label class="form-label" for="ip_type">用户IP地址获取方式</label>
                        <select name="ip_type" class="form-select" default="<?php echo $conf['ip_type']?>"><option value="0">0_X_FORWARDED_FOR</option><option value="1">1_X_REAL_IP</option><option value="2">2_REMOTE_ADDR</option></select>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check opacity-50 me-1"></i> <b>保存设置</b>
                        </button>
                    </div>
                </form>
                <div class="panel-footer">
                    <span class="glyphicon glyphicon-info-sign"></span>
                    此功能设置用于防止用户伪造IP请求。<br/>
                    X_FORWARDED_FOR：之前的获取真实IP方式，极易被伪造IP<br/>
                    X_REAL_IP：在网站使用CDN的情况下选择此项，在不使用CDN的情况下也会被伪造<br/>
                    REMOTE_ADDR：直接获取真实请求IP，无法被伪造，但可能获取到的是CDN节点IP<br/>
                    <b>你可以从中选择一个能显示你真实地址的IP，优先选下方的选项。</b>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'foot.php';?>
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