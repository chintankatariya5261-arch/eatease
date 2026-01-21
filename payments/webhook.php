<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/util.php';

$sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$input = file_get_contents('php://input');
if (!verify_webhook_signature($input, $sig)) {
  json_response(['success' => false, 'message' => 'Invalid signature'], 400);
  exit;
}
$payload = json_decode($input, true);
if (!is_array($payload) || empty($payload['event'])) {
  json_response(['success' => false, 'message' => 'Invalid payload'], 400);
  exit;
}
$event = $payload['event'];
$entity = $payload['payload']['payment']['entity'] ?? [];
$orderId = $entity['order_id'] ?? null;
$paymentId = $entity['id'] ?? null;
$status = $entity['status'] ?? null;
if (!$orderId || !$paymentId) {
  json_response(['success' => true]);
  exit;
}
$stmt = $mysqli->prepare("UPDATE payments SET provider_payment_id = ?, status = ?, updated_at = NOW() WHERE provider_order_id = ?");
if ($stmt) {
  $newStatus = ($status === 'captured') ? 'captured' : (($status === 'failed') ? 'failed' : ($status ?: 'processing'));
  $stmt->bind_param("sss", $paymentId, $newStatus, $orderId);
  $stmt->execute();
  $stmt->close();
}
json_response(['success' => true]);
