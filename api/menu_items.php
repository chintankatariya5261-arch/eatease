<?php
session_start();
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
$hotel_id = isset($_GET['hotel_id']) ? intval($_GET['hotel_id']) : 0;
if ($hotel_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid restaurant']);
  exit;
}
$rows = [];
$stmt = $mysqli->prepare("SELECT id, name, description, price, image_path FROM menu_items WHERE hotel_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $rows[] = $r;
}
$stmt->close();
echo json_encode(['success' => true, 'data' => $rows]);
