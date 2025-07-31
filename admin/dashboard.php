<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is logged in and is admin
requireLogin();
requireAdmin();

// Get admin statistics
$userId = $_SESSION['user_id'];

// Total users
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stmt->execute();
$totalUsers = $stmt->fetch()['total'];

// Total orders
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders");
$stmt->execute();
$totalOrders = $stmt->fetch()['total'];

// Pending orders
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$stmt->execute();
$pendingOrders = $stmt->fetch()['total'];

// Total revenue
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM orders WHERE status IN ('paid', 'processing', 'completed')");
$stmt->execute();
$totalRevenue = $stmt->fetch()['total'] ?? 0;

// Active servers
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM servers WHERE status = 'running'");
$stmt->execute();
$activeServers = $stmt->fetch()['total'];

// Recent orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.category, u.username 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentOrders = $stmt->fetchAll();

// Recent users
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE role = 'user' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recentUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Antidonasi Store</title>
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">Order Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">User Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Product Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="servers.php">Server Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sync_pterodactyl.php">Sync Pterodactyl</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)
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
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                        </h4>
                        <p class="card-text">Kelola semua aspek sistem dari dashboard admin ini.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number text-primary"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number text-success"><?php echo $totalOrders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number text-warning"><?php echo $pendingOrders; ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number text-info">IDR <?php echo number_format($totalRevenue, 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-success">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="stat-number text-success"><?php echo $activeServers; ?></div>
                    <div class="stat-label">Active Servers</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number text-danger"><?php echo $totalRevenue > 0 ? round(($totalRevenue / $totalOrders), 0) : 0; ?></div>
                    <div class="stat-label">Avg Order Value</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Order Terbaru
                        </h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentOrders)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada order</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order Number</th>
                                            <th>User</th>
                                            <th>Produk</th>
                                            <th>Harga</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['username']); ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($order['product_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['category']); ?></small>
                                                    </div>
                                                </td>
                                                <td>IDR <?php echo number_format($order['amount'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $order['status']; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>User Terbaru
                        </h5>
                        <a href="users.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentUsers)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada user</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentUsers as $user): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="flex-shrink-0">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                        <br>
                                        <small class="text-muted">Bergabung: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></small>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <a href="user_detail.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Aksi Cepat
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="orders.php?status=pending" class="btn btn-warning w-100">
                                    <i class="fas fa-clock me-2"></i>Review Pending Orders
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="users.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-users me-2"></i>Kelola Users
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="products.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-box me-2"></i>Kelola Products
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="servers.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-server me-2"></i>Kelola Servers
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Status Sistem
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="border rounded p-3">
                                    <i class="fas fa-database fa-2x text-success mb-2"></i>
                                    <h6>Database</h6>
                                    <span class="badge bg-success">Online</span>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="border rounded p-3">
                                    <i class="fas fa-server fa-2x text-success mb-2"></i>
                                    <h6>Pterodactyl API</h6>
                                    <span class="badge bg-success">Connected</span>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="border rounded p-3">
                                    <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                    <h6>Security</h6>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="border rounded p-3">
                                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                    <h6>Last Backup</h6>
                                    <span class="badge bg-warning">2 hours ago</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html> 