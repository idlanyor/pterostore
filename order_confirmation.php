<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$orderId = $_GET['order_id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.code as product_code, p.price, p.ram, p.cpu_percent, p.category
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: user/orders.php');
    exit();
}

// Generate QRIS code with proper EMV QR Code structure
$qrisData = [
    'merchant_id' => 'ANTIDONASI',
    'amount' => $order['amount'],
    'order_id' => $order['order_number'],
    'description' => 'Pembayaran ' . $order['product_name']
];

// Generate QRIS URL for payment
$qrisCode = 'https://api.qris.id/pay?' . http_build_query($qrisData);

// Add payment status checking
$paymentStatus = 'pending';
if ($order['status'] === 'paid') {
    $paymentStatus = 'paid';
} elseif ($order['status'] === 'processing') {
    $paymentStatus = 'processing';
} elseif ($order['status'] === 'completed') {
    $paymentStatus = 'completed';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - Antidonasi Store</title>
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
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-qrcode me-2"></i>Konfirmasi Pembayaran
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Order Details -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Detail Order</h5>
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
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Total Pembayaran</h5>
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="text-success mb-0">IDR <?php echo number_format($order['amount'], 0, ',', '.'); ?></h3>
                                        <p class="text-muted mb-0">per bulan</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- QRIS Payment -->
                        <div class="qris-container">
                            <h5 class="text-center mb-4">
                                <i class="fas fa-mobile-alt me-2"></i>Scan QRIS untuk Pembayaran
                            </h5>
                            
                            <div class="text-center mb-3">
                                <div class="qris-code">
                                    <!-- QRIS Code Image -->
                                    <img src="generate_qris.php?order_id=<?php echo $orderId; ?>" 
                                         alt="QRIS Code" 
                                         class="img-fluid border rounded" 
                                         style="max-width: 300px; height: auto;">
                                    <div class="mt-2">
                                        <small class="text-muted">Scan dengan aplikasi e-wallet atau mobile banking</small>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mb-4">
                                <p class="text-muted mb-2">Waktu tersisa untuk pembayaran:</p>
                                <h4 class="text-danger" id="payment-timer">15:00</h4>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Instruksi Pembayaran:</h6>
                                <ol class="mb-0">
                                    <li>Buka aplikasi e-wallet atau mobile banking Anda</li>
                                    <li>Pilih fitur scan QRIS</li>
                                    <li>Scan kode QR di atas</li>
                                    <li>Masukkan nominal pembayaran: <strong>IDR <?php echo number_format($order['amount'], 0, ',', '.'); ?></strong></li>
                                    <li>Konfirmasi pembayaran</li>
                                    <li>Simpan bukti pembayaran</li>
                                </ol>
                            </div>

                            <div class="text-center">
                                <a href="<?php echo $qrisCode; ?>" class="btn btn-success btn-lg" target="_blank">
                                    <i class="fas fa-external-link-alt me-2"></i>Buka QRIS
                                </a>
                            </div>
                        </div>

                        <!-- Payment Proof Upload -->
                        <div class="mt-4">
                            <h5>Upload Bukti Pembayaran</h5>
                            <form action="upload_payment_proof.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                <div class="mb-3">
                                    <label for="payment_proof" class="form-label">Bukti Pembayaran (Screenshot)</label>
                                    <input type="file" class="form-control" id="payment_proof" name="payment_proof" 
                                           accept="image/*" required>
                                    <div class="form-text">Upload screenshot bukti pembayaran dari e-wallet/banking Anda</div>
                                </div>
                                <div class="mb-3">
                                    <img id="proof-preview" src="" alt="" style="max-width: 300px; display: none;" class="img-thumbnail">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                                </button>
                            </form>
                        </div>

                        <!-- Order Status -->
                        <div class="mt-4">
                            <h5>Status Order</h5>
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
                                    <p class="mb-0">Order Dibuat</p>
                                </div>
                                <div class="col">
                                    <i class="fas fa-credit-card text-primary"></i>
                                    <p class="mb-0">Menunggu Pembayaran</p>
                                </div>
                                <div class="col">
                                    <i class="fas fa-cog text-primary"></i>
                                    <p class="mb-0">Diproses</p>
                                </div>
                                <div class="col">
                                    <i class="fas fa-check text-primary"></i>
                                    <p class="mb-0">Selesai</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <a href="user/orders.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Lihat Semua Order
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Payment Expired Alert -->
                <div class="alert alert-warning mt-4" id="payment-expired" style="display: none;">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Waktu Pembayaran Habis</h6>
                    <p class="mb-2">Waktu pembayaran telah habis. Silakan buat order baru.</p>
                    <a href="index.php" class="btn btn-warning btn-sm">
                        <i class="fas fa-plus me-2"></i>Buat Order Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
        // Payment status checking
        let paymentCheckInterval;
        let paymentTimer;
        
        $(document).ready(function() {
            // Initialize payment timer
            initializePaymentTimer();
            
            // Start payment status checking
            startPaymentStatusCheck();
            
            // File preview
            $('#payment_proof').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#proof-preview').attr('src', e.target.result).show();
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
        
        function initializePaymentTimer() {
            let timeLeft = 15 * 60; // 15 minutes in seconds
            
            paymentTimer = setInterval(function() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                $('#payment-timer').text(
                    (minutes < 10 ? '0' : '') + minutes + ':' + 
                    (seconds < 10 ? '0' : '') + seconds
                );
                
                if (timeLeft <= 0) {
                    clearInterval(paymentTimer);
                    $('#payment-expired').show();
                    $('.qris-container').hide();
                }
                
                timeLeft--;
            }, 1000);
        }
        
        function startPaymentStatusCheck() {
            paymentCheckInterval = setInterval(function() {
                checkPaymentStatus();
            }, 10000); // Check every 10 seconds
        }
        
        function checkPaymentStatus() {
            $.ajax({
                url: 'check_order_status.php',
                method: 'POST',
                data: {
                    order_id: <?php echo $orderId; ?>
                },
                success: function(response) {
                    if (response.success) {
                        if (response.status === 'paid' || response.status === 'processing' || response.status === 'completed') {
                            clearInterval(paymentCheckInterval);
                            clearInterval(paymentTimer);
                            
                            // Show success message
                            Swal.fire({
                                title: 'Pembayaran Berhasil!',
                                text: 'Order Anda sedang diproses. Server akan siap dalam beberapa menit.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                window.location.href = 'user/order_detail.php?id=<?php echo $orderId; ?>';
                            });
                        }
                    }
                },
                error: function() {
                    console.log('Payment status check failed');
                }
            });
        }
        
        // Stop checking when page is unloaded
        $(window).on('beforeunload', function() {
            if (paymentCheckInterval) {
                clearInterval(paymentCheckInterval);
            }
            if (paymentTimer) {
                clearInterval(paymentTimer);
            }
        });
    </script>
    
    <!-- SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html> 