<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to favorite restaurants.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$hotel_id = intval($data['hotel_id'] ?? 0);

if ($hotel_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid restaurant ID.']);
    exit;
}

// Get user ID
$user_email = $_SESSION['email'];
$user_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data['id'];
$user_stmt->close();

// Check if already favorited
$check_stmt = $mysqli->prepare("SELECT id FROM favorites WHERE user_id = ? AND hotel_id = ?");
$check_stmt->bind_param("ii", $user_id, $hotel_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$is_favorited = $check_result->num_rows > 0;
$check_stmt->close();

if ($is_favorited) {
    // Remove favorite
    $del_stmt = $mysqli->prepare("DELETE FROM favorites WHERE user_id = ? AND hotel_id = ?");
    $del_stmt->bind_param("ii", $user_id, $hotel_id);
    $success = $del_stmt->execute();
    $del_stmt->close();
    $action = 'removed';
} else {
    // Add favorite
    $add_stmt = $mysqli->prepare("INSERT INTO favorites (user_id, hotel_id, created_at) VALUES (?, ?, NOW())");
    $add_stmt->bind_param("ii", $user_id, $hotel_id);
    $success = $add_stmt->execute();
    $add_stmt->close();
    $action = 'added';
}

if ($success) {
    echo json_encode(['success' => true, 'action' => $action]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
