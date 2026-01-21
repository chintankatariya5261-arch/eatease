<?php
require_once __DIR__ . '/../../payments/util.php';
$secret = getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';
if (!$secret) {
  echo "SKIP: RAZORPAY_WEBHOOK_SECRET not set\n";
  exit(0);
}
$payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_123', 'order_id' => 'order_123', 'status' => 'captured']]]]);
$sig = hash_hmac('sha256', $payload, $secret);
$ok = verify_webhook_signature($payload, $sig);
if (!$ok) {
  echo "FAIL: Webhook signature verification failed\n";
  exit(1);
}
echo "PASS: Webhook signature verification succeeded\n";
