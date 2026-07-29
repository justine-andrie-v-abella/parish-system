<?php
/**
 * staff-register-process.php — handles staff-register.php's POST.
 * Gated by a one-time invite code so the public can't self-provision
 * internal roles.
 */
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/logs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff-register.php');
    exit;
}

$role     = trim($_POST['role'] ?? '');
$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$code     = trim($_POST['invite_code'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

$old = compact('role', 'fullname', 'email');
$allowedRoles = ['priest', 'secretary', 'treasurer'];

if (!in_array($role, $allowedRoles, true)) {
    flash_fail('staff-register.php', 'Please choose a valid role.', $old);
}
if ($fullname === '' || $email === '' || $code === '' || $password === '' || $confirm === '') {
    flash_fail('staff-register.php', 'Please fill in every field.', $old);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_fail('staff-register.php', 'Please enter a valid email address.', $old);
}
if (strlen($password) < 8) {
    flash_fail('staff-register.php', 'Password must be at least 8 characters.', $old);
}
if ($password !== $confirm) {
    flash_fail('staff-register.php', 'Password and confirm password do not match.', $old);
}

// Validate the invite code: must exist, match the chosen role, and be unused.
$codeStmt = $pdo->prepare('SELECT * FROM invite_codes WHERE code = ? AND role = ? AND is_used = 0');
$codeStmt->execute([$code, $role]);
$invite = $codeStmt->fetch();
if (!$invite) {
    flash_fail('staff-register.php', 'That invite code is invalid, already used, or does not match the selected role.', $old);
}

$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    flash_fail('staff-register.php', 'That email is already registered. Try signing in instead.', $old);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        'INSERT INTO users (role, full_name, email, password_hash) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([$role, $fullname, $email, $hash]);
    $userId = $pdo->lastInsertId();

    $markUsed = $pdo->prepare('UPDATE invite_codes SET is_used = 1, used_by = ? WHERE id = ?');
    $markUsed->execute([$userId, $invite['id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    flash_fail('staff-register.php', 'Something went wrong creating the account. Please try again.', $old);
}

clear_old();
session_regenerate_id(true);
$_SESSION['user_id']   = $userId;
$_SESSION['role']      = $role;
$_SESSION['full_name'] = $fullname;

log_activity($pdo, $userId, 'register', $fullname . ' was provisioned as ' . ucfirst($role) . '.');

header('Location: ' . dashboard_for($role));
exit;