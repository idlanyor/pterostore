<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is admin
requireAdmin();

$category = $_GET['category'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($category) {
    $whereClause .= " AND category = ?";
    $params[] = $category;
}

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM products 
    $whereClause
");
$countStmt->execute($params);
$totalProducts = $countStmt->fetch()['total'];
$totalPages = ceil($totalProducts / $limit);

// Get products
$stmt = $pdo->prepare("
    SELECT p.*, 
           COUNT(o.id) as total_orders,
           SUM(CASE WHEN o.status IN ('paid', 'processing', 'completed') THEN o.amount ELSE 0 END) as total_revenue
    FROM products p 
    LEFT JOIN orders o ON p.id = o.product_id 
    $whereClause
    GROUP BY p.id
    ORDER BY p.category, p.ram, p.cpu_percent 
    LIMIT ? OFFSET ?
");

// Bind parameters with explicit types
$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex++, $param);
}
$stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Get product statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN category = 'NodeJS VIP' THEN 1 ELSE 0 END) as nodejs_products,
        SUM(CASE WHEN category = 'VPS' THEN 1 ELSE 0 END) as vps_products,
        SUM(CASE WHEN category = 'Python' THEN 1 ELSE 0 END) as python_products,
        AVG(price) as avg_price
    FROM products
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

