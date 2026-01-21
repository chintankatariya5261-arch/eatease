<?php
require_once __DIR__ . '/../../payments/util.php';

$order = 'order_test_123';
$pay = 'pay_test_456';
$secret = getenv('RAZORPAY_SECRET') ?: '';
if (!$secret) {
  echo "SKIP: RAZORPAY_SECRET not set\n";
  exit(0);
}
$sig = hash_hmac('sha256', $order . '|' . $pay, $secret);
$ok = verify_signature($order, $pay, $sig);
if (!$ok) {
  echo "FAIL: Signature verification failed\n";
  exit(1);
}
echo "PASS: Signature verification succeeded\n";
