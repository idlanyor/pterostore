<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is admin
requireAdmin();

$pterodactyl = new PterodactylAPI();
$success = '';
$error = '';
$syncResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'sync_all_servers') {
        $syncResults = $pterodactyl->syncAllServersStatus($pdo);
        $success = 'Sinkronisasi status server selesai';
    } elseif ($action === 'sync_specific_server') {
        $serverId = $_POST['server_id'] ?? '';
        if ($serverId) {
            $result = $pterodactyl->syncServerStatus($pdo, $serverId);
            $syncResults[$serverId] = $result;
            if (isset($result['success'])) {
                $success = 'Status server berhasil disinkronkan';
            } else {
                $error = 'Gagal sinkronisasi server: ' . ($result['error'] ?? 'Unknown error');
            }
        }
    } elseif ($action === 'view_logs') {
        // Display recent logs
        $logFile = __DIR__ . '/../logs/pterodactyl_api.log';
        if (file_exists($logFile)) {
            $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recentLogs = array_slice($logs, -50); // Get last 50 log entries
            $logData = [];
            foreach ($recentLogs as $log) {
                $logData[] = json_decode($log, true);
            }
        } else {
            $logData = [];
        }
    } elseif ($action === 'sync_from_pterodactyl') {
        // Sync servers from Pterodactyl to database
        $pterodactylServers = $pterodactyl->getAllServers();
        if (isset($pterodactylServers['data'])) {
            $syncedCount = 0;
            foreach ($pterodactylServers['data'] as $server) {
                $serverId = $server['attributes']['id'];
                $serverName = $server['attributes']['name'];
                $serverStatus = $server['attributes']['current_state'];
                
                // Check if server exists in database
                $checkStmt = $pdo->prepare("SELECT id FROM servers WHERE pterodactyl_server_id = ?");
                $checkStmt->execute([$serverId]);
                
                if (!$checkStmt->fetch()) {
                    // Get server details from Pterodactyl to get user information
                    $serverDetails = $pterodactyl->getServer($serverId);
                    $userId = 1; // Default to admin user
                    $userEmail = 'admin@example.com';
                    $userName = 'System';
                    
                    if (isset($serverDetails['attributes']['user'])) {
                        $pterodactylUserId = $serverDetails['attributes']['user'];
                        $userDetails = $pterodactyl->getUser($pterodactylUserId);
                        
                        if (isset($userDetails['attributes']) && !isset($userDetails['error'])) {
                            $userEmail = $userDetails['attributes']['email'];
                            $userName = $userDetails['attributes']['username'];
                            
                            // Check if user exists in our database
                            $userCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                            $userCheckStmt->execute([$userEmail]);
                            $existingUser = $userCheckStmt->fetch();
                            
                            if ($existingUser) {
                                $userId = $existingUser['id'];
                            } else {
                                // Create user in our database
                                $createUserStmt = $pdo->prepare("
                                    INSERT INTO users (username, email, first_name, last_name, role, created_at) 
                                    VALUES (?, ?, ?, ?, 'user', NOW())
                                ");
                                $createUserStmt->execute([$userName, $userEmail, $userName, '']);
                                $userId = $pdo->lastInsertId();
                            }
                        } else {
                            // If user not found in Pterodactyl, use default admin user
                            $userId = 1;
                            $userEmail = 'admin@example.com';
                            $userName = 'System';
                        }
                    } else {
                        // If server has no user, use default admin user
                        $userId = 1;
                        $userEmail = 'admin@example.com';
                        $userName = 'System';
                    }
                    
                    // Create a dummy order for standalone servers
                    $dummyOrderNumber = 'DUMMY-' . substr($serverId, 0, 10); // Limit to 10 chars
                    $dummyOrderStmt = $pdo->prepare("
                        INSERT INTO orders (user_id, product_id, amount, status, order_number, created_at) 
                        VALUES (?, 1, 0, 'completed', ?, NOW())
                    ");
                    $dummyOrderStmt->execute([$userId, $dummyOrderNumber]);
                    $dummyOrderId = $pdo->lastInsertId();
                    
                    // Create server record in database with all required fields
                    $insertStmt = $pdo->prepare("
                        INSERT INTO servers (
                            pterodactyl_server_id, 
                            name, 
                            status, 
                            order_id, 
                            node_id,
                            allocation_id,
                            egg_id,
                            created_at
                        ) VALUES (?, ?, ?, ?, '1', '1', '1', NOW())
                    ");
                    if ($insertStmt->execute([$serverId, $serverName, $serverStatus, $dummyOrderId])) {
                        $syncedCount++;
                    }
                }
            }
            $success = "Berhasil sinkronisasi $syncedCount server dari Pterodactyl";
        } else {
            $error = 'Gagal mengambil data server dari Pterodactyl';
        }
    }
}

// Get all servers from database (including those without orders)
$stmt = $pdo->prepare("
    SELECT s.*, 
           o.order_number, 
           u.username, 
           p.name as product_name,
           CASE WHEN o.id IS NOT NULL THEN 'ordered' ELSE 'standalone' END as server_type
    FROM servers s 
    LEFT JOIN orders o ON s.order_id = o.id 
    LEFT JOIN users u ON o.user_id = u.id 
    LEFT JOIN products p ON o.product_id = p.id 
    ORDER BY s.created_at DESC
");
$stmt->execute();
$servers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sinkronisasi Pterodactyl - Admin Panel</title>
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
                        <a class="nav-link" href="orders.php">Orders</a>
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
                    <h2><i class="fas fa-sync me-2"></i>Sinkronisasi Pterodactyl</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Actions -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sync me-2"></i>Aksi Sinkronisasi</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="sync_all_servers">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sync me-2"></i>Sinkronisasi Semua Server
                            </button>
                        </form>
                        
                        <form method="POST" class="mb-3">
                            <div class="mb-3">
                                <label for="server_id" class="form-label">Server ID:</label>
                                <input type="text" class="form-control" id="server_id" name="server_id" placeholder="Masukkan Server ID">
                            </div>
                            <input type="hidden" name="action" value="sync_specific_server">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fas fa-sync me-2"></i>Sinkronisasi Server Tertentu
                            </button>
                        </form>
                        
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="view_logs">
                            <button type="submit" class="btn btn-outline-info w-100">
                                <i class="fas fa-file-alt me-2"></i>Tampilkan Log API
                            </button>
                        </form>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="sync_from_pterodactyl">
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="fas fa-download me-2"></i>Sync dari Pterodactyl
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Total Server (DB):</strong> <?php echo count($servers); ?></p>
                        <p><strong>URL Pterodactyl:</strong> <?php echo PTERODACTYL_URL; ?></p>
                        <p><strong>Status API:</strong> 
                            <?php 
                            $testResponse = $pterodactyl->getNodes();
                            if (isset($testResponse['error'])) {
                                echo '<span class="text-danger">Error: ' . htmlspecialchars($testResponse['error']) . '</span>';
                            } else {
                                echo '<span class="text-success">Connected</span>';
                            }
                            ?>
                        </p>
                        <p><strong>Server di Pterodactyl:</strong> 
                            <?php 
                            $pterodactylServers = $pterodactyl->getAllServers();
                            if (isset($pterodactylServers['data'])) {
                                echo '<span class="text-info">' . count($pterodactylServers['data']) . ' server</span>';
                            } else {
                                echo '<span class="text-muted">Tidak dapat mengambil data</span>';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Results -->
        <?php if (!empty($syncResults)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Hasil Sinkronisasi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Server ID</th>
                                        <th>Status</th>
                                        <th>Pesan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($syncResults as $serverId => $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($serverId); ?></td>
                                        <td>
                                            <?php if (isset($result['success'])): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($result['success'])): ?>
                                                Status: <?php echo htmlspecialchars($result['status']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($result['error'] ?? 'Unknown error'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- API Logs -->
        <?php if (isset($logData) && !empty($logData)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Log API Pterodactyl (50 Terakhir)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Method</th>
                                        <th>URL</th>
                                        <th>HTTP Code</th>
                                        <th>Status</th>
                                        <th>Response/Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($logData) as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['timestamp'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo strtolower($log['method']) === 'get' ? 'success' : (strtolower($log['method']) === 'post' ? 'primary' : 'warning'); ?>">
                                                <?php echo htmlspecialchars($log['method'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['url'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $httpCode = $log['http_code'] ?? 0;
                                            $codeClass = ($httpCode >= 200 && $httpCode < 300) ? 'success' : (($httpCode >= 400 && $httpCode < 500) ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $codeClass; ?>">
                                                <?php echo htmlspecialchars($httpCode); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($log['error']) && $log['error']): ?>
                                                <span class="badge bg-danger">Error</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($log['error']) && $log['error']): ?>
                                                <small class="text-danger"><?php echo htmlspecialchars($log['error']); ?></small>
                                            <?php else: ?>
                                                <small class="text-success">OK</small>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#logDetailModal" 
                                                    data-log='<?php echo htmlspecialchars(json_encode($log)); ?>'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Server List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-server me-2"></i>Daftar Server</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order Number</th>
                                        <th>Username</th>
                                        <th>Product</th>
                                        <th>Server ID</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($servers as $server): ?>
                                    <tr>
                                        <td>
                                            <?php if ($server['order_number']): ?>
                                                <?php echo htmlspecialchars($server['order_number']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Standalone Server</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($server['username']): ?>
                                                <?php echo htmlspecialchars($server['username']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($server['product_name']): ?>
                                                <?php echo htmlspecialchars($server['product_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($server['pterodactyl_server_id']): ?>
                                                <?php echo htmlspecialchars($server['pterodactyl_server_id']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not created</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = 'bg-secondary';
                                            switch ($server['status']) {
                                                case 'running':
                                                    $statusClass = 'bg-success';
                                                    break;
                                                case 'stopped':
                                                    $statusClass = 'bg-warning';
                                                    break;
                                                case 'suspended':
                                                    $statusClass = 'bg-danger';
                                                    break;
                                                case 'installing':
                                                    $statusClass = 'bg-info';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($server['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($server['created_at'])); ?></td>
                                        <td>
                                            <?php if ($server['pterodactyl_server_id']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="sync_specific_server">
                                                    <input type="hidden" name="server_id" value="<?php echo htmlspecialchars($server['pterodactyl_server_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-sync"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Detail Modal -->
    <div class="modal fade" id="logDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Log API</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Request Details</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Timestamp:</strong></td><td id="log-timestamp"></td></tr>
                                <tr><td><strong>Method:</strong></td><td id="log-method"></td></tr>
                                <tr><td><strong>URL:</strong></td><td id="log-url"></td></tr>
                                <tr><td><strong>HTTP Code:</strong></td><td id="log-http-code"></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Request Data</h6>
                            <pre id="log-data" class="bg-light p-2 rounded"></pre>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Response/Error</h6>
                            <pre id="log-response" class="bg-light p-2 rounded"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle log detail modal
        document.addEventListener('DOMContentLoaded', function() {
            const logDetailModal = document.getElementById('logDetailModal');
            if (logDetailModal) {
                logDetailModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const logData = JSON.parse(button.getAttribute('data-log'));
                    
                    document.getElementById('log-timestamp').textContent = logData.timestamp || '';
                    document.getElementById('log-method').textContent = logData.method || '';
                    document.getElementById('log-url').textContent = logData.url || '';
                    document.getElementById('log-http-code').textContent = logData.http_code || '';
                    
                    const requestData = logData.data ? JSON.stringify(logData.data, null, 2) : 'No data';
                    document.getElementById('log-data').textContent = requestData;
                    
                    const responseData = logData.response ? JSON.stringify(logData.response, null, 2) : 
                                      (logData.error ? logData.error : 'No response');
                    document.getElementById('log-response').textContent = responseData;
                });
            }
        });
    </script>
</body>
</html> 