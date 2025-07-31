<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Check if user is logged in
requireLogin();

$orderId = $_GET['order_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Verify order belongs to user or user is admin
$whereClause = "WHERE o.id = ?";
$params = [$orderId];

if (!isAdmin()) {
    $whereClause .= " AND o.user_id = ?";
    $params[] = $userId;
}

$stmt = $pdo->prepare("
    SELECT o.*, u.username 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    $whereClause
");
$stmt->execute($params);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    die('Order not found');
}

$paymentProofPath = 'uploads/payment_proofs/' . $order['payment_proof'];

if (!file_exists($paymentProofPath)) {
    http_response_code(404);
    die('Payment proof not found');
}

// Get file info
$fileInfo = pathinfo($paymentProofPath);
$extension = strtolower($fileInfo['extension']);

// Set appropriate content type
$contentTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

if (!isset($contentTypes[$extension])) {
    http_response_code(400);
    die('Invalid file type');
}

// Output the image
header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($paymentProofPath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($paymentProofPath);
exit; 