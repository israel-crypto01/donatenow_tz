<?php
// backend/payment/mongike.php — Unified Mongike Mobile Money API
require_once __DIR__.'/../config/env.php';

class MongikeAPI {
    public function requestPayment(string $phone, float $amount, string $ref, string $buyerName = '', string $buyerEmail = ''): array {
        $payload = [
            'order_id'    => $ref,
            'amount'      => $amount,
            // ensure international format no +
            'buyer_phone' => preg_replace('/^\+/', '', $phone),
            'buyer_name'  => $buyerName,
            'buyer_email' => $buyerEmail,
            'fee_payer'   => 'MERCHANT',
            'webhook_url' => MONGIKE_WEBHOOK
        ];

        $ch = curl_init(MONGIKE_BASE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . MONGIKE_API_KEY
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true) ?? ['message' => 'No valid response from Mongike'];
        return [
            'http_code' => $httpCode,
            'data'      => $result,
            'success'   => ($result['status'] ?? '') === 'success'
        ];
    }
}
