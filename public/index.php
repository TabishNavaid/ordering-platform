<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    require_login();
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: /?error=' . urlencode('Invalid form submission'));
        exit;
    }
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($product_id > 0 && $quantity > 0) {
        $stmtProd = $pdo->prepare("SELECT stock_qty, active FROM products WHERE id = ?");
        $stmtProd->execute([$product_id]);
        $product = $stmtProd->fetch();
        
        if ($product && $product['active']) {
            $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            $existing = $stmt->fetch();
            
            $current_qty = $existing ? $existing['quantity'] : 0;
            if (($current_qty + $quantity) > $product['stock_qty']) {
                header('Location: /?error=' . urlencode('Not enough stock available'));
                exit;
            }
            
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$quantity, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
            }
            header('Location: /?added=1');
            exit;
        } else {
            header('Location: /?error=' . urlencode('Product not available'));
            exit;
        }
    }
}

// Fetch active products
$stmt = $pdo->query("SELECT * FROM products WHERE active = 1 ORDER BY category, name");
$products = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between align-center mb-2">
    <h1>Provisions</h1>
</div>

<div class="product-grid">
    <?php foreach ($products as $product): ?>
        <div class="product-card">
            <?php if ($product['image_path'] && file_exists(__DIR__ . '/uploads/' . $product['image_path'])): ?>
                <img src="/uploads/<?= h($product['image_path']) ?>" alt="<?= h($product['name']) ?>" class="product-image">
            <?php else: ?>
                <div class="product-image-placeholder"><?= h(substr($product['name'], 0, 1)) ?></div>
            <?php endif; ?>
            
            <div class="product-info">
                <div class="flex justify-between align-center mb-1">
                    <h3 class="product-title"><?= h($product['name']) ?></h3>
                    <div class="product-price">₹<?= number_format($product['price'], 2) ?></div>
                </div>
                <p class="product-desc"><?= h($product['description']) ?></p>
                <div class="product-actions mt-2">
                    <?php if ($product['stock_qty'] > 0): ?>
                        <form method="POST" action="/" class="flex gap-1 align-center">
                            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock_qty'] ?>" class="form-control" style="width: 70px;" <?php if (!is_logged_in() || is_admin()) echo 'disabled'; ?>>
                            <?php if (is_logged_in() && !is_admin()): ?>
                                <button type="submit" class="btn">Add to Cart</button>
                            <?php else: ?>
                                <a href="/login.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Login to Add</a>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled style="width: 100%">Out of Stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Toast container for add-to-cart feedback -->
<div class="toast-container" id="toast-container"></div>

<script>
// Toast notification system
function showToast(message, type = 'success', duration = 3500) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'toast toast--' + type;
    toast.innerHTML = (type === 'success' ? '✓ ' : '✕ ') + message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('toast--out');
        setTimeout(() => toast.remove(), 350);
    }, duration);
}

// Show toast based on URL query params then clean the URL
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('added')) {
        showToast('Added to cart! <a href="/cart.php" style="color:#fff; text-decoration:underline;">View Cart →</a>');
    } else if (params.get('error')) {
        showToast(params.get('error'), 'error');
    }
    // Remove query params from URL without page reload
    if (params.has('added') || params.has('error')) {
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, '', cleanUrl);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
