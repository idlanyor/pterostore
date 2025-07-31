# API Documentation - Antidonasi Store

## Overview
Antidonasi Store adalah aplikasi web panel Pterodactyl private dengan sistem pembayaran QRIS. Dokumentasi ini menjelaskan endpoint API yang tersedia untuk integrasi.

## Base URL
```
https://your-domain.com/
```

## Authentication
Semua endpoint memerlukan autentikasi user. Gunakan session-based authentication.

## Endpoints

### 1. User Authentication

#### Login
```
POST /login.php
```

**Request Body:**
```json
{
    "username": "user@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "user": {
        "id": 1,
        "username": "user123",
        "email": "user@example.com",
        "role": "user"
    }
}
```

#### Register
```
POST /register.php
```

**Request Body:**
```json
{
    "username": "newuser",
    "email": "user@example.com",
    "password": "password123",
    "confirm_password": "password123"
}
```

### 2. Order Management

#### Create Order
```
POST /order.php
```

**Request Body:**
```json
{
    "product_id": 1,
    "duration": 1
}
```

**Response:**
```json
{
    "success": true,
    "order": {
        "id": 123,
        "order_number": "ORD-20241201-ABC12345",
        "amount": 50000,
        "status": "pending"
    }
}
```

#### Get Order Status
```
POST /check_order_status.php
```

**Request Body:**
```json
{
    "order_id": 123
}
```

**Response:**
```json
{
    "success": true,
    "status": "paid",
    "order_number": "ORD-20241201-ABC12345",
    "product_name": "NodeJS Standar",
    "amount": 50000,
    "server_status": "running",
    "pterodactyl_server_id": "abc123",
    "message": "Pembayaran diterima",
    "next_step": "Server sedang dibuat"
}
```

### 3. QRIS Payment

#### Generate QRIS Code
```
GET /generate_qris.php?order_id=123
```

**Response:** PNG image (QRIS QR Code)

#### Payment Webhook
```
POST /payment_webhook.php
```

**Headers:**
```
X-Signature: webhook_signature_here
Content-Type: application/json
```

**Request Body:**
```json
{
    "order_number": "ORD-20241201-ABC12345",
    "amount": 50000,
    "status": "success",
    "transaction_id": "TXN123456789"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Payment processed successfully"
}
```

### 4. File Upload

#### Upload Payment Proof
```
POST /upload_payment_proof.php
```

**Request Body (multipart/form-data):**
```
order_id: 123
payment_proof: [file]
```

**Response:**
```json
{
    "success": true,
    "message": "Bukti pembayaran berhasil diupload"
}
```

### 5. Server Management

#### Get User Servers
```
GET /user/servers.php
```

**Response:**
```json
{
    "servers": [
        {
            "id": 1,
            "name": "NodeJS Standar - user123",
            "status": "running",
            "pterodactyl_server_id": "abc123",
            "product_name": "NodeJS Standar",
            "ram": 7,
            "cpu_percent": 200
        }
    ]
}
```

#### Get Server Details
```
GET /user/server_detail.php?id=1
```

**Response:**
```json
{
    "server": {
        "id": 1,
        "name": "NodeJS Standar - user123",
        "status": "running",
        "pterodactyl_server_id": "abc123",
        "product_name": "NodeJS Standar",
        "ram": 7,
        "cpu_percent": 200,
        "disk": 25,
        "created_at": "2024-12-01 10:00:00"
    }
}
```

### 6. Admin Endpoints

#### Get Admin Dashboard Stats
```
GET /admin/dashboard.php
```

**Response:**
```json
{
    "stats": {
        "total_users": 150,
        "total_orders": 300,
        "pending_orders": 25,
        "total_revenue": 15000000,
        "active_servers": 200
    }
}
```

#### Get All Orders (Admin)
```
GET /admin/orders.php
```

**Response:**
```json
{
    "orders": [
        {
            "id": 123,
            "order_number": "ORD-20241201-ABC12345",
            "user_username": "user123",
            "product_name": "NodeJS Standar",
            "amount": 50000,
            "status": "paid",
            "created_at": "2024-12-01 10:00:00"
        }
    ]
}
```

## Error Responses

### Standard Error Format
```json
{
    "success": false,
    "error": "Error message here"
}
```

### Common HTTP Status Codes
- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

## QRIS Integration

### QRIS Data Structure
QRIS menggunakan format EMV QR Code dengan struktur berikut:

```
00020101021226550014ID.CO.QRIS.WWW011893600914
[Merchant Name]52045[Merchant City]5303360
[Amount]5802ID59[Merchant Name]60[Merchant City]
62[Additional Data]6304[CRC]
```

### Webhook Verification
Implementasi verifikasi signature webhook:

```php
function verifyWebhookSignature($payload, $signature) {
    $secret = 'your_webhook_secret_key';
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expectedSignature, $signature);
}
```

## Pterodactyl Integration

### Server Creation
```php
$serverData = [
    'name' => 'Server Name',
    'user' => 'pterodactyl_user_id',
    'egg' => 'egg_id',
    'docker_image' => 'docker_image',
    'startup' => 'startup_command',
    'environment' => [],
    'limits' => [
        'memory' => 7168, // MB
        'swap' => 0,
        'disk' => 25600, // MB
        'io' => 500,
        'cpu' => 200 // CPU limit
    ],
    'feature_limits' => [
        'databases' => 0,
        'allocations' => 1,
        'backups' => 0
    ],
    'allocation' => [
        'default' => 1
    ]
];
```

## Security Considerations

### 1. Input Validation
- Semua input harus divalidasi server-side
- Gunakan prepared statements untuk mencegah SQL injection
- Escape output untuk mencegah XSS

### 2. Authentication
- Session-based authentication
- Password hashing dengan bcrypt
- CSRF protection

### 3. File Upload
- Validasi tipe file
- Batasan ukuran file
- Rename file untuk keamanan

### 4. Rate Limiting
- Implementasi rate limiting untuk API endpoints
- Batasan request per IP

## Configuration

### Database Configuration
```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'database_name');
```

### Pterodactyl Configuration
```php
// config/pterodactyl.php
define('PTERODACTYL_URL', 'https://your-pterodactyl-panel.com');
define('PTERODACTYL_API_KEY', 'your-ptla-api-key');
define('PTERODACTYL_CLIENT_API_KEY', 'your-ptlc-api-key');
```

## Testing

### Test QRIS Integration
```bash
# Test QRIS generation
curl -X GET "https://your-domain.com/generate_qris.php?order_id=123" \
  -H "Cookie: PHPSESSID=your_session_id"

# Test payment webhook
curl -X POST "https://your-domain.com/payment_webhook.php" \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_signature" \
  -d '{
    "order_number": "ORD-20241201-ABC12345",
    "amount": 50000,
    "status": "success",
    "transaction_id": "TXN123456789"
  }'
```

## Support

Untuk bantuan teknis atau pertanyaan tentang API:
- **WhatsApp**: 0895395590009
- **Community**: https://chat.whatsapp.com/I5JCuQnIo4f79JsZAGCvDD
- **Email**: support@antidonasi.com

---

**Antidonasi Store** - Panel Pterodactyl Private dengan performa terbaik! 