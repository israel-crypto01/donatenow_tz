<?php
// backend/api/notifications.php — Fetch in-app notifications for current user
require_once '../config/db.php';
require_once '../utils/helpers.php';
header('Content-Type: application/json'); cors(); session_secure();
$sess = require_auth(); $db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $uid   = $sess['user_id'];

    // Mark all as read for this user when fetching
    $db->prepare('UPDATE notifications SET is_read=TRUE, read_at=NOW() WHERE user_id=? AND is_read=FALSE')
       ->execute([$uid]);

    $rows = $db->prepare('SELECT id, type, title, body, icon, action_url, is_read, read_at, created_at
                           FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?');
    $rows->execute([$uid, $limit]);
    json_ok(['success'=>true,'data'=>$rows->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark specific notification as read
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!empty($d['id'])) {
        $db->prepare('UPDATE notifications SET is_read=TRUE, read_at=NOW() WHERE id=? AND user_id=?')
           ->execute([$d['id'], $sess['user_id']]);
    }
    json_ok(['success'=>true]);
}
json_error('Method Not Allowed', 405);
