<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='上传API设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<?php
$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$admin_path = substr($sitepath, strrpos($sitepath, '/'));
$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].str_replace($admin_path,'',$sitepath).'/';
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
                <h3 class="block-title"> 上传API设置 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
                    <div class="mb-4">
                        <label class="form-label" for="api_open">上传API开关</label>
                        <select name="api_open" class="form-select" default="<?php echo $conf['api_open']?>"><option value="0">关闭</option><option value="1">开启</option></select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="api_referer"><b>来源域名白名单</b></label>
                        <input type="text" class="form-control form-control-lg" name="api_referer" value="<?php echo $conf['api_referer']; ?>" placeholder="多个域名用|隔开"><font color="green">多个域名用|隔开，不填写则不限制来源域名</font>
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
    <div class="content animated fadeIn">
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title"> 上传API文档 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <pre>
                <h3>API接口地址：<?php echo $siteurl?>api.php</h3>
                <h4>当前API支持JSON、JSONP、FORM 3种返回方式，支持Web跨域调用，也支持程序中直接调用。</h4>
                <h4>请求方式：POST  multipart/form-data</h4>
                <h3>请求参数说明：</h3>
                <table class="table table-bordered table-hover">
                  <thead><tr><th>字段名</th><th>变量名</th><th>是否必填</th><th>示例值</th><th>描述</th></tr></thead>
                  <tbody>
                  <tr><td>文件</td><td>file</td><td>是</td><td></td><td>multipart格式文件</td></tr>
                  <tr><td>是否首页显示</td><td>show</td><td>否</td><td>1</td><td>默认为是</td></tr>
                  <tr><td>是否设置密码</td><td>ispwd</td><td>否</td><td>0</td><td>默认为否</td></tr>
                  <tr><td>下载密码</td><td>pwd</td><td>否</td><td>123456</td><td>默认留空</td></tr>
                  <tr><td>返回格式</td><td>format</td><td>否</td><td>json</td><td>有json、jsonp、form三种选择 默认为json</td></tr>
                  <tr><td>跳转页面url</td><td>backurl</td><td>否</td><td>http://...</td><td>上传成功后的跳转地址 只在form格式有效</td></tr>
                  <tr><td>callback</td><td>callback</td><td>否</td><td>callback</td><td>只在jsonp格式有效</td></tr>
                  </tbody>
                </table>
                <h3>返回参数说明：</h3>
                <table class="table table-bordered table-hover">
                  <thead><tr><th>字段名</th><th>变量名</th><th>类型</th><th>示例值</th><th>描述</th></tr></thead>
                  <tbody>
                  <tr><td>上传状态</td><td>code</td><td>Int</td><td>0</td><td>0为成功，其他为失败</td></tr>
                  <tr><td>提示信息</td><td>msg</td><td>String</td><td>上传成功！</td><td>如果上传失败会有错误提示</td></tr>
                  <tr><td>文件MD5</td><td>hash</td><td>String</td><td>f1e807cb0d6ba52d71bdb02864e6bda8</td><td></td></tr>
                  <tr><td>文件名称</td><td>name</td><td>String</td><td>exapmle1.jpg</td><td></td></tr>
                  <tr><td>文件大小</td><td>size</td><td>Int</td><td>58937</td><td>单位：字节</td></tr>
                  <tr><td>文件格式</td><td>type</td><td>String</td><td>jpg</td><td></td></tr>
                  <tr><td>下载地址</td><td>downurl</td><td>String</td><td>http://.....</td><td></td></tr>
                  <tr><td>预览地址</td><td>viewurl</td><td>String</td><td>http://.....</td><td>只有图片、音乐、视频文件才有</td></tr>
                  </tbody>
                </table>
                </pre>
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