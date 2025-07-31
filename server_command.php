<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';

// Check if user is logged in
requireLogin();

$serverId = $_POST['server_id'] ?? '';
$command = $_POST['command'] ?? '';
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
    die(json_encode(['error' => 'Server not found or access denied']));
}

if (empty($command)) {
    http_response_code(400);
    die(json_encode(['error' => 'Command is required']));
}

// Set content type
header('Content-Type: application/json');

// Simulate command execution
$response = [
    'success' => true,
    'command' => $command,
    'output' => '',
    'timestamp' => date('H:i:s')
];

// Simple command simulation
$command = strtolower(trim($command));

if (strpos($command, 'help') !== false) {
    $response['output'] = "Available commands:\n- help: Show this help\n- status: Show server status\n- players: Show online players\n- save: Save world\n- stop: Stop server";
} elseif (strpos($command, 'status') !== false) {
    $response['output'] = "Server Status:\n- Status: Online\n- Players: 0/20\n- Memory: 512MB/2048MB\n- CPU: 15%\n- Uptime: 2h 15m";
} elseif (strpos($command, 'players') !== false) {
    $response['output'] = "Online Players: 0\nNo players are currently online.";
} elseif (strpos($command, 'save') !== false) {
    $response['output'] = "Saving world...\nWorld saved successfully!";
} elseif (strpos($command, 'stop') !== false) {
    $response['output'] = "Stopping server...\nServer stopped successfully!";
} else {
    $response['output'] = "Unknown command: $command\nType 'help' for available commands.";
}

echo json_encode($response); 