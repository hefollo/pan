<?php
// 分享仅记录虚拟目录入口。每次请求重新读取子树，不能用会话中缓存的文件清单授权。
function folder_share_input($data, $key, $default = ''){
    return isset($data[$key]) && is_scalar($data[$key]) ? (string)$data[$key] : $default;
}

function folder_share_expired($share){
    return !empty($share['expiretime']) && strtotime($share['expiretime']) <= time();
}

function folder_share_meta($row, $all){
    $id = intval($row['folder_id']);
    return [
        'folder_id'=>$id, 'name'=>isset($all[$id]) ? $all[$id]['name'] : '已删除的文件夹',
        'url'=>site_root_url().'share.php?token='.$row['token'],
        'enabled'=>(bool)$row['enabled'], 'expired'=>folder_share_expired($row),
        'missing'=>!isset($all[$id]), 'has_password'=>$row['password_hash'] !== '',
        'expiretime'=>$row['expiretime'] ?: '',
    ];
}

function folder_share_manage($action, $folder_id, $input = []){
    global $DB, $islogin2, $uid;
    if(empty($islogin2) || intval($uid) <= 0)return folder_result(-1, '请先登录后再管理分享');
    $user = $DB->getRow('SELECT uid,enable FROM pre_user WHERE uid=:uid', [':uid'=>intval($uid)]);
    if(!$user || empty($user['enable']))return folder_result(-1, '账号不可用');
    $owner = ['uid'=>intval($uid), 'gkey'=>''];
    $all = folder_all($owner);
    if($action === 'list'){
        $rows = $DB->getAll('SELECT * FROM pre_folder_share WHERE uid=:uid ORDER BY id DESC', [':uid'=>intval($uid)]);
        $items = [];
        foreach((array)$rows as $row)$items[] = folder_share_meta($row, $all);
        return folder_result(0, 'ok', ['shares'=>$items, 'can_edit'=>can_use_folders()]);
    }
    $folder_id = intval($folder_id);
    $row = $DB->getRow('SELECT * FROM pre_folder_share WHERE folder_id=:folder AND uid=:uid', [':folder'=>$folder_id, ':uid'=>intval($uid)]);
    // 权限到期或目录删除后仍能撤销，撤销入口不能放到 can_use_folders 门禁后面。
    if($action === 'revoke'){
        if(!$row)return folder_result(-1, '分享不存在');
        $ok = $DB->exec('UPDATE pre_folder_share SET enabled=0,auth_version=:auth,updatetime=NOW() WHERE id=:id AND uid=:uid',
            [':auth'=>bin2hex(random_bytes(16)), ':id'=>$row['id'], ':uid'=>intval($uid)]);
        return folder_result($ok ? 0 : -1, $ok ? '已取消分享，原有独立文件链接不受影响' : '保存失败');
    }
    if(!isset($all[$folder_id]))return folder_result(-1, '文件夹不存在或不属于你');
    if($action === 'get')return folder_result(0, 'ok', ['share'=>$row ? folder_share_meta($row, $all) : null, 'name'=>$all[$folder_id]['name'], 'can_edit'=>can_use_folders()]);
    if(!in_array($action, ['save','reset'], true))return folder_result(-1, '未知操作');
    if(!can_use_folders())return folder_result(-1, '当前账号不能创建或修改文件夹分享，仍可取消已有分享');
    if($action === 'reset'){
        if(!$row || empty($row['enabled']))return folder_result(-1, '请先创建分享');
        $ok = $DB->exec('UPDATE pre_folder_share SET token=:token,auth_version=:auth,updatetime=NOW() WHERE id=:id AND uid=:uid',
            [':token'=>bin2hex(random_bytes(32)), ':auth'=>bin2hex(random_bytes(16)), ':id'=>$row['id'], ':uid'=>intval($uid)]);
    }else{
        $mode = folder_share_input($input, 'password_mode', 'keep');
        if(!in_array($mode, ['keep','set','clear'], true))return folder_result(-1, '密码设置无效');
        $password = folder_share_input($input, 'password');
        if($mode === 'set' && (strlen($password) < 4 || strlen($password) > 72 || strpos($password, "\0") !== false))return folder_result(-1, '分享密码需要 4～72 个字节');
        $hash = $row ? $row['password_hash'] : '';
        if($mode === 'clear')$hash = '';
        if($mode === 'set')$hash = password_hash($password, PASSWORD_DEFAULT);
        $expire = trim(folder_share_input($input, 'expiretime'));
        if($expire !== ''){
            $dt = DateTime::createFromFormat('!Y-m-d\TH:i', $expire);
            if(!$dt || $dt->format('Y-m-d\TH:i') !== $expire || $dt->getTimestamp() <= time())return folder_result(-1, '请选择未来的有效期，留空为永久');
            $expire = $dt->format('Y-m-d H:i:s');
        }else $expire = null;
        // 重新启用已撤销的分享会生成新链接，不能让曾经撤销的旧链接重新生效。
        $token = $row && !empty($row['enabled']) ? $row['token'] : bin2hex(random_bytes(32));
        $params = [':uid'=>intval($uid), ':folder'=>$folder_id, ':token'=>$token, ':password'=>$hash, ':auth'=>bin2hex(random_bytes(16)), ':expire'=>$expire];
        if($row){
            $ok = $DB->exec('UPDATE pre_folder_share SET token=:token,password_hash=:password,auth_version=:auth,expiretime=:expire,enabled=1,updatetime=NOW() WHERE folder_id=:folder AND uid=:uid', $params);
        }else{
            $ok = $DB->exec('INSERT INTO pre_folder_share (uid,folder_id,token,password_hash,auth_version,expiretime,enabled,addtime,updatetime) VALUES (:uid,:folder,:token,:password,:auth,:expire,1,NOW(),NOW())', $params);
        }
    }
    if(!$ok)return folder_result(-1, '保存失败，请刷新后重试');
    $row = $DB->getRow('SELECT * FROM pre_folder_share WHERE folder_id=:folder AND uid=:uid', [':folder'=>$folder_id, ':uid'=>intval($uid)]);
    return folder_result(0, $action === 'reset' ? '链接已重置，旧链接已失效' : '分享已保存', ['share'=>folder_share_meta($row, $all)]);
}

