<?php
require_once __DIR__ . '/../config/app.php';
if (isLoggedIn()) auditLog('logout', 'users', (int)$_SESSION['user_id'], 'User logged out');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', time()-42000, '/');
}
session_destroy();
redirect(BASE_URL . '/auth/login.php');
