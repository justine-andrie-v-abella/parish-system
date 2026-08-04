<?php
// includes/paymongo.php

 // move to config.php / env var in practice

function paymongo_create_gcash_source(int $amountCentavos, string $successUrl, string $failedUrl): ?array
{
    $payload = [
        'data' => [
            'attributes' => [
                'type' => 'gcash',
                'amount' => $amountCentavos,
                'currency' => 'PHP',
                'redirect' => [
                    'success' => $successUrl,
                    'failed' => $failedUrl,
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.paymongo.com/v1/sources');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $result = json_decode($response, true);
    if (empty($result['data']['id'])) {
        return null;
    }

    return [
        'id' => $result['data']['id'],
        'checkout_url' => $result['data']['attributes']['redirect']['checkout_url'] ?? null,
    ];
}