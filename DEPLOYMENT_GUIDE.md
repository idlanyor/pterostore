# Deployment Guide - Antidonasi Store

## Prerequisites

### System Requirements
- **OS**: Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- **PHP**: 7.4 atau lebih tinggi
- **MySQL**: 5.7 atau lebih tinggi / MariaDB 10.3+
- **Web Server**: Apache 2.4+ atau Nginx 1.18+
- **Memory**: Minimum 2GB RAM
- **Storage**: Minimum 20GB free space

### PHP Extensions Required
```bash
php-fpm
php-mysql
php-curl
php-gd
php-mbstring
php-xml
php-zip
php-json
php-session
```

## Installation Steps

### 1. Server Setup

#### Update System
```bash
sudo apt update && sudo apt upgrade -y
```

#### Install Required Packages
```bash
sudo apt install -y apache2 mysql-server php php-fpm php-mysql php-curl php-gd php-mbstring php-xml php-zip php-json unzip git composer
```

### 2. Database Setup

#### Create Database
```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE pterodactyl_store;
CREATE USER 'store_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON pterodactyl_store.* TO 'store_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Application Setup

#### Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/yourusername/pterodactyl-store.git
sudo chown -R www-data:www-data pterodactyl-store
sudo chmod -R 755 pterodactyl-store
```

#### Install Dependencies
```bash
cd pterodactyl-store
composer install --no-dev --optimize-autoloader
```

#### Configure Application
```bash
# Copy configuration files
cp config/database.example.php config/database.php
cp config/pterodactyl.example.php config/pterodactyl.php

# Edit database configuration
nano config/database.php
```

**Database Configuration:**
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'store_user');
define('DB_PASS', 'your_secure_password');
define('DB_NAME', 'pterodactyl_store');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
```

**Pterodactyl Configuration:**
```php
<?php
define('PTERODACTYL_URL', 'https://your-pterodactyl-panel.com');
define('PTERODACTYL_API_KEY', 'your-ptla-api-key');
define('PTERODACTYL_CLIENT_API_KEY', 'your-ptlc-api-key');
?>
```

### 4. Web Server Configuration

#### Apache Configuration
```bash
sudo nano /etc/apache2/sites-available/pterodactyl-store.conf
```

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/pterodactyl-store
    
    <Directory /var/www/pterodactyl-store>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/pterodactyl-store_error.log
    CustomLog ${APACHE_LOG_DIR}/pterodactyl-store_access.log combined
</VirtualHost>
```

#### Enable Site and Modules
```bash
sudo a2ensite pterodactyl-store
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx Configuration (Alternative)
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/pterodactyl-store;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 5. SSL Certificate (Recommended)

#### Install Certbot
```bash
sudo apt install certbot python3-certbot-apache
```

#### Obtain SSL Certificate
```bash
sudo certbot --apache -d your-domain.com -d www.your-domain.com
```

### 6. File Permissions

#### Set Proper Permissions
```bash
sudo chown -R www-data:www-data /var/www/pterodactyl-store
sudo chmod -R 755 /var/www/pterodactyl-store
sudo chmod -R 777 /var/www/pterodactyl-store/uploads
```

### 7. Database Initialization

#### Create Tables
Access your application in browser to automatically create database tables, or run:

```sql
-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    pterodactyl_user_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    ram INT NOT NULL,
    cpu_percent INT NOT NULL,
    disk INT NOT NULL,
    pterodactyl_egg_id INT,
    docker_image VARCHAR(100),
    startup_command TEXT,
    environment_variables JSON,
    allocation_id INT DEFAULT 1,
    database_limit INT DEFAULT 0,
    allocation_limit INT DEFAULT 1,
    backup_limit INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'processing', 'completed', 'cancelled', 'failed') DEFAULT 'pending',
    payment_proof VARCHAR(255),
    payment_transaction_id VARCHAR(100),
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Servers table
CREATE TABLE servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    pterodactyl_server_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    status ENUM('installing', 'running', 'stopped', 'error') DEFAULT 'installing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Insert default admin user
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@antidonasi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample products
INSERT INTO products (name, code, category, description, price, ram, cpu_percent, disk, pterodactyl_egg_id, docker_image, startup_command) VALUES
('NodeJS Kroco', 'A1', 'NodeJS', 'NodeJS server dengan performa dasar', 5000, 3, 100, 10, 1, 'ghcr.io/parkervcp/installers:nodejs', 'npm start'),
('NodeJS Karbit', 'A2', 'NodeJS', 'NodeJS server dengan performa menengah', 7500, 5, 150, 15, 1, 'ghcr.io/parkervcp/installers:nodejs', 'npm start'),
('NodeJS Standar', 'A3', 'NodeJS', 'NodeJS server dengan performa standar', 10000, 7, 200, 25, 1, 'ghcr.io/parkervcp/installers:nodejs', 'npm start'),
('VPS Kroco', 'B1', 'VPS', 'VPS server dengan performa dasar', 7500, 3, 100, 10, 2, 'ghcr.io/parkervcp/installers:ubuntu', 'bash'),
('VPS Karbit', 'B2', 'VPS', 'VPS server dengan performa menengah', 10000, 5, 150, 15, 2, 'ghcr.io/parkervcp/installers:ubuntu', 'bash'),
('VPS Standar', 'B3', 'VPS', 'VPS server dengan performa standar', 15000, 7, 200, 25, 2, 'ghcr.io/parkervcp/installers:ubuntu', 'bash'),
('Python Kroco', 'C1', 'Python', 'Python server dengan performa dasar', 3000, 3, 100, 10, 3, 'ghcr.io/parkervcp/installers:python', 'python app.py'),
('Python Karbit', 'C2', 'Python', 'Python server dengan performa menengah', 5000, 5, 150, 15, 3, 'ghcr.io/parkervcp/installers:python', 'python app.py'),
('Python Standar', 'C3', 'Python', 'Python server dengan performa standar', 7500, 7, 150, 25, 3, 'ghcr.io/parkervcp/installers:python', 'python app.py');
```

### 8. QRIS Integration Setup

#### Configure QRIS Provider
Edit `config/pterodactyl.php` and add your QRIS provider settings:

```php
// QRIS Configuration
define('QRIS_MERCHANT_ID', 'your_merchant_id');
define('QRIS_MERCHANT_NAME', 'Antidonasi Store');
define('QRIS_MERCHANT_CITY', 'Jakarta');
define('QRIS_WEBHOOK_SECRET', 'your_webhook_secret');
```

#### Test QRIS Integration
```bash
# Test QRIS generation
curl -X GET "https://your-domain.com/generate_qris.php?order_id=1" \
  -H "Cookie: PHPSESSID=test_session"

