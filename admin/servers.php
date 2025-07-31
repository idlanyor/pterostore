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
    $whereClause .= " AND s.status = ?";
    $params[] = $status;
}

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM servers s 
    JOIN orders o ON s.order_id = o.id 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    $whereClause
");
$countStmt->execute($params);
$totalServers = $countStmt->fetch()['total'];
$totalPages = ceil($totalServers / $limit);

// Get servers
$stmt = $pdo->prepare("
    SELECT s.*, o.order_number, u.username, u.email, p.name as product_name, p.ram, p.cpu_percent, p.category
    FROM servers s 
    JOIN orders o ON s.order_id = o.id 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    $whereClause
    ORDER BY s.created_at DESC 
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
$servers = $stmt->fetchAll();

// Get server statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_servers,
        SUM(CASE WHEN s.status = 'running' THEN 1 ELSE 0 END) as running_servers,
        SUM(CASE WHEN s.status = 'stopped' THEN 1 ELSE 0 END) as stopped_servers,
        SUM(CASE WHEN s.status = 'suspended' THEN 1 ELSE 0 END) as suspended_servers,
        SUM(CASE WHEN s.status = 'installing' THEN 1 ELSE 0 END) as installing_servers
    FROM servers s 
    JOIN orders o ON s.order_id = o.id 
    WHERE o.status = 'completed'
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

// Handle server actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $serverId = $_POST['server_id'] ?? '';
    
    if ($action === 'start_server') {
        $pterodactyl = new PterodactylAPI();
        $response = $pterodactyl->startServer($serverId);
        if (isset($response['error'])) {
            $error = 'Gagal start server: ' . $response['error'];
        } else {
            $success = 'Server berhasil di-start';
        }
    } elseif ($action === 'stop_server') {
        $pterodactyl = new PterodactylAPI();
        $response = $pterodactyl->stopServer($serverId);
        if (isset($response['error'])) {
            $error = 'Gagal stop server: ' . $response['error'];
        } else {
            $success = 'Server berhasil di-stop';
        }
    } elseif ($action === 'restart_server') {
        $pterodactyl = new PterodactylAPI();
        $response = $pterodactyl->restartServer($serverId);
        if (isset($response['error'])) {
            $error = 'Gagal restart server: ' . $response['error'];
        } else {
            $success = 'Server berhasil di-restart';
        }
    } elseif ($action === 'suspend_server') {
        $pterodactyl = new PterodactylAPI();
        $response = $pterodactyl->suspendServer($serverId);
        if (isset($response['error'])) {
            $error = 'Gagal suspend server: ' . $response['error'];
        } else {
            $success = 'Server berhasil di-suspend';
        }
    } elseif ($action === 'delete_server') {
        // Check if server has active orders
        $orderCheckStmt = $pdo->prepare("
            SELECT COUNT(*) as order_count FROM orders o 
            JOIN servers s ON o.id = s.order_id 
            WHERE s.pterodactyl_server_id = ? AND o.status IN ('paid', 'processing', 'completed')
        ");
        $orderCheckStmt->execute([$serverId]);
        $orderCount = $orderCheckStmt->fetch()['order_count'];
        
        if ($orderCount > 0) {
            $error = 'Tidak dapat menghapus server yang memiliki order aktif';
        } else {
            $deleteStmt = $pdo->prepare("DELETE FROM servers WHERE pterodactyl_server_id = ?");
            if ($deleteStmt->execute([$serverId])) {
                $success = 'Server berhasil dihapus';
            } else {
                $error = 'Gagal menghapus server';
            }
        }
    }
    
    // Refresh servers list
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $servers = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Management - Admin Panel</title>
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
                        <a class="nav-link active" href="servers.php">Servers</a>
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
                    <h2><i class="fas fa-server me-2"></i>Server Management</h2>
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
                        <h4 class="text-primary"><?php echo $stats['total_servers']; ?></h4>
                        <small class="text-muted">Total Servers</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success"><?php echo $stats['running_servers']; ?></h4>
                        <small class="text-muted">Running</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-danger"><?php echo $stats['stopped_servers']; ?></h4>
                        <small class="text-muted">Stopped</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-warning"><?php echo $stats['suspended_servers']; ?></h4>
                        <small class="text-muted">Suspended</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-info"><?php echo $stats['installing_servers']; ?></h4>
                        <small class="text-muted">Installing</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success">99%</h4>
                        <small class="text-muted">Uptime</small>
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
                                    <a href="servers.php" class="btn btn-outline-secondary <?php echo !$status ? 'active' : ''; ?>">
                                        Semua
                                    </a>
                                    <a href="servers.php?status=running" class="btn btn-outline-success <?php echo $status === 'running' ? 'active' : ''; ?>">
                                        Running
                                    </a>
                                    <a href="servers.php?status=stopped" class="btn btn-outline-danger <?php echo $status === 'stopped' ? 'active' : ''; ?>">
                                        Stopped
                                    </a>
                                    <a href="servers.php?status=suspended" class="btn btn-outline-warning <?php echo $status === 'suspended' ? 'active' : ''; ?>">
                                        Suspended
                                    </a>
                                    <a href="servers.php?status=installing" class="btn btn-outline-info <?php echo $status === 'installing' ? 'active' : ''; ?>">
                                        Installing
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Servers List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Daftar Servers
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($servers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-server fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada server</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Server ID</th>
                                            <th>Name</th>
                                            <th>Owner</th>
                                            <th>Product</th>
                                            <th>Specs</th>
                                            <th>Status</th>
                                            <th>Order</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($servers as $server): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($server['pterodactyl_server_id']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($server['name']); ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($server['username']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($server['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($server['product_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($server['category']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $server['ram']; ?>GB RAM<br>
                                                        <?php echo $server['cpu_percent']; ?>% CPU
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="server-status <?php echo $server['status']; ?>">
                                                        <?php echo ucfirst($server['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($server['order_number']); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($server['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#serverModal" 
                                                                data-server='<?php echo json_encode($server); ?>'
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success server-action" 
                                                                data-action="start_server" 
                                                                data-server-id="<?php echo $server['pterodactyl_server_id']; ?>"
                                                                title="Start Server">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning server-action" 
                                                                data-action="restart_server" 
                                                                data-server-id="<?php echo $server['pterodactyl_server_id']; ?>"
                                                                title="Restart Server">
                                                            <i class="fas fa-redo"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger server-action" 
                                                                data-action="stop_server" 
                                                                data-server-id="<?php echo $server['pterodactyl_server_id']; ?>"
                                                                title="Stop Server">
                                                            <i class="fas fa-stop"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary server-action" 
                                                                data-action="suspend_server" 
                                                                data-server-id="<?php echo $server['pterodactyl_server_id']; ?>"
                                                                title="Suspend Server">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-server" 
                                                                data-server-id="<?php echo $server['pterodactyl_server_id']; ?>"
                                                                data-server-name="<?php echo htmlspecialchars($server['name']); ?>"
                                                                title="Delete Server">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <nav aria-label="Server pagination">
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

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-success w-100" id="start-all-servers">
                                    <i class="fas fa-play me-2"></i>Start All Servers
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-warning w-100" id="restart-all-servers">
                                    <i class="fas fa-redo me-2"></i>Restart All Servers
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-danger w-100" id="stop-all-servers">
                                    <i class="fas fa-stop me-2"></i>Stop All Servers
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="orders.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-shopping-cart me-2"></i>View Orders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Detail Modal -->
    <div class="modal fade" id="serverModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Server Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="serverModalBody">
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
        // Server actions
        $('.server-action').on('click', function() {
            var action = $(this).data('action');
            var serverId = $(this).data('server-id');
            
            if (confirm('Execute ' + action.replace('_', ' ') + '?')) {
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="' + action + '">');
                form.append('<input type="hidden" name="server_id" value="' + serverId + '">');
                $('body').append(form);
                form.submit();
            }
        });

        $('.delete-server').on('click', function() {
            var serverId = $(this).data('server-id');
            var serverName = $(this).data('server-name');
            
            if (confirm('Hapus server "' + serverName + '"?')) {
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="delete_server">');
                form.append('<input type="hidden" name="server_id" value="' + serverId + '">');
                $('body').append(form);
                form.submit();
            }
        });

        // Bulk server actions
        $('#start-all-servers').on('click', function() {
            if (confirm('Start semua server?')) {
                $('.server-action[data-action="start_server"]').each(function() {
                    $(this).click();
                });
            }
        });

        $('#restart-all-servers').on('click', function() {
            if (confirm('Restart semua server?')) {
                $('.server-action[data-action="restart_server"]').each(function() {
                    $(this).click();
                });
            }
        });

        $('#stop-all-servers').on('click', function() {
            if (confirm('Stop semua server?')) {
                $('.server-action[data-action="stop_server"]').each(function() {
                    $(this).click();
                });
            }
        });

        // Server modal
        $('#serverModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var server = button.data('server');
            var modal = $(this);
            
            var content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Server Information</h6>
                        <table class="table table-sm">
                            <tr><td>Server ID:</td><td>${server.pterodactyl_server_id}</td></tr>
                            <tr><td>Name:</td><td>${server.name}</td></tr>
                            <tr><td>Status:</td><td><span class="server-status ${server.status}">${server.status}</span></td></tr>
                            <tr><td>Created:</td><td>${new Date(server.created_at).toLocaleDateString()}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Owner Information</h6>
                        <table class="table table-sm">
                            <tr><td>Username:</td><td>${server.username}</td></tr>
                            <tr><td>Email:</td><td>${server.email}</td></tr>
                            <tr><td>Order:</td><td>${server.order_number}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6>Product Information</h6>
                        <table class="table table-sm">
                            <tr><td>Product:</td><td>${server.product_name}</td></tr>
                            <tr><td>Category:</td><td>${server.category}</td></tr>
                            <tr><td>RAM:</td><td>${server.ram}GB</td></tr>
                            <tr><td>CPU:</td><td>${server.cpu_percent}%</td></tr>
                        </table>
                    </div>
                </div>
            `;
            
            modal.find('#serverModalBody').html(content);
        });
    </script>
</body>
</html> 