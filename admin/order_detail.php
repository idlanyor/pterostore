<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is logged in and is admin
requireAdmin();

$orderId = $_GET['id'] ?? 0;

// Get order details with product, user, and server info
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.code as product_code, p.category, p.ram, p.cpu_percent,
           u.username, u.email,
           s.pterodactyl_server_id, s.name as server_name, s.status as server_status
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    JOIN users u ON o.user_id = u.id
    LEFT JOIN servers s ON o.id = s.order_id 
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Handle admin actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['status'] ?? '';
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$newStatus, $orderId])) {
                $success = 'Status order berhasil diperbarui';
                // Refresh order data
                $stmt = $pdo->prepare("
                    SELECT o.*, p.name as product_name, p.code as product_code, p.category, p.ram, p.cpu_percent,
                           u.username, u.email,
                           s.pterodactyl_server_id, s.name as server_name, s.status as server_status
                    FROM orders o 
                    JOIN products p ON o.product_id = p.id 
                    JOIN users u ON o.user_id = u.id
                    LEFT JOIN servers s ON o.id = s.order_id 
                    WHERE o.id = ?
                ");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();
            } else {
                $error = 'Gagal memperbarui status order';
            }
            break;
            
        case 'create_server':
            if ($order['status'] === 'paid' && !$order['pterodactyl_server_id']) {
                $pterodactyl = new PterodactylAPI();
                
                // Get user from Pterodactyl or create if not exists
                $pterodactylUser = $pterodactyl->getUserByEmail($order['email']);
                if (!$pterodactylUser) {
                    $pterodactylUser = $pterodactyl->createUser(
                        $order['username'],
                        $order['email'],
                        $order['username'],
                        ''
                    );
                }
                
                if (isset($pterodactylUser['error'])) {
                    $error = 'Gagal membuat user di Pterodactyl: ' . $pterodactylUser['error'];
                } else {
                    // Create server in Pterodactyl
                    $serverName = $order['server_name'] ?: $order['product_name'] . ' Server';
                    $serverResponse = $pterodactyl->createServer(
                        $serverName,
                        $pterodactylUser['attributes']['id'],
                        1, // node_id
                        1, // allocation_id
                        1, // egg_id
                        $order['ram'],
                        $order['cpu_percent']
                    );
                    
                    if (isset($serverResponse['error'])) {
                        $error = 'Gagal membuat server di Pterodactyl: ' . $serverResponse['error'];
                    } else {
                        // Create server record in database
                        $stmt = $pdo->prepare("
                            INSERT INTO servers (pterodactyl_server_id, name, status, order_id, node_id, allocation_id, egg_id, created_at)
                            VALUES (?, ?, ?, ?, '1', '1', '1', NOW())
                        ");
                        if ($stmt->execute([$serverResponse['attributes']['id'], $serverName, 'installing', $orderId])) {
                            // Update order status
                            $stmt = $pdo->prepare("UPDATE orders SET status = 'processing', updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$orderId]);
                            
                            $success = 'Server berhasil dibuat di Pterodactyl';
                            
                            // Refresh order data
                            $stmt = $pdo->prepare("
                                SELECT o.*, p.name as product_name, p.code as product_code, p.category, p.ram, p.cpu_percent,
                                       u.username, u.email,
                                       s.pterodactyl_server_id, s.name as server_name, s.status as server_status
                                FROM orders o 
                                JOIN products p ON o.product_id = p.id 
                                JOIN users u ON o.user_id = u.id
                                LEFT JOIN servers s ON o.id = s.order_id 
                                WHERE o.id = ?
                            ");
                            $stmt->execute([$orderId]);
                            $order = $stmt->fetch();
                        } else {
                            $error = 'Gagal menyimpan data server ke database';
                        }
                    }
                }
            } else {
                $error = 'Order harus berstatus "paid" dan belum memiliki server';
            }
            break;
    }
}

// Get server details from Pterodactyl if server exists
$serverDetails = null;
if ($order['pterodactyl_server_id']) {
    $pterodactyl = new PterodactylAPI();
    $serverDetails = $pterodactyl->getServer($order['pterodactyl_server_id']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Order #<?php echo $order['order_number']; ?> - Admin Panel</title>
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

        <div class="row">
            <div class="col-lg-8">
                <!-- Order Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2"></i>Detail Order #<?php echo htmlspecialchars($order['order_number']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Order Number:</strong></td>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $order['status'] === 'completed' ? 'success' : 
                                                    ($order['status'] === 'processing' ? 'warning' : 
                                                    ($order['status'] === 'paid' ? 'info' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount:</strong></td>
                                        <td>Rp <?php echo number_format($order['amount'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Created:</strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                    <?php if ($order['paid_at']): ?>
                                    <tr>
                                        <td><strong>Paid:</strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['paid_at'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Product:</strong></td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Category:</strong></td>
                                        <td><?php echo htmlspecialchars($order['category']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>RAM:</strong></td>
                                        <td><?php echo $order['ram']; ?>GB</td>
                                    </tr>
                                    <tr>
                                        <td><strong>CPU:</strong></td>
                                        <td><?php echo $order['cpu_percent']; ?>%</td>
                                    </tr>
                                    <?php if ($order['notes']): ?>
                                    <tr>
                                        <td><strong>Notes:</strong></td>
                                        <td><?php echo htmlspecialchars($order['notes']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>Informasi User
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Username:</strong></td>
                                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo htmlspecialchars($order['email']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>User ID:</strong></td>
                                        <td><?php echo $order['user_id']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Server Information -->
                <?php if ($order['pterodactyl_server_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-server me-2"></i>Informasi Server
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Server Name:</strong></td>
                                        <td><?php echo htmlspecialchars($order['server_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Server Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $order['server_status'] === 'running' ? 'success' : 
                                                    ($order['server_status'] === 'installing' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($order['server_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Pterodactyl ID:</strong></td>
                                        <td><?php echo $order['pterodactyl_server_id']; ?></td>
                                    </tr>
                                    <?php if ($serverDetails && !isset($serverDetails['error'])): ?>
                                    <tr>
                                        <td><strong>External ID:</strong></td>
                                        <td><?php echo $serverDetails['attributes']['external_id'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment Proof -->
                <?php if ($order['payment_proof']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-image me-2"></i>Bukti Pembayaran
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="../<?php echo htmlspecialchars($order['payment_proof']); ?>" 
                             alt="Payment Proof" class="img-fluid" style="max-width: 400px;">
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <!-- Admin Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>Admin Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Update Status -->
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="update_status">
                            <div class="mb-3">
                                <label for="status" class="form-label">Update Status:</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $order['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="failed" <?php echo $order['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i>Update Status
                            </button>
                        </form>

                        <!-- Create Server -->
                        <?php if ($order['status'] === 'paid' && !$order['pterodactyl_server_id']): ?>
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="create_server">
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Buat server di Pterodactyl?')">
                                <i class="fas fa-server me-1"></i>Create Server
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- View in Pterodactyl -->
                        <?php if ($order['pterodactyl_server_id']): ?>
                        <a href="<?php echo PTERODACTYL_URL; ?>/admin/servers/view/<?php echo $order['pterodactyl_server_id']; ?>" 
                           target="_blank" class="btn btn-info btn-sm">
                            <i class="fas fa-external-link-alt me-1"></i>View in Pterodactyl
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>Quick Links
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Orders
                            </a>
                            <a href="users.php?id=<?php echo $order['user_id']; ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-user me-1"></i>View User
                            </a>
                            <?php if ($order['pterodactyl_server_id']): ?>
                            <a href="servers.php?id=<?php echo $order['pterodactyl_server_id']; ?>" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-server me-1"></i>View Server
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 