<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reset'])) {
  $email = trim($_POST['email'] ?? '');

  if ($email === '') {
    $errors[] = 'Please enter your registered Gmail address.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z][A-Za-z0-9._%+-]*@gmail\.com$/', $email)) {
    $errors[] = 'Please enter a Gmail that starts with a letter (e.g., abc01@gmail.com).';
  } else {
    $stmt = $mysqli->prepare('SELECT id, first_name, last_name FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
      $errors[] = 'No account found with this email address.';
    } else {
      $otp = random_int(100000, 999999);
      $otpExp = date('Y-m-d H:i:s', strtotime('+5 minute'));

      $update = $mysqli->prepare('UPDATE users SET otp = ?, otp_exp = ? WHERE id = ?');
      $update->bind_param('ssi', $otp, $otpExp, $user['id']);

      if ($update->execute()) {
        $update->close();
        try {
          $mail = new PHPMailer(true);
          $mail->isSMTP();
          if (isset($_GET['smtpdebug'])) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function ($str) { error_log('ForgotPwd PHPMailer: ' . $str); };
          }
          $mail->Host = 'mail.aerisgo.in';
          $mail->Hostname = 'mail.aerisgo.in';
          $mail->SMTPAuth = true;
          $mail->Username = 'no-reply@aerisgo.in';
          $mail->Password = 'AerisGo@2025*';
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
          $mail->Port = 587;

          $mail->setFrom('no-reply@aerisgo.in', 'EatEase');
          $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);
          $mail->isHTML(true);
          $mail->Subject = 'Reset Your Password - EatEase';
          $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
              <meta charset='UTF-8'>
              <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            <body style='margin:0;padding:0;background-color:#f8f9fa;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,\"Helvetica Neue\",Arial,sans-serif;'>
              <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f8f9fa;padding:40px 20px;'>
                <tr>
                  <td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background:white;border-radius:24px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.08);'>
                      <tr>
                        <td style='background:linear-gradient(135deg,#f97316 0%,#fb923c 100%);padding:40px 30px;text-align:center;'>
                          <div style='width:64px;height:64px;background:rgba(255,255,255,0.2);border-radius:16px;margin:0 auto 20px;display:inline-flex;align-items:center;justify-content:center;'>
                            <span style='font-size:32px;color:white;'>🔐</span>
                          </div>
                          <h1 style='color:white;margin:0;font-size:26px;font-weight:800;'>Password Reset Request</h1>
                          <p style='color:rgba(255,255,255,0.9);margin:10px 0 0;font-size:15px;'>Use the code below to set a new password</p>
                        </td>
                      </tr>
                      <tr>
                        <td style='padding:40px 30px;'>
                          <p style='color:#333;font-size:16px;line-height:1.6;margin:0 0 20px;'>Hi <strong>{$user['first_name']}</strong>,</p>
                          <p style='color:#666;font-size:15px;line-height:1.6;margin:0 0 30px;'>We received a request to reset your EatEase password. Enter the OTP below on the reset screen to continue:</p>
                          <table width='100%' cellpadding='0' cellspacing='0'>
                            <tr>
                              <td align='center' style='padding:30px 0;'>
                                <div style='background:linear-gradient(135deg,rgba(249,115,22,0.08),rgba(251,146,60,0.08));border:2px dashed #fb923c;border-radius:16px;padding:30px;display:inline-block;'>
                                  <p style='color:#666;font-size:13px;margin:0 0 10px;text-transform:uppercase;letter-spacing:1px;font-weight:600;'>Reset Code</p>
                                  <h1 style='color:#f97316;font-size:48px;font-weight:800;margin:0;letter-spacing:8px;font-family:\"Courier New\",monospace;'>{$otp}</h1>
                                </div>
                              </td>
                            </tr>
                          </table>
                          <div style='background:#fff3cd;border-left:4px solid #f59e0b;padding:16px;border-radius:8px;margin:20px 0;'>
                            <p style='color:#92400e;margin:0;font-size:14px;'><strong>⏰ Heads up:</strong> This code expires in <strong>5 minutes</strong>.</p>
                          </div>
                          <p style='color:#666;font-size:14px;line-height:1.6;margin:20px 0 0;'>If you didn't request a password reset, you can ignore this email.</p>
                        </td>
                      </tr>
                      <tr>
                        <td style='background:#111827;padding:24px;text-align:center;'>
                          <p style='color:#9ca3af;font-size:12px;margin:0;'>© 2025 EatEase. All rights reserved.</p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </body>
            </html>
          ";
          $mail->AltBody = "Hi {$user['first_name']},\n\nYour EatEase password reset OTP is: {$otp}\nIt will expire in 5 minutes.\nIf you didn't request this, you can ignore the email.";

          $mail->send();

          header('Location: reset_password.php?email=' . urlencode($email));
          exit();
        } catch (Exception $e) {
          $errors[] = 'Failed to send OTP email. Please try again later.';
        }
      } else {
        $errors[] = 'Could not generate reset code. Please try again.';
        $update->close();
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
  <title>Forgot Password | EatEase</title>
  <link rel="stylesheet" href="../assets/css/style.css"/>
  <link rel="stylesheet" href="../assets/css/auth.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <div class="auth-logo">
          <i class="fas fa-unlock-alt"></i>
        </div>
        <h1>Forgot Password</h1>
        <p>Enter your Gmail to receive a reset code</p>
      </div>

      <?php if ($errors): ?>
        <div class="auth-alert auth-alert-error">
          <?php foreach ($errors as $e) { echo '<div>'.htmlspecialchars($e).'</div>'; } ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="auth-alert auth-alert-success">
          <?php echo htmlspecialchars($success); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" class="auth-form">
        <div class="form-group">
          <label for="email">Registered Gmail</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-input"
            placeholder="e.g., abc01@gmail.com"
            pattern="^[A-Za-z][A-Za-z0-9._%+-]*@gmail\.com$"
            title="Use your Gmail that starts with a letter (e.g., abc01@gmail.com)"
            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
            required
          >
        </div>

        <button type="submit" name="send_reset" class="btn-auth">
          <i class="fas fa-paper-plane"></i> Send Reset OTP
        </button>
      </form>

      <div class="auth-footer">
        Remember your password? <a href="login.php">Back to Login</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>

