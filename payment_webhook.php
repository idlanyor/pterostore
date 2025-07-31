<?php
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log the webhook data for debugging
error_log('QRIS Webhook received: ' . $input);

// Verify webhook signature (implement based on your QRIS provider)
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if (!verifyWebhookSignature($input, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit();
}

try {
    // Process the payment notification
    $result = processPaymentNotification($data);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['message']]);
    }
} catch (Exception $e) {
    error_log('Payment webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function processPaymentNotification($data) {
    global $pdo;
    
    // Extract payment information
    $orderNumber = $data['order_number'] ?? $data['reference_id'] ?? '';
    $amount = $data['amount'] ?? 0;
    $status = $data['status'] ?? '';
    $transactionId = $data['transaction_id'] ?? '';
    
    if (empty($orderNumber)) {
        return ['success' => false, 'message' => 'Order number not found'];
    }
    
    // Find the order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if (!$order) {
        return ['success' => false, 'message' => 'Order not found'];
    }
    
    // Verify amount
    if ($amount != $order['amount']) {
        return ['success' => false, 'message' => 'Amount mismatch'];
    }
    
    // Update order status based on payment status
    $newStatus = '';
    switch (strtolower($status)) {
        case 'success':
        case 'paid':
        case 'completed':
            $newStatus = 'paid';
            break;
        case 'pending':
            $newStatus = 'pending';
            break;
        case 'failed':
        case 'cancelled':
            $newStatus = 'cancelled';
            break;
        default:
            return ['success' => false, 'message' => 'Invalid payment status'];
    }
    
    // Update order
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = ?, 
            payment_transaction_id = ?,
            paid_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    if ($stmt->execute([$newStatus, $transactionId, $order['id']])) {
        // If payment is successful, trigger server provisioning
        if ($newStatus === 'paid') {
            provisionServer($order);
        }
        
        return ['success' => true, 'message' => 'Order updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to update order'];
    }
}

function provisionServer($order) {
    global $pdo;
    
    try {
        // Get product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$order['product_id']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        // Get user details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$order['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Create server in Pterodactyl
        $serverData = [
            'name' => $product['name'] . ' - ' . $user['username'],
            'user' => $user['pterodactyl_user_id'] ?? null,
            'egg' => $product['pterodactyl_egg_id'],
            'docker_image' => $product['docker_image'],
            'startup' => $product['startup_command'],
            'environment' => $product['environment_variables'] ?? [],
            'limits' => [
                'memory' => $product['ram'] * 1024, // Convert to MB
                'swap' => 0,
                'disk' => $product['disk'] * 1024, // Convert to MB
                'io' => 500,
                'cpu' => $product['cpu_percent'] * 100 // Convert to CPU limit
            ],
            'feature_limits' => [
                'databases' => $product['database_limit'] ?? 0,
                'allocations' => $product['allocation_limit'] ?? 1,
                'backups' => $product['backup_limit'] ?? 0
            ],
            'allocation' => [
                'default' => $product['allocation_id'] ?? 1
            ]
        ];
        
        // Create server via Pterodactyl API
        $response = createPterodactylServer($serverData);
        
        if ($response['success']) {
            // Update order status to processing
            $stmt = $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
            $stmt->execute([$order['id']]);
            
            // Store server information
            $stmt = $pdo->prepare("
                INSERT INTO servers (order_id, pterodactyl_server_id, name, status, created_at)
                VALUES (?, ?, ?, 'installing', NOW())
            ");
            $stmt->execute([$order['id'], $response['server_id'], $serverData['name']]);
            
            // Send notification to user
            sendPaymentSuccessNotification($user, $order, $product);
            
        } else {
            throw new Exception('Failed to create server: ' . $response['message']);
        }
        
    } catch (Exception $e) {
        error_log('Server provisioning error: ' . $e->getMessage());
        
        // Update order status to failed
        $stmt = $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ?");
        $stmt->execute([$order['id']]);
    }
}

function verifyWebhookSignature($payload, $signature) {
    // Implement signature verification based on your QRIS provider
    // This is a placeholder implementation
    $secret = 'your_webhook_secret_key';
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    
    return hash_equals($expectedSignature, $signature);
}

function sendPaymentSuccessNotification($user, $order, $product) {
    // Send email notification
    $subject = 'Pembayaran Berhasil - Antidonasi Store';
    $message = "
    Halo {$user['username']},
    
    Pembayaran untuk order {$order['order_number']} telah berhasil!
    
    Detail Order:
    - Produk: {$product['name']}
    - Jumlah: IDR " . number_format($order['amount'], 0, ',', '.') . "
    - Status: Sedang diproses
    
    Server Anda sedang dibuat dan akan siap dalam beberapa menit.
    Anda akan mendapat notifikasi ketika server siap.
    
    Terima kasih telah memilih Antidonasi Store!
    
    Salam,
    Tim Antidonasi Store
    ";
    
    // Send email (implement your email sending logic)
    // mail($user['email'], $subject, $message);
    
    // Log the notification
    error_log("Payment success notification sent to user {$user['id']} for order {$order['id']}");
} 