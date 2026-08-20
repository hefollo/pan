<?php
include("./includes/common.php");

if(isset($conf['violation_open']) && $conf['violation_open'] == 0){
    header('Location: ./');
    exit;
}

$title = '违规文件公示 - ' . $conf['title'];
$notice = isset($conf['violation_notice']) && $conf['violation_notice'] !== '' ? $conf['violation_notice'] : '本站严格遵守国家法律法规，对用户举报及系统检测发现的违规文件一律予以封禁，并在此公示。文件名、上传IP等信息已做脱敏处理。';

$sql = " is_show=1";
$link = '';

include SYSTEM_ROOT.'header.php';
?>
<div class="container">
    <div class="well bs-component">
        <h2>违规文件公示</h2>
        <p class="text-muted"><?php echo htmlspecialchars($notice)?></p>
        <div class="table-responsive">
        <table class="table table-striped table-hover filelist">
            <thead>
                <tr>
                    <th>#</th>
                    <th>文件名</th>
                    <th>文件大小</th>
                    <th>文件格式</th>
                    <th>上传者IP</th>
                    <th>记录时间</th>
                </tr>
            </thead>
            <tbody>
<?php
$numrows=$DB->getColumn("SELECT count(*) from pre_violation WHERE{$sql}");
$pagesize=20;
$pages=ceil($numrows/$pagesize);
$page=isset($_GET['page'])?intval($_GET['page']):1;
if($page<1)$page=1;
$offset=$pagesize*($page - 1);

$rs=$DB->query("SELECT * FROM pre_violation WHERE{$sql} ORDER BY id DESC LIMIT $offset,$pagesize");
$i=$offset+1;
while($res = $rs->fetch())
{
    //公示只输出脱敏后的信息，不输出 token 和任何可访问链接，避免公示页反过来变成违规内容的索引
    $type_text = $res['type'] ? $res['type'] : '未知';
    $remark = $res['remark'] ? '<br/><small class="text-muted">'.htmlspecialchars($res['remark']).'</small>' : '';
    echo '<tr><td><b>'.$i++.'</b></td><td><i class="fa '.type_to_icon($res['type']).' fa-fw"></i>'.htmlspecialchars(violation_mask_name($res['name'])).$remark.'</td><td>'.size_format($res['size']).'</td><td><span class="file-type-badge">'.htmlspecialchars($type_text).'</span></td><td>'.htmlspecialchars(violation_mask_ip($res['ip'])).'</td><td>'.$res['addtime'].'</td></tr>';
}
if($numrows == 0) echo '<tr><td colspan="6" align="center">暂无违规封禁记录</td></tr>';
?>
            </tbody>
        </table>
        </div>
        <div class="filelist-footer">
        <div class="filelist-summary">共公示 <?php echo $numrows?> 条记录&nbsp;&nbsp;当前第 <?php echo $page?> 页，共 <?php echo $pages?> 页</div>
        <nav class="filelist-pager">
  <ul class="pagination pagination-sm">
<?php
$first=1;
$prev=$page-1;
$next=$page+1;
$last=$pages;
if ($page>1)
{
echo '<li><a href="violation.php?page='.$first.$link.'">首页</a></li>';
echo '<li><a href="violation.php?page='.$prev.$link.'">&laquo;</a></li>';
} else {
echo '<li class="disabled"><a>首页</a></li>';
echo '<li class="disabled"><a>&laquo;</a></li>';
}
$pagegroup = 10;
$start = $page < $pagegroup ? 1 : floor($page / $pagegroup) * $pagegroup;
$end = min($start + $pagegroup, $pages);
for ($i=$start;$i<=$end;$i++){
	if($i == $page){
		echo '<li class="disabled"><a>'.$i.'</a></li>';
	}else{
		echo '<li><a href="violation.php?page='.$i.$link.'">'.$i.'</a></li>';
	}
}
if ($page<$pages)
{
echo '<li><a href="violation.php?page='.$next.$link.'">&raquo;</a></li>';
echo '<li><a href="violation.php?page='.$last.$link.'">尾页</a></li>';
} else {
echo '<li class="disabled"><a>&raquo;</a></li>';
echo '<li class="disabled"><a>尾页</a></li>';
}
?>
  </ul>
</nav>
</div>
    </div>
<?php include SYSTEM_ROOT.'footer.php';?>
</body>
</html>
