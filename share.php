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
function share_name($name){
    // 原有文件名入库时已做实体编码，输出时先还原再统一转义，避免双重编码。
    return folder_h(html_entity_decode($name, ENT_QUOTES, 'UTF-8'));
}

$token = folder_share_input($_GET, 'token');
$ctx = folder_share_resolve($token);
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
if(!$partial){
?><!doctype html>
<html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo folder_h($title)?> · 文件夹分享</title><link rel="stylesheet" href="./assets/css/folder-share.css?v=<?php echo VERSION?>"><script src="./assets/js/folder-share.js?v=<?php echo VERSION?>" defer></script></head><body>
<header class="share-header"><a href="./">文件分享</a><span>只读访问</span></header>
<?php } ?>
<main id="share-main" data-title="<?php echo folder_h($title)?>">
<?php if($view === 'error'){ ?>
    <section class="share-card share-message"><h1>暂时无法访问</h1><p><?php echo folder_h($error)?></p><a class="share-button" href="./">返回首页</a></section>
<?php }elseif($view === 'unlock' || $view === 'unlock_file'){ ?>
    <section class="share-card share-message"><span class="share-eyebrow">受密码保护的分享</span><h1><?php echo $view === 'unlock_file' ? '请输入文件密码' : '请输入分享密码'?></h1><p><?php echo $view === 'unlock_file' ? '该文件另外设置了密码，请向分享者获取。' : '输入分享者提供的密码后，即可浏览文件夹。'?></p>
    <?php if($error){ ?><p class="share-error" role="alert"><?php echo folder_h($error)?></p><?php } ?>
    <form method="post" action="<?php echo folder_h(share_url($file_id > 0 ? ['file'=>$file_id] : []))?>"><input type="hidden" name="csrf_token" value="<?php echo folder_h($csrf)?>"><label for="share-password">访问密码</label><input id="share-password" type="password" name="password" required maxlength="255" autocomplete="current-password" autofocus><button class="share-button primary" type="submit">解锁查看</button></form>
    <?php if($view === 'unlock_file'){ ?><a data-share-nav href="<?php echo folder_h(share_url())?>">返回文件夹</a><?php } ?></section>
<?php }else{ ?>
    <section class="share-heading"><span class="share-eyebrow">文件夹分享</span><h1><?php echo folder_h($title)?></h1><p>内容随文件夹实时更新<?php echo $ctx['share']['expiretime'] ? ' · 有效期至 '.folder_h($ctx['share']['expiretime']) : ''?></p></section>
    <?php if($view === 'file'){ ?>
    <nav class="share-breadcrumb"><a data-share-nav href="<?php echo folder_h(share_url(['folder'=>$file['folder_id']]))?>">返回所在文件夹</a></nav>
    <section class="share-card"><div class="share-file-heading"><div><h2><?php echo share_name($file['name'])?></h2><p><?php echo folder_h(size_format($file['size']).' · '.$file['addtime'])?></p></div><a class="share-button primary" href="<?php echo folder_h(share_url(['file'=>$file_id, 'action'=>'down']))?>">下载文件</a></div>
    <?php
    $type = strtolower($file['type']);
    $src = folder_h(share_url(['file'=>$file_id, 'action'=>'view']));
    if(!is_scriptable_file_type($type) && in_array($type, ['jpg','jpeg','png','gif','webp','avif','bmp','ico'], true)){
        echo '<img class="share-preview" src="'.$src.'" alt="'.share_name($file['name']).'">';
    }elseif(in_array($type, ['mp4','webm','ogg','mov','m4v','mp3','wav','m4a','flac','aac'], true)){
        $tag = in_array($type, ['mp3','wav','m4a','flac','aac'], true) ? 'audio' : 'video';
        echo '<'.$tag.' class="share-preview" controls preload="metadata" src="'.$src.'"></'.$tag.'><p class="share-note">若浏览器不支持此格式，请下载后播放。</p>';
    }elseif(is_editable_file_type($type) && intval($file['size']) <= get_editable_file_max_size()){
        echo '<iframe class="share-text" sandbox title="文本预览" src="'.folder_h(share_url(['file'=>$file_id, 'action'=>'text'])).'"></iframe>';
    }elseif($type === 'pdf'){
        echo '<iframe class="share-text" title="PDF 预览" src="'.$src.'"></iframe>';
    }else echo '<div class="share-empty">此格式暂不支持在线预览，可下载后查看。</div>';
    ?></section>
    <?php }else{
        $state = ['folder'=>$listing['current'], 'mode'=>$listing['mode'], 'q'=>$listing['q'], 'type'=>$listing['type'], 'sort'=>$listing['sort']];
    ?>
    <section class="share-card">
    <div class="share-controls"><div class="share-tabs"><a data-share-nav class="<?php echo $listing['mode'] === 'folder' ? 'active' : ''?>" href="<?php echo folder_h(share_url(['folder'=>$listing['current']]))?>">按目录浏览</a><a data-share-nav class="<?php echo $listing['mode'] === 'all' ? 'active' : ''?>" href="<?php echo folder_h(share_url(array_merge($state, ['mode'=>'all', 'folder'=>$ctx['root']])))?>">全部文件</a></div><span class="share-note">共 <?php echo $listing['total']?> 个文件<?php if($listing['folders'])echo ' · '.count($listing['folders']).' 个子文件夹';?></span></div>
    <form class="share-search" method="get" action="./share.php" data-share-search><input type="hidden" name="token" value="<?php echo folder_h($token)?>"><input type="hidden" name="folder" value="<?php echo $ctx['root']?>"><input type="hidden" name="mode" value="all"><input name="q" aria-label="搜索分享内全部文件" placeholder="搜索分享内全部文件" maxlength="100" value="<?php echo folder_h($listing['q'])?>"><select name="type" aria-label="文件类型"><?php foreach(layout_type_filters() as $key=>$label){ ?><option value="<?php echo folder_h($key)?>"<?php echo $key === $listing['type'] ? ' selected' : ''?>><?php echo folder_h($label)?></option><?php } ?></select><select name="sort" aria-label="排序"><?php foreach(['newest'=>'最新上传','oldest'=>'最早上传','name'=>'名称排序','size'=>'大小排序'] as $key=>$label){ ?><option value="<?php echo $key?>"<?php echo $key === $listing['sort'] ? ' selected' : ''?>><?php echo $label?></option><?php } ?></select><button class="share-button" type="submit">搜索全部文件</button><?php if($listing['q'] !== '' || $listing['type'] !== ''){ ?><a data-share-nav class="share-button" href="<?php echo folder_h(share_url(['mode'=>'all','folder'=>$ctx['root']]))?>">清除筛选</a><?php } ?></form>
    <nav class="share-breadcrumb" aria-label="分享内路径"><?php if($listing['recursive']){echo '分享内全部文件';}else{ foreach(folder_path($ctx['all'], $listing['current']) as $i=>$f){ if($i)echo '<span>/</span>'; ?><a data-share-nav href="<?php echo folder_h(share_url(['folder'=>$f['id']]))?>"><?php echo folder_h($f['name'])?></a><?php }} ?></nav>
    <div class="share-table-wrap"><table class="share-table"><thead><tr><th>名称</th><th>大小</th><th>上传时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach($listing['folders'] as $f){ ?><tr><td><a data-share-nav class="share-entry" href="<?php echo folder_h(share_url(['folder'=>$f['id']]))?>"><span class="share-folder-icon" aria-hidden="true">▰</span><?php echo folder_h($f['name'])?></a></td><td>文件夹</td><td>—</td><td><a data-share-nav href="<?php echo folder_h(share_url(['folder'=>$f['id']]))?>">打开</a></td></tr><?php } ?>
    <?php foreach($listing['files'] as $f){ $link = folder_h(share_url(['file'=>$f['id']])); ?><tr><td><a data-share-nav class="share-entry" href="<?php echo $link?>"><span class="share-file-icon" aria-hidden="true">▤</span><?php echo share_name($f['name'])?><?php if($f['locked'])echo '<small class="share-lock">有密码</small>';?></a><?php if($listing['recursive']){ $names = array_column(folder_path($ctx['all'], intval($f['folder_id'])), 'name'); array_shift($names); ?><span class="share-path"><?php echo folder_h($names ? implode(' / ', $names) : '分享根目录')?></span><?php } ?></td><td><?php echo folder_h(size_format($f['size']))?></td><td><?php echo folder_h($f['addtime'])?></td><td><a data-share-nav href="<?php echo $link?>">查看</a></td></tr><?php } ?>
    </tbody></table></div>
    <?php if(!$listing['files'] && !$listing['folders']){ ?><div class="share-empty"><?php echo $listing['q'] !== '' || $listing['type'] !== '' ? '未找到符合条件的文件' : '文件夹暂时为空'?></div><?php } ?>
    <nav class="share-pagination" aria-label="分页"><?php if($listing['page'] > 1){ ?><a data-share-nav class="share-button" href="<?php echo folder_h(share_url(array_merge($state, ['page'=>$listing['page']-1])))?>">上一页</a><?php } ?><span>第 <?php echo $listing['page']?> / <?php echo $listing['pages']?> 页</span><?php if($listing['page'] < $listing['pages']){ ?><a data-share-nav class="share-button" href="<?php echo folder_h(share_url(array_merge($state, ['page'=>$listing['page']+1])))?>">下一页</a><?php } ?></nav>
    </section>
    <?php } ?>
<?php } ?>
</main>
<?php if(!$partial){ ?><footer class="share-footer">文件由分享者提供 · 请妥善保管分享链接</footer></body></html><?php } ?>
