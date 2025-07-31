<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Check if user is logged in
requireLogin();

$serverId = $_GET['server_id'] ?? '';
$userId = $_SESSION['user_id'];

// Verify server belongs to user
$stmt = $pdo->prepare("
    SELECT s.*, o.user_id 
    FROM servers s 
    JOIN orders o ON s.order_id = o.id 
    WHERE s.pterodactyl_server_id = ? AND o.user_id = ?
");
$stmt->execute([$serverId, $userId]);
$server = $stmt->fetch();

if (!$server) {
    http_response_code(404);
    die('Server not found or access denied');
}

// Set content type for SSE (Server-Sent Events)
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Send initial connection message
echo "data: {\"type\": \"connection\", \"message\": \"Console connected\"}\n\n";

// Simulate console output
$consoleMessages = [
    "Starting server...",
    "Loading configuration...",
    "Initializing services...",
    "Server is running on port 25565",
    "Players online: 0",
    "Memory usage: 512MB / 2048MB",
    "CPU usage: 15%",
    "Uptime: 2 hours 15 minutes"
];

foreach ($consoleMessages as $message) {
    echo "data: {\"type\": \"log\", \"message\": \"" . addslashes($message) . "\", \"timestamp\": \"" . date('H:i:s') . "\"}\n\n";
    ob_flush();
    flush();
    sleep(1);
}

// Keep connection alive for a while
for ($i = 0; $i < 30; $i++) {
    $randomMessages = [
        "Player joined: TestUser123",
        "Player left: TestUser123",
        "World saved successfully",
        "Backup completed",
        "Memory usage: 512MB / 2048MB",
        "CPU usage: 15%"
    ];
    
    $randomMessage = $randomMessages[array_rand($randomMessages)];
    echo "data: {\"type\": \"log\", \"message\": \"" . addslashes($randomMessage) . "\", \"timestamp\": \"" . date('H:i:s') . "\"}\n\n";
    ob_flush();
    flush();
    sleep(rand(5, 15));
}

echo "data: {\"type\": \"disconnect\", \"message\": \"Console disconnected\"}\n\n"; 