<?php
// 后台只读视图独立于用户文件夹权限，管理员仍可检查关闭功能后的历史目录。
function admin_folder_share_state($row, $folder_open){
    if(empty($row['share_id']))return 'none';
    if(empty($row['enabled']))return 'revoked';
    if(!empty($row['expiretime']) && strtotime($row['expiretime']) <= time())return 'expired';
    if(!$folder_open || empty($row['user_enable']))return 'unavailable';
    return 'active';
}

function admin_folder_state_labels(){
    return ['none'=>'未单独分享', 'active'=>'分享中', 'revoked'=>'已取消', 'expired'=>'已过期', 'unavailable'=>'暂不可访问'];
}

function admin_folder_listing($input){
    global $DB, $islogin, $conf;
    if(empty($islogin) || intval($islogin) !== 1)throw new RuntimeException('需要管理员登录');
    $uid = max(0, intval(folder_share_input($input, 'uid', '0')));
    $q = mb_substr(trim(folder_share_input($input, 'q')), 0, 100, 'UTF-8');
    $status = folder_share_input($input, 'status');
    if(!isset(admin_folder_state_labels()[$status]))$status = '';
    $folder = max(0, intval(folder_share_input($input, 'folder', '0')));
    $current = null;
    $breadcrumbs = [];
    if($folder){
        $current = $DB->getRow("SELECT f.id,f.uid,f.parent_id,f.name,u.nickname,u.enable AS user_enable,s.id AS share_id,s.enabled,s.expiretime,(s.password_hash IS NOT NULL AND s.password_hash<>'') AS has_password FROM pre_folder f LEFT JOIN pre_user u ON u.uid=f.uid LEFT JOIN pre_folder_share s ON s.folder_id=f.id AND s.uid=f.uid WHERE f.id=:folder AND f.uid>0 AND f.gkey=''", [':folder'=>$folder]);
        if(!$current || ($uid && $uid !== intval($current['uid'])))throw new InvalidArgumentException('文件夹不存在或不属于指定用户');
        $uid = intval($current['uid']);
        $breadcrumbs = folder_path(folder_all(['uid'=>$uid, 'gkey'=>'']), $folder);
        $current['share_state'] = admin_folder_share_state($current, !empty($conf['folder_open']));
        $current['inherited'] = [];
        $shares = $DB->getAll('SELECT folder_id,id AS share_id,enabled,expiretime FROM pre_folder_share WHERE uid=:uid', [':uid'=>$uid]);
        if($shares === false)throw new RuntimeException('读取当前目录分享失败');
        $active = [];
        foreach($shares as $share){
            $share['user_enable'] = $current['user_enable'];
            if(admin_folder_share_state($share, !empty($conf['folder_open'])) === 'active')$active[intval($share['folder_id'])] = true;
        }
        foreach($breadcrumbs as $crumb){
            if(intval($crumb['id']) !== $folder && isset($active[intval($crumb['id'])]))$current['inherited'][] = $crumb['name'];
        }
    }
    $where = "f.uid>0 AND f.gkey='' AND f.parent_id=:parent";
    $params = [':parent'=>$folder];
    if($uid){ $where .= ' AND f.uid=:uid'; $params[':uid'] = $uid; }
    if($q !== ''){
        $where .= " AND f.name LIKE :name ESCAPE '='";
        $params[':name'] = '%'.str_replace(['=','%','_'], ['==','=%','=_'], $q).'%';
    }
    $state_sql = "CASE WHEN s.id IS NULL THEN 'none' WHEN s.enabled=0 THEN 'revoked' WHEN s.expiretime IS NOT NULL AND s.expiretime<=NOW() THEN 'expired' WHEN ".(empty($conf['folder_open']) ? '1=1' : '0=1')." OR u.enable IS NULL OR u.enable=0 THEN 'unavailable' ELSE 'active' END";
    if($status !== ''){ $where .= ' AND ('.$state_sql.')=:status'; $params[':status'] = $status; }
    $from = ' FROM pre_folder f LEFT JOIN pre_user u ON u.uid=f.uid LEFT JOIN pre_folder_share s ON s.folder_id=f.id AND s.uid=f.uid WHERE '.$where;
    $total = $DB->getColumn('SELECT COUNT(*)'.$from, $params);
    if($total === false)throw new RuntimeException('读取文件夹数量失败');
    $pages = max(1, (int)ceil($total / 40));
    $page = max(1, min($pages, intval(folder_share_input($input, 'page', '1'))));
    $rows = $DB->getAll('SELECT f.id,f.uid,f.name,f.parent_id,f.addtime,u.nickname,u.enable AS user_enable,s.id AS share_id,s.enabled,s.expiretime,s.token,(s.password_hash IS NOT NULL AND s.password_hash<>\'\') AS has_password,('.$state_sql.') AS share_state,(SELECT COUNT(*) FROM pre_file x WHERE x.folder_id=f.id AND x.uid=f.uid) AS file_count'.$from.' ORDER BY f.id DESC LIMIT '.(($page-1)*40).',40', $params);
    if($rows === false)throw new RuntimeException('读取文件夹列表失败');
    $owners = [];
    foreach($rows as &$row){
        $id = intval($row['uid']);
        if(!isset($owners[$id])){
            $all = folder_all(['uid'=>$id, 'gkey'=>'']);
            $shares = $DB->getAll('SELECT folder_id,id AS share_id,enabled,expiretime FROM pre_folder_share WHERE uid=:uid', [':uid'=>$id]);
            if($shares === false)throw new RuntimeException('读取父目录分享失败');
            $active = [];
            foreach($shares as $share){
                $share['user_enable'] = $row['user_enable'];
                if(admin_folder_share_state($share, !empty($conf['folder_open'])) === 'active')$active[intval($share['folder_id'])] = true;
            }
            $owners[$id] = ['all'=>$all, 'active'=>$active];
        }
        $path = folder_path($owners[$id]['all'], intval($row['id']));
        $row['path'] = implode(' / ', array_column($path, 'name'));
        $row['inherited'] = [];
        foreach($path as $ancestor){
            if(intval($ancestor['id']) !== intval($row['id']) && isset($owners[$id]['active'][intval($ancestor['id'])]))$row['inherited'][] = $ancestor['name'];
        }
    }
    unset($row);
    $files = [];
    $file_total = 0;
    $file_pages = 1;
    $file_page = 1;
    if($uid){
        $file_where = 'uid=:owner AND folder_id=:folder';
        $file_params = [':owner'=>$uid, ':folder'=>$folder];
        if($q !== ''){
            $file_where .= " AND name LIKE :name ESCAPE '='";
            $file_params[':name'] = '%'.str_replace(['=','%','_'], ['==','=%','=_'], htmlspecialchars($q, ENT_QUOTES, 'UTF-8')).'%';
        }
        $file_total = $DB->getColumn('SELECT COUNT(*) FROM pre_file WHERE '.$file_where, $file_params);
        if($file_total === false)throw new RuntimeException('读取目录文件数量失败');
        $file_pages = max(1, (int)ceil($file_total / 40));
        $file_page = max(1, min($file_pages, intval(folder_share_input($input, 'file_page', '1'))));
        $files = $DB->getAll('SELECT id,name,type,size,block,hide,addtime FROM pre_file WHERE '.$file_where.' ORDER BY id DESC LIMIT '.(($file_page-1)*40).',40', $file_params);
        if($files === false)throw new RuntimeException('读取目录文件失败');
    }
    $listing = ['rows'=>$rows, 'total'=>intval($total), 'page'=>$page, 'pages'=>$pages, 'uid'=>$uid, 'q'=>$q, 'status'=>$status,
        'folder'=>$folder, 'current'=>$current, 'breadcrumbs'=>$breadcrumbs, 'files'=>$files, 'file_total'=>intval($file_total), 'file_page'=>$file_page, 'file_pages'=>$file_pages];
    $listing['trail'] = admin_folder_trail($input, $listing);
    return $listing;
}

