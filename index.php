<?php
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('require PHP >= 7.4 !');
}
include("./includes/common.php");

//后台把某个自定义页面设成了网站入口：不带任何参数打开 / 显示那个页面；文件列表走 ./?list=1 和带参数的链接（DEC-20261026-001）
if(empty($_GET) && ($home_slug = home_page_slug()) !== ''){
    $custom_page = custom_page_get($home_slug);
    include __DIR__.'/page.php';
    exit;
}

//游客「我的文件」局部刷新取页面时沿用现有令牌，见 page_csrf_token()
$csrf_token = page_csrf_token();

//老的 ?m=mine 链接（书签、外部引用）继续可用：已登录的转去个人中心，
//那边才有重命名/删除/公开私密这些管理操作；游客没有账号，留在这里看浏览器缓存记录
if(isset($_GET['m']) && $_GET['m']=='mine' && $islogin2){
    header('Location: ./user.php?tab=files');
    exit;
}
if(isset($_GET['m']) && $_GET['m']=='mine'){
    $title = '我的文件 - ' . $conf['title'];
    $htext = '我上传的文件';
    if($islogin2){
        $sql = " uid='{$uid}'";
    }else{
        if($conf['userlogin']==1){
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录，<a href="login.php">登录</a>后可永久保留记录）</span>';
        }else{
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录）</span>';
        }
        if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
            $ids = array_reverse($_SESSION['fileids']);
            if(count($ids) > 60){
                $ids = array_splice($ids, 0, 60);
            }
            $ids = implode(',',$ids);
            $sql = " id IN ($ids)";
        }else{
            $sql = " 1=2";
        }
    }
    $link = '&m=mine';
}else{
    //首页的标签标题带上后台填的副标题（没填就只有网站标题）
    $title = site_home_title();
    $htext = '文件列表';
    //公共列表只列正常状态：封禁（block=1）和待审核（block=2）的都不上首页。
    //统计卡、筛选计数、占用空间、最近上传都用这个条件（$sql_base），会跟着一起排除
    $sql = " hide=0 AND block=0";
    $link = '';
}
//搜索词要分三种用途保存：入SQL的转义版、进URL的编码版、进HTML的实体版，混用会出漏洞
$kw = (isset($_GET['kw']) && is_string($_GET['kw']))?trim(strip_tags($_GET['kw'])):null;
if($conf['filesearch']==1 && $kw){
    $kw_sql = daddslashes($kw);
    $sql.=" AND name LIKE '%{$kw_sql}%'";
    $link .= '&kw='.urlencode($kw);
}

include_once SYSTEM_ROOT.'layout_blocks.php';
//类型筛选（数据控制台风/深色工作台风的筛选标签）；$sql_base 不带类型条件，给标签上的计数用
$sql_base = $sql;
$ft = (isset($_GET['ft']) && is_string($_GET['ft']) && array_key_exists($_GET['ft'], layout_type_filters())) ? $_GET['ft'] : '';
if($ft !== ''){
    $sql .= layout_type_filter_sql($ft);
    $link .= '&ft='.urlencode($ft);
}
//排序：蓝白工作台风的列表上方有排序下拉，其它外观没有入口但参数照样认。
//只认白名单里的四种，直接拼进 ORDER BY 的字符串全部是常量
$sort_map = ['new'=>'id DESC', 'old'=>'id ASC', 'big'=>'size DESC', 'small'=>'size ASC'];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sort_map[$_GET['sort']])) ? $_GET['sort'] : 'new';
$order_sql = $sort_map[$sort];
if($sort !== 'new'){
    $link .= '&sort='.$sort;
}
//游客「我的文件」的文件夹（登录用户在文件开头已经跳去个人中心了）。
//搜索或按类型筛选时跨全部文件夹查、结果里标出所在文件夹；否则只列当前这一层
$fd_on = isset($_GET['m']) && $_GET['m'] === 'mine' && !$islogin2 && can_use_folders();
$fd_all = $fd_on ? folder_all(folder_owner(false)) : [];
$fd_cur = $fd_on ? folder_current($fd_all) : 0;
$fd_searching = $fd_on && (($conf['filesearch']==1 && $kw) || $ft !== '');
$fd_url = './?m=mine';
if($fd_on && !$fd_searching){
    $sql .= " AND folder_id=".$fd_cur;
    if($fd_cur > 0) $link .= '&folder='.$fd_cur;
}

//script_manager.php 文件开头带 BOM，旧首页一直在页头之前引入它（页面最前面因此有个 BOM），位置保持不动
include_once SYSTEM_ROOT.'script_manager.php';
include SYSTEM_ROOT.'header.php';
/*
 * 页面内容是布局 index（DEC-20261025-003）：系统默认在 includes/layouts/index.json，
 * 各外观自己的在 themes/<外观>/layouts/index.json（工作台家族共用 includes/layouts/index@studio.json），
 * 站长在后台改过的存在 pre_layout。卡片在 includes/blocks/{common,index,cockpit,studio}/ 下，
 * 这里只把上面算好的列表条件交给它们。
 */
echo theme_render_layout('index', [
    'sql' => $sql,
    'sql_base' => $sql_base,
    'order_sql' => $order_sql,
    'link' => $link,
    'kw' => $kw,
    'ft' => $ft,
    'sort' => $sort,
    //标题在旧代码里是原样输出的（游客「我的文件」后面带一段说明标记）
    'htext_html' => $htext,
    'is_mine' => isset($_GET['m']) && $_GET['m'] === 'mine',
    //游客的「我的文件」用个人中心那种表格（勾选、批量删除、文件夹）；登录用户在文件开头已经跳去个人中心了
    'guest_mine' => isset($_GET['m']) && $_GET['m'] === 'mine' && !$islogin2,
    'fd_on' => $fd_on,
    'fd_all' => $fd_all,
    'fd_cur' => $fd_cur,
    'fd_searching' => $fd_searching,
    'fd_url' => $fd_url,
    'csrf_token' => $csrf_token,
], 'front', $site_theme);
include SYSTEM_ROOT.'footer.php';
//卡片登记的页面末尾脚本（工作台预览、macOS 视图切换、门户首屏上传、「我的文件」的操作）
echo theme_deferred_html();
/* 原来这里有个「首页公告」弹窗（snackbar），站长要求删掉，后台入口也一并去掉了；站点公告请用顶部的公告条 */
?></body>
</html>
