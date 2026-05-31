<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin();

$status_filter = $_GET['status'] ?? '';
$query = "
    SELECT o.id, o.status, o.total_price, o.created_at, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
    (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
";

$params = [];
if ($status_filter) {
    $query .= " WHERE o.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY o.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-2">
    <h1>All Orders</h1>
    <div>
        <form method="GET" action="/admin/orders.php" class="flex gap-1 align-center">
            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="preparing" <?= $status_filter === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                <option value="ready" <?= $status_filter === 'ready' ? 'selected' : '' ?>>Ready</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr <?= $order['status'] === 'pending' ? 'style="background-color: #FFFDF5;"' : '' ?>>
                    <td><?= h($order['id']) ?></td>
                    <td>
                        <?= h($order['customer_name']) ?><br>
                        <small style="color: var(--text-light);">
                            <?php if ($order['customer_email']): ?><?= h($order['customer_email']) ?><br><?php endif; ?>
                            <?php if ($order['customer_phone']): ?>+91 <?= h($order['customer_phone']) ?><?php endif; ?>
                        </small>
                    </td>
                    <td><?= h(format_time($order['created_at'])) ?></td>
                    <td><?= h($order['item_count']) ?></td>
                    <td>₹<?= number_format($order['total_price'], 2) ?></td>
                    <td><span class="status-badge status-<?= h($order['status']) ?>"><?= h($order['status']) ?></span></td>
                    <td><a href="/admin/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-small">View Details</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7">No orders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
