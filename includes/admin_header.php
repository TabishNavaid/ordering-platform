<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - The Local Provisions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="admin-body">
    <!-- require_admin() is always called before this file is included,
         so we can render the nav unconditionally here. -->
    <header class="admin-header">
        <div class="container header-inner">
            <div class="logo">
                <a href="/admin/">Admin Dashboard</a>
            </div>
            <nav class="main-nav">
                <a href="/admin/">Overview</a>
                <a href="/admin/orders.php">Orders</a>
                <a href="/admin/products.php">Products</a>
                <a href="/admin/logout.php">Logout</a>
            </nav>
        </div>
    </header>
    <main class="container admin-main">