function folder_share_resolve($token){
    global $DB, $conf;
    if(empty($conf['folder_open']) || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token))return null;
    $share = $DB->getRow('SELECT * FROM pre_folder_share WHERE token=:token AND enabled=1', [':token'=>$token]);
    if(!$share || folder_share_expired($share))return null;
    $user = $DB->getRow('SELECT uid,enable FROM pre_user WHERE uid=:uid', [':uid'=>intval($share['uid'])]);
    if(!$user || empty($user['enable']))return null;
    $all = folder_all(['uid'=>intval($share['uid']), 'gkey'=>'']);
    $root = intval($share['folder_id']);
    if(!isset($all[$root]))return null;
    $ids = folder_subtree_ids($all, $root);
    $all = array_intersect_key($all, array_fill_keys($ids, true));
    // 裁掉分享根以上路径，任何页面都看不到私人父目录名称。
    $all[$root]['parent_id'] = 0;
    return ['share'=>$share, 'root'=>$root, 'ids'=>$ids, 'all'=>$all];
}

function folder_share_grant_key($ctx, $file = null){
    $share = $ctx['share'];
    return hash_hmac('sha256', $share['token'].'|'.$share['auth_version'].'|'.($file ? $file['id'].'|'.$file['pwd'] : 'folder'), SYS_KEY);
}

function folder_share_unlocked($ctx, $file = null){
    $password = $file ? (string)$file['pwd'] : $ctx['share']['password_hash'];
    if($password === '')return true;
    $key = folder_share_grant_key($ctx, $file);
    return isset($_SESSION['folder_share_grants'][$key]) && $_SESSION['folder_share_grants'][$key] > time();
}