// Handle product actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_product') {
        $name = trim($_POST['name']);
        $code = trim($_POST['code']);
        $category = trim($_POST['category']);
        $ram = (int)$_POST['ram'];
        $cpuPercent = (int)$_POST['cpu_percent'];
        $price = (int)$_POST['price'];
        $description = trim($_POST['description']);
        
        // Validate input
        if (empty($name) || empty($code) || empty($category) || $ram <= 0 || $cpuPercent <= 0 || $price <= 0) {
            $error = 'Semua field harus diisi dengan benar';
        } else {
            // Check if code exists
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
            $checkStmt->execute([$code]);
            if ($checkStmt->fetch()) {
                $error = 'Kode produk sudah digunakan';
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO products (name, code, category, ram, cpu_percent, price, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                if ($insertStmt->execute([$name, $code, $category, $ram, $cpuPercent, $price, $description])) {
                    $success = 'Produk berhasil ditambahkan';
                } else {
                    $error = 'Gagal menambahkan produk';
                }
            }
        }
    } elseif ($action === 'edit_product') {
        $productId = $_POST['product_id'];
        $name = trim($_POST['name']);
        $code = trim($_POST['code']);
        $category = trim($_POST['category']);
        $ram = (int)$_POST['ram'];
        $cpuPercent = (int)$_POST['cpu_percent'];
        $price = (int)$_POST['price'];
        $description = trim($_POST['description']);
        
        // Validate input
        if (empty($name) || empty($code) || empty($category) || $ram <= 0 || $cpuPercent <= 0 || $price <= 0) {
            $error = 'Semua field harus diisi dengan benar';
        } else {
            // Check if code exists (except current product)
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE code = ? AND id != ?");
            $checkStmt->execute([$code, $productId]);
            if ($checkStmt->fetch()) {
                $error = 'Kode produk sudah digunakan';
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, code = ?, category = ?, ram = ?, cpu_percent = ?, price = ?, description = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                if ($updateStmt->execute([$name, $code, $category, $ram, $cpuPercent, $price, $description, $productId])) {
                    $success = 'Produk berhasil diperbarui';
                } else {
                    $error = 'Gagal memperbarui produk';
                }
            }
        }
    } elseif ($action === 'delete_product') {
        $productId = $_POST['product_id'];
        
        // Check if product has orders
        $orderCheckStmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE product_id = ?");
        $orderCheckStmt->execute([$productId]);
        $orderCount = $orderCheckStmt->fetch()['order_count'];
        
        if ($orderCount > 0) {
            $error = 'Tidak dapat menghapus produk yang memiliki order';
        } else {
            $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            if ($deleteStmt->execute([$productId])) {
                $success = 'Produk berhasil dihapus';
            } else {
                $error = 'Gagal menghapus produk';
            }
        }
    }
    
    // Refresh products list
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-server me-2"></i>Antidonasi Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="servers.php">Servers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sync_pterodactyl.php">Sync Pterodactyl</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-box me-2"></i>Product Management</h2>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary"><?php echo $stats['total_products']; ?></h4>
                        <small class="text-muted">Total Products</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success"><?php echo $stats['nodejs_products']; ?></h4>
                        <small class="text-muted">NodeJS VIP</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-info"><?php echo $stats['vps_products']; ?></h4>
                        <small class="text-muted">VPS</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-warning"><?php echo $stats['python_products']; ?></h4>
                        <small class="text-muted">Python</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success">IDR <?php echo number_format($stats['avg_price'], 0, ',', '.'); ?></h4>
                        <small class="text-muted">Avg Price</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary">18</h4>
                        <small class="text-muted">Active Products</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-0">Filter Category:</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="btn-group" role="group">
                                    <a href="products.php" class="btn btn-outline-secondary <?php echo !$category ? 'active' : ''; ?>">
                                        Semua
                                    </a>
                                    <a href="products.php?category=NodeJS VIP" class="btn btn-outline-success <?php echo $category === 'NodeJS VIP' ? 'active' : ''; ?>">
                                        NodeJS VIP
                                    </a>
                                    <a href="products.php?category=VPS" class="btn btn-outline-info <?php echo $category === 'VPS' ? 'active' : ''; ?>">
                                        VPS
                                    </a>
                                    <a href="products.php?category=Python" class="btn btn-outline-warning <?php echo $category === 'Python' ? 'active' : ''; ?>">
                                        Python
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Daftar Products
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada produk</h5>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                    <i class="fas fa-plus me-2"></i>Add First Product
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Specs</th>
                                            <th>Price</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['code']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <?php if ($product['description']): ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $product['category'] === 'NodeJS VIP' ? 'success' : 
                                                            ($product['category'] === 'VPS' ? 'info' : 'warning'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($product['category']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $product['ram']; ?>GB RAM<br>
                                                        <?php echo $product['cpu_percent']; ?>% CPU
                                                    </small>
                                                </td>
                                                <td>IDR <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <div class="text-center">
                                                        <strong><?php echo $product['total_orders']; ?></strong>
                                                        <br>
                                                        <small class="text-muted">orders</small>
                                                    </div>
                                                </td>
                                                <td>IDR <?php echo number_format($product['total_revenue'] ?? 0, 0, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary edit-product" 
                                                                data-product='<?php echo json_encode($product); ?>'
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editProductModal"
                                                                title="Edit Product">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-product" 
                                                                data-product-id="<?php echo $product['id']; ?>"
                                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                                title="Delete Product">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <nav aria-label="Product pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_product">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="code" class="form-label">Product Code</label>
                                <input type="text" class="form-control" id="code" name="code" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="NodeJS VIP">NodeJS VIP</option>
                                    <option value="VPS">VPS</option>
                                    <option value="Python">Python</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price (IDR)</label>
                                <input type="number" class="form-control" id="price" name="price" min="0" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ram" class="form-label">RAM (GB)</label>
                                <input type="number" class="form-control" id="ram" name="ram" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cpu_percent" class="form-label">CPU (%)</label>
                                <input type="number" class="form-control" id="cpu_percent" name="cpu_percent" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_product">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_name" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_code" class="form-label">Product Code</label>
                                <input type="text" class="form-control" id="edit_code" name="code" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_category" class="form-label">Category</label>
                                <select class="form-select" id="edit_category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="NodeJS VIP">NodeJS VIP</option>
                                    <option value="VPS">VPS</option>
                                    <option value="Python">Python</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_price" class="form-label">Price (IDR)</label>
                                <input type="number" class="form-control" id="edit_price" name="price" min="0" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_ram" class="form-label">RAM (GB)</label>
                                <input type="number" class="form-control" id="edit_ram" name="ram" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_cpu_percent" class="form-label">CPU (%)</label>
                                <input type="number" class="form-control" id="edit_cpu_percent" name="cpu_percent" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Product actions
        $('.edit-product').on('click', function() {
            var product = $(this).data('product');
            
            $('#edit_product_id').val(product.id);
            $('#edit_name').val(product.name);
            $('#edit_code').val(product.code);
            $('#edit_category').val(product.category);
            $('#edit_price').val(product.price);
            $('#edit_ram').val(product.ram);
            $('#edit_cpu_percent').val(product.cpu_percent);
            $('#edit_description').val(product.description);
        });

        $('.delete-product').on('click', function() {
            var productId = $(this).data('product-id');
            var productName = $(this).data('product-name');
            
            if (confirm('Hapus produk "' + productName + '"?')) {
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="delete_product">');
                form.append('<input type="hidden" name="product_id" value="' + productId + '">');
                $('body').append(form);
                form.submit();
            }
        });
    </script>
</body>
</html> 