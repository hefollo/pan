<?php
/**
 * 文件管理
**/
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

function display_status($status, $id){
	if($status == 2){
		return '<a href="javascript:setBlock('.$id.',0)" class="btn btn-xs btn-warning">待审</a>';
	}elseif($status == 1){
		return '<a href="javascript:setBlock('.$id.',0)" class="btn btn-xs btn-danger">封禁</a>';
	}else{
		return '<a href="javascript:setBlock('.$id.',1)" class="btn btn-xs btn-success">正常</a>';
	}
}

if (isset($_GET['dstatus']) && $_GET['dstatus']>0) {
	if($_GET['dstatus']==3){
		$sqls = " AND `block`=2";
		$links = "&dstatus=".$_GET['dstatus'];
	}elseif($_GET['dstatus']==2){
		$sqls = " AND `block`=1";
		$links = "&dstatus=".$_GET['dstatus'];
	}elseif($_GET['dstatus']==1){
		$sqls = " AND `block`=0";
		$links = "&dstatus=".$_GET['dstatus'];
	}
}

if(isset($_GET['kw']) && !empty($_GET['kw'])) {
	$type = intval($_GET['type']);
	//搜索词分两种用途：入SQL的转义版和回显/进URL的原始版，混用就是漏洞。
	//原来 $kw 只过了 addslashes（那是给 SQL 用的）却直接回显，
	//给管理员发一条带 kw=<script> 的链接就能在后台上下文执行脚本
	$kw_raw = trim((string)$_GET['kw']);
	$kw = daddslashes($kw_raw);
	if($type == 2){
		$sql=" `hash`='{$kw}'";
	}elseif($type == 3){
		$sql=" `type`='{$kw}'";
	}elseif($type == 4){
		$sql=" `ip`='{$kw}'";
	}else{
		//type 不在 1~4 时原来 $sql 是未定义的，拼出来的 SQL 会直接报错让整页崩掉
		$type = 1;
		$sql=" `name` LIKE '%{$kw}%'";
	}
	$sql.=$sqls;
	$numrows=$DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
	$con='包含 '.htmlspecialchars($kw_raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').' 的共有 <b>'.$numrows.'</b> 个记录';
	$link='&type='.$type.'&kw='.urlencode($kw_raw).$links;
}else{
	$sql=" 1".$sqls;
	$numrows=$DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
	$con='系统共有 <b>'.$numrows.'</b> 条记录';
	$link=$links;
}

?>
<?php echo $con?>
<form name="form1" id="form1">
	  <div class="table-responsive">
        <table class="table table-striped table-vcenter table-hover">
		  <thead><tr><th>ID</th><th>文件名</th><th>文件大小</th><th>文件格式</th><th>上传日期/上次下载</th><th>上传IP/下载量</th><th>状态</th><th>操作</th></tr></thead>
          <tbody>
<?php
$pagesize=10000;
$pages=ceil($numrows/$pagesize);
$page=isset($_GET['page'])?intval($_GET['page']):1;
$offset=$pagesize*($page - 1);

$rs=$DB->query("SELECT * FROM pre_file WHERE{$sql} order by id desc limit $offset,$pagesize");
while($res = $rs->fetch())
{
	$pwd_ext1='';$pwd_ext2='';
	if(!empty($res['pwd'])){
		$pwd_ext1='&'.$res['pwd'];
		$pwd_ext2='&pwd='.$res['pwd'];
	}
	$fileurl = './down.php/'.$res['token'].'.'.($res['type']?$res['type']:'file').$pwd_ext1;
	$viewurl = '../file.php?hash='.$res['token'].$pwd_ext2;
echo '<tr><td><input type="checkbox" name="checkbox[]" id="list1" value="'.$res['id'].'" onClick="unselectall1()"><b>'.$res['id'].'</b></td><td><a href="'.$fileurl.'" title="点击下载"><i class="fa '.type_to_icon($res['type']).' fa-fw"></i>'.$res['name'].'</a>'.(is_view($res['type'])?' [<a href="javascript:showfile('.$res['id'].')">预览</a>]':null).'</td><td>'.size_format($res['size']).'</td><td><font color="blue">'.($res['type']?$res['type']:'未知').'</font></td><td>'.$res['addtime'].'<br/>'.$res['lasttime'].'</td><td><a href="https://m.ip138.com/iplookup.asp?ip='.$res['ip'].'" target="_blank" rel="noreferrer">'.$res['ip'].'</a><br/><b>'.$res['count'].'</b></td><td>'.display_status($res['block'], $res['id']).'</td><td><a href="javascript:editframe('.$res['id'].')" class="btn btn-xs btn-info">编辑</a>&nbsp;<a href="'.$viewurl.'" class="btn btn-xs btn-warning" target="_blank">查看</a>&nbsp;<a href="javascript:delFile('.$res['id'].')" class="btn btn-xs btn-danger">删除</a></td></tr>';
}
?>
          </tbody>
        </table>
		<label class="checkbox-inline"><input name="chkAll1" type="checkbox" id="chkAll1" onClick="this.value=check1(this.form.list1)" value="checkbox">全选</label>&nbsp;&nbsp;<button type="button" onclick="operation()">删除选中</button>
      </div>
	  </form>
<?php
echo'<div class="text-center"><ul class="pagination">';
$first=1;
$prev=$page-1;
$next=$page+1;
$last=$pages;
if ($page>1)
{
echo '<li><a href="javascript:void(0)" onclick="listTable(\'page='.$first.$link.'\')">首页</a></li>';
echo '<li><a href="javascript:void(0)" onclick="listTable(\'page='.$prev.$link.'\')">&laquo;</a></li>';
} else {
echo '<li class="disabled"><a>首页</a></li>';
echo '<li class="disabled"><a>&laquo;</a></li>';
}
$start=$page-10>1?$page-10:1;
$end=$page+10<$pages?$page+10:$pages;
for ($i=$start;$i<$page;$i++)
echo '<li><a href="javascript:void(0)" onclick="listTable(\'page='.$i.$link.'\')">'.$i .'</a></li>';
echo '<li class="disabled"><a>'.$page.'</a></li>';
for ($i=$page+1;$i<=$end;$i++)
echo '<li><a href="javascript:void(0)" onclick="listTable(\'page='.$i.$link.'\')">'.$i .'</a></li>';
if ($page<$pages)
{
echo '<li><a href="javascript:void(0)" onclick="listTable(\'page='.$next.$link.'\')">&raquo;</a></li>';
echo '<li><a href="javascript:void(0)" onclick="listTable(\'page='.$last.$link.'\')">尾页</a></li>';
} else {
echo '<li class="disabled"><a>&raquo;</a></li>';
echo '<li class="disabled"><a>尾页</a></li>';
}
echo'</ul></div>';
