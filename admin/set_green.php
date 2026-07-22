<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='图片检测设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<?php
$green_label_porn = explode(',', $conf['green_label_porn']);
$green_label_terrorism = explode(',', $conf['green_label_terrorism']);
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
                <h3 class="block-title"> 图片检测设置 </h3>
                <div class="block-options">
                    <button type="button" onclick="" class="btn-block-option" data-toggle="block-option" data-action="state_toggle" data-action-mode="demo">
                        <i class="si si-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="block-content">
                <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
                    <div class="mb-4">
                        <label class="form-label" for="green_check"><b>图片违规检测</b></label>
                        <select name="green_check" class="form-select" default="<?php echo $conf['green_check']?>"><option value="0">关闭</option><option value="1">阿里云内容安全接口</option><option value="2">腾讯云内容安全接口</option></select>
                    </div>
                    <div id="green_aliyun" style="<?php echo $conf['green_check']!='1'?'display:none;':null; ?>">
                        <div class="mb-4">
                            <label class="form-label" for="aliyun_ak"><b>阿里云AccessKey Id</b></label>
                            <input type="text" class="form-control form-control-lg" name="aliyun_ak" value="<?php echo $conf['aliyun_ak']; ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="aliyun_sk"><b>阿里云AccessKey Secret</b></label>
                            <input type="text" class="form-control form-control-lg" name="aliyun_sk" value="<?php echo $conf['aliyun_sk']; ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_check_region"><b>图片检测接入区域</b></label>
                            <select name="green_check_region" class="form-select" default="<?php echo $conf['green_check_region']?>"><option value="cn-beijing">华北2（北京）</option><option value="cn-shanghai">华东2（上海）</option><option value="cn-shenzhen">华南1（深圳）</option><option value="ap-southeast-1">新加坡</option><option value="us-west-1">美西</option></select><font color="green">你可以选择一个离本站服务器最近的以提升检测速度</font>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_check_porn"><b>图片智能鉴黄</b></label>
                            <select name="green_check_porn" class="form-select" default="<?php echo $conf['green_check_porn']?>"><option value="0">关闭</option><option value="1">开启</option></select>
                        </div>
                        <div class="mb-4" id="green_check_porn_" style="<?php echo $conf['green_check_porn']!=1?'display:none;':null; ?>">
                            <label class="form-label"><b>图片智能鉴黄屏蔽类型</b></label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_porn[]" value="porn" <?php echo in_array('porn',$green_label_porn)?'checked':null;?>> 色情图片（porn）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_porn[]" value="sexy" <?php echo in_array('sexy',$green_label_porn)?'checked':null;?>> 性感图片（sexy）</label>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_check_terrorism"><b>图片暴恐涉政识别</b></label>
                            <select name="green_check_terrorism" class="form-select" default="<?php echo $conf['green_check_terrorism']?>"><option value="0">关闭</option><option value="1">开启</option></select>
                        </div>
                        <div class="mb-4" id="green_check_terrorism_" style="<?php echo $conf['green_check_terrorism']!=1?'display:none;':null; ?>">
                            <label class="form-label"><b>图片暴恐涉政识别屏蔽类型</b></label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="bloody" <?php echo in_array('bloody',$green_label_terrorism)?'checked':null;?>> 血腥（bloody）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="explosion" <?php echo in_array('explosion',$green_label_terrorism)?'checked':null;?>> 爆炸烟光（explosion）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="outfit" <?php echo in_array('outfit',$green_label_terrorism)?'checked':null;?>> 特殊装束（outfit）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="logo" <?php echo in_array('logo',$green_label_terrorism)?'checked':null;?>> 特殊标识（logo）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="weapon" <?php echo in_array('weapon',$green_label_terrorism)?'checked':null;?>> 武器（weapon）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="politics" <?php echo in_array('politics',$green_label_terrorism)?'checked':null;?>> 涉政（politics）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="violence" <?php echo in_array('violence',$green_label_terrorism)?'checked':null;?>> 打斗（violence）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="crowd" <?php echo in_array('crowd',$green_label_terrorism)?'checked':null;?>> 聚众（crowd）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="parade" <?php echo in_array('parade',$green_label_terrorism)?'checked':null;?>> 游行（parade）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="carcrash" <?php echo in_array('carcrash',$green_label_terrorism)?'checked':null;?>> 车祸现场（carcrash）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="flag" <?php echo in_array('flag',$green_label_terrorism)?'checked':null;?>> 旗帜（flag）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="location" <?php echo in_array('location',$green_label_terrorism)?'checked':null;?>> 地标（location）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="drug" <?php echo in_array('drug',$green_label_terrorism)?'checked':null;?>> 涉毒（drug）</label>
                            <label class="checkbox-inline"><input type="checkbox" name="green_label_terrorism[]" value="gamble" <?php echo in_array('gamble',$green_label_terrorism)?'checked':null;?>> 赌博（gamble）</label>
                        </div>
                    </div>
                    <div id="green_qcloud" style="<?php echo $conf['green_check']!='2'?'display:none;':null; ?>">
                        <div class="mb-4">
                            <label class="form-label" for="qcloud_green_id"><b>腾讯云SecretId</b></label>
                            <input type="text" class="form-control form-control-lg" name="qcloud_green_id" value="<?php echo $conf['qcloud_green_id']; ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="qcloud_green_key"><b>腾讯云SecretKey</b></label>
                            <input type="text" class="form-control form-control-lg" name="qcloud_green_key" value="<?php echo $conf['qcloud_green_key']; ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_check_region"><b>图片检测接入区域</b></label>
                            <select name="green_check_region" class="form-select" default="<?php echo $conf['green_check_region']?>"><option value="ap-beijing">华北地区(北京)</option><option value="ap-shanghai">华东地区(上海)</option><option value="ap-guangzhou">华南地区(广州)</option><option value="ap-mumbai">亚太南部(孟买)</option><option value="ap-singapore">亚太东南(新加坡)</option><option value="eu-frankfurt">欧洲地区(法兰克福)</option><option value="na-ashburn">美国东部(弗吉尼亚)</option><option value="na-siliconvalley">美国西部(硅谷)</option></select><font color="green">你可以选择一个离本站服务器最近的以提升检测速度</font>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="apiurl"><b>图片检测访问网址</b></label>
                        <input type="text" class="form-control form-control-lg" name="apiurl" value="<?php echo $conf['apiurl']; ?>" placeholder="不填写则默认使用当前网址"><font color="green">此处是图片检测的时候阿里云访问本站的网址，不填写则默认使用当前网址，如果填写必需以http://开头，以/结尾</font>
                    </div>
                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check opacity-50 me-1"></i> <b>保存设置</b>
                        </button>
                    </div>
                </form>
                <div class="panel-footer">
                    <span class="glyphicon glyphicon-info-sign"></span>
                    阿里云内容安全接口：<a href="https://yundun.console.aliyun.com/?p=cts#/api/statistics" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://usercenter.console.aliyun.com/#/manage/ak" target="_blank" rel="noreferrer">获取密钥</a><br/>
                    腾讯云内容安全接口：<a href="https://cloud.tencent.com/product/ims" target="_blank" rel="noreferrer">点此进入</a>｜<a href="https://console.cloud.tencent.com/cam/capi" target="_blank" rel="noreferrer">获取密钥</a><br/>
                    屏蔽类型选不选都可以，会同时根据返回的建议结果进行屏蔽
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'foot.php';?>
<script>
    $("select[name='green_check']").change(function(){
        if($(this).val() == 1){
            $("#green_aliyun").show();
            $("#green_qcloud").hide();
        }else if($(this).val() == 2){
            $("#green_aliyun").hide();
            $("#green_qcloud").show();
        }else{
            $("#green_aliyun").hide();
            $("#green_qcloud").hide();
        }
    });
    $("select[name='green_check_porn']").change(function(){
        if($(this).val() == 1){
            $("#green_check_porn_").show();
        }else{
            $("#green_check_porn_").hide();
        }
    });
    $("select[name='green_check_terrorism']").change(function(){
        if($(this).val() == 1){
            $("#green_check_terrorism_").show();
        }else{
            $("#green_check_terrorism_").hide();
        }
    });
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