function admin_folder_url($args = []){
    return './folders.php?'.http_build_query($args, '', '&', PHP_QUERY_RFC3986);
}

// 导航仅保存本页白名单状态，不接受回跳URL；每次请求按真实目录树验证祖先链。
function admin_folder_view_state($input){
    $state = [];
    foreach(['uid','folder'] as $key)$state[$key] = max(0, intval(folder_share_input($input, $key, '0')));
    foreach(['page','file_page'] as $key)$state[$key] = max(1, intval(folder_share_input($input, $key, '1')));
    $state['q'] = mb_substr(folder_share_input($input, 'q'), 0, 100, 'UTF-8');
    $state['status'] = folder_share_input($input, 'status');
    if(!isset(admin_folder_state_labels()[$state['status']]))$state['status'] = '';
    return $state;
}

function admin_folder_trail($input, $listing){
    $raw = folder_share_input($input, 'nav');
    if((!$listing['folder'] && !$listing['uid']) || strlen($raw) > 12000)return [];
    $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
    $trail = $decoded === false ? null : json_decode($decoded, true);
    if(!is_array($trail) || count($trail) > FOLDER_DEPTH_MAX + 2)return [];
    $ancestors = [0];
    foreach($listing['breadcrumbs'] as $crumb){
        if(intval($crumb['id']) !== $listing['folder'])$ancestors[] = intval($crumb['id']);
    }
    $clean = []; $previous = -1;
    foreach($trail as $entry){
        if(!is_array($entry))return [];
        $state = admin_folder_view_state($entry);
        $position = array_search($state['folder'], $ancestors, true);
        // 全用户总览和单用户根目录是两个相邻的虚拟层级，folder都为0。
        $user_root = $position === 0 && $previous === 0 && count($clean) === 1 && $clean[0]['uid'] === 0 && $state['uid'] === $listing['uid'] && $state['uid'] > 0;
        if($position === false || ($position <= $previous && !$user_root))return [];
        if($state['uid'] !== $listing['uid'] && !($state['folder'] === 0 && $state['uid'] === 0))return [];
        $previous = $position;
        $clean[] = $state;
    }
    return $clean;
}

