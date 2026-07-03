<?php
// backend/api/stats.php — Dashboard Stats
// GET → platform stats (admin) or personal stats (donor)
require_once '../config/db.php';
require_once '../utils/helpers.php';
header('Content-Type: application/json'); cors(); session_secure();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method Not Allowed', 405);

$db = getDB();
$sess = $_SESSION ?? [];

if (empty($sess['user_id']) || ($sess['user_role'] ?? '') === 'admin') {
    json_ok(['success'=>true,'data'=>[
        'total_raised'     => (int)$db->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='confirmed'")->fetchColumn(),
        'total_donors'     => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='donor'")->fetchColumn(),
        'active_campaigns' => (int)$db->query("SELECT COUNT(*) FROM campaigns WHERE status='active'")->fetchColumn(),
        'pending_campaigns'=> (int)$db->query("SELECT COUNT(*) FROM campaigns WHERE status='pending'")->fetchColumn(),
        'today_raised'     => (int)$db->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status='confirmed' AND DATE(donated_at)=CURRENT_DATE")->fetchColumn(),
        'total_users'      => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'failed_today'     => (int)$db->query("SELECT COUNT(*) FROM donations WHERE status='failed' AND DATE(donated_at)=CURRENT_DATE")->fetchColumn(),
    ]]);
}

$uid = $sess['user_id'];
$s = $db->prepare("SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt FROM donations WHERE donor_id=? AND status='confirmed'");
$s->execute([$uid]); $row = $s->fetch();
$camps = $db->prepare("SELECT COUNT(DISTINCT campaign_id) FROM donations WHERE donor_id=? AND status='confirmed'");
$camps->execute([$uid]);
json_ok(['success'=>true,'data'=>[
    'total_donated'       => (int)$row['total'],
    'donation_count'      => (int)$row['cnt'],
    'campaigns_supported' => (int)$camps->fetchColumn(),
]]);
