<?php
// ── Environment detection ─────────────────────────────────────────────────────
// APP_ENV=production is set in Railway Variables tab.
// Locally it won't be set, so $is_local will be true.
$is_local = (getenv('APP_ENV') !== 'production')
    && in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']);

// ── Error visibility ──────────────────────────────────────────────────────────
if ($is_local) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// ── Credentials ───────────────────────────────────────────────────────────────
// Railway MySQL plugin automatically injects these env vars when linked.
// Local fallbacks are safe for development only.
$host    = getenv('MYSQLHOST')     ?: '127.0.0.1';
$port    = getenv('MYSQLPORT')     ?: '3306';
$db      = getenv('MYSQLDATABASE') ?: 'tau_ordering';
$user    = getenv('MYSQLUSER')     ?: 'root';
$pass    = getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '';
$charset = 'utf8mb4';

// ── DSN — explicit port for Railway's non-standard ports ─────────────────────
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

// ── Global exception handler ─────────────────────────────────────────────────
set_exception_handler(function (\Throwable $e) use ($is_local) {
    if ($is_local) {
        echo "<pre>Uncaught: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
           . "\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    } else {
        error_log("Uncaught exception: " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo "An unexpected error occurred. Please try again later.";
    }
    exit;
});

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if ($is_local) {
        die("Local DB Connection failed: " . $e->getMessage());
    }
    error_log("DB Connection failed: " . $e->getMessage());
    die("Database connection failed. The system administrator has been notified.");
}
?>
