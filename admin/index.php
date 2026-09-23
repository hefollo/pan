<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='管理中心';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<?php
$mysqlversion=$DB->getColumn("select VERSION()");
/*
 * 这里原来是 JSONP 加载 //auth.cccyun.cc/app/pan.php 做版本检查。
 * JSONP 响应本身就是 JavaScript，会在后台域下执行，等于把后台的完全控制权
 * 交给第三方域名（及其服务器、DNS、传输链路）；返回值还被 .html() 直接插进 DOM。
 * 本项目是二开分支，版本号和上游不是一套，检查结果也没有参考意义，直接去掉。
 */
?>
<div class="container">
<div class="admin-page">
<div id="browser-notice"></div>
<div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-primary stat-panel">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-cloud fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count1">0</div>
                                    <div>文件总数</div>
                                </div>
                            </div>
                        </div>
                        <a href="file.php">
                            <div class="panel-footer">
                                <span class="pull-left" herf="file.php">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-green stat-panel">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-cloud-upload fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count2">0</div>
                                    <div>今日上传文件</div>
                                </div>
                            </div>
                        </div>
                        <a href="file.php">
                            <div class="panel-footer">
                                <span class="pull-left">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-yellow stat-panel">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-inbox fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count3">0</div>
                                    <div>昨日上传文件</div>
                                </div>
                            </div>
                        </div>
                        <a href="file.php">
                            <div class="panel-footer">
                                <span class="pull-left">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-red stat-panel">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-users fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count4">0</div>
                                    <div>用户总数</div>
                                </div>
                            </div>
                        </div>
                        <a href="user.php">
                            <div class="panel-footer">
                                <span class="pull-left">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /.row -->
            <!-- 文件类型分布：原来是一行六张 col-lg-2 的小卡，数字和占用空间挤成一团，
                 改成一整块列表，每类一行：图标 / 名称 / 占比条 / 数量 / 占用空间 / 占比 -->
            <div class="row">
                <div class="col-xs-12">
                    <div class="panel panel-default filetype-panel">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-pie-chart" aria-hidden="true"></i> 文件类型分布</h3>
                            <div class="filetype-tools">
                                <div class="filetype-total">
                                    <span>全部文件占用</span>
                                    <b id="size_total">-</b>
                                </div>
                                <div class="btn-group filetype-switch" role="group">
                                    <button type="button" class="btn btn-xs btn-primary" data-by="count">按数量</button>
                                    <button type="button" class="btn btn-xs btn-default" data-by="size">按占用</button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body filetype-body">
                    <div class="filetype-row" data-group="image">
                        <span class="filetype-icon"><i class="fa fa-picture-o" aria-hidden="true"></i></span>
                        <span class="filetype-name">图片</span>
                        <span class="filetype-track"><i></i></span>
                        <span class="filetype-num"><b>0</b> 个</span>
                        <span class="filetype-size">-</span>
                        <span class="filetype-pct">-</span>
                    </div>
                    <div class="filetype-row" data-group="video">
                        <span class="filetype-icon"><i class="fa fa-video-camera" aria-hidden="true"></i></span>
                        <span class="filetype-name">视频</span>
                        <span class="filetype-track"><i></i></span>
                        <span class="filetype-num"><b>0</b> 个</span>
                        <span class="filetype-size">-</span>
                        <span class="filetype-pct">-</span>
                    </div>
                    <div class="filetype-row" data-group="audio">
                        <span class="filetype-icon"><i class="fa fa-music" aria-hidden="true"></i></span>
                        <span class="filetype-name">音频</span>
                        <span class="filetype-track"><i></i></span>
                        <span class="filetype-num"><b>0</b> 个</span>
                        <span class="filetype-size">-</span>
                        <span class="filetype-pct">-</span>
                    </div>
                    <div class="filetype-row" data-group="doc">
                        <span class="filetype-icon"><i class="fa fa-file-text-o" aria-hidden="true"></i></span>
                        <span class="filetype-name">文档</span>
                        <span class="filetype-track"><i></i></span>
                        <span class="filetype-num"><b>0</b> 个</span>
                        <span class="filetype-size">-</span>
                        <span class="filetype-pct">-</span>
                    </div>
                    <div class="filetype-row" data-group="archive">
                        <span class="filetype-icon"><i class="fa fa-file-archive-o" aria-hidden="true"></i></span>
                        <span class="filetype-name">压缩包</span>
                        <span class="filetype-track"><i></i></span>
                        <span class="filetype-num"><b>0</b> 个</span>
                        <span class="filetype-size">-</span>
                        <span class="filetype-pct">-</span>
                    </div>
                    <div class="filetype-row" data-group="other">
                        <span class="filetype-icon"><i class="fa fa-ellipsis-h" aria-hidden="true"></i></span>
                        <span class="filetype-name">其它</span>
                        <span class="filetype-track"><i></i></span>
                        <span class="filetype-num"><b>0</b> 个</span>
                        <span class="filetype-size">-</span>
                        <span class="filetype-pct">-</span>
                    </div>
                            <p class="filetype-note">「其它」是扩展名不在图片 / 视频 / 音频 / 文档 / 压缩包五类里的文件（如 apk、exe），分类口径跟「文件设置」里填的格式一致。</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.row -->
        <div class="row">
            <div class="col-md-8 col-sm-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title">服务器信息</h3>
                    </div>
                    <ul class="list-group">
                        <li class="list-group-item">
                            <b>PHP 版本：</b><?php echo phpversion() ?>
                            <?php if(ini_get('safe_mode')) { echo '线程安全'; } else { echo '非线程安全'; } ?>
                        </li>
                        <li class="list-group-item">
                            <b>MySQL 版本：</b><?php echo $mysqlversion ?>
                        </li>
                        <li class="list-group-item">
                            <b>WEB软件：</b><?php echo $_SERVER['SERVER_SOFTWARE'] ?>
                        </li>
                        
                        <li class="list-group-item">
                            <b>服务器时间：</b><?php echo $date ?>
                        </li>
                        <li class="list-group-item">
                            <b>POST许可：</b><?php echo ini_get('post_max_size'); ?>
                        </li>
                        <li class="list-group-item">
                            <b>文件上传许可：</b><?php echo ini_get('upload_max_filesize'); ?>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4 col-sm-12">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title">版本信息</h3>
                    </div>
                    <ul class="list-group text-dark" id="checkupdate">
                        <li class="list-group-item">程序版本：<?php echo htmlspecialchars(VERSION, ENT_QUOTES, 'UTF-8')?>（数据库版本 <?php echo htmlspecialchars(DB_VERSION, ENT_QUOTES, 'UTF-8')?>）</li>
                        <li class="list-group-item update-line">
                            <span>更新检查：<span id="updateState" class="label label-default">检查中…</span></span>
                            <a href="./update.php" class="update-line-link">更新日志 <i class="fa fa-angle-right" aria-hidden="true"></i></a>
                        </li>
                        <li class="list-group-item update-latest" id="updateLatest" style="display:none">最新提交：<span></span></li>
                        <li class="list-group-item">惜染美化：<a href="https://wpa.qq.com/msgrd?v=3&amp;uin=1322445750&amp;site=qq&amp;menu=yes&amp;jumpflag=1" target="_blank" rel="noopener noreferrer">1322445750</a></li>
                        <li class="list-group-item">本项目开源地址：<a href="https://github.com/hefollo/pan" target="_blank" rel="noopener noreferrer">github.com/hefollo/pan</a></li>
                        <li class="list-group-item">原开源项目地址：<a href="https://github.com/netcccyun/pan" target="_blank" rel="noopener noreferrer">github.com/netcccyun/pan</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
