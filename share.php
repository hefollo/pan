<?php
$nosecu = true;
require './includes/common.php';
require_once SYSTEM_ROOT.'layout_blocks.php';

header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");

function share_url($args = []){
    global $token;
    return './share.php?'.http_build_query(array_merge(['token'=>$token], $args), '', '&', PHP_QUERY_RFC3986);
}
function share_icon($name){
    $paths = [
        'download'=>'M12 3v12m-4-4 4 4 4-4M5 16v5h14v-5',
        'view'=>'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12 M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'copy'=>'m10 13 4-4 M8 16l-1 1a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0 M12 18a4 4 0 0 0 6 0l5-5a4 4 0 0 0-6-6l-1 1',
        'rename'=>'m4 16-1 5 5-1L20 8l-4-4L4 16 M13 7l4 4',
        'pwd'=>'M13 8a5 5 0 1 1-10 0 5 5 0 0 1 10 0 M12 12l9 9 M16 16l3-3 M19 19l3-3',
        'hide'=>'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 M3 12h18 M12 3c-5 5-5 13 0 18 5-5 5-13 0-18',
        'replace'=>'M20 7a9 9 0 0 0-15-1L2 9 M2 3v6h6 M4 17a9 9 0 0 0 15 1l3-3 M22 21v-6h-6',
        'del'=>'M3 6h18 M9 6V3h6v3 M6 6l1 15h10l1-15 M10 10v7 M14 10v7',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="'.$paths[$name].'"/></svg>';
}
function share_actions($f){
    global $owner;
    $id = intval($f['id']);
    $url = folder_h(share_url(['file'=>$id]));
    echo '<div class="share-actions" data-file-id="'.$id.'" data-name="'.share_name($f['name']).'" data-hide="'.intval($f['hide']).'">';
    echo '<a class="share-act" href="'.folder_h(share_url(['file'=>$id,'action'=>'down'])).'" title="下载" aria-label="下载">'.share_icon('download').'</a>';
    echo '<a class="share-act" data-share-nav href="'.$url.'" title="查看" aria-label="查看">'.share_icon('view').'</a>';
    echo '<button class="share-act" type="button" data-share-action="copy" data-url="'.$url.'" title="复制链接" aria-label="复制链接">'.share_icon('copy').'</button>';
    if($owner){
        foreach(['rename'=>['✎','重命名'], 'pwd'=>['⚿','访问密码'], 'hide'=>['◌',intval($f['hide']) ? '设为公开' : '设为私密'], 'replace'=>['⟳','重新上传替换'], 'del'=>['×','删除']] as $action=>$label){
            echo '<button class="share-act'.($action === 'del' ? ' danger' : '').'" type="button" data-share-action="'.$action.'" title="'.$label[1].'" aria-label="'.$label[1].'">'.share_icon($action).'</button>';
        }
    }
    echo '</div>';
}
function share_name($name){
    // 原有文件名入库时已做实体编码，输出时先还原再统一转义，避免双重编码。
    return folder_h(html_entity_decode($name, ENT_QUOTES, 'UTF-8'));
}

$token = folder_share_input($_GET, 'token');
$ctx = folder_share_resolve($token);
$owner = folder_share_is_owner($ctx);
if($owner){
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://s4.zstatic.net; style-src 'self'; connect-src 'self' https:; img-src 'self' data:; media-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");
}
$manage_csrf = $owner ? (isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : page_csrf_token()) : '';
$file = null;
$listing = null;
$error = '';
$view = 'list';
$action = folder_share_input($_GET, 'action');
$file_id = intval(folder_share_input($_GET, 'file', '0'));
$content_request = in_array($action, ['view','down','text'], true);
if(!$ctx){
    $error = '分享不存在、已过期或已取消。';
    $view = 'error';
    http_response_code(404);
}else{
    if(empty($_SESSION['folder_share_csrf']))$_SESSION['folder_share_csrf'] = bin2hex(random_bytes(16));
    $csrf = $_SESSION['folder_share_csrf'];
    if(!folder_share_unlocked($ctx))$view = 'unlock';
    elseif($file_id > 0){
        $file = folder_share_file($ctx, $file_id);
        if(!$file){ $view = 'error'; $error = '文件已移出分享、被删除或暂不可访问。'; http_response_code(404); }
        elseif(!folder_share_unlocked($ctx, $file))$view = 'unlock_file';
        else $view = 'file';
    }elseif($content_request){
        $view = 'error'; $error = '文件不存在。'; http_response_code(404);
    }
    if($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($view, ['unlock','unlock_file'], true)){
        // 分享密码与单文件密码分别授权，修改密码或重置链接后旧授权自然失效。
        $attempts = isset($_SESSION['folder_share_attempts']) ? $_SESSION['folder_share_attempts'] : ['time'=>0, 'count'=>0];
        if($attempts['time'] + 60 <= time())$attempts = ['time'=>time(), 'count'=>0];
        if(!hash_equals($csrf, folder_share_input($_POST, 'csrf_token'))){
            $error = '页面验证已失效，请刷新重试。'; http_response_code(403);
        }elseif($attempts['count'] >= 10){
            $error = '尝试次数过多，请一分钟后重试。'; http_response_code(429);
        }else{
            $attempts['count']++;
            $_SESSION['folder_share_attempts'] = $attempts;
            if(folder_share_unlock($ctx, folder_share_input($_POST, 'password'), $view === 'unlock_file' ? $file : null)){
                header('Location: '.share_url($file_id > 0 ? ['file'=>$file_id] : []), true, 303);
                exit;
            }
            $error = '密码不正确，请重试。';
        }
    }
    if($content_request){
        if($view !== 'file'){
            if($view !== 'error')http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            exit($error ?: '请先在分享页面解锁文件。');
        }
        // 长时间传输前释放会话锁，播放/下载不能堵住同浏览器的其它页面。
        session_write_close();
        if(!\lib\StorHelper::get($file['storage'])->exists($file['hash'])){
            http_response_code(404); exit('文件内容不存在。');
        }
        if($action === 'text'){
            if(!is_editable_file_type($file['type']) || intval($file['size']) > get_editable_file_max_size()){
                http_response_code(415); exit('此文件不支持文本预览，请下载查看。');
            }
            $content = get_storage_content($file['hash'], $file['storage']);
            if($content === false){ http_response_code(502); exit('读取文件失败。'); }
            $decoded = decode_editable_content($content);
            if($decoded['code'] != 0){ http_response_code(415); exit('此文件无法作为文本预览。'); }
            //文本预览不经过 file_output()，分享流量在这里单独拦和记
            file_traffic_guard($file['uid']);
            header('Content-Type: text/plain; charset=UTF-8');
            start_download_throttle(file_download_speed_kbps($file['uid']));
            download_output_chunk($decoded['content']);
        }else{
            if($action === 'down')$DB->exec('UPDATE pre_file SET lasttime=NOW(),count=count+1 WHERE id=:id', [':id'=>$file['id']]);
            file_output($file['hash'], $file['type'], $file['size'], $file['name'], $action === 'view', false, $file['storage'], $file['uid'], true);
        }
        exit;
    }
    if($view === 'list'){
        try{ $listing = folder_share_listing($ctx, $_GET); }
        catch(RuntimeException $e){ http_response_code(503); $error = '暂时无法读取分享，请稍后重试。'; }
        if(!$listing){ $view = 'error'; if(!$error){ http_response_code(404); $error = '文件夹不在此分享范围内。'; } }
    }
}
if($content_request){ header('Content-Type: text/plain; charset=UTF-8'); exit($error); }
$title = $ctx && $view !== 'unlock' ? $ctx['all'][$ctx['root']]['name'] : '文件夹分享';
$partial = isset($_SERVER['HTTP_X_PAN_SHARE']) && $_SERVER['HTTP_X_PAN_SHARE'] === '1';
/*
 * 分享页是独立页面（不走站点外壳）：文档头尾是主题可覆盖的固定模板 share/document_open、document_close，
 * 中间 <main> 里是布局 share（includes/layouts/share.json，卡片在 includes/blocks/share/）。
 * <main id="share-main"> 留在这里不进卡片：folder-share.js 局部刷新时只要 <main> 里的内容（X-Pan-Share 请求）。
 */
$share_theme = theme_active_id();
if(!$partial){
    echo theme_tpl_render('share/document_open', [
        'title' => $title,
        'version' => VERSION,
        'owner' => $owner,
        //页面的 CSP 只许本站样式表，主题包的样式表在本站，可以加
        'theme_styles' => theme_asset_list($share_theme, 'styles'),
    ], $share_theme);
}
?>
<main data-manage-csrf="<?php echo folder_h($manage_csrf)?>" id="share-main" data-title="<?php echo folder_h($title)?>">
<?php echo theme_render_layout('share', [
    'view' => $view,
    'error' => $error,
    'ctx' => $ctx,
    'file' => $file,
    'file_id' => $file_id,
    'listing' => $listing,
    'title' => $title,
    'token' => $token,
    'csrf' => isset($csrf) ? $csrf : '',
], 'front', $share_theme);?>
</main>
<?php if(!$partial){ echo theme_tpl_render('share/document_close', [], $share_theme); } ?>
