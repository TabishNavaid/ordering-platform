<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (is_admin()) {
    header('Location: /admin/');
    exit;
}

$error = '';

// Rate limiting
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes

if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
    $_SESSION['admin_login_last_attempt'] = time();
}

if ($_SESSION['admin_login_attempts'] >= $max_attempts && (time() - $_SESSION['admin_login_last_attempt']) < $lockout_time) {
    $error = 'Too many attempts. Please try again in 15 minutes.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT id, name, password_hash, role FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['admin_login_attempts'] = 0; // Reset
            header('Location: /admin/');
            exit;
        } else {
            $_SESSION['admin_login_attempts']++;
            $_SESSION['admin_login_last_attempt'] = time();
            $error = 'Invalid admin credentials.';
        }
    }
}

require_once __DIR__ . '/../../includes/admin_header.php';
?>

<div class="auth-card mt-2 mb-2" style="margin-top: 4rem;">
    <h2>Admin Access</h2>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="/admin/login.php">
        <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
        
        <div class="form-group">
            <label for="email">Admin Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <button type="submit" class="btn" style="width: 100%">Login to Dashboard</button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
