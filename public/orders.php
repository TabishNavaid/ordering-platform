<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
if (is_admin()) {
    header('Location: /admin/');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// High fix #3: Eliminate N+1 — fetch ALL items for ALL orders in one query,
// then group by order_id in PHP. Previously this was one query per order.
$itemsByOrder = [];
if (!empty($orders)) {
    $orderIds     = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmtItems = $pdo->prepare("
        SELECT oi.order_id, oi.quantity, oi.unit_price, p.name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_id, oi.id
    ");
    $stmtItems->execute($orderIds);
    foreach ($stmtItems->fetchAll() as $item) {
        $itemsByOrder[$item['order_id']][] = $item;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1>My Orders</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" id="order-success-banner">Your order has been placed successfully! 🎉</div>
<?php endif; ?>

<?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">🛍️</div>
        <h2>No orders yet</h2>
        <p>Looks like you haven't placed an order with us yet. Browse our provisions and find something you'll love.</p>
        <a href="/" class="btn">Start Shopping</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= h($order['id']) ?></td>
                        <td><?= h(format_time($order['created_at'])) ?></td>
                        <td>₹<?= number_format($order['total_price'], 2) ?></td>
                        <td>
                            <span class="status-badge status-<?= h($order['status']) ?><?= in_array($order['status'], ['pending', 'confirmed', 'preparing']) ? ' status-badge--pulse' : '' ?>">
                                <?= h($order['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-secondary btn-small" onclick="toggleDetails(<?= $order['id'] ?>)">View Items</button>
                        </td>
                    </tr>
                    <tr id="details-<?= $order['id'] ?>" style="display: none; background-color: var(--bg-color);">
                        <td colspan="5">
                            <?php $items = $itemsByOrder[$order['id']] ?? []; ?>
                            <?php if (!empty($items)): ?>
                                <ul style="list-style: none; padding-left: 0; margin: 0.5rem 0;">
                                    <?php foreach ($items as $item): ?>
                                        <li class="flex justify-between mb-1" style="border-bottom: 1px dashed var(--border-color); padding-bottom: 0.25rem;">
                                            <span><?= h($item['quantity']) ?>x <?= h($item['name']) ?></span>
                                            <span>₹<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($order['notes'])): ?>
                                <p style="margin-top: 0.5rem; font-size: 0.9rem;"><strong>Notes:</strong> <?= nl2br(h($order['notes'])) ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
function toggleDetails(orderId) {
    const el = document.getElementById('details-' + orderId);
    if (el.style.display === 'none') {
        el.style.display = 'table-row';
    } else {
        el.style.display = 'none';
    }
}

// Auto-dismiss the success banner after 5 seconds
const successBanner = document.getElementById('order-success-banner');
if (successBanner) {
    setTimeout(() => {
        successBanner.style.transition = 'opacity 0.6s ease';
        successBanner.style.opacity = '0';
        setTimeout(() => successBanner.remove(), 600);
    }, 5000);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
