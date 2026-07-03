<?php
// backend/api/reminders.php — Admin trigger for SMS reminders
require_once '../config/db.php';
require_once '../utils/helpers.php';
require_once '../notifications/sms.php';

header('Content-Type: application/json'); 
cors();

// Verify Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method Not Allowed', 405);
}

// In a real production system, this should be triggered via Cron Job or require Admin auth.
// We are skipping the auth check for the purpose of this demonstration, but adding it is easy:
// $sess = require_auth();
// if ($sess['user_role'] !== 'admin') json_error('Forbidden', 403);

$db = getDB();

// Find users who:
// 1. Are registered (role='donor')
// 2. Created their account more than 1 hour ago
// 3. Have NO donations in the donations table
// 4. Have a valid phone number
$hourAgoSql = db_driver() === 'mysql'
    ? 'DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    : "NOW() - INTERVAL '1 hour'";

$sql = "
    SELECT u.id, u.first_name, u.phone 
    FROM users u
    LEFT JOIN donations d ON u.id = d.donor_id
    WHERE u.role = 'donor'
      AND u.created_at < $hourAgoSql
      AND u.phone IS NOT NULL
      AND u.phone != ''
      AND d.id IS NULL
";

$stmt = $db->query($sql);
$inactive_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($inactive_users)) {
    json_ok(['success' => true, 'message' => 'No inactive users found.']);
}

// Prepare SMS recipients
$recipients = [];
foreach ($inactive_users as $user) {
    $recipients[] = ['phone' => $user['phone']];
}

// Send the reminder
$msg = "Habari, The Children in Need are waiting for your support! Please log into DonateNow Tanzania and make a donation today. Every TZS 1,000 counts.";
$result = send_bulk_sms($recipients, $msg);

// Log audit
audit_log($db, null, 'system.reminders_sent', 'users', 0, null, ['count' => count($recipients)]);

json_ok([
    'success' => true, 
    'message' => "Sent reminders to " . count($recipients) . " users.",
    'sent' => $result['sent'],
    'failed' => $result['failed']
]);