function folder_share_unlock($ctx, $password, $file = null){
    if(!is_string($password) || strlen($password) > 255)return false;
    $ok = $file ? check_file_pwd($file, $password) : password_verify($password, $ctx['share']['password_hash']);
    if(!$ok)return false;
    $grants = isset($_SESSION['folder_share_grants']) ? $_SESSION['folder_share_grants'] : [];
    $grants = array_filter($grants, function($until){ return $until > time(); });
    if(count($grants) >= 200)$grants = array_slice($grants, -199, null, true);
    $grants[folder_share_grant_key($ctx, $file)] = time() + 43200;
    $_SESSION['folder_share_grants'] = $grants;
    return true;
}

function folder_share_file($ctx, $id){
    global $DB;
    $row = $DB->getRow('SELECT * FROM pre_file WHERE id=:id AND uid=:uid AND block=0', [':id'=>intval($id), ':uid'=>intval($ctx['share']['uid'])]);
    return $row && isset($ctx['all'][intval($row['folder_id'])]) ? $row : null;
}

function folder_share_listing($ctx, $input){
    global $DB;
    $all = $ctx['all'];
    $current = intval(folder_share_input($input, 'folder', (string)$ctx['root']));
    if(!isset($all[$current]))return null;
    $mode = folder_share_input($input, 'mode') === 'all' ? 'all' : 'folder';
    $kw = mb_substr(trim(folder_share_input($input, 'q')), 0, 100, 'UTF-8');
    $filters = layout_type_filters();
    $ft = folder_share_input($input, 'type');
    if(!isset($filters[$ft]))$ft = '';
    $sorts = ['newest'=>'addtime DESC,id DESC', 'oldest'=>'addtime ASC,id ASC', 'name'=>'name ASC,id ASC', 'size'=>'size DESC,id DESC'];
    $sort = folder_share_input($input, 'sort', 'newest');
    if(!isset($sorts[$sort]))$sort = 'newest';
    // 搜索和类型筛选始终限定分享根的整棵子树，不借用私人列表的全账号范围。
    $recursive = $mode === 'all' || $kw !== '' || $ft !== '';
    $ids = $recursive ? $ctx['ids'] : [$current];
    $where = 'uid=:uid AND block=0 AND folder_id IN ('.implode(',', array_map('intval', $ids)).')';
    $params = [':uid'=>intval($ctx['share']['uid'])];
    if($kw !== ''){
        $where .= " AND name LIKE :keyword ESCAPE '='";
        $params[':keyword'] = '%'.str_replace(['=','%','_'], ['==','=%','=_'], htmlspecialchars($kw, ENT_QUOTES, 'UTF-8')).'%';
    }
    if($ft !== ''){
        $exts = layout_type_group_exts($ft);
        $marks = [];
        foreach($exts as $i=>$ext){ $marks[] = ':ext'.$i; $params[':ext'.$i] = $ext; }
        $where .= $marks ? ' AND LOWER(type) IN ('.implode(',', $marks).')' : ' AND 1=0';
    }
    $total = $DB->getColumn('SELECT COUNT(*) FROM pre_file WHERE '.$where, $params);
    if($total === false)throw new RuntimeException('分享列表读取失败');
    $pages = max(1, (int)ceil($total / 40));
    $page = max(1, min($pages, intval(folder_share_input($input, 'page', '1'))));
    $rows = $DB->getAll('SELECT id,name,type,size,folder_id,addtime,(pwd IS NOT NULL AND pwd<>\'\') AS locked FROM pre_file WHERE '.$where.' ORDER BY '.$sorts[$sort].' LIMIT '.(($page-1)*40).',40', $params);
    if($rows === false)throw new RuntimeException('分享列表读取失败');
    $folders = [];
    if(!$recursive)foreach($all as $f)if($f['parent_id'] === $current)$folders[] = $f;
    return ['files'=>$rows, 'folders'=>$folders, 'total'=>intval($total), 'pages'=>$pages, 'page'=>$page, 'current'=>$current, 'mode'=>$mode, 'q'=>$kw, 'type'=>$ft, 'sort'=>$sort, 'recursive'=>$recursive];
}
