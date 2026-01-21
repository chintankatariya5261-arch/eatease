<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Razorpay.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
  echo json_encode(['success' => false, 'message' => 'Please login to proceed.']);
  exit;
}

if (empty(RAZORPAY_KEY_ID) || empty(RAZORPAY_SECRET)) {
  echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
  exit;
}

$bookingId = intval($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid booking id.']);
  exit;
}

$userEmail = $_SESSION['email'];
$uStmt = $mysqli->prepare("SELECT id, first_name, last_name, phone FROM users WHERE email = ? LIMIT 1");
if (!$uStmt) {
  echo json_encode(['success' => false, 'message' => 'Server error.']);
  exit;
}
$uStmt->bind_param("s", $userEmail);
$uStmt->execute();
$uRes = $uStmt->get_result();
$user = $uRes->fetch_assoc();
$uStmt->close();
if (!$user) {
  echo json_encode(['success' => false, 'message' => 'User not found.']);
  exit;
}

$bStmt = $mysqli->prepare("SELECT id, number_of_guests, booking_date, booking_time, status, created_at FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
if (!$bStmt) {
  echo json_encode(['success' => false, 'message' => 'Server error.']);
  exit;
}
$bStmt->bind_param("ii", $bookingId, $user['id']);
$bStmt->execute();
$bRes = $bStmt->get_result();
$booking = $bRes->fetch_assoc();
$bStmt->close();
if (!$booking) {
  echo json_encode(['success' => false, 'message' => 'Booking not found.']);
  exit;
}

$alreadyConfirmed = ($booking['status'] !== 'pending');
if ($alreadyConfirmed) {
  echo json_encode(['success' => false, 'message' => 'Booking already confirmed. Payment not required.']);
  exit;
}

$chkStmt = $mysqli->prepare("SELECT id FROM payments WHERE booking_id = ? AND status = 'captured' LIMIT 1");
if ($chkStmt) {
  $chkStmt->bind_param("i", $bookingId);
  $chkStmt->execute();
  $chkRes = $chkStmt->get_result();
  if ($chkRes && $chkRes->num_rows > 0) {
    $chkStmt->close();
    echo json_encode(['success' => false, 'message' => 'Payment already completed for this booking.']);
    exit;
  }
  $chkStmt->close();
}

// Check if booking has expired (30 minutes from creation)
$createdTs = strtotime($booking['created_at']);
if ((time() - $createdTs) > (30 * 60)) {
  echo json_encode(['success' => false, 'message' => 'Booking expired. Payment cannot be processed.']);
  exit;
}

// Fixed amount: INR 100 per booking
$amountMinor = 10000;
$currency = 'INR';

// Ensure payments table exists
$mysqli->query("
  CREATE TABLE IF NOT EXISTS payments (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT(10) UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_order_id VARCHAR(100) DEFAULT NULL,
    provider_payment_id VARCHAR(100) DEFAULT NULL,
    amount INT NOT NULL,
    currency VARCHAR(10) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_id (booking_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
$mysqli->query("CREATE INDEX IF NOT EXISTS idx_provider_order_id ON payments(provider_order_id)");
$mysqli->query("CREATE INDEX IF NOT EXISTS idx_status ON payments(status)");

// Create pending payment record
$pStmt = $mysqli->prepare("INSERT INTO payments (booking_id, provider, amount, currency, status) VALUES (?, 'razorpay', ?, ?, 'pending')");
if (!$pStmt) {
  echo json_encode(['success' => false, 'message' => 'Server error.']);
  exit;
}
$pStmt->bind_param("iis", $bookingId, $amountMinor, $currency);
$okInsert = $pStmt->execute();
if (!$okInsert) {
  $pStmt->close();
  echo json_encode(['success' => false, 'message' => 'Unable to create payment record.']);
  exit;
}
$paymentId = $pStmt->insert_id;
$pStmt->close();

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_SECRET);
try {
  $order = $api->order->create([
    'amount' => $amountMinor,
    'currency' => $currency,
    'receipt' => 'PAY' . str_pad($paymentId, 6, '0', STR_PAD_LEFT),
    'notes' => [
      'booking_id' => $bookingId,
      'user_id' => $user['id']
    ]
  ]);
} catch (\Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Unable to initialize payment.']);
  exit;
}
$providerOrderId = is_array($order) ? ($order['id'] ?? null) : ($order->id ?? null);
if (!$providerOrderId) {
  echo json_encode(['success' => false, 'message' => 'Invalid payment order response.']);
  exit;
}

// Update payment with order id
$upStmt = $mysqli->prepare("UPDATE payments SET provider_order_id = ? WHERE id = ?");
if (!$upStmt) {
  echo json_encode(['success' => false, 'message' => 'Server error.']);
  exit;
}
$upStmt->bind_param("si", $providerOrderId, $paymentId);
$okUpdate = $upStmt->execute();
$upStmt->close();
if (!$okUpdate) {
  echo json_encode(['success' => false, 'message' => 'Unable to persist payment order.']);
  exit;
}

echo json_encode([
  'success' => true,
  'order_id' => $providerOrderId,
  'key_id' => RAZORPAY_KEY_ID,
  'amount' => $amountMinor,
  'currency' => $currency,
  'payment_id' => $paymentId,
  'booking_id' => $bookingId,
  'user' => [
    'name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
    'email' => $userEmail,
    'contact' => $user['phone'] ?? ''
  ]
]);