function admin_folder_nav_value($trail){
    return rtrim(strtr(base64_encode(json_encode($trail, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
}

function admin_folder_link($listing, $overrides = []){
    $state = array_merge(admin_folder_view_state($listing), $overrides);
    if($listing['trail'])$state['nav'] = admin_folder_nav_value($listing['trail']);
    return admin_folder_url($state);
}

function admin_folder_child_link($listing, $row){
    $trail = $listing['trail'];
    $trail[] = admin_folder_view_state($listing);
    return admin_folder_url(['uid'=>intval($row['uid']), 'folder'=>intval($row['id']), 'nav'=>admin_folder_nav_value($trail)]);
}

function admin_folder_owner_link($listing, $uid){
    return admin_folder_url(['uid'=>$uid, 'nav'=>admin_folder_nav_value([admin_folder_view_state($listing)])]);
}

function admin_folder_ancestor_link($listing, $folder, $uid){
    foreach($listing['trail'] as $i=>$state){
        if($state['folder'] === $folder && $state['uid'] === $uid){
            if($i)$state['nav'] = admin_folder_nav_value(array_slice($listing['trail'], 0, $i));
            return admin_folder_url($state);
        }
    }
    $trail = array_values(array_filter($listing['trail'], function($state)use($listing,$folder){
        if($folder === 0)return false;
        foreach($listing['breadcrumbs'] as $crumb){
            if(intval($crumb['id']) === $state['folder'])return true;
            if(intval($crumb['id']) === $folder)break;
        }
        return $state['folder'] === 0;
    }));
    $state = ['uid'=>$uid, 'folder'=>$folder];
    if($trail)$state['nav'] = admin_folder_nav_value($trail);
    return admin_folder_url($state);
}

function admin_folder_up_link($listing){
    if(!$listing['current'])return $listing['uid'] ? ($listing['trail'] ? admin_folder_ancestor_link($listing, 0, 0) : './folders.php') : '';
    $parent = intval($listing['current']['parent_id']);
    // 上级被删除/数据异常时回用户根，避免生成无法访问的导航。
    if($parent && !in_array($parent, array_map('intval', array_column($listing['breadcrumbs'], 'id')), true))$parent = 0;
    $uid = $listing['uid'];
    $last = $listing['trail'] ? end($listing['trail']) : null;
    if($last && $last['folder'] === $parent)$uid = $last['uid'];
    return admin_folder_ancestor_link($listing, $parent, $uid);
}
