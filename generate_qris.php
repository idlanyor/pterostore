<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';
require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

// Check if user is logged in
requireLogin();

$orderId = $_GET['order_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Verify order belongs to user
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    die('Order not found');
}

// Generate proper QRIS EMV QR Code data
$qrisData = generateQRISData($order);

// Create QR Code
$qrCode = QrCode::create($qrisData)
    ->setSize(300)
    ->setMargin(10)
    ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
    ->setForegroundColor(new Color(0, 0, 0))
    ->setBackgroundColor(new Color(255, 255, 255));

// Create generic writer
$writer = new PngWriter();

// Write the QR code to a file
$result = $writer->write($qrCode);

// Set headers
header('Content-Type: image/png');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the QR code
echo $result->getString();

function generateQRISData($order) {
    // EMV QR Code specification for QRIS
    $merchantName = 'Antidonasi Store';
    $merchantCity = 'Jakarta';
    $amount = $order['amount'];
    $orderNumber = $order['order_number'];
    
    // Build EMV QR Code string
    $qrString = '';
    
    // Payload Format Indicator (00)
    $qrString .= '000201';
    
    // Point of Initiation Method (01) - 12 for QRIS
    $qrString .= '010212';
    
    // Merchant Account Information (26)
    $merchantInfo = '';
    // Global Unique Identifier (00) - ID for QRIS
    $merchantInfo .= '0016A000000677010112';
    // Merchant Account Information (01) - Merchant ID
    $merchantInfo .= '0113' . lengthEncode('8888888888888888');
    
    $qrString .= '26' . lengthEncode($merchantInfo);
    
    // Merchant Category Code (52) - 0000 for General
    $qrString .= '52040000';
    
    // Transaction Currency (53) - 360 for IDR
    $qrString .= '5303360';
    
    // Transaction Amount (54)
    $qrString .= '54' . lengthEncode($amount);
    
    // Country Code (58) - ID for Indonesia
    $qrString .= '5802ID';
    
    // Merchant Name (59)
    $qrString .= '59' . lengthEncode($merchantName);
    
    // Merchant City (60)
    $qrString .= '60' . lengthEncode($merchantCity);
    
    // Additional Data Field Template (62)
    $additionalData = '';
    // Reference Label (00) - Order number
    $additionalData .= '00' . lengthEncode($orderNumber);
    
    $qrString .= '62' . lengthEncode($additionalData);
    
    // CRC (63) - Cyclic Redundancy Check
    $crc = crc16($qrString . '6304');
    $qrString .= '6304' . strtoupper(dechex($crc));
    
    return $qrString;
}

function lengthEncode($string) {
    $length = strlen($string);
    return str_pad($length, 2, '0', STR_PAD_LEFT) . $string;
}

function crc16($string) {
    $crc = 0xFFFF;
    $length = strlen($string);
    
    for ($i = 0; $i < $length; $i++) {
        $crc ^= ord($string[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc = $crc << 1;
            }
        }
    }
    
    return $crc & 0xFFFF;
} 