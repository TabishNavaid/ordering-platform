<?php
// Require auth.php so that secure session_set_cookie_params() is called
// before session_start() — this ensures the cookie flags (HttpOnly, SameSite, Secure)
// are respected when we delete the cookie below.
require_once __DIR__ . '/../includes/auth.php';

// 1. Wipe all session variables
$_SESSION = [];

// 2. Expire the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 86400, // one day in the past
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the server-side session data
session_destroy();

header('Location: /login.php');
exit;
