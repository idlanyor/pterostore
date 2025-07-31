<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $serverId = $_POST['server_id'] ?? '';
    
    // Verify server belongs to user
    $stmt = $pdo->prepare("
        SELECT s.* FROM servers s 
        JOIN orders o ON s.order_id = o.id 
        WHERE s.pterodactyl_server_id = ? AND o.user_id = ?
    ");
    $stmt->execute([$serverId, $_SESSION['user_id']]);
    $server = $stmt->fetch();
    
    if (!$server) {
        http_response_code(404);
        echo json_encode(['error' => 'Server not found']);
        exit();
    }
    
    // Initialize Pterodactyl API
    $pterodactyl = new PterodactylAPI();
    
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
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit();
    }
    
    if (isset($response['error'])) {
        http_response_code(500);
        echo json_encode(['error' => $response['error']]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Server ' . $action . 'ed successfully']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?> 