//文件类型分布的六组，顺序跟页面上的行一致
var FILETYPE_GROUPS = ['image', 'video', 'audio', 'doc', 'archive', 'other'];
var filetypeData = null;

//四位以上的数字加千分位，39580 这种连着看容易数错位
function adminNumber(n){
    n = parseInt(n, 10);
    if(isNaN(n)) return '0';
    return String(n).replace(/(\d)(?=(\d{3})+$)/g, '$1,');
}

//by='count' 按文件数量算占比，by='size' 按占用空间算
function renderFiletype(by){
    if(!filetypeData || !filetypeData.types) return;
    var types = filetypeData.types, sizes = filetypeData.sizes || {}, bytes = filetypeData.bytes || {};
    var base = 0;
    $.each(FILETYPE_GROUPS, function(i, g){
        base += (by === 'size' ? (parseFloat(bytes[g]) || 0) : (parseInt(types[g], 10) || 0));
    });
    $.each(FILETYPE_GROUPS, function(i, g){
        var row = $('.filetype-row[data-group="' + g + '"]');
        if(!row.length) return;
        var num = parseInt(types[g], 10) || 0;
        var val = (by === 'size' ? (parseFloat(bytes[g]) || 0) : num);
        var pct = base > 0 ? (val / base * 100) : 0;
        row.find('.filetype-num b').text(adminNumber(num));
        row.find('.filetype-size').text(sizes[g] ? sizes[g] : '-');
        //占比条太细看不出来，非零的至少留一点宽度
        row.find('.filetype-track > i').css('width', (pct > 0 ? Math.max(pct, 1.2) : 0) + '%');
        row.find('.filetype-pct').text(pct <= 0 ? '0%' : (pct < 0.1 ? '<0.1%' : pct.toFixed(1) + '%'));
    });
}

