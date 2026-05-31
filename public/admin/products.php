<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: /admin/products.php?error=' . urlencode('Invalid form submission'));
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $stock_qty = (int)($_POST['stock_qty'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name) || $price < 0 || $stock_qty < 0 || strlen($category) > 100) {
            header('Location: /admin/products.php?error=' . urlencode('Invalid product details.'));
            exit;
        }
        
        $image_path = '';
        if ($action === 'edit') {
            $stmtImg = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
            $stmtImg->execute([$id]);
            $image_path = $stmtImg->fetchColumn() ?: '';
        }
        
        $upload_error = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($file_ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) {
                $upload_error = 'Invalid image type. Only JPG, PNG, and WebP are allowed.';
            } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $upload_error = 'Image size must be less than 2MB.';
            } elseif (!getimagesize($_FILES['image']['tmp_name'])) {
                $upload_error = 'Uploaded file is not a valid image.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/';
                $filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = $filename;
                } else {
                    $upload_error = 'Failed to save uploaded image.';
                }
            }
        }
        
        if ($upload_error) {
            header('Location: /admin/products.php?error=' . urlencode($upload_error));
            exit;
        }
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, stock_qty, active, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $category, $stock_qty, $active, $image_path]);
        } else {
            // Medium fix #9: verify the product exists before running UPDATE
            $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmtCheck->execute([$id]);
            if (!$stmtCheck->fetch()) {
                header('Location: /admin/products.php?error=' . urlencode('Product not found.'));
                exit;
            }
            $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, category=?, stock_qty=?, active=?, image_path=? WHERE id=?");
            $stmt->execute([$name, $description, $price, $category, $stock_qty, $active, $image_path, $id]);
        }
        header('Location: /admin/products.php?success=1');
        exit;
    }
}

$stmt = $pdo->query("SELECT * FROM products ORDER BY category, name");
$products = $stmt->fetchAll();

$stmtCats = $pdo->query("SELECT DISTINCT category FROM products WHERE category != '' ORDER BY category");
$categories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-2">
    <h1>Products</h1>
    <button class="btn" onclick="openModal()">Add New Product</button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Product saved successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error"><?= h($_GET['error']) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr <?= $product['stock_qty'] <= 5 ? 'style="background-color: #FFF3CD;"' : '' ?>>
                    <td>
                        <?php if ($product['image_path']): ?>
                            <img src="/uploads/<?= h($product['image_path']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                            <div style="width: 40px; height: 40px; background: var(--border-color); border-radius: 4px;"></div>
                        <?php endif; ?>
                    </td>
                    <td><?= h($product['name']) ?></td>
                    <td><?= h($product['category']) ?></td>
                    <td>₹<?= number_format($product['price'], 2) ?></td>
                    <td>
                        <?php if ($product['stock_qty'] <= 5): ?>
                            <strong style="color: var(--error-color);"><?= h($product['stock_qty']) ?> (Low)</strong>
                        <?php else: ?>
                            <?= h($product['stock_qty']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($product['active']): ?>
                            <span class="status-badge status-completed">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-cancelled">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-small" onclick='editProduct(<?= json_encode($product) ?>)'>Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="productModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="auth-card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div class="flex justify-between align-center mb-2">
            <h2 id="modalTitle">Add Product</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="/admin/products.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="productId" value="">
            <input type="hidden" name="current_image" id="currentImage" value="">
            
            <div class="flex gap-1" style="flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 250px;">
                    <label>Name</label>
                    <input type="text" name="name" id="productName" class="form-control" required>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label>Category</label>
                    <input type="text" name="category" id="productCategory" class="form-control" list="categoryList">
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="productDescription" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="flex gap-1" style="flex-wrap: wrap;">
                <div class="form-group" style="flex: 1;">
                    <label>Price (₹)</label>
                    <input type="number" step="0.01" name="price" id="productPrice" class="form-control" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_qty" id="productStock" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <small id="imageHelp" style="display: block; margin-top: 0.25rem; color: var(--text-light);"></small>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="active" id="productActive" value="1" checked>
                <label for="productActive" style="margin-bottom: 0;">Active (visible to customers)</label>
            </div>
            
            <div class="flex justify-between mt-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value = 'add';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productCategory').value = '';
    document.getElementById('productDescription').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productStock').value = '';
    document.getElementById('currentImage').value = '';
    document.getElementById('imageHelp').textContent = '';
    document.getElementById('productActive').checked = true;
    
    document.getElementById('productModal').style.display = 'flex';
}

function editProduct(product) {
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productCategory').value = product.category || '';
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productStock').value = product.stock_qty;
    document.getElementById('currentImage').value = product.image_path || '';
    
    if (product.image_path) {
        document.getElementById('imageHelp').textContent = 'Current image: ' + product.image_path + '. Upload new to replace.';
    } else {
        document.getElementById('imageHelp').textContent = '';
    }
    
    document.getElementById('productActive').checked = product.active == 1;
    
    document.getElementById('productModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
