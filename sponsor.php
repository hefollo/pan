<?php
include("./includes/common.php");

$title = '赞助名单 - ' . $conf['title'];

$sponsors = $DB->getAll("SELECT `name`,`platform`,`amount`,`sponsor_time` FROM pre_sponsor ORDER BY id ASC");
if(!$sponsors) $sponsors = [];

//收款码沿用后台“赞助名单管理”里的配置，配置成空则不显示这一种收款方式
$pay_methods = [
    ['key'=>'sponsor_img_weixin', 'default'=>'images/weixin.png',   'label'=>'微信赞助'],
    ['key'=>'sponsor_img_qq',     'default'=>'images/qq.png',       'label'=>'QQ钱包赞助'],
    ['key'=>'sponsor_img_alipay', 'default'=>'images/zhifubao.png', 'label'=>'支付宝赞助'],
];
$pay_list = [];
foreach($pay_methods as $m){
    $url = isset($conf[$m['key']]) ? trim($conf[$m['key']]) : $m['default'];
    if($url === '')continue;
    //后台存的是相对 includes/sponsor/ 的路径，本页在站点根目录，需要补上前缀
    if(!preg_match('#^(https?:)?//#i', $url) && $url[0] !== '/'){
        $url = 'includes/sponsor/'.$url;
    }
    $pay_list[] = ['url'=>$url, 'label'=>$m['label']];
}

include SYSTEM_ROOT.'header.php';
?>
<div class="container">
    <div class="well bs-component">
        <h2>赞助名单</h2>
        <p class="text-muted">感谢每一位支持本站的朋友，您的支持是本站持续运行的动力。</p>

        <?php if($pay_list){?>
        <div class="sponsor-pay-list">
            <?php foreach($pay_list as $m){ $label = htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?>
            <div class="sponsor-pay-item">
                <img src="<?php echo htmlspecialchars($m['url'], ENT_QUOTES, 'UTF-8')?>" alt="<?php echo $label?>" title="<?php echo $label?>">
                <p><?php echo $label?></p>
            </div>
            <?php }?>
        </div>
        <?php }?>

        <div class="table-responsive">
        <table class="table table-striped table-hover filelist">
            <thead>
                <tr>
                    <th>#</th>
                    <th>昵称</th>
                    <th>赞助方式</th>
                    <th>赞助金额</th>
                    <th>赞助时间</th>
                </tr>
            </thead>
            <tbody>
<?php
$i = 1;
foreach($sponsors as $row){
    echo '<tr><td><b>'.$i++.'</b></td><td>'.htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8').'</td><td><span class="file-type-badge">'.htmlspecialchars($row['platform'], ENT_QUOTES, 'UTF-8').'</span></td><td>'.htmlspecialchars($row['amount'], ENT_QUOTES, 'UTF-8').'</td><td>'.htmlspecialchars($row['sponsor_time'], ENT_QUOTES, 'UTF-8').'</td></tr>';
}
if(count($sponsors) == 0) echo '<tr><td colspan="5" align="center">暂无赞助记录，成为第一个赞助者吧！</td></tr>';
?>
            </tbody>
        </table>
        </div>
        <div class="filelist-footer">
            <div class="filelist-summary">共 <?php echo count($sponsors)?> 条赞助记录</div>
        </div>
    </div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
</body>
</html>