# Test webhook endpoint
curl -X POST "https://your-domain.com/payment_webhook.php" \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_signature" \
  -d '{"order_number":"TEST-123","amount":50000,"status":"success"}'
```

### 9. Security Hardening

#### Configure Firewall
```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

#### Secure MySQL
```bash
sudo mysql_secure_installation
```

#### Set File Permissions
```bash
sudo find /var/www/pterodactyl-store -type f -exec chmod 644 {} \;
sudo find /var/www/pterodactyl-store -type d -exec chmod 755 {} \;
sudo chmod 777 /var/www/pterodactyl-store/uploads
```

### 10. Monitoring Setup

#### Install Monitoring Tools
```bash
sudo apt install htop iotop nethogs
```

#### Setup Log Rotation
```bash
sudo nano /etc/logrotate.d/pterodactyl-store
```

```
/var/www/pterodactyl-store/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### 11. Backup Setup

#### Create Backup Script
```bash
sudo nano /usr/local/bin/backup-pterodactyl-store.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/backup/pterodactyl-store"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u store_user -p'your_password' pterodactyl_store > $BACKUP_DIR/database_$DATE.sql

# Backup application files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C /var/www pterodactyl-store

# Remove old backups (keep last 7 days)
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

#### Make Script Executable
```bash
sudo chmod +x /usr/local/bin/backup-pterodactyl-store.sh
```

#### Setup Cron Job
```bash
sudo crontab -e
```

Add this line for daily backup at 2 AM:
```
0 2 * * * /usr/local/bin/backup-pterodactyl-store.sh
```

## Post-Installation Checklist

- [ ] Application accessible via web browser
- [ ] Database tables created successfully
- [ ] Admin user can login (admin/admin123)
- [ ] QRIS integration working
- [ ] File uploads working
- [ ] SSL certificate installed
- [ ] Firewall configured
- [ ] Backup system working
- [ ] Monitoring tools installed
- [ ] Log rotation configured

## Troubleshooting

### Common Issues

#### 1. Permission Denied
```bash
sudo chown -R www-data:www-data /var/www/pterodactyl-store
sudo chmod -R 755 /var/www/pterodactyl-store
```

#### 2. Database Connection Error
- Check database credentials in `config/database.php`
- Ensure MySQL service is running: `sudo systemctl status mysql`

#### 3. QRIS Not Working
- Check QRIS provider configuration
- Verify webhook URL is accessible
- Check webhook signature verification

#### 4. File Upload Issues
- Check upload directory permissions
- Verify PHP upload settings in `php.ini`
- Check file size limits

### Log Files
- Apache error log: `/var/log/apache2/error.log`
- Application logs: `/var/www/pterodactyl-store/logs/`
- MySQL logs: `/var/log/mysql/error.log`

## Support

For technical support:
- **WhatsApp**: 0895395590009
- **Community**: https://chat.whatsapp.com/I5JCuQnIo4f79JsZAGCvDD
- **Email**: support@antidonasi.com

---

**Antidonasi Store** - Panel Pterodactyl Private dengan performa terbaik! 