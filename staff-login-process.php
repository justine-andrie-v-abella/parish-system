<?php
/**
 * staff-login-process.php — handles staff-login.php's POST.
 */
require_once 'includes/session.php';
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff-login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);
$old      = ['email' => $email];

if ($email === '' || $password === '') {
    flash_fail('staff-login.php', 'Please enter your email and password.', $old);
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role IN ('priest','secretary','treasurer')");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash_fail('staff-login.php', 'Incorrect email or password.', $old);
}
if (!$user['is_active']) {
    flash_fail('staff-login.php', 'This account has been deactivated. Contact the Parish Administrator.', $old);
}

clear_old();
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['role']      = $user['role'];
$_SESSION['full_name'] = $user['full_name'];

if ($remember) {
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), time() + 30 * 24 * 60 * 60, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

header('Location: ' . dashboard_for($user['role']));
exit;