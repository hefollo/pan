<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='管理员账号设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<?php
$mod=isset($_GET['mod'])?$_GET['mod']:null;
if($mod=='account_n' && $_POST['do']=='submit'){
    if(!checkRefererHost())exit;
    $user=$_POST['user'];
    $oldpwd=$_POST['oldpwd'];
    $newpwd=$_POST['newpwd'];
    $newpwd2=$_POST['newpwd2'];
    if($user==null)showmsg('用户名不能为空！',3);
    saveSetting('admin_user',$user);
    if(!empty($newpwd) && !empty($newpwd2)){
        if($oldpwd!=$conf['admin_pwd'])showmsg('旧密码不正确！',3);
        if($newpwd!=$newpwd2)showmsg('两次输入的密码不一致！',3);
        saveSetting('admin_pwd',$newpwd);
    }
    showmsg('修改成功！请重新登录',1);
}
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
                <h3 class="block-title"> 管理员账号设置 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <form action="./set_account.php?mod=account_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>
                    <div class="mb-4">
                        <label class="form-label" for="user"><b>用户名</b></label>
                        <input type="text" class="form-control form-control-lg" name="user" value="<?php echo $conf['admin_user']; ?>" placeholder="多个域名用|隔开" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="oldpwd"><b>旧密码</b></label>
                        <input type="text" class="form-control form-control-lg" name="oldpwd" value="" placeholder="请输入当前的管理员密码">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="newpwd"><b>新密码</b></label>
                        <input type="text" class="form-control form-control-lg" name="newpwd" value="" placeholder="不修改请留空">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="newpwd2"><b>重输密码</b></label>
                        <input type="text" class="form-control form-control-lg" name="newpwd2" value="" placeholder="不修改请留空">
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