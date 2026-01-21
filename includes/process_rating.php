<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to rate restaurants']);
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
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user_id = $user_data['id'];

// Get POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$hotel_id = isset($_POST['hotel_id']) ? (int)$_POST['hotel_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review = isset($_POST['review']) ? trim($_POST['review']) : '';

// Validate inputs
if ($hotel_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid restaurant']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

// Check if hotel exists
$hotel_stmt = $mysqli->prepare("SELECT id, name FROM hotels WHERE id = ?");
$hotel_stmt->bind_param("i", $hotel_id);
$hotel_stmt->execute();
$hotel_result = $hotel_stmt->get_result();
$hotel_data = $hotel_result->fetch_assoc();
$hotel_stmt->close();

if (!$hotel_data) {
    echo json_encode(['success' => false, 'message' => 'Restaurant not found']);
    exit;
}

// Require at least one booking for this restaurant
$booking_id = null;
$booking_stmt = $mysqli->prepare("SELECT id FROM bookings WHERE user_id = ? AND hotel_id = ? AND status IN ('confirmed','completed') ORDER BY created_at DESC LIMIT 1");
$booking_stmt->bind_param("ii", $user_id, $hotel_id);
$booking_stmt->execute();
$booking_res = $booking_stmt->get_result();
if ($b = $booking_res->fetch_assoc()) {
    $booking_id = (int)$b['id'];
}
$booking_stmt->close();
if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Only customers with a confirmed or completed booking can submit a review']);
    exit;
}

// Check if user already rated this restaurant
$check_stmt = $mysqli->prepare("SELECT id, rating FROM ratings WHERE user_id = ? AND hotel_id = ?");
$check_stmt->bind_param("ii", $user_id, $hotel_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$existing_rating = $check_result->fetch_assoc();
$check_stmt->close();

if ($existing_rating) {
  echo json_encode(['success' => false, 'message' => 'You have already submitted a review for this restaurant']);
} else {
  // Insert new rating
  $has_booking_id_col = false;
  $has_hidden_col = false;
  try {
    $col_res = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'booking_id'");
    if ($col_res && $col_res->num_rows > 0) {
      $has_booking_id_col = true;
    }
    $col_hidden = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'hidden_at'");
    if ($col_hidden && $col_hidden->num_rows > 0) {
      $has_hidden_col = true;
    }
  } catch (Throwable $th) {
  }
  if ($has_booking_id_col) {
    $insert_stmt = $mysqli->prepare("INSERT INTO ratings (user_id, hotel_id, booking_id, rating, review, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $insert_stmt->bind_param("iiiis", $user_id, $hotel_id, $booking_id, $rating, $review);
    } else {
        $insert_stmt = $mysqli->prepare("INSERT INTO ratings (user_id, hotel_id, rating, review, created_at) VALUES (?, ?, ?, ?, NOW())");
        $insert_stmt->bind_param("iiis", $user_id, $hotel_id, $rating, $review);
    }

  if ($insert_stmt->execute()) {
    $insert_stmt->close();

    // Recalculate average rating
    $avg_sql = $has_hidden_col
      ? "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE hotel_id = ? AND hidden_at IS NULL"
      : "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE hotel_id = ?";
    $avg_stmt = $mysqli->prepare($avg_sql);
    $avg_stmt->bind_param("i", $hotel_id);
    $avg_stmt->execute();
    $avg_result = $avg_stmt->get_result();
    $avg_data = $avg_result->fetch_assoc();
    $avg_stmt->close();

        $avg_rating = round($avg_data['avg_rating'], 2);
        $total_ratings = $avg_data['total_ratings'];

        // Update hotel's average rating
        $update_hotel_stmt = $mysqli->prepare("UPDATE hotels SET avg_rating = ?, total_ratings = ? WHERE id = ?");
        $update_hotel_stmt->bind_param("dii", $avg_rating, $total_ratings, $hotel_id);
        $update_hotel_stmt->execute();
        $update_hotel_stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Rating submitted successfully',
            'avg_rating' => $avg_rating,
            'total_ratings' => $total_ratings
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit rating']);
    }
}
