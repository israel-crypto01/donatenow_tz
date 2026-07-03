<?php
// backend/payment/pay.php — Standalone Mongike Payment endpoint for frontend integration
error_reporting(0);
require_once __DIR__.'/../config/env.php';
require_once 'mongike.php';

header('Content-Type: application/json');
// Handle CORS if needed
$o = $_SERVER['HTTP_ORIGIN'] ?? '';
if(in_array($o, CORS_ORIGINS)) header("Access-Control-Allow-Origin: $o");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$phone = $data['phone'] ?? '';
$amount = (float)($data['amount'] ?? 0);
$ref = 'ORDER_' . time();

if ($amount < 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Minimum amount is 1000 TZS']);
    exit;
}

if (!$phone) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone number is required']);
    exit;
}

// Initiate payment via Mongike
$mongike = new MongikeAPI();
$result = $mongike->requestPayment($phone, $amount, $ref, 'KidHope Donor', '');

echo json_encode($result);
