<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? 0;
    
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Order ID is required']);
        exit();
    }
    
    // Get order status with more details
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name, s.status as server_status, s.pterodactyl_server_id
        FROM orders o 
        LEFT JOIN products p ON o.product_id = p.id 
        LEFT JOIN servers s ON o.id = s.order_id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if ($order) {
        $response = [
            'success' => true,
            'status' => $order['status'],
            'order_number' => $order['order_number'],
            'product_name' => $order['product_name'],
            'amount' => $order['amount'],
            'server_status' => $order['server_status'] ?? null,
            'pterodactyl_server_id' => $order['pterodactyl_server_id'] ?? null,
            'created_at' => $order['created_at'],
            'paid_at' => $order['paid_at'] ?? null
        ];
        
        // Add status-specific information
        switch ($order['status']) {
            case 'pending':
                $response['message'] = 'Menunggu pembayaran';
                $response['next_step'] = 'Lakukan pembayaran via QRIS';
                break;
            case 'paid':
                $response['message'] = 'Pembayaran diterima';
                $response['next_step'] = 'Server sedang dibuat';
                break;
            case 'processing':
                $response['message'] = 'Server sedang diproses';
                $response['next_step'] = 'Menunggu server siap';
                break;
            case 'completed':
                $response['message'] = 'Server siap digunakan';
                $response['next_step'] = 'Akses server Anda';
                break;
            case 'cancelled':
                $response['message'] = 'Order dibatalkan';
                $response['next_step'] = 'Buat order baru';
                break;
            case 'failed':
                $response['message'] = 'Order gagal diproses';
                $response['next_step'] = 'Hubungi support';
                break;
            default:
                $response['message'] = 'Status tidak diketahui';
                $response['next_step'] = 'Hubungi support';
        }
        
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?> 