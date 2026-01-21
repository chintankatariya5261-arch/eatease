<?php
// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eatease');

// Create database connection
$mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$mysqli) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4
mysqli_set_charset($mysqli, 'utf8mb4');

// Razorpay configuration (use environment variables for secrets)
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            if ($line === '' || $line[0] === '#') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if ($k !== '' && $v !== '') {
                putenv("$k=$v");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}
if (!defined('RAZORPAY_KEY_ID')) {
    define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: '');
}
if (!defined('RAZORPAY_SECRET')) {
    define('RAZORPAY_SECRET', getenv('RAZORPAY_SECRET') ?: '');
}
