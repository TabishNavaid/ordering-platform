<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Get cart count
$cart_count = 0;
if (is_logged_in() && !is_admin()) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cart_count = $result['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Local Provisions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <div class="logo">
                <a href="/">The Local Provisions</a>
            </div>
            <nav class="main-nav">
                <a href="/">Shop</a>
                <?php if (is_logged_in()): ?>
                    <?php if (!is_admin()): ?>
                        <a href="/orders.php">My Orders</a>
                        <a href="/cart.php" class="cart-link">
                            Cart <span class="badge"><?= $cart_count ?></span>
                        </a>
                    <?php endif; ?>
                    <a href="/logout.php">Logout</a>
                <?php else: ?>
                    <a href="/login.php">Login / Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">
