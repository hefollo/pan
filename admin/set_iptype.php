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
                <?php
                //按这一次访问的实际情况说明地址是怎么来的：四个选项都显示同一个内网地址时，光看下拉框看不出原因
                $ip_hint = ip_setting_hint();
                if($ip_hint){?>
                <div class="alert alert-<?php echo $ip_hint['type']?>"><?php foreach($ip_hint['lines'] as $i=>$line){echo ($i ? '<br/>' : '').htmlspecialchars($line, ENT_QUOTES, 'UTF-8');}?></div>
                <?php }?>
                <form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
                    <div class="mb-4">
                        <label class="form-label" for="ip_type">用户IP地址获取方式</label>
                        <p class="form-text" style="margin:6px 0 10px;color:#8a94a6;font-size:13px">
                          站点套了 Cloudflare、或者前面有自己的反向代理的话请选 <b>3_自动校验来源</b>：它只在请求确实来自 CF 的 IP 段、或来自下面填的可信反向代理时，才采信它们传来的真实 IP，其余情况一律用 TCP 连接的对端地址。
                          选 0 或 1 时，请求头可以被伪造——脚本每次换一个假 IP 就等于换一个新身份，按 IP 的上传限制会失效。
                          下面每个选项后面显示的就是当前这次访问用该方式取到的地址，对照着选即可。
                        </p>
                        <select name="ip_type" class="form-select" default="<?php echo $conf['ip_type']?>"><option value="0">0_X_FORWARDED_FOR</option><option value="1">1_X_REAL_IP</option><option value="2">2_REMOTE_ADDR</option><option value="3">3_自动校验来源（Cloudflare / 指定的反向代理）</option></select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="ip_proxy_trust">可信反向代理地址</label>
                        <input type="text" name="ip_proxy_trust" class="form-control" value="<?php echo htmlspecialchars(isset($conf['ip_proxy_trust']) ? $conf['ip_proxy_trust'] : '', ENT_QUOTES, 'UTF-8')?>" placeholder="前面没有自己的反向代理就留空"/>
                        <p class="form-text" style="margin:6px 0 0;color:#8a94a6;font-size:13px">
                          本站前面如果有你自己的反向代理（宿主机上的 Nginx、面板里的「反向代理」、内网穿透等），把它连到本站时用的地址填在这里，多个用空格或逗号隔开，可以写网段（如 <code>192.168.100.0/24</code>）。
                          填了之后，只有这些地址传来的 <code>X-Forwarded-For</code> / <code>X-Real-IP</code> 才会被采信，配合上面的方式 3 使用；后台登录失败锁定、验证码邮件的限额也按它算。
                          <b>纯端口转发不是反向代理，它不传访客地址，填了也没用。</b>
                        </p>
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
                    //查不到归属地时接口给的是空值，原来直接拼出来就是字面的「false」
                    $("select[name='ip_type']").append('<option value="'+k+'" '+(defaultv==k?'selected':'')+'>'+ item.name +' - '+ item.ip + (item.city ? ' ' + item.city : '') +'</option>');
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