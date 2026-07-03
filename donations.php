<?php
// backend/api/donations.php — Donations API
// GET  (donor) → own donation history
// GET  ?id=X   → single donation detail
require_once '../config/db.php';
require_once '../utils/helpers.php';
header('Content-Type: application/json'); cors(); session_secure();
$sess = require_auth(); $db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($method === 'GET') {
    if ($id) {
        $s = $db->prepare('SELECT d.*,c.title AS camp_title
            FROM donations d JOIN campaigns c ON d.campaign_id=c.id
            WHERE d.id=? AND (d.donor_id=? OR ?=\'admin\')');
        $s->execute([$id, $sess['user_id'], $sess['user_role']]);
        $don = $s->fetch();
        if (!$don) json_error('Donation not found', 404);
        json_ok(['success'=>true,'data'=>$don]);
    }
    $limit  = min((int)($_GET['limit'] ?? 20), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $where  = $sess['user_role'] === 'admin' ? '1=1' : 'd.donor_id=?';
    $params = $sess['user_role'] === 'admin' ? [] : [$sess['user_id']];
    if (!empty($_GET['status'])) { $where.=' AND d.status=?'; $params[]=sanitize($_GET['status']); }
    $nameSql = db_driver() === 'mysql'
        ? "TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')))"
        : "TRIM(COALESCE(u.first_name,'')||' '||COALESCE(u.last_name,''))";
    $s = $db->prepare("SELECT d.id,d.uuid,d.campaign_id,d.donor_id,d.amount,d.payment_method,d.status,
        d.gateway_ref,d.is_anonymous,d.receipt_sent,d.donated_at,d.confirmed_at,
        c.title AS camp_title,c.category,
        u.email AS donor_email,$nameSql AS donor_name
        FROM donations d JOIN campaigns c ON d.campaign_id=c.id
        LEFT JOIN users u ON d.donor_id=u.id
        WHERE $where ORDER BY d.donated_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($params);
    $cnt = $db->prepare("SELECT COUNT(*) FROM donations d WHERE $where");
    $cnt->execute($params);
    json_ok(['success'=>true,'data'=>$s->fetchAll(),
             'total'=>(int)$cnt->fetchColumn(),'limit'=>$limit,'offset'=>$offset]);
}
json_error('Method Not Allowed', 405);
