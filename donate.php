<?php
// backend/payment/donate.php — Initiate Donation
// POST: {campaign_id, amount, phone, method}
require_once '../config/db.php';
require_once '../utils/helpers.php';
require_once 'mongike.php';

header('Content-Type: application/json'); cors(); session_secure();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method Not Allowed', 405);

$sess = require_auth();
$d    = json_decode(file_get_contents('php://input'), true) ?? [];

$campaignId = (int)($d['campaign_id'] ?? 0);
$amount     = (int)($d['amount']      ?? 0);
$phone      = sanitize($d['phone']    ?? '');
$method     = sanitize($d['method']   ?? '');

$errors = [];
if (!$campaignId)              $errors['campaign_id'] = 'Campaign required';
if ($amount < MIN_DONATION)    $errors['amount']      = 'Minimum donation is '.tzs(MIN_DONATION);
if (!valid_phone($phone))      $errors['phone']       = 'Invalid Tanzanian phone number';
if (!in_array($method, ['mpesa','airtel','tigo','halo','crdb','nmb']))
                               $errors['method']      = 'Invalid payment method';
if (!empty($errors))           json_error('Validation failed', 422, $errors);

$db = getDB();

// Check campaign active
$s = $db->prepare('SELECT id,title,status FROM campaigns WHERE id=? FOR UPDATE');
$s->execute([$campaignId]); $camp = $s->fetch();
if (!$camp || $camp['status'] !== 'active') json_error('Campaign not found or inactive', 404);

// Create donation record
$ref = gen_ref('DN');
if (db_driver() === 'mysql') {
    $s = $db->prepare('INSERT INTO donations
        (donor_id,campaign_id,amount,payment_method,phone_used,gateway_ref,status)
        VALUES (?,?,?,?,?,?,\'pending\')');
    $s->execute([$sess['user_id'], $campaignId, $amount, $method, norm_phone($phone), $ref]);
    $donId = (int)$db->lastInsertId();
} else {
    $s = $db->prepare('INSERT INTO donations
        (donor_id,campaign_id,amount,payment_method,phone_used,gateway_ref,status)
        VALUES (?,?,?,?,?,?,\'pending\') RETURNING id');
    $s->execute([$sess['user_id'], $campaignId, $amount, $method, norm_phone($phone), $ref]);
    $donId = (int)$s->fetchColumn();
}

// Initiate payment via Mongike
$buyerName = $sess['first_name'] ?? 'Donor';
$buyerEmail = $sess['email'] ?? '';
$result = (new MongikeAPI())->requestPayment($phone, $amount, $ref, $buyerName, $buyerEmail);

// Log transaction
$db->prepare('INSERT INTO payment_transactions
    (donation_id,provider,request_payload,response_payload,http_status)
    VALUES (?,?,?,?,?)')->execute([
    $donId, $method,
    json_encode($d), json_encode($result['data'] ?? []),
    $result['http_code'] ?? 0,
]);

// Update status to processing
$db->prepare('UPDATE donations SET status=\'processing\' WHERE id=?')->execute([$donId]);

json_ok([
    'success'     => true,
    'ref'         => $ref,
    'donation_id' => $donId,
    'message'     => 'Payment request sent. Check your phone for the prompt.',
]);
