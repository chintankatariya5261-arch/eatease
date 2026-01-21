<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
  $userIdentifier = trim($_POST['userid'] ?? '');
  $password = $_POST['password'] ?? '';

  if (empty($userIdentifier) || empty($password)) {
    $errors[] = 'Please fill in all fields.';
  } elseif (!filter_var($userIdentifier, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
  } else {
    $stmt = $mysqli->prepare('SELECT id, email, first_name, last_name, phone, password_plain, status, role FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $userIdentifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
      $errors[] = 'No account found with this email address.';
    } elseif ($user['password_plain'] !== $password) {
      $errors[] = 'Incorrect password. Please try again.';
    }

    if (!$errors) {
      if (strtolower((string)$user['status']) !== 'verified') {
        // Generate new OTP and send email
        $otp = random_int(100000, 999999);
        $otp_exp = date('Y-m-d H:i:s', strtotime('+5 minute'));

        $stmt = $mysqli->prepare("UPDATE users SET otp = ?, otp_exp = ? WHERE email = ?");
        $stmt->bind_param("sss", $otp, $otp_exp, $user['email']);
        $stmt->execute();
        $stmt->close();

        // Send OTP email
        try {
          $mail = new PHPMailer(true);
          $mail->isSMTP();
          $mail->Host = 'mail.aerisgo.in';
          $mail->Hostname = 'mail.aerisgo.in';
          $mail->SMTPAuth = true;
          $mail->Username = 'no-reply@aerisgo.in';
          $mail->Password = 'AerisGo@2025*';
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
          $mail->Port = 587;

          $mail->setFrom('no-reply@aerisgo.in', 'EatEase');
          $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);

          $mail->isHTML(true);
          $mail->Subject = 'Verify Your Email - EatEase';
          $mail->Body = "
          <!DOCTYPE html>
          <html>
          <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
          </head>
          <body style='margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8f9fa; padding: 40px 20px;'>
              <tr>
                <td align='center'>
                  <table width='600' cellpadding='0' cellspacing='0' style='background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);'>
                    <tr>
                      <td style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;'>
                        <div style='width: 64px; height: 64px; background: rgba(255, 255, 255, 0.2); border-radius: 16px; margin: 0 auto 20px; display: inline-flex; align-items: center; justify-content: center;'>
                          <span style='font-size: 32px; color: white;'>🍽️</span>
                        </div>
                        <h1 style='color: white; margin: 0; font-size: 28px; font-weight: 800;'>Verify Your Email</h1>
                        <p style='color: rgba(255, 255, 255, 0.9); margin: 10px 0 0; font-size: 16px;'>Complete your account setup</p>
                      </td>
                    </tr>
                    <tr>
                      <td style='padding: 40px 30px;'>
                        <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px;'>Hi <strong>{$user['first_name']}</strong>,</p>
                        <p style='color: #666; font-size: 15px; line-height: 1.6; margin: 0 0 30px;'>You attempted to sign in, but your email is not verified yet. Please use the code below to verify your account:</p>
                        <table width='100%' cellpadding='0' cellspacing='0'>
                          <tr>
                            <td align='center' style='padding: 30px 0;'>
                              <div style='background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 2px dashed #667eea; border-radius: 16px; padding: 30px; display: inline-block;'>
                                <p style='color: #666; font-size: 13px; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;'>Your Verification Code</p>
                                <h1 style='color: #667eea; font-size: 48px; font-weight: 800; margin: 0; letter-spacing: 8px; font-family: \"Courier New\", monospace;'>{$otp}</h1>
                              </div>
                            </td>
                          </tr>
                        </table>
                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; border-radius: 8px; margin: 20px 0;'>
                          <p style='color: #856404; margin: 0; font-size: 14px;'><strong>⏰ Important:</strong> This code will expire in <strong>5 minutes</strong>.</p>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style='background: #1a1a1a; padding: 30px; text-align: center;'>
                        <p style='color: #999; font-size: 13px; margin: 0 0 10px;'>Best regards,<br><strong style='color: #fff;'>The EatEase Team</strong></p>
                        <p style='color: #666; font-size: 12px; margin: 10px 0 0;'>© 2025 EatEase. All rights reserved.</p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </body>
          </html>
        ";
          $mail->AltBody = "Hi {$user['first_name']},\n\nYour verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nBest regards,\nThe EatEase Team";

          $mail->send();

          // Redirect to OTP page
          header("Location: otp.php?email=" . urlencode($user['email']));
          exit();
        } catch (Exception $e) {
          $errors[] = "Your email is not verified. We couldn't send a new OTP. Please try again later.";
        }
      } else {
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['role'] = $user['role'] ?? 'user';

        // Redirect based on role
        if ($user['role'] === 'admin') {
          header('Location: ../admin/dashboard.php');
        } elseif ($user['role'] === 'restaurant_owner') {
          header('Location: ../restaurants/dashboard.php');
        } else {
          header('Location: ../index.php');
        }
        exit;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In | EatEase</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="../assets/css/auth.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <!-- Header -->
        <div class="auth-header">
          <div class="auth-logo">
            <i class="fas fa-utensils"></i>
          </div>
          <h1>Welcome Back!</h1>
          <p>Sign in to your account to continue</p>
        </div>

        <!-- Error Messages -->
        <?php if ($errors): ?>
          <div class="auth-alert auth-alert-error">
            <?php foreach ($errors as $e) {
              echo '<div>' . htmlspecialchars($e) . '</div>';
            } ?>
          </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" class="auth-form">
          <div class="form-group">
            <label for="userid">Email</label>
            <input
              type="email"
              id="userid"
              name="userid"
              class="form-input"
              placeholder="Enter your email address"
              value="<?php echo isset($_POST['userid']) ? htmlspecialchars($_POST['userid']) : ''; ?>"
              required>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="password-group">
              <input
                type="password"
                id="password"
                name="password"
                class="form-input"
                placeholder="Enter your password"
                required>
              <button type="button" class="password-toggle" onclick="togglePassword()">
                <i class="fas fa-eye" id="toggleIcon"></i>
              </button>
            </div>
            <div class="text-right" style="margin-top:8px;">
              <a href="forgot_password.php" style="color:#667eea;font-weight:600;text-decoration:none;">Forgot password?</a>
            </div>
          </div>

          <button type="submit" name="login" class="btn-auth">
            Sign In
          </button>
        </form>

        <!-- Footer -->
        <div class="auth-footer">
          Don't have an account? <a href="registration.php">Create Account</a>
        </div>

        <!-- Features -->
        <div class="auth-features">
          <h3>Why EatEase?</h3>
          <ul>
            <li><i class="fas fa-check-circle"></i> Instant table confirmations</li>
            <li><i class="fas fa-check-circle"></i> Exclusive restaurant perks</li>
            <li><i class="fas fa-check-circle"></i> Easy booking management</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById('password');
      const toggleIcon = document.getElementById('toggleIcon');

      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }
  </script>

</body>

</html>