$(document).ready(function(){
    $.ajax({
        type : "GET",
        url : "ajax.php?act=getcount",
        dataType : 'json',
        async: true,
        success : function(data) {
            //统计数字全部走 text()，避免接口返回的内容被当成 HTML 解析
            $('#count1').text(adminNumber(data.count1));
            $('#count2').text(adminNumber(data.count2));
            $('#count3').text(adminNumber(data.count3));
            $('#count4').text(adminNumber(data.count4));
            //类型统计：接口没返回（比如只传了部分文件、layout_blocks.php 还是旧版）就保持占位符，不报错
            if(data.types){
                filetypeData = data;
                $('#size_total').text(data.totalsize ? data.totalsize : '-');
                renderFiletype($('.filetype-switch .btn.btn-primary').data('by') || 'count');
            }
        }
    })

    /*
     * 更新检查单独一个请求：服务端要访问 GitHub，慢的时候十几秒，
     * 不能和上面那几个统计数字挤在同一个请求里，否则首页数字也跟着一起等。
     * 返回的文案一律走 text()，不用 html() —— 内容来自外部接口。
     */
    $.getJSON('ajax.php?act=checkupdate', function(d){
        if(!d || typeof d.state === 'undefined')return;
        var cls = {'new':'label-warning', 'latest':'label-success', 'ahead':'label-info', 'unknown':'label-default', 'error':'label-default'};
        var tip = '检查时间 ' + (d.checked || '未知');
        if(d.state === 'error' && d.error)tip = d.error;
        if(d.state === 'new' && d.remote)tip = '仓库版本 ' + d.remote + '，本地 ' + d.local + (d.needdb ? '；这次动过数据库，传完包要再跑一次 /install/update.php' : '');
        $('#updateState')
            .attr('class', 'label ' + (cls[d.state] || 'label-default'))
            .attr('title', tip)
            .text(d.text || '');
        if(d.latest){
            $('#updateLatest').show().find('span').text(d.latest);
        }
    });

    //「按数量 / 按占用」切换：只重算已经拿到的数据，不再请求接口
    $('.filetype-switch .btn').on('click', function(){
        var btn = $(this);
        if(btn.hasClass('btn-primary')) return;
        btn.addClass('btn-primary').removeClass('btn-default')
            .siblings().addClass('btn-default').removeClass('btn-primary');
        renderFiletype(btn.data('by'));
    });
})
</script>
<script>
function speedModeNotice(){
    var ua = window.navigator.userAgent;
    if(ua.indexOf('Windows NT')>-1 && ua.indexOf('Trident/')>-1){
        var html = "<div class=\"panel panel-default\"><div class=\"panel-body\">当前浏览器是兼容模式，为确保后台功能正常使用，请切换到<b style='color:#51b72f'>极速模式</b>。<br>操作方法：点击浏览器地址栏右侧的IE符号<b style='color:#51b72f;'><i class='fa fa-internet-explorer fa-fw'></i></b>→选择“<b style='color:#51b72f;'><i class='fa fa-flash fa-fw'></i></b><b style='color:#51b72f;'>极速模式</b>”</div></div>";
        $("#browser-notice").html(html)
    }
}
speedModeNotice();
</script>
