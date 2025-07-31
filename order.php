<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$productCode = $_GET['product'] ?? '';
$product = getProductByCode($pdo, $productCode);

if (!$product) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serverName = trim($_POST['server_name']);
    $notes = trim($_POST['notes']);
    
    if (empty($serverName)) {
        $error = 'Nama server harus diisi';
    } else {
        // Create order
        $orderNumber = generateOrderNumber();
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, product_id, order_number, amount, notes) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$_SESSION['user_id'], $product['id'], $orderNumber, $product['price'], $notes])) {
            $orderId = $pdo->lastInsertId();
            header("Location: order_confirmation.php?order_id=" . $orderId);
            exit();
        } else {
            $error = 'Terjadi kesalahan saat membuat order';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order - <?php echo htmlspecialchars($product['name']); ?> - Antidonasi Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-server me-2"></i>Antidonasi Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Beranda</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="user/dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="user/profile.php">Profil</a></li>
                            <li><a class="dropdown-item" href="user/orders.php">Riwayat Order</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-shopping-cart me-2"></i>Order Server
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Product Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Detail Produk</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($product['description']); ?></p>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-memory me-2"></i><?php echo $product['ram']; ?>GB RAM</li>
                                            <li><i class="fas fa-microchip me-2"></i><?php echo $product['cpu_percent']; ?>% CPU</li>
                                            <li><i class="fas fa-tag me-2"></i>Kategori: <?php echo htmlspecialchars($product['category']); ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Harga</h5>
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary mb-0">IDR <?php echo number_format($product['price'], 0, ',', '.'); ?></h3>
                                        <p class="text-muted mb-0">per bulan</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Form -->
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="server_name" class="form-label">
                                    <i class="fas fa-server me-2"></i>Nama Server
                                </label>
                                <input type="text" class="form-control" id="server_name" name="server_name" 
                                       value="<?php echo isset($_POST['server_name']) ? htmlspecialchars($_POST['server_name']) : ''; ?>" 
                                       placeholder="Contoh: MyGameServer" required>
                                <div class="form-text">Nama server akan digunakan untuk identifikasi server Anda</div>
                                <div class="invalid-feedback">
                                    Nama server harus diisi
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note me-2"></i>Catatan (Opsional)
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Tambahkan catatan khusus untuk server Anda..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    Saya setuju dengan <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Syarat dan Ketentuan</a>
                                </label>
                                <div class="invalid-feedback">
                                    Anda harus menyetujui syarat dan ketentuan
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>Lanjutkan ke Pembayaran
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Product Features -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-star me-2"></i>Keunggulan Layanan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Uptime 99%</li>
                                    <li><i class="fas fa-check text-success me-2"></i>DDos Protection</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Processor Epyc Genoa</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>ECC DDR5 Memory</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Speed Internet 2Gb/s</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Garansi 30 Hari</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Syarat dan Ketentuan Pembelian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Proses Pembayaran</h6>
                    <p>Setelah order dibuat, Anda akan diarahkan ke halaman pembayaran QRIS. Pembayaran harus dilakukan dalam waktu 15 menit.</p>
                    
                    <h6>2. Konfirmasi Order</h6>
                    <p>Order akan diproses setelah admin mengkonfirmasi pembayaran. Proses ini biasanya memakan waktu 1-2 jam.</p>
                    
                    <h6>3. Pembuatan Server</h6>
                    <p>Server akan dibuat secara otomatis setelah pembayaran dikonfirmasi. Anda akan menerima informasi akses melalui email.</p>
                    
                    <h6>4. Kebijakan Pengembalian</h6>
                    <p>Pengembalian dana hanya berlaku dalam 30 hari pertama jika terjadi masalah teknis dari pihak kami.</p>
                    
                    <h6>5. Penggunaan Server</h6>
                    <p>Server hanya boleh digunakan untuk tujuan yang sah dan tidak melanggar hukum yang berlaku.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html> 