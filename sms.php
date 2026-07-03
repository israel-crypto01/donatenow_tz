<?php
// backend/notifications/sms.php — Beem Africa SMS Gateway
require_once __DIR__.'/../config/env.php';
require_once __DIR__.'/../utils/helpers.php';

function send_sms(string $phone, string $message): bool {
    if (!function_exists('curl_init')) {
        error_log('curl extension not available. SMS not sent.');
        return false;
    }
    $phone = norm_phone($phone);
    $payload = json_encode([
        'source_addr'   => BEEM_SENDER,
        'schedule_time' => '',
        'encoding'      => '0',
        'message'       => mb_substr($message, 0, 459),
        'recipients'    => [['recipient_id' => 1, 'dest_addr' => $phone]],
    ]);
    $auth = base64_encode(BEEM_API_KEY.':'.BEEM_SECRET);
    $ch = curl_init(BEEM_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic '.$auth,
            'Content-Type: application/json',
        ],
    ]);
    $res  = json_decode(curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    dev_log("SMS to $phone HTTP $code", $res);
    return ($res['successful'] ?? false) === true;
}

function send_donation_sms(string $phone, string $name,
    int $amount, string $campaign, string $ref): bool {
    $fmt = number_format($amount);
    $msg = "Asante {$name}! Mchango wako wa TZS {$fmt} kwa ".
           "'{$campaign}' umepokelewa. Ref: {$ref}. DONATE NOW Tanzania.";
    return send_sms($phone, $msg);
}

function send_payment_failed_sms(string $phone, string $name,
    int $amount, string $method): bool {
    $fmt = number_format($amount);
    $msg = "Samahani {$name}. Malipo ya TZS {$fmt} kupitia {$method} ".
           "hayakufanikiwa. Tafadhali jaribu tena. DONATE NOW.";
    return send_sms($phone, $msg);
}

function send_otp_sms(string $phone, string $otp): bool {
    $msg = "Nambari yako ya uthibitisho wa DONATE NOW ni: {$otp}. ".
           "Halali kwa dakika 10. Usishirikishe mtu yeyote.";
    return send_sms($phone, $msg);
}

function send_bulk_sms(array $recipients, string $message): array {
    if (!function_exists('curl_init')) {
        error_log('curl extension not available. Bulk SMS not sent.');
        return ['sent' => 0, 'failed' => count($recipients)];
    }
    $recs = []; $id = 1;
    foreach ($recipients as $r) {
        $p = norm_phone($r['phone'] ?? '');
        if ($p) $recs[] = ['recipient_id' => $id++, 'dest_addr' => $p];
    }
    if (empty($recs)) return ['sent' => 0, 'failed' => 0];
    $payload = json_encode([
        'source_addr' => BEEM_SENDER, 'schedule_time' => '',
        'encoding' => '0', 'message' => mb_substr($message, 0, 459),
        'recipients' => $recs,
    ]);
    $auth = base64_encode(BEEM_API_KEY.':'.BEEM_SECRET);
    $ch = curl_init(BEEM_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Authorization: Basic '.$auth,'Content-Type: application/json']]);
    $res = json_decode(curl_exec($ch), true); curl_close($ch);
    $sent = ($res['successful']??false) ? count($recs) : 0;
    return ['sent' => $sent, 'failed' => count($recs) - $sent];
}
