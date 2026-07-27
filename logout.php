<?php
require_once 'includes/session.php';

$_SESSION = [];
session_unset();
session_destroy();
// Also clear the remember-me cookie if one was set.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

header('Location: index.php');
exit;