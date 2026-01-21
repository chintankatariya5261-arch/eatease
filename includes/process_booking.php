<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
  echo json_encode(['success' => false, 'message' => 'Please login to make a booking.', 'redirect' => 'auth/login.php']);
  exit;
}

// Prevent restaurant owners from booking tables
$user_role = $_SESSION['role'] ?? 'user';
if ($user_role === 'restaurant_owner') {
  echo json_encode(['success' => false, 'message' => 'Restaurant owners cannot book tables.']);
  exit;
}

// Get user ID
$user_email = $_SESSION['email'];
$user_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data) {
  echo json_encode(['success' => false, 'message' => 'User not found.']);
  exit;
}

// Get POST data
$restaurant_id = intval($_POST['restaurant_id'] ?? 0);
$guests = intval($_POST['guests'] ?? 0);
$booking_date = trim($_POST['date'] ?? '');
$booking_time = trim($_POST['time'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validation
if (empty($restaurant_id) || empty($guests) || empty($booking_date) || empty($booking_time)) {
  echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
  exit;
}

if ($guests < 1 || $guests > 20) {
  echo json_encode(['success' => false, 'message' => 'Number of guests must be between 1 and 20.']);
  exit;
}

if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
  echo json_encode(['success' => false, 'message' => 'Booking date cannot be in the past.']);
  exit;
}

// Verify restaurant exists and is not deleted
$hotel_stmt = $mysqli->prepare("SELECT id FROM hotels WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$hotel_stmt->bind_param("i", $restaurant_id);
$hotel_stmt->execute();
$hotel_result = $hotel_stmt->get_result();
$hotel_data = $hotel_result->fetch_assoc();
$hotel_stmt->close();

if (!$hotel_data) {
  echo json_encode(['success' => false, 'message' => 'Restaurant not found.']);
  exit;
}

// Insert booking
$stmt = $mysqli->prepare("INSERT INTO bookings (user_id, hotel_id, booking_date, booking_time, number_of_guests, special_requests, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("iissis", $user_data['id'], $hotel_data['id'], $booking_date, $booking_time, $guests, $notes);

if ($stmt->execute()) {
  $booking_id = $stmt->insert_id();
  echo json_encode([
    'success' => true, 
    'message' => 'Reservation confirmed!',
    'booking_id' => 'BK' . str_pad($booking_id, 4, '0', STR_PAD_LEFT)
  ]);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to create booking. Please try again.']);
}

$stmt->close();
$mysqli->close();
?>
