<?php
// Pterodactyl API Configuration
define('PTERODACTYL_URL', 'https://panel.roidev.my.id'); // Ganti dengan URL panel Pterodactyl Anda
define('PTERODACTYL_API_KEY', 'ptla_suW1wqLztnQUv7IRUnr9B395MQ7YFTcTmeHI4ThqiXv'); // Ganti dengan API key PTLA Anda
define('PTERODACTYL_CLIENT_API_KEY', 'ptlc_Sz7FDOVryIBdpYDLrjnRgzzC4zieAkpGxXDF5FA0a3F'); // Ganti dengan API key PTLC Anda

// Node configuration
define('DEFAULT_NODE_ID', '1'); // ID node default untuk server baru
define('DEFAULT_EGG_ID', '5'); // ID egg default (sesuaikan dengan egg yang Anda gunakan)

// Pterodactyl API Class
class PterodactylAPI {
    private $baseUrl;
    private $apiKey;
    private $clientApiKey;

    public function __construct() {
        $this->baseUrl = PTERODACTYL_URL;
        $this->apiKey = PTERODACTYL_API_KEY;
        $this->clientApiKey = PTERODACTYL_CLIENT_API_KEY;
    }

    // Create user in Pterodactyl
    public function createUser($username, $email, $firstName, $lastName) {
        // Check if user already exists
        $existingUser = $this->getUserByEmail($email);
        if ($existingUser && !isset($existingUser['error'])) {
            return $existingUser; // Return existing user
        }

        $url = $this->baseUrl . '/api/application/users';
        $data = [
            'username' => $username,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => $this->generatePassword(),
            'root_admin' => false,
            'language' => 'en'
        ];

        $response = $this->makeRequest($url, 'POST', $data, $this->apiKey);
        return $response;
    }

    // Get user by email
    public function getUserByEmail($email) {
        $url = $this->baseUrl . '/api/application/users';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        
        if (isset($response['data'])) {
            foreach ($response['data'] as $user) {
                if ($user['attributes']['email'] === $email) {
                    return $user;
                }
            }
        }
        
        return ['error' => 'User not found'];
    }

    // Create server in Pterodactyl
    public function createServer($name, $userId, $nodeId, $allocationId, $eggId, $ram, $cpuPercent) {
        // Get egg details to determine server type
        $eggDetails = $this->getEgg($eggId);
        if (isset($eggDetails['error'])) {
            return $eggDetails;
        }

        $url = $this->baseUrl . '/api/application/servers';
        
        // Determine server configuration based on egg
        $serverConfig = $this->getServerConfigByEgg($eggDetails, $ram);
        
        $data = [
            'name' => $name,
            'user' => $userId,
            'node' => $nodeId,
            'allocation' => $allocationId,
            'egg' => $eggId,
            'docker_image' => $serverConfig['docker_image'],
            'startup' => $serverConfig['startup'],
            'environment' => $serverConfig['environment'],
            'limits' => [
                'memory' => $ram * 1024, // Convert GB to MB
                'swap' => 0,
                'disk' => $serverConfig['disk_limit'], // Dynamic disk limit
                'io' => 500,
                'cpu' => $cpuPercent
            ],
            'feature_limits' => [
                'databases' => 0,
                'backups' => 0,
                'allocations' => 1
            ]
        ];

        $response = $this->makeRequest($url, 'POST', $data, $this->apiKey);
        return $response;
    }

