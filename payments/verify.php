<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/util.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
  echo json_encode(['success' => false, 'message' => 'Please login to proceed.']);
  exit;
}

if (empty(RAZORPAY_KEY_ID) || empty(RAZORPAY_SECRET)) {
  echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
  exit;
}

$razorpayOrderId = $_POST['razorpay_order_id'] ?? '';
$razorpayPaymentId = $_POST['razorpay_payment_id'] ?? '';
$razorpaySignature = $_POST['razorpay_signature'] ?? '';
$paymentId = intval($_POST['payment_id'] ?? 0);
$bookingId = intval($_POST['booking_id'] ?? 0);

if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature || $paymentId <= 0 || $bookingId <= 0) {
  echo json_encode(['success' => false, 'message' => 'Incomplete payment details.']);
  exit;
}

$userEmail = $_SESSION['email'];
$uStmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
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

// Verify payment belongs to user and booking
$pStmt = $mysqli->prepare("SELECT id, booking_id, provider_order_id, status FROM payments WHERE id = ? AND booking_id = ? LIMIT 1");
if (!$pStmt) {
  echo json_encode(['success' => false, 'message' => 'Server error.']);
  exit;
}
$pStmt->bind_param("ii", $paymentId, $bookingId);
$pStmt->execute();
$pRes = $pStmt->get_result();
$payment = $pRes->fetch_assoc();
$pStmt->close();
if (!$payment || $payment['provider_order_id'] !== $razorpayOrderId) {
  echo json_encode(['success' => false, 'message' => 'Invalid payment record.']);
  exit;
}

$bStmt = $mysqli->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
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

if (!verify_signature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
  echo json_encode(['success' => false, 'message' => 'Signature verification failed.']);
  exit;
}

// Update payment status
$upStmt = $mysqli->prepare("UPDATE payments SET status = 'captured', provider_payment_id = ?, updated_at = NOW() WHERE id = ? AND status <> 'captured'");
if (!$upStmt) {
  echo json_encode(['success' => false, 'message' => 'Server error.']);
  exit;
}
$upStmt->bind_param("si", $razorpayPaymentId, $paymentId);
$ok = $upStmt->execute();
$upStmt->close();

if (!$ok) {
  error_log("Payment capture DB update failed for payment_id={$paymentId}");
  echo json_encode(['success' => false, 'message' => 'Failed to update payment status.']);
  exit;
}

// Update booking status to confirmed with timestamp
$bUpd = $mysqli->prepare("UPDATE bookings SET status = 'confirmed', updated_at = NOW() WHERE id = ? AND user_id = ? AND status <> 'confirmed'");
if ($bUpd) {
  $bUpd->bind_param("ii", $bookingId, $user['id']);
  $bUpdOk = $bUpd->execute();
  $bUpd->close();
} else {
  $bUpdOk = false;
}

$mysqli->query("
  CREATE TABLE IF NOT EXISTS payment_events (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT(10) UNSIGNED NOT NULL,
    booking_id INT(10) UNSIGNED NOT NULL,
    event VARCHAR(50) NOT NULL,
    provider_payment_id VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payment_id (payment_id),
    KEY idx_booking_id (booking_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
$ev = $mysqli->prepare("INSERT INTO payment_events (payment_id, booking_id, event, provider_payment_id) VALUES (?, ?, 'captured', ?)");
if ($ev) {
  $ev->bind_param("iis", $paymentId, $bookingId, $razorpayPaymentId);
  $ev->execute();
  $ev->close();
}

if ($bUpdOk) {
  $_SESSION['success'] = 'Booking Confirmed! Your table has been successfully booked.';
  echo json_encode(['success' => true, 'message' => 'Booking Confirmed! Your table has been successfully booked.']);
} else {
  // If booking was already confirmed, it's still a success for the user (idempotency)
  if ($booking['status'] === 'confirmed') {
    $_SESSION['success'] = 'Booking Confirmed! Your table has been successfully booked.';
    echo json_encode(['success' => true, 'message' => 'Booking Confirmed! Your table has been successfully booked.']);
  } else {
    error_log("Booking status update failed for booking_id={$bookingId}");
    $_SESSION['error'] = 'Payment captured, but booking confirmation could not be processed.';
    echo json_encode(['success' => false, 'message' => 'Payment captured, but booking confirmation could not be processed.']);
  }
}
