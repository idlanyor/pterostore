<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is logged in
requireLogin();

$serverId = $_GET['id'] ?? '';
$userId = $_SESSION['user_id'];

// Verify server belongs to user
$stmt = $pdo->prepare("
    SELECT s.*, o.order_number, p.name as product_name, p.ram, p.cpu_percent, p.category
    FROM servers s 
    JOIN orders o ON s.order_id = o.id 
    JOIN products p ON o.product_id = p.id 
    WHERE s.pterodactyl_server_id = ? AND o.user_id = ?
");
$stmt->execute([$serverId, $userId]);
$server = $stmt->fetch();

if (!$server) {
    header('Location: servers.php');
    exit();
}

// Get server details from Pterodactyl
$pterodactyl = new PterodactylAPI();
$serverDetails = $pterodactyl->getServer($serverId);
$serverResources = $pterodactyl->getServerResources($serverId);

// Handle server actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'start':
            $response = $pterodactyl->startServer($serverId);
            break;
        case 'stop':
            $response = $pterodactyl->stopServer($serverId);
            break;
        case 'restart':
            $response = $pterodactyl->restartServer($serverId);
            break;
        case 'suspend':
            $response = $pterodactyl->suspendServer($serverId);
            break;
    }
    
    if (isset($response['error'])) {
        $error = $response['error'];
    } else {
        $success = 'Server ' . $action . 'ed successfully';
        // Refresh server details
        $serverDetails = $pterodactyl->getServer($serverId);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Server - Antidonasi Store</title>
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
                        <a class="nav-link active" href="servers.php">Server</a>
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
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Server Information -->
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
                                            <td><?php echo htmlspecialchars($serverId); ?></td>
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
                                        <tr>
                                            <td><strong>Order Number:</strong></td>
                                            <td><?php echo htmlspecialchars($server['order_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Produk:</strong></td>
                                            <td><?php echo htmlspecialchars($server['product_name']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Spesifikasi Server</h6>
                                            <ul class="list-unstyled">
                                                <li><i class="fas fa-memory me-2"></i><?php echo $server['ram']; ?>GB RAM</li>
                                                <li><i class="fas fa-microchip me-2"></i><?php echo $server['cpu_percent']; ?>% CPU</li>
                                                <li><i class="fas fa-hdd me-2"></i>10GB Storage</li>
                                                <li><i class="fas fa-network-wired me-2"></i>Unlimited Bandwidth</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>Server sedang dalam proses pembuatan atau tidak dapat diakses.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Server Resources -->
                <?php if ($serverResources && !isset($serverResources['error'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Penggunaan Resource
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>CPU Usage</h6>
                                <div class="progress mb-3">
                                    <?php 
                                    $cpuUsage = $serverResources['attributes']['cpu_absolute'] ?? 0;
                                    $cpuPercent = min(100, ($cpuUsage / $server['cpu_percent']) * 100);
                                    ?>
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo $cpuPercent; ?>%" 
                                         aria-valuenow="<?php echo $cpuPercent; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo round($cpuPercent, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo round($cpuUsage, 2); ?>% dari <?php echo $server['cpu_percent']; ?>%</small>
                            </div>
                            <div class="col-md-6">
                                <h6>Memory Usage</h6>
                                <div class="progress mb-3">
                                    <?php 
                                    $memoryUsage = $serverResources['attributes']['memory_bytes'] ?? 0;
                                    $memoryLimit = $server['ram'] * 1024 * 1024 * 1024; // Convert GB to bytes
                                    $memoryPercent = min(100, ($memoryUsage / $memoryLimit) * 100);
                                    ?>
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $memoryPercent; ?>%" 
                                         aria-valuenow="<?php echo $memoryPercent; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo round($memoryPercent, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo formatBytes($memoryUsage); ?> dari <?php echo $server['ram']; ?>GB</small>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Disk Usage</h6>
                                <div class="progress mb-3">
                                    <?php 
                                    $diskUsage = $serverResources['attributes']['disk_bytes'] ?? 0;
                                    $diskLimit = 10 * 1024 * 1024 * 1024; // 10GB in bytes
                                    $diskPercent = min(100, ($diskUsage / $diskLimit) * 100);
                                    ?>
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $diskPercent; ?>%" 
                                         aria-valuenow="<?php echo $diskPercent; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo round($diskPercent, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo formatBytes($diskUsage); ?> dari 10GB</small>
                            </div>
                            <div class="col-md-6">
                                <h6>Network</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Upload</small>
                                        <div class="h6"><?php echo formatBytes($serverResources['attributes']['network']['tx_bytes'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Download</small>
                                        <div class="h6"><?php echo formatBytes($serverResources['attributes']['network']['rx_bytes'] ?? 0); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Server Console -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-terminal me-2"></i>Server Console
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="server-console" data-server-id="<?php echo $serverId; ?>" class="bg-dark text-light p-3 rounded" style="height: 300px; overflow-y: auto; font-family: 'Courier New', monospace;">
                            <div class="text-muted">Console akan muncul di sini...</div>
                        </div>
                        <div class="mt-3">
                            <div class="input-group">
                                <input type="text" class="form-control" id="console-input" placeholder="Masukkan command...">
                                <button class="btn btn-outline-secondary" type="button" id="send-command">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Server Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>Server Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-success server-action" data-action="start" data-server-id="<?php echo $serverId; ?>">
                                <i class="fas fa-play me-2"></i>Start Server
                            </button>
                            <button class="btn btn-warning server-action" data-action="restart" data-server-id="<?php echo $serverId; ?>">
                                <i class="fas fa-redo me-2"></i>Restart Server
                            </button>
                            <button class="btn btn-danger server-action" data-action="stop" data-server-id="<?php echo $serverId; ?>">
                                <i class="fas fa-stop me-2"></i>Stop Server
                            </button>
                            <button class="btn btn-secondary server-action" data-action="suspend" data-server-id="<?php echo $serverId; ?>">
                                <i class="fas fa-pause me-2"></i>Suspend Server
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>Quick Links
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="servers.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Daftar Server
                            </a>
                            <a href="orders.php" class="btn btn-outline-primary">
                                <i class="fas fa-shopping-cart me-2"></i>Riwayat Order
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-info">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Server Status -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Server Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <i class="fas fa-server fa-2x text-primary mb-2"></i>
                                    <h6>Server</h6>
                                    <span class="badge bg-success">Online</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                    <h6>Security</h6>
                                    <span class="badge bg-success">Protected</span>
                                </div>
                            </div>
                        </div>
                        <div class="row text-center mt-3">
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                    <h6>Uptime</h6>
                                    <span class="badge bg-info">99%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <i class="fas fa-wifi fa-2x text-warning mb-2"></i>
                                    <h6>Network</h6>
                                    <span class="badge bg-success">Stable</span>
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