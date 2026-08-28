<?php
/**
 * includes/supabase-storage.php
 * Thin wrapper around the Supabase Storage REST API (no official PHP SDK
 * exists, so this talks to the HTTP API directly — same pattern as
 * includes/paymongo.php). Used to store parishioner-uploaded document
 * scans so they survive redeploys/multiple servers instead of living on
 * whichever single machine happens to run this PHP app.
 *
 * The bucket must already exist and be PRIVATE (Supabase dashboard →
 * Storage → New bucket → "appointment-documents", Public OFF). Every call
 * here uses the service_role key, which bypasses Storage's row-level
 * security — appropriate because this app enforces its own
 * session-based ownership checks (see ajax/view-appointment-document.php)
 * rather than using Supabase Auth.
 */

define('SUPABASE_STORAGE_BUCKET', 'appointment-documents');

function supabase_storage_configured(): bool {
    return SUPABASE_URL !== '' && !empty(SUPABASE_SERVICE_ROLE_KEY);
}

/** Uploads a local temp file to the bucket at $objectPath. */
function supabase_storage_upload(string $objectPath, string $localTmpPath, string $contentType): bool {
    $url = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_STORAGE_BUCKET . '/' . rawurlencode($objectPath);

    $fileData = file_get_contents($localTmpPath);
    if ($fileData === false) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $fileData,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: ' . $contentType,
            'x-upsert: false',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('supabase_storage_upload failed (' . $httpCode . '): ' . $response);
        return false;
    }
    return true;
}

/**
 * Downloads an object's raw bytes. Returns null if not found or the
 * request failed — caller should treat that as "document missing."
 */
function supabase_storage_download(string $objectPath): ?string {
    $url = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_STORAGE_BUCKET . '/' . rawurlencode($objectPath);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        error_log('supabase_storage_download failed (' . $httpCode . '): ' . $response);
        return null;
    }
    return $response;
}

/** Deletes one or more objects from the bucket. */
function supabase_storage_delete(array $objectPaths): bool {
    if (!$objectPaths) {
        return true;
    }

    $url = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_STORAGE_BUCKET;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_POSTFIELDS => json_encode(['prefixes' => array_values($objectPaths)]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}
