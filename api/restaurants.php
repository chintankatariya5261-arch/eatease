<?php
session_start();
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
$rows = [];
$res = $mysqli->query("SELECT id, name, cuisine_type, location, price_range, image_url FROM hotels WHERE deleted_at IS NULL ORDER BY created_at DESC");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
  }
}
echo json_encode(['success' => true, 'data' => $rows]);
