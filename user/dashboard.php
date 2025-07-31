<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is logged in
requireLogin();

// Get user statistics
$userId = $_SESSION['user_id'];

// Total orders
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$stmt->execute([$userId]);
$totalOrders = $stmt->fetch()['total'];

// Active servers
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM orders o 
    JOIN servers s ON o.id = s.order_id 
    WHERE o.user_id = ? AND o.status = 'completed'
");
$stmt->execute([$userId]);
$activeServers = $stmt->fetch()['total'];

// Total spent
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM orders WHERE user_id = ? AND status IN ('paid', 'processing', 'completed')");
$stmt->execute([$userId]);
$totalSpent = $stmt->fetch()['total'] ?? 0;

// Recent orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.category 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$recentOrders = $stmt->fetchAll();

// Active servers details
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.ram, p.cpu_percent, s.pterodactyl_server_id, s.status as server_status
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    LEFT JOIN servers s ON o.id = s.order_id 
    WHERE o.user_id = ? AND o.status = 'completed'
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId]);
$activeServersList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Antidonasi Store</title>
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
                        <a class="nav-link" href="orders.php">Order</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="servers.php">Server</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
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
                            <i class="fas fa-home me-2"></i>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                        </h4>
                        <p class="card-text">Kelola server dan order Anda dari dashboard ini.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number text-primary"><?php echo $totalOrders; ?></div>
                    <div class="stat-label">Total Order</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-success">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="stat-number text-success"><?php echo $activeServers; ?></div>
                    <div class="stat-label">Server Aktif</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-stats text-center">
                    <div class="stat-icon text-info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number text-info">IDR <?php echo number_format($totalSpent, 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Pengeluaran</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Order Terbaru
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentOrders)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada order</p>
                                <a href="../index.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Buat Order Pertama
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order Number</th>
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
                            <div class="text-center mt-3">
                                <a href="orders.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>Lihat Semua Order
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Servers -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-server me-2"></i>Server Aktif
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeServersList)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-server fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada server aktif</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activeServersList as $server): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($server['product_name']); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="fas fa-memory me-1"></i><?php echo $server['ram']; ?>GB RAM<br>
                                                <i class="fas fa-microchip me-1"></i><?php echo $server['cpu_percent']; ?>% CPU
                                            </small>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="server-status <?php echo $server['server_status'] ?? 'installing'; ?>">
                                                <?php echo ucfirst($server['server_status'] ?? 'installing'); ?>
                                            </span>
                                            <a href="server_detail.php?id=<?php echo $server['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-cog"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="servers.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-list me-2"></i>Lihat Semua Server
                                </a>
                            </div>
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
                                <a href="../index.php" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Buat Order Baru
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="orders.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-list me-2"></i>Lihat Order
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="servers.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-server me-2"></i>Kelola Server
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="profile.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-user me-2"></i>Edit Profil
                                </a>
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