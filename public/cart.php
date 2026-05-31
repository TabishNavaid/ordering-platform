<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
if (is_admin()) {
    header('Location: /admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update') {
            foreach ($_POST['quantity'] as $cart_id => $qty) {
                $qty = (int)$qty;
                if ($qty > 0) {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$qty, $cart_id, $_SESSION['user_id']]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                    $stmt->execute([$cart_id, $_SESSION['user_id']]);
                }
            }
            header('Location: /cart.php');
            exit;

        } elseif ($action === 'remove') {
            $cart_id = (int)$_POST['cart_id'];
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, $_SESSION['user_id']]);
            header('Location: /cart.php');
            exit;

        } elseif ($action === 'checkout') {
            // Medium fix #8: cap notes length server-side to prevent oversized payloads
            $notes = mb_substr(trim($_POST['notes'] ?? ''), 0, 500);

            try {
                $pdo->beginTransaction();

                // Fetch cart items — only active products
                $stmt = $pdo->prepare("
                    SELECT c.product_id, c.quantity, p.price, p.stock_qty, p.name
                    FROM cart c
                    JOIN products p ON c.product_id = p.id
                    WHERE c.user_id = ? AND p.active = 1
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $items = $stmt->fetchAll();

                if (empty($items)) {
                    throw new Exception("Your cart is empty or contains unavailable products.");
                }

                // Medium fix #6: acquire row-level locks (SELECT FOR UPDATE) on all product
                // rows before the pre-flight check. This serialises concurrent checkouts
                // so two users cannot both pass the pre-flight for the same last item.
                $productIds  = array_column($items, 'product_id');
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $lockStmt = $pdo->prepare("SELECT id FROM products WHERE id IN ($placeholders) FOR UPDATE");
                $lockStmt->execute($productIds);

                // Pre-flight stock check (prevents a wasted INSERT if obviously out of stock)
                $total_price = 0;
                foreach ($items as $item) {
                    if ($item['quantity'] > $item['stock_qty']) {
                        throw new Exception("Not enough stock for " . $item['name'] . ". Available: " . $item['stock_qty']);
                    }
                    $total_price += $item['price'] * $item['quantity'];
                }

                // Create the order
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_price, notes) VALUES (?, 'pending', ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $total_price, $notes]);
                $order_id = $pdo->lastInsertId();

                // Insert order items and atomically reduce stock
                $stmtItem  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $stmtStock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?");

                foreach ($items as $item) {
                    $stmtItem->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
                    $stmtStock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);

                    if ($stmtStock->rowCount() === 0) {
                        throw new Exception("Not enough stock for " . $item['name'] . ". Please review your cart.");
                    }
                }

                // Clear cart
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);

                $pdo->commit();
                header('Location: /orders.php?success=1');
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image_path, p.stock_qty
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1>Your Cart</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (empty($cart_items)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">🛒</div>
        <h2>Your cart is empty</h2>
        <p>You haven't added anything yet. Head back to the shop and find something you'll love.</p>
        <a href="/" class="btn">Browse Provisions</a>
    </div>
<?php else: ?>
    <div class="flex gap-1" style="flex-wrap: wrap;">
        <div style="flex: 2; min-width: 300px;">
            <div class="table-responsive">
                <form method="POST" action="/cart.php" id="update-form">
                    <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <tr>
                                    <td><?= h($item['name']) ?></td>
                                    <td>₹<?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <input type="number" name="quantity[<?= $item['cart_id'] ?>]" value="<?= $item['quantity'] ?>" min="0" max="<?= $item['stock_qty'] ?>" class="form-control" style="width: 80px;">
                                    </td>
                                    <td>₹<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-small" onclick="document.getElementById('remove-<?= $item['cart_id'] ?>').submit();">Remove</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="flex justify-between align-center" style="padding: 1rem;">
                        <a href="/" class="btn btn-secondary">Continue Shopping</a>
                        <button type="submit" class="btn btn-secondary">Update Cart</button>
                    </div>
                </form>
            </div>
            
            <?php foreach ($cart_items as $item): ?>
                <form method="POST" action="/cart.php" id="remove-<?= $item['cart_id'] ?>" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                </form>
            <?php endforeach; ?>
        </div>
        
        <div style="flex: 1; min-width: 300px;">
            <div class="auth-card" style="margin: 0; max-width: 100%;">
                <h3>Order Summary</h3>
                <div class="flex justify-between mt-1 mb-2">
                    <span>Subtotal</span>
                    <strong>₹<?= number_format($subtotal, 2) ?></strong>
                </div>
                
                <form method="POST" action="/cart.php">
                    <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="checkout">
                    
                    <div class="form-group">
                        <label for="notes">Order Notes (Optional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Any special requests?"></textarea>
                    </div>
                    
                    <button type="submit" class="btn" style="width: 100%;">Place Order</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// UX: Checkout button loading state — prevents double-submit and shows spinner
document.addEventListener('DOMContentLoaded', function () {
    const checkoutForm = document.querySelector('form input[name="action"][value="checkout"]')?.closest('form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function () {
            const btn = this.querySelector('[type="submit"]');
            if (btn) {
                btn.classList.add('btn--loading');
                btn.textContent = 'Placing Order…';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>