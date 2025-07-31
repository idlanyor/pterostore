<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is logged in
requireLogin();

$userId = $_SESSION['user_id'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get user statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status IN ('paid', 'processing', 'completed') THEN amount ELSE 0 END) as total_spent,
        MAX(created_at) as last_order_date
    FROM orders 
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// Handle profile update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        
        // Validate input
        if (empty($username) || empty($email)) {
            $error = 'Username dan email harus diisi';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid';
        } else {
            // Check if username exists (except current user)
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $checkStmt->execute([$username, $userId]);
            if ($checkStmt->fetch()) {
                $error = 'Username sudah digunakan';
            } else {
                // Check if email exists (except current user)
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkStmt->execute([$email, $userId]);
                if ($checkStmt->fetch()) {
                    $error = 'Email sudah digunakan';
                } else {
                    // Update profile
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, email = ?, first_name = ?, last_name = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($updateStmt->execute([$username, $email, $firstName, $lastName, $userId])) {
                        $success = 'Profil berhasil diperbarui';
                        // Update session
                        $_SESSION['username'] = $username;
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $user = $stmt->fetch();
                    } else {
                        $error = 'Gagal memperbarui profil';
                    }
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate current password
        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Password saat ini salah';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password baru minimal 6 karakter';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi password tidak cocok';
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            if ($updateStmt->execute([$hashedPassword, $userId])) {
                $success = 'Password berhasil diubah';
            } else {
                $error = 'Gagal mengubah password';
            }
        }
    }
}

// Get recent orders
$recentOrdersStmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.category
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$recentOrdersStmt->execute([$userId]);
$recentOrders = $recentOrdersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Antidonasi Store</title>
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
                        <a class="nav-link" href="orders.php">Order</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="servers.php">Server</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php">Profil</a></li>
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
                <!-- Profile Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>Informasi Profil
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Nama Depan</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Nama Belakang</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Member Sejak</label>
                                    <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Terakhir Update</label>
                                    <input type="text" class="form-control" value="<?php echo $user['updated_at'] ? date('d/m/Y H:i', strtotime($user['updated_at'])) : 'Belum pernah'; ?>" readonly>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profil
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lock me-2"></i>Ubah Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="current_password" class="form-label">Password Saat Ini</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Minimal 6 karakter</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Ubah Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- User Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Statistik User
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-primary"><?php echo $stats['total_orders']; ?></h4>
                                    <small class="text-muted">Total Order</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-success"><?php echo $stats['completed_orders']; ?></h4>
                                    <small class="text-muted">Order Selesai</small>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-success">IDR <?php echo number_format($stats['total_spent'], 0, ',', '.'); ?></h4>
                                    <small class="text-muted">Total Pengeluaran</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted">Order Terakhir</h6>
                                    <small class="text-muted">
                                        <?php echo $stats['last_order_date'] ? date('d/m/Y H:i', strtotime($stats['last_order_date'])) : 'Belum ada order'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Order Terbaru
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentOrders)): ?>
                            <p class="text-muted text-center">Belum ada order</p>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['product_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['category']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge badge-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></small>
                                    </div>
                                </div>
                                <?php if ($order !== end($recentOrders)): ?>
                                    <hr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="orders.php" class="btn btn-outline-primary btn-sm">
                                    Lihat Semua Order
                                </a>
                            </div>
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
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                            <a href="orders.php" class="btn btn-outline-success">
                                <i class="fas fa-shopping-cart me-2"></i>Riwayat Order
                            </a>
                            <a href="servers.php" class="btn btn-outline-info">
                                <i class="fas fa-server me-2"></i>Daftar Server
                            </a>
                            <a href="../index.php" class="btn btn-outline-warning">
                                <i class="fas fa-plus me-2"></i>Buat Order Baru
                            </a>
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