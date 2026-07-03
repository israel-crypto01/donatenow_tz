<?php
// backend/admin/reports.php — Platform Reports & Analytics
// GET ?type=monthly|regional|method|growth
require_once '../config/db.php';
require_once '../utils/helpers.php';
header('Content-Type: application/json'); cors(); session_secure();
require_admin(); $db = getDB();
if ($_SERVER['REQUEST_METHOD']!=='GET') json_error('Method Not Allowed',405);
$type = sanitize($_GET['type']??'monthly');

$data = match($type) {
    'monthly' => $db->query("
        SELECT TO_CHAR(donated_at,'YYYY-MM') AS month,
               SUM(amount) AS total, COUNT(*) AS count
        FROM donations WHERE status='confirmed'
        GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll(),
    'regional' => $db->query("
        SELECT u.region, SUM(d.amount) AS total, COUNT(*) AS count
        FROM donations d JOIN users u ON d.donor_id=u.id
        WHERE d.status='confirmed' AND u.region IS NOT NULL
        GROUP BY u.region ORDER BY total DESC LIMIT 10")->fetchAll(),
    'method' => $db->query("
        SELECT payment_method, SUM(amount) AS total, COUNT(*) AS count,
               ROUND(COUNT(*)*100.0/SUM(COUNT(*)) OVER(),1) AS pct
        FROM donations WHERE status='confirmed'
        GROUP BY payment_method ORDER BY total DESC")->fetchAll(),
    'growth' => $db->query("
        SELECT TO_CHAR(created_at,'YYYY-MM') AS month, COUNT(*) AS new_users
        FROM users GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll(),
    'category' => $db->query("
        SELECT c.category, SUM(d.amount) AS total, COUNT(DISTINCT d.id) AS donations
        FROM donations d JOIN campaigns c ON d.campaign_id=c.id
        WHERE d.status='confirmed' GROUP BY c.category ORDER BY total DESC")->fetchAll(),
    default => []
};
json_ok(['success'=>true,'type'=>$type,'data'=>$data]);
