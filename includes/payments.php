<?php
/**
 * includes/payments.php
 * Shared helpers for anything money-related (Treasurer dashboard,
 * payments.php, receipt.php). Fees live in includes/config.php's
 * $services array (not a DB column) — these helpers just map a
 * service_key to its current fee.
 */

/** service_key => fee, from the $services array in config.php */
function get_fee_map(array $services): array {
    return array_column($services, 'fee', 'key');
}

function payment_amount(string $serviceKey, array $feeMap): int {
    return $feeMap[$serviceKey] ?? 0;
}

/** Deterministic, human-friendly receipt number for a given appointment. */
function generate_receipt_number(int $appointmentId): string {
    return 'RCPT-' . date('Y') . '-' . str_pad((string) $appointmentId, 5, '0', STR_PAD_LEFT);
}