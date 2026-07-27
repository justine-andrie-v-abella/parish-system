<?php
/**
 * includes/session.php
 * Starts the session once and provides small flash-message helpers so a
 * failed form submit can bounce back to its page with an error banner
 * and the previously typed values still filled in (minus passwords).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Store an error + the submitted fields (for repopulating the form), then redirect back. */
function flash_fail(string $to, string $message, array $old = []): void {
    unset($old['password'], $old['confirm_password']);
    $_SESSION['flash_error'] = $message;
    $_SESSION['flash_old']   = $old;
    header('Location: ' . $to);
    exit;
}

/** Read + clear the current error message. */
function flash_error(): ?string {
    $e = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);
    return $e;
}

/** Read a previously submitted field value (for sticky forms), HTML-escaped. */
function old(string $key, string $default = ''): string {
    $val = $_SESSION['flash_old'][$key] ?? $default;
    return htmlspecialchars($val);
}

/** Clear stored old input (call once the form has rendered, or on success). */
function clear_old(): void {
    unset($_SESSION['flash_old']);
}

/** Send the logged-in user to their role's dashboard. */
function dashboard_for(string $role): string {
    $map = [
        'parishioner' => 'dashboard-parishioner.php',
        'priest'      => 'dashboard-priest.php',
        'secretary'   => 'dashboard-secretary.php',
        'treasurer'   => 'dashboard-treasurer.php',
    ];
    return $map[$role] ?? 'index.php';
}

/** Guard a page to specific roles; redirects to the right login if not allowed. */
function require_role(array $roles): void {
    if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $roles, true)) {
        $target = in_array('parishioner', $roles, true) ? 'login.php' : 'staff-login.php';
        header('Location: ' . $target);
        exit;
    }
}