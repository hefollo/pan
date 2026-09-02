<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='内容检测设置';
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
                <h3 class="block-title"> 内容检测设置 </h3>
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
                        <select name="green_check" class="form-select" default="<?php echo $conf['green_check']?>"><option value="0">关闭</option><option value="1">阿里云内容安全接口</option><option value="2">腾讯云内容安全接口</option><option value="3">自建检测服务（本机模型）</option></select>
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
                    <div id="green_self" style="<?php echo $conf['green_check']!='3'?'display:none;':null; ?>">
                        <div class="mb-4">
                            <label class="form-label" for="green_self_api"><b>检测服务地址</b></label>
                            <input type="text" class="form-control form-control-lg" name="green_self_api" value="<?php echo htmlspecialchars(isset($conf['green_self_api'])?$conf['green_self_api']:'', ENT_QUOTES, 'UTF-8'); ?>" placeholder="http://127.0.0.1:9012/check">
                            <font color="green">留空就用默认的 http://127.0.0.1:9012/check。服务只监听本机回环地址，不要暴露到公网。装法见 tools/nsfw/README.md</font>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_self_token"><b>访问令牌</b></label>
                            <input type="text" class="form-control form-control-lg" name="green_self_token" value="<?php echo htmlspecialchars(isset($conf['green_self_token'])?$conf['green_self_token']:'', ENT_QUOTES, 'UTF-8'); ?>" placeholder="选填，与 config.json 里的 token 保持一致">
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_self_block"><b>直接封禁阈值</b></label>
                            <input type="text" class="form-control form-control-lg" name="green_self_block" value="<?php echo htmlspecialchars(isset($conf['green_self_block']) && $conf['green_self_block']!==''?$conf['green_self_block']:'0.85', ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.85">
                            <font color="green">0~1 之间。评分达到这个值直接屏蔽并记入违规公示，调低会更严，误伤也更多</font>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_self_review"><b>转人工阈值</b></label>
                            <input type="text" class="form-control form-control-lg" name="green_self_review" value="<?php echo htmlspecialchars(isset($conf['green_self_review']) && $conf['green_self_review']!==''?$conf['green_self_review']:'0.6', ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.6">
                            <font color="green">评分在这个值和封禁阈值之间的标成待审核（前台下载不了），等你在文件管理里逐个确认</font>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_self_timeout"><b>超时时间（秒）</b></label>
                            <input type="text" class="form-control form-control-lg" name="green_self_timeout" value="<?php echo htmlspecialchars(isset($conf['green_self_timeout']) && $conf['green_self_timeout']!==''?$conf['green_self_timeout']:'5', ENT_QUOTES, 'UTF-8'); ?>" placeholder="5">
                            <font color="green">检测服务没起来或者超时，一律放行不拦，不会因为它挂了就让用户传不了图</font>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="green_video"><b>视频违规检测</b></label>
                            <select name="green_video" class="form-select" default="<?php echo isset($conf['green_video'])?$conf['green_video']:0?>"><option value="0">关闭</option><option value="1">开启</option></select>
                            <font color="green">抽帧送进同一套模型判分。视频是<b>先挂起再放行</b>：传完先标成待审核，检测跑完自动放行或封禁，不卡上传。</font>
                            <div id="greenhealth" class="mt-1 text-muted">检测服务状态查询中…</div>
                        </div>
                        <div id="green_video_" style="<?php echo empty($conf['green_video'])?'display:none;':null; ?>">
                            <div class="mb-4">
                                <label class="form-label" for="green_video_block"><b>视频封禁阈值</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_block" value="<?php echo htmlspecialchars(isset($conf['green_video_block']) && $conf['green_video_block']!==''?$conf['green_video_block']:'0.85', ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.85">
                                <font color="green">单帧分数达到这个值算「命中一帧」，够下面那个帧数才会真的封</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_hit"><b>封禁所需命中帧数</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_hit" value="<?php echo htmlspecialchars(isset($conf['green_video_hit']) && $conf['green_video_hit']!==''?$conf['green_video_hit']:'2', ENT_QUOTES, 'UTF-8'); ?>" placeholder="2">
                                <font color="green">转场、肤色、泳装剧照都可能让某一帧飙到 0.9，只看最高分会误判到没法用。默认要 2 帧命中才封，只命中 1 帧的转人工</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_review"><b>视频转人工阈值</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_review" value="<?php echo htmlspecialchars(isset($conf['green_video_review']) && $conf['green_video_review']!==''?$conf['green_video_review']:'0.6', ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.6">
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_interval"><b>抽帧间隔（秒）</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_interval" value="<?php echo htmlspecialchars(isset($conf['green_video_interval']) && $conf['green_video_interval']!==''?$conf['green_video_interval']:'5', ENT_QUOTES, 'UTF-8'); ?>" placeholder="5">
                                <font color="green">调小查得细但慢，一帧在 CPU 上要一两百毫秒</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_frames"><b>最多抽取帧数</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_frames" value="<?php echo htmlspecialchars(isset($conf['green_video_frames']) && $conf['green_video_frames']!==''?$conf['green_video_frames']:'40', ENT_QUOTES, 'UTF-8'); ?>" placeholder="40">
                                <font color="green">封顶，长片子按这个数在全片上均匀取。40 帧两套模型大概 15~25 秒</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_maxlen"><b>跳过超长视频（秒）</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_maxlen" value="<?php echo htmlspecialchars(isset($conf['green_video_maxlen']) && $conf['green_video_maxlen']!==''?$conf['green_video_maxlen']:'7200', ENT_QUOTES, 'UTF-8'); ?>" placeholder="7200">
                                <font color="green">超过这个时长的不自动检测，直接转人工（不是放行）</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_maxsize"><b>跳过超大视频（MB）</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_maxsize" value="<?php echo htmlspecialchars(isset($conf['green_video_maxsize']) && $conf['green_video_maxsize']!==''?$conf['green_video_maxsize']:'2048', ENT_QUOTES, 'UTF-8'); ?>" placeholder="2048">
                                <font color="green">同上，超过就转人工。0 为不限制</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_timeout"><b>待检超时自动放行（分钟）</b></label>
                                <input type="text" class="form-control form-control-lg" name="green_video_timeout" value="<?php echo htmlspecialchars(isset($conf['green_video_timeout']) && $conf['green_video_timeout']!==''?$conf['green_video_timeout']:'30', ENT_QUOTES, 'UTF-8'); ?>" placeholder="30">
                                <font color="red">这一条是安全阀，别关。</font><font color="green">视频先挂起再放行，检测服务要是挂了，所有视频会永远卡在待审核，等于上传功能作废。超过这个时间还没结果的一律放行并记日志</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="green_video_shot"><b>保存证据帧</b></label>
                                <select name="green_video_shot" class="form-select" default="<?php echo isset($conf['green_video_shot'])?$conf['green_video_shot']:1?>"><option value="1">开启</option><option value="0">关闭</option></select>
                                <font color="green">把命中的那一帧存到 data/greenshot/，人工复核时直接看。目录带 .htaccess 拒绝直接访问，只有后台能看</font>
                            </div>
                            <div class="mb-4">
                                <label class="form-label"><b>定时轮询（建议配）</b></label>
                                <input type="text" class="form-control form-control-lg" onclick="this.select()" readonly value="* * * * * curl -s '<?php echo htmlspecialchars($siteurl.'green_cb.php?poll=1&k='.green_poll_key(), ENT_QUOTES, 'UTF-8'); ?>' >/dev/null">
                                <font color="green">检测服务跑完会主动回调，但回调可能被反代拦掉或内网不通，那样文件会一直卡在待审。加进 crontab 就有了兜底；没加也能转，有人上传视频时会顺带推进一次</font>
                            </div>
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
        var v = $(this).val();
        $("#green_aliyun").toggle(v == 1);
        $("#green_qcloud").toggle(v == 2);
        $("#green_self").toggle(v == 3);
    });
    $("select[name='green_video']").change(function(){
        $("#green_video_").toggle($(this).val() == 1);
    });
    //问一次检测服务自己：模型加载了几个、有没有 ffmpeg。
    //视频检测依赖 ffmpeg，装没装从网站这边完全看不出来，只能问它
    $.getJSON("./ajax.php?act=greenhealth", function(r){
        if(r.code != 0){
            $("#greenhealth").html('<b class="text-danger">检测服务连不上</b>：'+(r.msg||''));
            return;
        }
        var s = '已加载 '+r.models+' 个模型，队列 '+r.queue;
        if(r.video){
            s += '，<b class="text-success">视频检测可用</b>（ffmpeg '+r.ffmpeg+'）';
        }else{
            s += '，<b class="text-danger">视频检测不可用</b>：服务端没有 ffmpeg，开了也不会工作';
        }
        $("#greenhealth").html(s);
    }).fail(function(){
        $("#greenhealth").html('<span class="text-danger">检测服务状态查不到</span>');
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