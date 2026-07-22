<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='网站信息设置';
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
                <h3 class="block-title"> 网站信息设置 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
                    <div class="mb-4">
                        <label class="form-label" for="title"><b>网站标题</b></label>
                        <input type="text" class="form-control form-control-lg" name="title" value="<?php echo $conf['title']; ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="description"><b>关键字</b></label>
                        <input type="text" class="form-control form-control-lg" name="keywords" value="<?php echo $conf['keywords']; ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="description"><b>网站描述</b></label>
                        <input type="text" class="form-control form-control-lg" name="description" value="<?php echo $conf['description']; ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="blackip"><b>禁止访问IP</b></label>
                        <textarea type="text" class="form-control form-control-lg" name="blackip" rows="2" placeholder="多个IP用|隔开"><?php echo htmlspecialchars($conf['blackip'])?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="gonggao"><b>首页公告</b></label>
                        <textarea type="text" class="form-control form-control-lg" name="gonggao" rows="3" placeholder="不填写则不显示首页公告"><?php echo htmlspecialchars($conf['gonggao'])?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="gg_file"><b>文件查看页公告</b></label>
                        <textarea type="text" class="form-control form-control-lg" name="gg_file" rows="3" placeholder="不填写则不显示"><?php echo htmlspecialchars($conf['gg_file'])?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="tongji"><b>统计代码</b></label>
                        <textarea type="text" class="form-control form-control-lg" name="tongji" rows="3" placeholder="不填写则不显示统计代码"><?php echo htmlspecialchars($conf['tongji'])?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="filesearch">文件搜索功能</label>
                        <select name="filesearch" class="form-select" default="<?php echo $conf['filesearch']?>"><option value="0">关闭</option><option value="1">开启</option></select>
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