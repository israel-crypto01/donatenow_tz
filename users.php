<?php
// backend/admin/users.php — Admin User Management
// GET    ?limit=&offset=&role=&q=  → list users
// PUT    ?id=X                     → update user (role/status)
// DELETE ?id=X                     → delete user
require_once '../config/db.php';
require_once '../utils/helpers.php';
header('Content-Type: application/json'); cors(); session_secure();
$sess = require_admin(); $db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isMysql = db_driver() === 'mysql';
$likeSql = $isMysql ? 'LIKE' : 'ILIKE';
$trueSql = $isMysql ? '1' : 'TRUE';
$falseSql = $isMysql ? '0' : 'FALSE';

if ($method === 'GET') {
    $limit  = min((int)($_GET['limit'] ?? 25), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $where  = ['1=1']; $params = [];
    if (!empty($_GET['role'])) { $where[]='role=?'; $params[]=sanitize($_GET['role']); }
    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $where[] = $_GET['status']==='active' ? "is_active=$trueSql" : "is_active=$falseSql";
    } elseif (empty($_GET['status'])) {
        $where[] = "is_active=$trueSql";
    }
    if (!empty($_GET['q'])) {
        $where[]="(email $likeSql ? OR first_name $likeSql ? OR last_name $likeSql ?)";
        $q='%'.sanitize($_GET['q']).'%'; $params[]=$q; $params[]=$q; $params[]=$q;
    }
    $w = implode(' AND ', $where);
    $s = $db->prepare("SELECT id,uuid,first_name,last_name,email,phone,role,region,
        is_verified,is_active,google_id IS NOT NULL AS has_google,last_login,created_at
        FROM users WHERE $w ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($params);
    $cnt = $db->prepare("SELECT COUNT(*) FROM users WHERE $w"); $cnt->execute($params);
    json_ok(['success'=>true,'data'=>$s->fetchAll(),
             'total'=>(int)$cnt->fetchColumn(),'limit'=>$limit,'offset'=>$offset]);
}

if ($method === 'PUT' && $id) {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $s = $db->prepare('SELECT id,role FROM users WHERE id=?'); $s->execute([$id]); $u=$s->fetch();
    if (!$u) json_error('User not found', 404);
    $fields=[]; $params=[];
    if (isset($d['is_active'])) { $fields[]='is_active=?'; $params[]=(bool)$d['is_active']; }
    if (isset($d['role']) && in_array($d['role'],['donor','ngo','admin'])) {
        $fields[]='role=?'; $params[]=sanitize($d['role']);
    }
    if (empty($fields)) json_error('No fields to update', 422);
    $params[]=$id;
    $db->prepare('UPDATE users SET '.implode(',',$fields).',updated_at=NOW() WHERE id=?')->execute($params);
    audit_log($db,$sess['user_id'],'admin.user.updated','users',$id,null,$d);
    json_ok(['success'=>true]);
}

if ($method === 'DELETE' && $id) {
    $s=$db->prepare("SELECT role FROM users WHERE id=?"); $s->execute([$id]); $u=$s->fetch();
    if (!$u) json_error('User not found',404);
    if ($u['role']==='admin') json_error('Cannot delete admin accounts',403);
    $db->prepare("UPDATE users SET is_active=$falseSql,updated_at=NOW() WHERE id=? AND role!='admin'")->execute([$id]);
    audit_log($db,$sess['user_id'],'admin.user.deleted','users',$id);
    json_ok(['success'=>true]);
}
json_error('Method Not Allowed', 405);
