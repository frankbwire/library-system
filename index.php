<?php
require_once __DIR__ . '/config/app.php';
if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
} else {
    redirect(BASE_URL . '/auth/login.php');
}
