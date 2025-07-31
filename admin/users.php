<?php
session_start();
require_once '../config/database.php';
require_once '../config/pterodactyl.php';

// Check if user is admin
requireAdmin();

$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($role) {
    $whereClause .= " AND u.role = ?";
    $params[] = $role;
}

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM users u 
    $whereClause
");
$countStmt->execute($params);
$totalUsers = $countStmt->fetch()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(o.id) as total_orders,
           SUM(CASE WHEN o.status IN ('paid', 'processing', 'completed') THEN o.amount ELSE 0 END) as total_spent,
           MAX(o.created_at) as last_order_date
    FROM users u 
    LEFT JOIN orders o ON u.id = o.user_id 
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC 
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
$users = $stmt->fetchAll();

// Get user statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
    FROM users
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

// Handle user actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? 0;
    
    if ($action === 'change_role') {
        $newRole = $_POST['new_role'];
        $updateStmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
        if ($updateStmt->execute([$newRole, $userId])) {
            $success = 'Role user berhasil diubah';
        } else {
            $error = 'Gagal mengubah role user';
        }
    } elseif ($action === 'delete_user') {
        // Check if user has orders
        $orderCheckStmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
        $orderCheckStmt->execute([$userId]);
        $orderCount = $orderCheckStmt->fetch()['order_count'];
        
        if ($orderCount > 0) {
            $error = 'Tidak dapat menghapus user yang memiliki order';
        } else {
            $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($deleteStmt->execute([$userId])) {
                $success = 'User berhasil dihapus';
            } else {
                $error = 'Gagal menghapus user';
            }
        }
    } elseif ($action === 'reset_password') {
        $newPassword = generateRandomPassword(8);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        if ($updateStmt->execute([$hashedPassword, $userId])) {
            $success = 'Password user berhasil direset. Password baru: ' . $newPassword;
        } else {
            $error = 'Gagal reset password user';
        }
    }
    
    // Refresh users list
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
                        <a class="nav-link active" href="users.php">Users</a>
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
                    <h2><i class="fas fa-users me-2"></i>User Management</h2>
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
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary"><?php echo $stats['total_users']; ?></h4>
                        <small class="text-muted">Total Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-danger"><?php echo $stats['admin_users']; ?></h4>
                        <small class="text-muted">Admin Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success"><?php echo $stats['regular_users']; ?></h4>
                        <small class="text-muted">Regular Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-info"><?php echo $stats['new_users_30d']; ?></h4>
                        <small class="text-muted">New Users (30d)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row align-items-end">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Users</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Username, email, or name...">
                            </div>
                            <div class="col-md-3">
                                <label for="role" class="form-label">Filter Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Search
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="users.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Daftar Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada user ditemukan</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User Info</th>
                                            <th>Role</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                        <?php if ($user['first_name'] || $user['last_name']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'success'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <strong><?php echo $user['total_orders']; ?></strong>
                                                        <br>
                                                        <small class="text-muted">orders</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    IDR <?php echo number_format($user['total_spent'] ?? 0, 0, ',', '.'); ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['last_order_date']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#userModal" 
                                                                data-user='<?php echo json_encode($user); ?>'
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <button class="btn btn-sm btn-outline-warning change-role" 
                                                                data-user-id="<?php echo $user['id']; ?>"
                                                                data-current-role="<?php echo $user['role']; ?>"
                                                                title="Change Role">
                                                            <i class="fas fa-user-cog"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info reset-password" 
                                                                data-user-id="<?php echo $user['id']; ?>"
                                                                title="Reset Password">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-user" 
                                                                data-user-id="<?php echo $user['id']; ?>"
                                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                                title="Delete User">
                                                            <i class="fas fa-trash"></i>
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
                            <nav aria-label="User pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role ? '&role=' . $role : ''; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role ? '&role=' . $role : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role ? '&role=' . $role : ''; ?>">
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

    <!-- User Detail Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userModalBody">
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
        // User actions
        $('.change-role').on('click', function() {
            var userId = $(this).data('user-id');
            var currentRole = $(this).data('current-role');
            var newRole = currentRole === 'admin' ? 'user' : 'admin';
            
            if (confirm('Ubah role user dari ' + currentRole + ' ke ' + newRole + '?')) {
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="change_role">');
                form.append('<input type="hidden" name="user_id" value="' + userId + '">');
                form.append('<input type="hidden" name="new_role" value="' + newRole + '">');
                $('body').append(form);
                form.submit();
            }
        });

        $('.reset-password').on('click', function() {
            var userId = $(this).data('user-id');
            
            if (confirm('Reset password user ini?')) {
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="reset_password">');
                form.append('<input type="hidden" name="user_id" value="' + userId + '">');
                $('body').append(form);
                form.submit();
            }
        });

        $('.delete-user').on('click', function() {
            var userId = $(this).data('user-id');
            var username = $(this).data('username');
            
            if (confirm('Hapus user "' + username + '"?')) {
                var form = $('<form method="POST"></form>');
                form.append('<input type="hidden" name="action" value="delete_user">');
                form.append('<input type="hidden" name="user_id" value="' + userId + '">');
                $('body').append(form);
                form.submit();
            }
        });

        // User modal
        $('#userModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var user = button.data('user');
            var modal = $(this);
            
            var content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>User Information</h6>
                        <table class="table table-sm">
                            <tr><td>ID:</td><td>${user.id}</td></tr>
                            <tr><td>Username:</td><td>${user.username}</td></tr>
                            <tr><td>Email:</td><td>${user.email}</td></tr>
                            <tr><td>Role:</td><td><span class="badge badge-${user.role === 'admin' ? 'danger' : 'success'}">${user.role}</span></td></tr>
                            <tr><td>Joined:</td><td>${new Date(user.created_at).toLocaleDateString()}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>User Statistics</h6>
                        <table class="table table-sm">
                            <tr><td>Total Orders:</td><td>${user.total_orders}</td></tr>
                            <tr><td>Total Spent:</td><td>IDR ${parseInt(user.total_spent || 0).toLocaleString()}</td></tr>
                            <tr><td>Last Order:</td><td>${user.last_order_date ? new Date(user.last_order_date).toLocaleDateString() : 'Never'}</td></tr>
                            <tr><td>Status:</td><td>${user.last_order_date ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td></tr>
                        </table>
                    </div>
                </div>
                ${user.first_name || user.last_name ? `
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6>Personal Information</h6>
                        <table class="table table-sm">
                            <tr><td>First Name:</td><td>${user.first_name || '-'}</td></tr>
                            <tr><td>Last Name:</td><td>${user.last_name || '-'}</td></tr>
                        </table>
                    </div>
                </div>
                ` : ''}
            `;
            
            modal.find('#userModalBody').html(content);
        });
    </script>
</body>
</html> 