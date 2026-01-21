<?php
require_once __DIR__ . '/../config/config.php';

function json_response($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data);
}

function verify_signature($orderId, $paymentId, $signature) {
  if (!$orderId || !$paymentId || !$signature || empty(RAZORPAY_SECRET)) {
    return false;
  }
  $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_SECRET);
  return hash_equals($generated, $signature);
}

function verify_webhook_signature($payload, $headerSignature) {
  $secret = getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';
  if (!$secret || !$payload || !$headerSignature) {
    return false;
  }
  $generated = hash_hmac('sha256', $payload, $secret);
  return hash_equals($generated, $headerSignature);
}
