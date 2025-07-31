<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is logged in
requireLogin();

$orderId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// Get order details with product and server info
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.code as product_code, p.category, p.ram, p.cpu_percent,
           s.pterodactyl_server_id, s.name as server_name, s.status as server_status
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    LEFT JOIN servers s ON o.id = s.order_id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
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
    <title>Detail Order - Antidonasi Store</title>
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
        <div class="row">
            <div class="col-lg-8">
                <!-- Order Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2"></i>Detail Order
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
                                        <td><strong>Produk:</strong></td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kategori:</strong></td>
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
                                    <tr>
                                        <td><strong>Harga:</strong></td>
                                        <td>IDR <?php echo number_format($order['amount'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tanggal Order:</strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                    <?php if ($order['notes']): ?>
                                    <tr>
                                        <td><strong>Catatan:</strong></td>
                                        <td><?php echo htmlspecialchars($order['notes']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Status Progress</h6>
                                        <div class="progress mb-3">
                                            <?php
                                            $progress = 0;
                                            switch($order['status']) {
                                                case 'pending':
                                                    $progress = 25;
                                                    break;
                                                case 'paid':
                                                    $progress = 50;
                                                    break;
                                                case 'processing':
                                                    $progress = 75;
                                                    break;
                                                case 'completed':
                                                    $progress = 100;
                                                    break;
                                            }
                                            ?>
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%" 
                                                 aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col">
                                                <i class="fas fa-shopping-cart text-primary"></i>
                                                <p class="mb-0 small">Order Dibuat</p>
                                            </div>
                                            <div class="col">
                                                <i class="fas fa-credit-card text-primary"></i>
                                                <p class="mb-0 small">Menunggu Pembayaran</p>
                                            </div>
                                            <div class="col">
                                                <i class="fas fa-cog text-primary"></i>
                                                <p class="mb-0 small">Diproses</p>
                                            </div>
                                            <div class="col">
                                                <i class="fas fa-check text-primary"></i>
                                                <p class="mb-0 small">Selesai</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if ($order['status'] === 'pending'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>Informasi Pembayaran
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Instruksi Pembayaran:</h6>
                            <ol class="mb-0">
                                <li>Buka aplikasi e-wallet atau mobile banking Anda</li>
                                <li>Pilih fitur scan QRIS</li>
                                <li>Scan kode QR yang tersedia</li>
                                <li>Masukkan nominal: <strong>IDR <?php echo number_format($order['amount'], 0, ',', '.'); ?></strong></li>
                                <li>Konfirmasi pembayaran</li>
                                <li>Upload bukti pembayaran di bawah ini</li>
                            </ol>
                        </div>
                        
                        <?php if (!$order['payment_proof']): ?>
                        <form action="../upload_payment_proof.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <div class="mb-3">
                                <label for="payment_proof" class="form-label">Upload Bukti Pembayaran</label>
                                <input type="file" class="form-control" id="payment_proof" name="payment_proof" 
                                       accept="image/*" required>
                                <div class="form-text">Upload screenshot bukti pembayaran (JPG, PNG, GIF, max 5MB)</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>Bukti pembayaran telah diupload. Menunggu konfirmasi admin.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Server Information -->
                <?php if ($order['pterodactyl_server_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-server me-2"></i>Informasi Server
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($serverDetails && !isset($serverDetails['error'])): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Server ID:</strong></td>
                                        <td><?php echo htmlspecialchars($order['pterodactyl_server_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nama Server:</strong></td>
                                        <td><?php echo htmlspecialchars($serverDetails['attributes']['name'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="server-status <?php echo $serverDetails['attributes']['current_state'] ?? 'unknown'; ?>">
                                                <?php echo ucfirst($serverDetails['attributes']['current_state'] ?? 'unknown'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Node:</strong></td>
                                        <td><?php echo htmlspecialchars($serverDetails['attributes']['node'] ?? 'N/A'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success server-action" data-action="start" data-server-id="<?php echo $order['pterodactyl_server_id']; ?>">
                                        <i class="fas fa-play me-2"></i>Start Server
                                    </button>
                                    <button class="btn btn-warning server-action" data-action="restart" data-server-id="<?php echo $order['pterodactyl_server_id']; ?>">
                                        <i class="fas fa-redo me-2"></i>Restart Server
                                    </button>
                                    <button class="btn btn-danger server-action" data-action="stop" data-server-id="<?php echo $order['pterodactyl_server_id']; ?>">
                                        <i class="fas fa-stop me-2"></i>Stop Server
                                    </button>
                                    <a href="server_detail.php?id=<?php echo $order['pterodactyl_server_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-cog me-2"></i>Kelola Server
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>Server sedang dalam proses pembuatan. Silakan tunggu beberapa saat.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Aksi Cepat
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Kembali ke Daftar Order
                            </a>
                            <?php if ($order['status'] === 'pending'): ?>
                            <a href="../order_confirmation.php?order_id=<?php echo $orderId; ?>" class="btn btn-primary">
                                <i class="fas fa-credit-card me-2"></i>Lanjutkan Pembayaran
                            </a>
                            <?php endif; ?>
                            <?php if ($order['pterodactyl_server_id']): ?>
                            <a href="server_detail.php?id=<?php echo $order['pterodactyl_server_id']; ?>" class="btn btn-success">
                                <i class="fas fa-server me-2"></i>Kelola Server
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Timeline Order
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Order Dibuat</h6>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></small>
                                </div>
                            </div>
                            <?php if ($order['status'] !== 'pending'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Pembayaran Dikonfirmasi</h6>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></small>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($order['status'] === 'completed'): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Server Siap</h6>
                                    <small class="text-muted">Server telah dibuat dan siap digunakan</small>
                                </div>
                            </div>
                            <?php endif; ?>
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