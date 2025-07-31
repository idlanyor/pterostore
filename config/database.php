<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'pterostore');
define('DB_PASS', 'pterostore1');
define('DB_NAME', 'pterodactyl_store');

// Create connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create tables if not exist
function createTables($pdo) {
    // Users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Products table
    $sql = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(10) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        category VARCHAR(50) NOT NULL,
        ram INT NOT NULL,
        cpu_percent INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Orders table
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        order_number VARCHAR(20) UNIQUE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'paid', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
        payment_method VARCHAR(50) DEFAULT 'qris',
        payment_proof VARCHAR(255),
        server_id VARCHAR(50),
        pterodactyl_user_id VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )";
    $pdo->exec($sql);

    // Servers table
    $sql = "CREATE TABLE IF NOT EXISTS servers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        pterodactyl_server_id VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        node_id VARCHAR(50) NOT NULL,
        allocation_id VARCHAR(50) NOT NULL,
        egg_id VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'installing',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    )";
    $pdo->exec($sql);

    // Insert default products if not exist
    $products = [
        ['A1', 'NodeJS Kroco', 'NodeJS', 3, 100, 5000.00, 'NodeJS server dengan 3GB RAM dan 100% CPU'],
        ['A2', 'NodeJS Karbit', 'NodeJS', 5, 150, 7500.00, 'NodeJS server dengan 5GB RAM dan 150% CPU'],
        ['A3', 'NodeJS Standar', 'NodeJS', 7, 200, 10000.00, 'NodeJS server dengan 7GB RAM dan 200% CPU'],
        ['A4', 'NodeJS Sepuh', 'NodeJS', 11, 250, 12500.00, 'NodeJS server dengan 11GB RAM dan 250% CPU'],
        ['A5', 'NodeJS Suhu', 'NodeJS', 13, 300, 15000.00, 'NodeJS server dengan 13GB RAM dan 300% CPU'],
        ['A6', 'NodeJS Pro Max', 'NodeJS', 16, 400, 20000.00, 'NodeJS server dengan 16GB RAM dan 400% CPU'],
        ['B1', 'VPS Kroco', 'VPS', 3, 100, 7500.00, 'VPS server dengan 3GB RAM dan 100% CPU'],
        ['B2', 'VPS Karbit', 'VPS', 5, 150, 10000.00, 'VPS server dengan 5GB RAM dan 150% CPU'],
        ['B3', 'VPS Standar', 'VPS', 7, 200, 15000.00, 'VPS server dengan 7GB RAM dan 200% CPU'],
        ['B4', 'VPS Sepuh', 'VPS', 9, 250, 20000.00, 'VPS server dengan 9GB RAM dan 250% CPU'],
        ['B5', 'VPS Suhu', 'VPS', 11, 300, 25000.00, 'VPS server dengan 11GB RAM dan 300% CPU'],
        ['B6', 'VPS Pro Max', 'VPS', 13, 350, 35000.00, 'VPS server dengan 13GB RAM dan 350% CPU'],
        ['C1', 'Python Kroco', 'Python', 3, 100, 3000.00, 'Python server dengan 3GB RAM dan 100% CPU'],
        ['C2', 'Python Karbit', 'Python', 5, 150, 5000.00, 'Python server dengan 5GB RAM dan 150% CPU'],
        ['C3', 'Python Standar', 'Python', 7, 150, 7500.00, 'Python server dengan 7GB RAM dan 150% CPU'],
        ['C4', 'Python Sepuh', 'Python', 9, 200, 10000.00, 'Python server dengan 9GB RAM dan 200% CPU'],
        ['C5', 'Python Suhu', 'Python', 11, 250, 12500.00, 'Python server dengan 11GB RAM dan 250% CPU'],
        ['C6', 'Python Pro Max', 'Python', 13, 300, 17500.00, 'Python server dengan 13GB RAM dan 300% CPU']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO products (code, name, category, ram, cpu_percent, price, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($products as $product) {
        $stmt->execute($product);
    }

    // Create default admin user if not exist
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@antidonasi.com', $adminPassword, 'admin']);
}

// Initialize tables
createTables($pdo);
?> 