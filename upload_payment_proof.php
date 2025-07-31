<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? 0;
    
    // Verify order belongs to user
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $error = 'Order tidak ditemukan';
    } else {
        // Handle file upload
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['payment_proof'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                $error = 'File harus berupa gambar (JPG, PNG, GIF)';
            } else {
                // Validate file size (max 5MB)
                if ($file['size'] > 5 * 1024 * 1024) {
                    $error = 'Ukuran file maksimal 5MB';
                } else {
                    // Create uploads directory if not exists
                    $uploadDir = 'uploads/payment_proofs/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'payment_' . $order['order_number'] . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Update order with payment proof
                        $stmt = $pdo->prepare("UPDATE orders SET payment_proof = ?, status = 'paid' WHERE id = ?");
                        if ($stmt->execute([$filepath, $orderId])) {
                            $success = 'Bukti pembayaran berhasil diupload. Admin akan mengecek dan memproses order Anda.';
                            
                            // Redirect to order detail after 3 seconds
                            header("refresh:3;url=user/order_detail.php?id=" . $orderId);
                        } else {
                            $error = 'Gagal menyimpan bukti pembayaran';
                        }
                    } else {
                        $error = 'Gagal mengupload file';
                    }
                }
            }
        } else {
            $error = 'Pilih file bukti pembayaran';
        }
    }
}

// If there's an error, redirect back to order confirmation
if ($error) {
    $_SESSION['upload_error'] = $error;
    header('Location: order_confirmation.php?order_id=' . $orderId);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Bukti Pembayaran - Antidonasi Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="user/order_detail.php?id=<?php echo $orderId; ?>" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali ke Detail Order
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 