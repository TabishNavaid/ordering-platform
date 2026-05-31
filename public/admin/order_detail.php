<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin();

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    header('Location: /admin/orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Critical fix: verify_csrf_token() return value was previously ignored.
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: /admin/order_detail.php?id=' . $order_id . '&error=invalid_token');
        exit;
    }
    $new_status = $_POST['status'] ?? '';
    
    if (in_array($new_status, ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'])) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        header("Location: /admin/order_detail.php?id=$order_id&updated=1");
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    // Fix: use a proper redirect instead of die() to maintain layout integrity
    // and return a meaningful HTTP status.
    http_response_code(404);
    header('Location: /admin/orders.php?error=order_not_found');
    exit;
}

$stmtItems = $pdo->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll();

require_once __DIR__ . '/../../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-2 print-hide">
    <div class="flex align-center gap-1">
        <a href="/admin/orders.php" class="btn btn-secondary btn-small">&larr; Back</a>
        <h1 style="margin-bottom: 0;">Order #<?= h($order['id']) ?></h1>
    </div>
    <button onclick="window.print()" class="btn btn-secondary">Print Receipt</button>
</div>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success print-hide">Order status updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_token'): ?>
    <div class="alert alert-error print-hide">Security check failed. Please try again.</div>
<?php endif; ?>

<div class="flex gap-1" style="flex-wrap: wrap;">
    <div style="flex: 2; min-width: 300px;">
        <div class="auth-card" style="margin: 0; max-width: 100%;">
            <h3>Items</h3>
            <div class="table-responsive mt-1">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= h($item['name']) ?></td>
                                <td><?= h($item['quantity']) ?></td>
                                <td>₹<?= number_format($item['unit_price'], 2) ?></td>
                                <td>₹<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Total:</strong></td>
                            <td><strong>₹<?= number_format($order['total_price'], 2) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if (!empty($order['notes'])): ?>
                <div class="mt-2">
                    <h4>Customer Notes</h4>
                    <p style="background: var(--bg-color); padding: 1rem; border-radius: var(--border-radius);"><?= nl2br(h($order['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="flex: 1; min-width: 300px;">
        <div class="auth-card print-hide" style="margin: 0; max-width: 100%;">
            <h3>Manage Order</h3>
            <form method="POST" action="/admin/order_detail.php?id=<?= $order['id'] ?>" class="mt-1">
                <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                <div class="form-group">
                    <label>Status</label>
                    <div class="flex gap-1">
                        <select name="status" class="form-control">
                            <?php foreach (['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'] as $st): ?>
                                <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn">Update</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="auth-card mt-2" style="margin-left: 0; margin-right: 0; max-width: 100%;">
            <h3>Customer Info</h3>
            <p class="mt-1"><strong>Name:</strong> <?= h($order['customer_name']) ?></p>
            <?php if ($order['customer_phone']): ?>
                <p><strong>Phone:</strong> +91 <?= h($order['customer_phone']) ?></p>
            <?php endif; ?>
            <?php if ($order['customer_email']): ?>
                <p><strong>Email:</strong> <?= h($order['customer_email']) ?></p>
            <?php endif; ?>
            <p><strong>Ordered:</strong> <?= h(format_time($order['created_at'])) ?></p>
            <?php if ($order['updated_at'] !== $order['created_at']): ?>
                <p><strong>Last Updated:</strong> <?= h(format_time($order['updated_at'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    .print-hide, .admin-header, .site-footer { display: none !important; }
    body { background: white; color: black; }
    .container { width: 100%; max-width: 100%; padding: 0; }
    .auth-card { box-shadow: none; border: none; padding: 0; }
    .table-responsive { border: none; box-shadow: none; }
    .table th, .table td { border-bottom: 1px solid #ddd; }
}
</style>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
