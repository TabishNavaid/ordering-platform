<?php
// ── Detect HTTPS correctly behind Railway's reverse proxy ─────────────────────
// Railway terminates TLS at the edge and forwards HTTP_X_FORWARDED_PROTO.
// We must check both the direct HTTPS flag and the forwarded header.
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// ── Session cookie hardening — must be called BEFORE session_start() ─────────
session_set_cookie_params([
    'lifetime' => 0,          // session cookie: dies when browser closes
    'path'     => '/',
    'domain'   => '',         // current domain only; no subdomains
    'secure'   => $is_https,  // HTTPS-only in production; works locally too
    'httponly' => true,       // JS cannot read this cookie (XSS mitigation)
    'samesite' => 'Strict',   // strongest CSRF protection; no cross-site sends
]);

session_start();

// ── Inactivity timeout: 2 hours ───────────────────────────────────────────────
$timeout = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// ── Helper functions ──────────────────────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: /admin/login.php');
        exit;
    }
}
?>
