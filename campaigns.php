<?php
// backend/admin/campaigns.php — Admin Campaign Management
// GET  ?status=pending → filter campaigns
// PUT  ?id=X           → approve/reject/suspend/feature
require_once '../config/db.php';
require_once '../utils/helpers.php';
require_once '../notifications/email.php';
header('Content-Type: application/json'); cors(); session_secure();
$sess = require_admin(); $db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isMysql = db_driver() === 'mysql';
$creatorNameSql = $isMysql
    ? "CONCAT(u.first_name,' ',u.last_name)"
    : 'u.first_name||chr(32)||u.last_name';
$pctSql = $isMysql
    ? 'ROUND((c.raised_amount/NULLIF(c.goal_amount,0))*100,1)'
    : 'ROUND((c.raised_amount::numeric/NULLIF(c.goal_amount,0))*100,1)';
$likeSql = $isMysql ? 'LIKE' : 'ILIKE';

if ($method === 'GET') {
    $limit  = min((int)($_GET['limit'] ?? 25), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $where  = ['1=1']; $params = [];
    if (!empty($_GET['status'])) { $where[]='c.status=?'; $params[]=sanitize($_GET['status']); }
    if (!empty($_GET['category'])) { $where[]='c.category=?'; $params[]=sanitize($_GET['category']); }
    if (!empty($_GET['q'])) {
        $where[]="c.title $likeSql ?"; $params[]='%'.sanitize($_GET['q']).'%';
    }
    $w = implode(' AND ', $where);
    $s = $db->prepare("SELECT c.*,$creatorNameSql AS creator_name,u.email AS creator_email,
        $pctSql AS pct
        FROM campaigns c JOIN users u ON c.created_by=u.id
        WHERE $w ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($params);
    $cnt=$db->prepare("SELECT COUNT(*) FROM campaigns c WHERE $w"); $cnt->execute($params);
    json_ok(['success'=>true,'data'=>$s->fetchAll(),
             'total'=>(int)$cnt->fetchColumn(),'limit'=>$limit,'offset'=>$offset]);
}

if ($method === 'PUT' && $id) {
    $d = json_decode(file_get_contents('php://input'),true)??[];
    $action = sanitize($d['action']??'');
    $s=$db->prepare('SELECT c.*,u.email,u.first_name FROM campaigns c JOIN users u ON c.created_by=u.id WHERE c.id=?');
    $s->execute([$id]); $c=$s->fetch();
    if (!$c) json_error('Campaign not found',404);

    $newStatus = match($action) {
        'approve'  => 'active',
        'reject'   => 'rejected',
        'suspend'  => 'suspended',
        'complete' => 'completed',
        default    => null,
    };

    if ($newStatus) {
        $db->prepare('UPDATE campaigns SET status=?,is_verified=?,updated_at=NOW() WHERE id=?')
           ->execute([$newStatus, $newStatus==='active', $id]);
        // Notify campaign creator
        if (in_array($action,['approve','reject'])) {
            send_campaign_status_email($c['email'],$c['first_name'],
                $c['title'],$action==='approve'?'approved':'rejected',
                sanitize($d['reason']??'Does not meet our guidelines'));
        }
        audit_log($db,$sess['user_id'],"admin.campaign.$action",'campaigns',$id);
    }

    // Feature/unfeature
    if (isset($d['is_featured'])) {
        $db->prepare('UPDATE campaigns SET is_featured=?,updated_at=NOW() WHERE id=?')
           ->execute([(bool)$d['is_featured'],$id]);
        audit_log($db,$sess['user_id'],'admin.campaign.featured','campaigns',$id);
    }

    json_ok(['success'=>true]);
}
json_error('Method Not Allowed', 405);