    // Get egg details
    public function getEgg($eggId) {
        $url = $this->baseUrl . '/api/application/eggs/' . $eggId;
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get server configuration based on egg type
    private function getServerConfigByEgg($eggDetails, $ram) {
        $eggName = strtolower($eggDetails['attributes']['name'] ?? '');
        
        if (strpos($eggName, 'minecraft') !== false) {
            return [
                'docker_image' => 'quay.io/pterodactyl/core:java',
                'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'environment' => [
                    'SERVER_JARFILE' => 'server.jar',
                    'VANILLA_VERSION' => 'latest',
                    'STARTUP' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}'
                ],
                'disk_limit' => 10000 // 10GB for Minecraft
            ];
        } elseif (strpos($eggName, 'vps') !== false || strpos($eggName, 'ubuntu') !== false) {
            return [
                'docker_image' => 'quay.io/pterodactyl/core:ubuntu',
                'startup' => 'bash',
                'environment' => [
                    'STARTUP' => 'bash'
                ],
                'disk_limit' => 20000 // 20GB for VPS
            ];
        } else {
            // Default configuration
            return [
                'docker_image' => 'quay.io/pterodactyl/core:java',
                'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'environment' => [
                    'SERVER_JARFILE' => 'server.jar',
                    'STARTUP' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}'
                ],
                'disk_limit' => 10000
            ];
        }
    }

    // Get available allocations
    public function getAllocations($nodeId) {
        $url = $this->baseUrl . '/api/application/nodes/' . $nodeId . '/allocations';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get available eggs
    public function getEggs() {
        $url = $this->baseUrl . '/api/application/nests/1/eggs';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get available nodes
    public function getNodes() {
        $url = $this->baseUrl . '/api/application/nodes';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get node details
    public function getNode($nodeId) {
        $url = $this->baseUrl . '/api/application/nodes/' . $nodeId;
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get server details
    public function getServer($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId;
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get all servers from Pterodactyl
    public function getAllServers() {
        $url = $this->baseUrl . '/api/application/servers';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get server resources
    public function getServerResources($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/resources';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get server logs
    public function getServerLogs($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/logs';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Get user details
    public function getUser($userId) {
        $url = $this->baseUrl . '/api/application/users/' . $userId;
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Start server
    public function startServer($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/power';
        $data = ['signal' => 'start'];
        $response = $this->makeRequest($url, 'POST', $data, $this->apiKey);
        return $response;
    }

    // Stop server
    public function stopServer($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/power';
        $data = ['signal' => 'stop'];
        $response = $this->makeRequest($url, 'POST', $data, $this->apiKey);
        return $response;
    }

    // Restart server
    public function restartServer($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/power';
        $data = ['signal' => 'restart'];
        $response = $this->makeRequest($url, 'POST', $data, $this->apiKey);
        return $response;
    }

    // Suspend server
    public function suspendServer($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/suspend';
        $response = $this->makeRequest($url, 'POST', null, $this->apiKey);
        return $response;
    }

    // Unsuspend server
    public function unsuspendServer($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/unsuspend';
        $response = $this->makeRequest($url, 'POST', null, $this->apiKey);
        return $response;
    }

    // Delete server
    public function deleteServer($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId;
        $response = $this->makeRequest($url, 'DELETE', null, $this->apiKey);
        return $response;
    }

    // Sync server status with database
    public function syncServerStatus($pdo, $serverId) {
        $serverDetails = $this->getServer($serverId);
        if (isset($serverDetails['error'])) {
            return $serverDetails;
        }

        $status = $serverDetails['attributes']['current_state'] ?? 'unknown';
        
        $updateStmt = $pdo->prepare("UPDATE servers SET status = ?, updated_at = NOW() WHERE pterodactyl_server_id = ?");
        $updateStmt->execute([$status, $serverId]);
        
        return ['success' => true, 'status' => $status];
    }

    // Sync all servers status
    public function syncAllServersStatus($pdo) {
        $stmt = $pdo->prepare("SELECT pterodactyl_server_id FROM servers WHERE pterodactyl_server_id IS NOT NULL");
        $stmt->execute();
        $servers = $stmt->fetchAll();

        $results = [];
        foreach ($servers as $server) {
            $serverId = $server['pterodactyl_server_id'];
            $result = $this->syncServerStatus($pdo, $serverId);
            $results[$serverId] = $result;
        }

        return $results;
    }

    // Get server console
    public function getServerConsole($serverId) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/console';
        $response = $this->makeRequest($url, 'GET', null, $this->apiKey);
        return $response;
    }

    // Send command to server
    public function sendServerCommand($serverId, $command) {
        $url = $this->baseUrl . '/api/application/servers/' . $serverId . '/command';
        $data = ['command' => $command];
        $response = $this->makeRequest($url, 'POST', $data, $this->apiKey);
        return $response;
    }

    // Generate random password
    private function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    // Make HTTP request
    private function makeRequest($url, $method = 'GET', $data = null, $apiKey = null) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($apiKey) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        // Log request for debugging
        $this->logRequest($method, $url, $data, $httpCode, $response, $error);

        if ($error) {
            return ['error' => 'CURL Error: ' . $error];
        }

        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $decodedResponse;
        } else {
            $errorMessage = 'HTTP Error: ' . $httpCode;
            if (isset($decodedResponse['errors'])) {
                $errorMessage .= ' - ' . json_encode($decodedResponse['errors']);
            }
            return [
                'error' => $errorMessage,
                'response' => $decodedResponse,
                'http_code' => $httpCode
            ];
        }
    }

    // Log request for debugging
    private function logRequest($method, $url, $data, $httpCode, $response, $error) {
        $logFile = __DIR__ . '/../logs/pterodactyl_api.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];

        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}

// Helper functions
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getProductByCode($pdo, $code) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    return $stmt->fetch();
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}
?> 