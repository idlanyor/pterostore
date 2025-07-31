<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is admin
requireAdmin();

$status = $_GET['status'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($status) {
    $whereClause .= " AND o.status = ?";
    $params[] = $status;
}

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    $whereClause
");
$countStmt->execute($params);
$totalOrders = $countStmt->fetch()['total'];
$totalPages = ceil($totalOrders / $limit);

// Get orders
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email, p.name as product_name, p.category, p.ram, p.cpu_percent,
           s.pterodactyl_server_id, s.status as server_status
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
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

// Get order statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status IN ('paid', 'processing', 'completed') THEN amount ELSE 0 END) as total_revenue
    FROM orders
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

// Handle order actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = $_POST['order_id'] ?? 0;
    
    if ($action === 'confirm_payment') {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'paid', updated_at = NOW() WHERE id = ?");
        if ($updateStmt->execute([$orderId])) {
            $success = 'Pembayaran berhasil dikonfirmasi';
        } else {
            $error = 'Gagal mengkonfirmasi pembayaran';
        }
    } elseif ($action === 'process_order') {
        // Get order details
        $orderStmt = $pdo->prepare("
            SELECT o.*, p.name as product_name, p.ram, p.cpu_percent, p.category, u.username, u.email
            FROM orders o 
            JOIN products p ON o.product_id = p.id 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        
        if ($order) {
            try {
                $pterodactyl = new PterodactylAPI();
                
                // Create user in Pterodactyl if not exists
                $pterodactylUser = $pterodactyl->getUser($order['user_id']);
                if (!$pterodactylUser || isset($pterodactylUser['error'])) {
                    $pterodactylUser = $pterodactyl->createUser(
                        $order['username'],
                        $order['email'],
                        $order['username'],
                        $order['username']
                    );
                }
                
                if ($pterodactylUser && !isset($pterodactylUser['error'])) {
                    // Get available nodes and allocations
                    $nodes = $pterodactyl->getNodes();
                    if ($nodes && !isset($nodes['error']) && !empty($nodes['data'])) {
                        $node = $nodes['data'][0]; // Use first available node
                        $allocations = $pterodactyl->getAllocations($node['attributes']['id']);
                        
                        if ($allocations && !isset($allocations['error']) && !empty($allocations['data'])) {
                            $allocation = $allocations['data'][0]; // Use first available allocation
                            
                            // Create server
                            $serverName = $order['product_name'] . '-' . $order['order_number'];
                            $server = $pterodactyl->createServer(
                                $serverName,
                                $pterodactylUser['attributes']['id'],
                                $node['attributes']['id'],
                                $allocation['attributes']['id'],
                                1, // Default egg ID
                                $order['ram'],
                                $order['cpu_percent']
                            );
                            
                            if ($server && !isset($server['error'])) {
                                // Update order status and create server record
                                $pdo->beginTransaction();
                                
                                $updateOrderStmt = $pdo->prepare("UPDATE orders SET status = 'completed', updated_at = NOW() WHERE id = ?");
                                $updateOrderStmt->execute([$orderId]);
                                
                                $insertServerStmt = $pdo->prepare("
                                    INSERT INTO servers (order_id, pterodactyl_server_id, name, status, created_at) 
                                    VALUES (?, ?, ?, 'installing', NOW())
                                ");
                                $insertServerStmt->execute([$orderId, $server['attributes']['id'], $serverName]);
                                
                                $pdo->commit();
                                $success = 'Order berhasil diproses dan server dibuat';
                            } else {
                                $pdo->rollBack();
                                $error = 'Gagal membuat server di Pterodactyl';
                            }
                        } else {
                            $error = 'Tidak ada allocation yang tersedia';
                        }
                    } else {
                        $error = 'Tidak ada node yang tersedia';
                    }
                } else {
                    $error = 'Gagal membuat user di Pterodactyl';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        } else {
            $error = 'Order tidak ditemukan';
        }
    } elseif ($action === 'reject_order') {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        if ($updateStmt->execute([$orderId])) {
            $success = 'Order berhasil ditolak';
        } else {
            $error = 'Gagal menolak order';
        }
    }
    
    // Refresh orders list
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Admin Panel</title>
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
                        <a class="nav-link active" href="orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="servers.php">Servers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Products</a>
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
                    <h2><i class="fas fa-shopping-cart me-2"></i>Order Management</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
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
                        <h4 class="text-primary"><?php echo $stats['total_orders']; ?></h4>
                        <small class="text-muted">Total Orders</small>
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
                        <h4 class="text-success">IDR <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></h4>
                        <small class="text-muted">Total Revenue</small>
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
                            <i class="fas fa-list me-2"></i>Daftar Orders
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada orders</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order Number</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Server</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong>
                                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                                        </a>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($order['username']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                                    </div>
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
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#orderModal" 
                                                                data-order='<?php echo json_encode($order); ?>'
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($order['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-success confirm-payment" 
                                                                data-order-id="<?php echo $order['id']; ?>"
                                                                title="Confirm Payment">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <?php if ($order['status'] === 'paid'): ?>
                                                        <button class="btn btn-sm btn-outline-primary process-order" 
                                                                data-order-id="<?php echo $order['id']; ?>"
                                                                title="Process Order">
                                                            <i class="fas fa-cog"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <?php if ($order['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-danger reject-order" 
                                                                data-order-id="<?php echo $order['id']; ?>"
                                                                title="Reject Order">
                                                            <i class="fas fa-times"></i>
                                                        </button>
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

    <!-- Order Detail Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderModalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Order actions
        $('.confirm-payment').on('click', function() {
            if (confirm('Konfirmasi pembayaran untuk order ini?')) {
                var orderId = $(this).data('order-id');
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="confirm_payment">');
                form.append('<input type="hidden" name="order_id" value="' + orderId + '">');
                $('body').append(form);
                form.submit();
            }
        });

        $('.process-order').on('click', function() {
            if (confirm('Proses order ini dan buat server?')) {
                var orderId = $(this).data('order-id');
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="process_order">');
                form.append('<input type="hidden" name="order_id" value="' + orderId + '">');
                $('body').append(form);
                form.submit();
            }
        });

        $('.reject-order').on('click', function() {
            if (confirm('Tolak order ini?')) {
                var orderId = $(this).data('order-id');
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="reject_order">');
                form.append('<input type="hidden" name="order_id" value="' + orderId + '">');
                $('body').append(form);
                form.submit();
            }
        });

        // Order modal
        $('#orderModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var order = button.data('order');
            var modal = $(this);
            
            var content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Order Information</h6>
                        <table class="table table-sm">
                            <tr><td>Order Number:</td><td>${order.order_number}</td></tr>
                            <tr><td>Status:</td><td><span class="badge badge-${order.status}">${order.status}</span></td></tr>
                            <tr><td>Amount:</td><td>IDR ${parseInt(order.amount).toLocaleString()}</td></tr>
                            <tr><td>Created:</td><td>${new Date(order.created_at).toLocaleString()}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Customer Information</h6>
                        <table class="table table-sm">
                            <tr><td>Username:</td><td>${order.username}</td></tr>
                            <tr><td>Email:</td><td>${order.email}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6>Product Information</h6>
                        <table class="table table-sm">
                            <tr><td>Product:</td><td>${order.product_name}</td></tr>
                            <tr><td>Category:</td><td>${order.category}</td></tr>
                            <tr><td>RAM:</td><td>${order.ram}GB</td></tr>
                            <tr><td>CPU:</td><td>${order.cpu_percent}%</td></tr>
                        </table>
                    </div>
                </div>
                ${order.notes ? `
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6>Notes</h6>
                        <p class="text-muted">${order.notes}</p>
                    </div>
                </div>
                ` : ''}
            `;
            
            modal.find('#orderModalBody').html(content);
        });
    </script>
</body>
</html> 