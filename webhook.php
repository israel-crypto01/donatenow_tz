<?php
// backend/payment/webhook.php — Unified Mongike Webhook
require_once '../config/env.php';
require_once '../config/db.php';
require_once '../utils/helpers.php';
require_once '../notifications/email.php';
require_once '../notifications/sms.php';

// Verify the request is from Mongike
$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($receivedKey !== MONGIKE_API_KEY) {
    http_response_code(401);
    exit('Unauthorized');
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?? [];

// Log raw webhook
if (!is_dir(__DIR__.'/../logs')) mkdir(__DIR__.'/../logs', 0777, true);
$log = __DIR__.'/../logs/mongike_webhooks.log';
file_put_contents($log, date('[Y-m-d H:i:s] ').$raw.PHP_EOL, FILE_APPEND | LOCK_EX);

$paymentStatus = strtoupper((string)($payload['payment_status'] ?? ''));
if ($paymentStatus === 'COMPLETED') {
    $ref   = $payload['order_id'] ?? '';
    $amount    = $payload['amount'] ?? 0;
    $txId = $payload['reference'] ?? '';

    if (!$ref) {
        http_response_code(200); echo 'OK'; exit;
    }

    $db  = getDB();

    // Fetch donation — idempotency: only process 'processing' status
    $s = $db->prepare('SELECT d.*,
        u.email, u.first_name, u.phone AS u_phone,
        c.title AS camp_title, c.id AS camp_id
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.id
        JOIN campaigns c ON d.campaign_id = c.id
        WHERE d.gateway_ref = ? AND d.status = \'processing\'');
    $s->execute([$ref]); $don = $s->fetch();

    if ($don) {
        $status = 'confirmed';
        
        // Update donation status
        $db->prepare('UPDATE donations SET status=?,transaction_id=?,confirmed_at=? WHERE id=?')
           ->execute([$status, $txId, date('Y-m-d H:i:s'), $don['id']]);

        // Update payment_transactions with callback payload
        $db->prepare('UPDATE payment_transactions SET callback_payload=?,result_code=?,updated_at=NOW()
            WHERE donation_id=?')->execute([$raw, $status, $don['id']]);

        // Atomic: increment raised_amount and donor_count
        $db->prepare('UPDATE campaigns
            SET raised_amount = raised_amount + ?,
                donor_count   = donor_count + 1,
                status = CASE WHEN raised_amount + ? >= goal_amount THEN \'completed\' ELSE status END
            WHERE id = ?')->execute([$don['amount'], $don['amount'], $don['camp_id']]);

        if ($don['donor_id']) {
            // In-app notification
            create_notification($db, $don['donor_id'], 'donation', '💝 Donation Confirmed',
                'Your donation of '.tzs($don['amount']).' to '.$don['camp_title'].' was received.',
                '💝', APP_URL.'/index.html#dashboard');
        }

        // Receipt email
        if ($don['email'] && function_exists('send_donation_receipt')) {
            send_donation_receipt($don['email'], $don['first_name'] ?: 'Donor', $ref,
                $don['amount'], $don['camp_title'], 'Mobile Money');
            $db->prepare('UPDATE donations SET receipt_sent=TRUE WHERE id=?')->execute([$don['id']]);
        }

        // SMS confirmation: send to the phone used for payment first, then profile phone.
        $smsPhone = $don['phone_used'] ?: $don['u_phone'];
        if ($smsPhone && function_exists('send_donation_sms')) {
            $smsSent = send_donation_sms($smsPhone, $don['first_name'] ?: 'Donor',
                (int)$don['amount'], $don['camp_title'], $ref);
            if ($smsSent) {
                $db->prepare('UPDATE donations SET sms_sent=TRUE WHERE id=?')->execute([$don['id']]);
            } else {
                dev_log('Donation SMS failed', ['donation_id' => $don['id'], 'phone' => $smsPhone, 'ref' => $ref]);
            }
        } else {
            dev_log('Donation SMS skipped: no recipient or SMS function unavailable', ['donation_id' => $don['id'], 'ref' => $ref]);
        }
        
        if ($don['donor_id']) {
            audit_log($db, $don['donor_id'], 'donation.confirmed', 'donations', $don['id']);
        }
    }
}

http_response_code(200);
echo 'OK';
