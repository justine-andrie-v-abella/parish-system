<?php
// webhooks/paymongo.php
require_once '../includes/config.php';
require_once '../includes/db.php';

$rawPayload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

function verifyPaymongoSignature(string $rawPayload, string $signatureHeader, string $secret, bool $liveMode = false): bool
{
    if (empty($signatureHeader)) return false;
    $parts = explode(',', $signatureHeader);
    $timestamp = null; $expectedSig = null;
    $targetKey = $liveMode ? 'li' : 'te';
    foreach ($parts as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
        if ($key === 't') $timestamp = $value;
        elseif ($key === $targetKey) $expectedSig = $value;
    }
    if (!$timestamp || !$expectedSig) return false;
    if (abs(time() - (int) $timestamp) > 300) return false;
    $computedSig = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $secret);
    return hash_equals($expectedSig, $computedSig);
}

function paymongo_create_payment(string $sourceId, int $amountCentavos): bool
{
    $payload = [
        'data' => [
            'attributes' => [
                'amount' => $amountCentavos,
                'currency' => 'PHP',
                'source' => ['id' => $sourceId, 'type' => 'source'],
            ],
        ],
    ];

    $ch = curl_init('https://api.paymongo.com/v1/payments');
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

    return $httpCode === 200;
}

$isLiveMode = false;
if (!verifyPaymongoSignature($rawPayload, $signatureHeader, PAYMONGO_WEBHOOK_SECRET, $isLiveMode)) {
    http_response_code(401);
    exit;
}

$data = json_decode($rawPayload, true);
$eventType = $data['data']['attributes']['type'] ?? null;

if ($eventType === 'source.chargeable') {
    $sourceId = $data['data']['attributes']['data']['id'] ?? null;

    if ($sourceId) {
        $stmt = $pdo->prepare('SELECT id, service_key FROM appointments WHERE paymongo_source_id = ?');
        $stmt->execute([$sourceId]);
        $appt = $stmt->fetch();

        if ($appt) {
            global $services;
            $serviceFees = array_column($services, 'fee', 'key');
            $fee = (int) ($serviceFees[$appt['service_key']] ?? 0);
            paymongo_create_payment($sourceId, $fee * 100);
            // payment.paid webhook will fire next and update the DB below
        }
    }
} elseif ($eventType === 'payment.paid') {
    $sourceId = $data['data']['attributes']['data']['attributes']['source']['id'] ?? null;
    if ($sourceId) {
        $stmt = $pdo->prepare(
            "UPDATE appointments SET payment_status = 'paid', status = 'confirmed' 
             WHERE paymongo_source_id = ? AND payment_status != 'paid'"
        );
        $stmt->execute([$sourceId]);
    }
} elseif ($eventType === 'payment.failed') {
    $sourceId = $data['data']['attributes']['data']['attributes']['source']['id'] ?? null;
    if ($sourceId) {
        $stmt = $pdo->prepare("UPDATE appointments SET status_reason = 'GCash payment failed' WHERE paymongo_source_id = ?");
        $stmt->execute([$sourceId]);
    }
}

http_response_code(200);