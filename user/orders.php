<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is logged in
requireLogin();

$userId = $_SESSION['user_id'];
$status = $_GET['status'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = "WHERE o.user_id = ?";
$params = [$userId];

if ($status) {
    $whereClause .= " AND o.status = ?";
    $params[] = $status;
}

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM orders o 
    JOIN products p ON o.product_id = p.id 
    $whereClause
");
$countStmt->execute($params);
$totalOrders = $countStmt->fetch()['total'];
$totalPages = ceil($totalOrders / $limit);

// Get orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.category, p.ram, p.cpu_percent,
           s.pterodactyl_server_id, s.status as server_status
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    LEFT JOIN servers s ON o.id = s.order_id 
    $whereClause
    ORDER BY o.created_at DESC 
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
$orders = $stmt->fetchAll();

// Get statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status IN ('paid', 'processing', 'completed') THEN amount ELSE 0 END) as total_spent
    FROM orders 
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Order - Antidonasi Store</title>
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
                        <a class="nav-link active" href="orders.php">Order</a>
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
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-shopping-cart me-2"></i>Riwayat Order</h2>
                    <a href="../index.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Buat Order Baru
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary"><?php echo $stats['total_orders']; ?></h4>
                        <small class="text-muted">Total Order</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-warning"><?php echo $stats['pending_orders']; ?></h4>
                        <small class="text-muted">Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-info"><?php echo $stats['paid_orders']; ?></h4>
                        <small class="text-muted">Paid</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary"><?php echo $stats['processing_orders']; ?></h4>
                        <small class="text-muted">Processing</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success"><?php echo $stats['completed_orders']; ?></h4>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success">IDR <?php echo number_format($stats['total_spent'], 0, ',', '.'); ?></h4>
                        <small class="text-muted">Total Spent</small>
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
                                <h6 class="mb-0">Filter Status:</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="btn-group" role="group">
                                    <a href="orders.php" class="btn btn-outline-secondary <?php echo !$status ? 'active' : ''; ?>">
                                        Semua
                                    </a>
                                    <a href="orders.php?status=pending" class="btn btn-outline-warning <?php echo $status === 'pending' ? 'active' : ''; ?>">
                                        Pending
                                    </a>
                                    <a href="orders.php?status=paid" class="btn btn-outline-info <?php echo $status === 'paid' ? 'active' : ''; ?>">
                                        Paid
                                    </a>
                                    <a href="orders.php?status=processing" class="btn btn-outline-primary <?php echo $status === 'processing' ? 'active' : ''; ?>">
                                        Processing
                                    </a>
                                    <a href="orders.php?status=completed" class="btn btn-outline-success <?php echo $status === 'completed' ? 'active' : ''; ?>">
                                        Completed
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Daftar Order
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada order</h5>
                                <p class="text-muted">Mulai dengan membuat order pertama Anda</p>
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
                                            <th>Server</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($order['product_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo $order['ram']; ?>GB RAM, <?php echo $order['cpu_percent']; ?>% CPU
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>IDR <?php echo number_format($order['amount'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $order['status']; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($order['pterodactyl_server_id']): ?>
                                                        <span class="server-status <?php echo $order['server_status'] ?? 'unknown'; ?>">
                                                            <?php echo ucfirst($order['server_status'] ?? 'unknown'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Lihat Detail">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($order['status'] === 'pending'): ?>
                                                        <a href="../order_confirmation.php?order_id=<?php echo $order['id']; ?>" 
                                                           class="btn btn-sm btn-outline-success" 
                                                           title="Lanjutkan Pembayaran">
                                                            <i class="fas fa-credit-card"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        <?php if ($order['pterodactyl_server_id']): ?>
                                                        <a href="server_detail.php?id=<?php echo $order['pterodactyl_server_id']; ?>" 
                                                           class="btn btn-sm btn-outline-info" 
                                                           title="Kelola Server">
                                                            <i class="fas fa-server"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <nav aria-label="Order pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $status ? '&status=' . $status : ''; ?>">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html> 