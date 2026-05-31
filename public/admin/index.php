<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin();

// Get today's stats
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

$stmtOrders = $pdo->prepare("SELECT COUNT(*) as count, SUM(total_price) as revenue FROM orders WHERE created_at BETWEEN ? AND ?");
$stmtOrders->execute([$todayStart, $todayEnd]);
$todayStats = $stmtOrders->fetch();

$stmtPending = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
$pendingCount = $stmtPending->fetch()['count'];

$stmtRecent = $pdo->query("
    SELECT o.id, o.status, o.total_price, o.created_at, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$recentOrders = $stmtRecent->fetchAll();

// UX polish: fetch low-stock products for the dashboard warning banner
$stmtLowStock = $pdo->query("SELECT id, name, stock_qty FROM products WHERE active = 1 AND stock_qty <= 5 ORDER BY stock_qty ASC");
$lowStockProducts = $stmtLowStock->fetchAll();

require_once __DIR__ . '/../../includes/admin_header.php';
?>

<h1>Dashboard Overview</h1>

<?php if (!empty($lowStockProducts)): ?>
<div class="alert alert-low-stock" role="alert">
    <strong>⚠ Low Stock Alert:</strong>
    <?php
        $names = array_map(fn($p) => h($p['name']) . ' (' . (int)$p['stock_qty'] . ' left)', $lowStockProducts);
        echo implode(' &bull; ', $names);
    ?>
    &mdash; <a href="/admin/products.php" style="color: inherit; text-decoration: underline;">Manage Inventory</a>
</div>
<?php endif; ?>

<div class="dashboard-cards mt-2">
    <div class="stat-card">
        <h3>Today's Orders</h3>
        <div class="value"><?= (int)$todayStats['count'] ?></div>
    </div>
    <div class="stat-card">
        <h3>Today's Revenue</h3>
        <div class="value">₹<?= number_format((float)$todayStats['revenue'], 2) ?></div>
    </div>
    <div class="stat-card">
        <h3>Pending Orders</h3>
        <div class="value" <?= $pendingCount > 0 ? 'style="color: var(--error-color);"' : '' ?>><?= $pendingCount ?></div>
    </div>
</div>

<h2>Recent Orders</h2>
<div class="table-responsive mt-1">
    <table class="table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentOrders as $order): ?>
                <tr <?= $order['status'] === 'pending' ? 'style="background-color: #FFFDF5;"' : '' ?>>
                    <td><?= h($order['id']) ?></td>
                    <td><?= h($order['customer_name']) ?></td>
                    <td><?= h(format_time($order['created_at'])) ?></td>
                    <td>₹<?= number_format($order['total_price'], 2) ?></td>
                    <td><span class="status-badge status-<?= h($order['status']) ?>"><?= h($order['status']) ?></span></td>
                    <td><a href="/admin/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-small">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentOrders)): ?>
                <tr><td colspan="6">No orders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
