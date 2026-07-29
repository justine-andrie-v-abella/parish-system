<?php
/**
 * register-process.php — handles register.php's POST.
 * Validates input, checks for duplicate email, hashes the password,
 * inserts a parishioner, logs them in, and sends them to their dashboard.
 */
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/logs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$address  = trim($_POST['address'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$contact  = trim($_POST['contact'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$agreed   = isset($_POST['agree']);

$old = compact('fullname', 'address', 'birthday', 'contact', 'email');

if ($fullname === '' || $address === '' || $birthday === '' || $contact === '' || $email === '' || $password === '' || $confirm === '') {
    flash_fail('register.php', 'Please fill in every field.', $old);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_fail('register.php', 'Please enter a valid email address.', $old);
}
if (strlen($password) < 8) {
    flash_fail('register.php', 'Password must be at least 8 characters.', $old);
}
if ($password !== $confirm) {
    flash_fail('register.php', 'Password and confirm password do not match.', $old);
}
if (!$agreed) {
    flash_fail('register.php', 'Please agree to the data privacy and terms of use.', $old);
}

$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    flash_fail('register.php', 'That email is already registered. Try signing in instead.', $old);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare(
    'INSERT INTO users (role, full_name, email, password_hash, address, birthday, contact_number)
     VALUES (\'parishioner\', ?, ?, ?, ?, ?, ?)'
);
$insert->execute([$fullname, $email, $hash, $address, $birthday, $contact]);

clear_old();
session_regenerate_id(true);
$_SESSION['user_id']   = $pdo->lastInsertId();
$_SESSION['role']      = 'parishioner';
$_SESSION['full_name'] = $fullname;

log_activity($pdo, (int) $_SESSION['user_id'], 'register', $fullname . ' registered as a new parishioner.');

header('Location: ' . dashboard_for('parishioner'));
exit;