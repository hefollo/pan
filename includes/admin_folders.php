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
    $where = "f.uid>0 AND f.gkey=''";
    $params = [];
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
    return ['rows'=>$rows, 'total'=>intval($total), 'page'=>$page, 'pages'=>$pages, 'uid'=>$uid, 'q'=>$q, 'status'=>$status];
}
