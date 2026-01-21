<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
  exit;
}

if (!isset($_SESSION['email'])) {
  echo json_encode(['success' => false, 'message' => 'Please sign in to manage bookings.']);
  exit;
}

$bookingId = intval($_POST['booking_id'] ?? 0);

if ($bookingId <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid booking selected.']);
  exit;
}

$userEmail = $_SESSION['email'];
$userStmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$userStmt->bind_param("s", $userEmail);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if (!$user) {
  echo json_encode(['success' => false, 'message' => 'User account not found.']);
  exit;
}

$bookingStmt = $mysqli->prepare("SELECT status, booking_date, booking_time, created_at FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
$bookingStmt->bind_param("ii", $bookingId, $user['id']);
$bookingStmt->execute();
$bookingResult = $bookingStmt->get_result();
$booking = $bookingResult->fetch_assoc();
$bookingStmt->close();

if (!$booking) {
  echo json_encode(['success' => false, 'message' => 'Booking not found.']);
  exit;
}

if ($booking['status'] === 'cancelled') {
  echo json_encode(['success' => false, 'message' => 'This booking is already cancelled.']);
  exit;
}

if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
  echo json_encode(['success' => false, 'message' => 'Only pending or confirmed bookings can be cancelled.']);
  exit;
}

// Enforce 30-minute cancellation window
$createdTs = strtotime($booking['created_at']);
if ($createdTs && (time() - $createdTs) > (30 * 60)) {
  echo json_encode(['success' => false, 'message' => 'Booking can only be modified or cancelled within 30 minutes of creation.']);
  exit;
}

$updateStmt = $mysqli->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ?");
$updateStmt->bind_param("ii", $bookingId, $user['id']);

if ($updateStmt->execute()) {
  $_SESSION['success'] = 'Booking cancelled successfully.';
  echo json_encode(['success' => true, 'message' => 'Your booking has been cancelled.']);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to cancel booking. Please try again.']);
}

$updateStmt->close();
$mysqli->close();
