<?php
/**
 * login-process.php — handles login.php's POST (parishioners only).
 */
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/logs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);
$old      = ['email' => $email];

if ($email === '' || $password === '') {
    flash_fail('login.php', 'Please enter your email and password.', $old);
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = \'parishioner\'');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Generic message on purpose — don't reveal whether the email exists.
if (!$user || !password_verify($password, $user['password_hash'])) {
    flash_fail('login.php', 'Incorrect email or password.', $old);
}
if (!$user['is_active']) {
    flash_fail('login.php', 'This account has been deactivated. Contact the parish office.', $old);
}

clear_old();
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['role']      = $user['role'];
$_SESSION['full_name'] = $user['full_name'];

log_activity($pdo, $user['id'], 'login', $user['full_name'] . ' logged in.');

if ($remember) {
    // Extend the session cookie to 30 days. (Swap for a proper remember-me
    // token table if you need this to survive across browsers/devices.)
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), time() + 30 * 24 * 60 * 60, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

header('Location: ' . dashboard_for($user['role']));
exit;