<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

$email = isset($_GET['email']) ? $_GET['email'] : (isset($_POST['email']) ? $_POST['email'] : '');

// Handle resend OTP request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
  $email = trim($_POST['email']);

  if (empty($email)) {
    $errors[] = 'Email is required.';
  } else {
    $stmt = $mysqli->prepare('SELECT id, email, first_name, last_name FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      $errors[] = 'No account found with this email address.';
    } else {
      $user = $result->fetch_assoc();

      // Generate new OTP
      $otp = random_int(100000, 999999);
      $otp_exp = date('Y-m-d H:i:s', strtotime('+5 minute'));

      $updateStmt = $mysqli->prepare("UPDATE users SET otp = ?, otp_exp = ? WHERE email = ?");
      $updateStmt->bind_param("sss", $otp, $otp_exp, $email);
      $updateStmt->execute();
      $updateStmt->close();

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
                          <span style='font-size: 32px; color: white;'>🔐</span>
                        </div>
                        <h1 style='color: white; margin: 0; font-size: 28px; font-weight: 800;'>New Verification Code</h1>
                      </td>
                    </tr>
                    <tr>
                      <td style='padding: 40px 30px;'>
                        <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px;'>Hi <strong>{$user['first_name']}</strong>,</p>
                        <p style='color: #666; font-size: 15px; line-height: 1.6; margin: 0 0 30px;'>You requested a new verification code. Please use the code below to verify your account:</p>
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
                  </table>
                </td>
              </tr>
            </table>
          </body>
          </html>
        ";

        $mail->send();
        $success = 'A new OTP has been sent to your email address.';
      } catch (Exception $e) {
        $errors[] = "Failed to send OTP. Please try again later.";
      }
    }
    $stmt->close();
  }

  // If AJAX request, return JSON
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
      'success' => !empty($success),
      'message' => !empty($success) ? $success : (isset($errors[0]) ? $errors[0] : 'An error occurred'),
      'errors' => $errors
    ]);
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
  $email = trim($_POST['email']);
  $otp = trim($_POST['otp']);

  if (empty($email)) {
    $errors[] = 'Email is required.';
  } elseif (empty($otp)) {
    $errors[] = 'Please enter the OTP code.';
  } elseif (!ctype_digit($otp) || strlen($otp) !== 6) {
    $errors[] = 'OTP must be exactly 6 digits.';
  } else {
    $stmt = $mysqli->prepare('SELECT id, email, first_name, last_name, role, otp, otp_exp FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      $errors[] = 'No account found with this email address.';
    } else {
      $user = $result->fetch_assoc();

      if (!$user['otp'] || !$user['otp_exp']) {
        $errors[] = 'No OTP found. Please request a new verification code.';
      } elseif (strtotime($user['otp_exp']) < time()) {
        $errors[] = 'OTP has expired. Please request a new verification code.';
      } elseif ((int)$user['otp'] !== (int)$otp) {
        $errors[] = 'Incorrect OTP. Please check the code and try again.';
      } else {
        $clear = $mysqli->prepare("UPDATE users SET otp = NULL, otp_exp = NULL, status = 'verified' WHERE id = ?");
        $clear->bind_param('i', $user['id']);
        if ($clear->execute()) {
          // Automatically log the user in
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
        } else {
          $errors[] = 'Verification failed. Please try again or contact support.';
        }
        $clear->close();
      }
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP | EatEase</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="../assets/css/auth.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .otp-inputs {
      display: flex;
      gap: 12px;
      justify-content: center;
      margin: 24px 0;
    }

    .otp-input {
      width: 56px;
      height: 56px;
      text-align: center;
      font-size: 24px;
      font-weight: 700;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .otp-input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .otp-timer {
      text-align: center;
      color: #666;
      font-size: 14px;
      margin-top: 16px;
    }

    .otp-timer.expired {
      color: #dc2626;
    }

    .resend-link {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      cursor: pointer;
    }

    .resend-link:hover {
      text-decoration: underline;
    }

    .resend-link.disabled {
      color: #999;
      cursor: not-allowed;
      pointer-events: none;
    }
  </style>
</head>

<body>

  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <!-- Header -->
        <div class="auth-header">
          <div class="auth-logo">
            <i class="fas fa-envelope-circle-check"></i>
          </div>
          <h1>Verify Your Email</h1>
          <p>We've sent a 6-digit code to<br /><strong><?php echo htmlspecialchars($email); ?></strong></p>
        </div>

        <!-- Error Messages -->
        <?php if ($errors): ?>
          <div class="auth-alert auth-alert-error">
            <?php foreach ($errors as $e) {
              echo '<div>' . htmlspecialchars($e) . '</div>';
            } ?>
          </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($success): ?>
          <div class="auth-alert auth-alert-success">
            <div><?php echo htmlspecialchars($success); ?></div>
          </div>
          <a href="login.php" class="btn-auth">
            <i class="fas fa-sign-in-alt"></i> Go to Login
          </a>
        <?php else:
          // Get OTP expiration time from database
          $otpExpTime = null;
          $isExpired = false;
          if (!empty($email)) {
            $stmt = $mysqli->prepare('SELECT otp_exp FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
              $user = $result->fetch_assoc();
              if ($user['otp_exp']) {
                $otpExpTime = strtotime($user['otp_exp']);
                $isExpired = $otpExpTime < time();
              }
            }
            $stmt->close();
          }
        ?>
          <!-- OTP Form -->
          <form method="POST" action="" id="otpForm" class="auth-form">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="otp" id="otpValue">
            <input type="hidden" id="otpExpTime" value="<?php echo $otpExpTime ? $otpExpTime : ''; ?>">

            <div class="otp-inputs">
              <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
              <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
              <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
              <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
              <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
              <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
            </div>

            <div class="otp-timer" id="timer">
              <?php if ($isExpired): ?>
                <strong style="color: #dc2626;">OTP has expired</strong> - Please request a new code
              <?php else: ?>
                Code expires in <strong id="countdown">5:00</strong>
              <?php endif; ?>
            </div>

            <div id="resendContainer" style="text-align: center; margin-top: 16px; <?php echo $isExpired ? '' : 'display: none;'; ?>">
              <button type="button" id="resendBtn" class="btn-auth" style="background: #667eea;">
                <i class="fas fa-redo"></i> Resend OTP
              </button>
            </div>

            <button type="submit" name="verify_otp" class="btn-auth" id="verifyBtn" <?php echo $isExpired ? 'disabled' : ''; ?>>
              Verify OTP
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    const otpInputs = document.querySelectorAll('.otp-input');
    const otpForm = document.getElementById('otpForm');
    const otpValue = document.getElementById('otpValue');
    const verifyBtn = document.getElementById('verifyBtn');
    const resendBtn = document.getElementById('resendBtn');
    const resendContainer = document.getElementById('resendContainer');
    const timerDiv = document.getElementById('timer');
    const countdownSpan = document.getElementById('countdown');
    const otpExpTimeInput = document.getElementById('otpExpTime');
    const emailInput = document.querySelector('input[name="email"]');

    // Calculate initial time left from expiration time
    let timeLeft = 0;
    if (otpExpTimeInput && otpExpTimeInput.value) {
      const expTime = parseInt(otpExpTimeInput.value);
      const now = Math.floor(Date.now() / 1000);
      timeLeft = Math.max(0, expTime - now);
    } else {
      timeLeft = 300; // Default 5 minutes
    }

    // OTP Input handling
    otpInputs.forEach((input, index) => {
      input.addEventListener('input', (e) => {
        const value = e.target.value;

        // Only allow numbers
        if (!/^\d*$/.test(value)) {
          e.target.value = '';
          return;
        }

        // Move to next input
        if (value && index < otpInputs.length - 1) {
          otpInputs[index + 1].focus();
        }

        // Update hidden input
        updateOTPValue();
      });

      input.addEventListener('keydown', (e) => {
        // Move to previous input on backspace
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
          otpInputs[index - 1].focus();
        }
      });

      input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').slice(0, 6);

        if (!/^\d+$/.test(pastedData)) return;

        pastedData.split('').forEach((char, i) => {
          if (otpInputs[i]) {
            otpInputs[i].value = char;
          }
        });

        updateOTPValue();
        otpInputs[Math.min(pastedData.length, 5)].focus();
      });
    });

    function updateOTPValue() {
      const otp = Array.from(otpInputs).map(input => input.value).join('');
      otpValue.value = otp;
      if (verifyBtn) {
        verifyBtn.disabled = otp.length !== 6 || timeLeft <= 0;
      }
    }

    // Countdown timer
    function updateTimer() {
      if (timeLeft <= 0) {
        timerDiv.classList.add('expired');
        timerDiv.innerHTML = '<strong style="color: #dc2626;">OTP has expired</strong> - Please request a new code';
        if (verifyBtn) {
          verifyBtn.disabled = true;
        }
        if (resendContainer) {
          resendContainer.style.display = 'block';
        }
      } else {
        const countdownEl = document.getElementById('countdown');
        if (countdownEl) {
          const minutes = Math.floor(timeLeft / 60);
          const seconds = timeLeft % 60;
          countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        timeLeft--;
        setTimeout(updateTimer, 1000);
      }
    }

    // Start timer if not expired
    if (timeLeft > 0) {
      updateTimer();
    } else {
      // Already expired
      timerDiv.classList.add('expired');
      timerDiv.innerHTML = '<strong style="color: #dc2626;">OTP has expired</strong> - Please request a new code';
      if (verifyBtn) {
        verifyBtn.disabled = true;
      }
      if (resendContainer) {
        resendContainer.style.display = 'block';
      }
    }

    // Resend OTP
    if (resendBtn) {
      resendBtn.addEventListener('click', (e) => {
        e.preventDefault();

        if (resendBtn.disabled) return;

        // Disable button and show loading
        resendBtn.disabled = true;
        const originalText = resendBtn.innerHTML;
        resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        // Make AJAX call to resend OTP
        const formData = new FormData();
        formData.append('resend_otp', '1');
        formData.append('email', emailInput.value);

        fetch(window.location.href, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Show success message
              const alertDiv = document.createElement('div');
              alertDiv.className = 'auth-alert auth-alert-success';
              alertDiv.innerHTML = '<div>' + data.message + '</div>';
              otpForm.parentNode.insertBefore(alertDiv, otpForm);

              // Reset inputs
              otpInputs.forEach(input => input.value = '');
              otpInputs[0].focus();

              // Reset timer (5 minutes = 300 seconds)
              timeLeft = 300;
              timerDiv.classList.remove('expired');
              timerDiv.innerHTML = 'Code expires in <strong id="countdown">5:00</strong>';
              const newCountdownSpan = document.getElementById('countdown');

              // Hide resend button
              resendContainer.style.display = 'none';

              // Reset verify button state (disabled until 6 digits entered)
              if (verifyBtn) {
                verifyBtn.disabled = true;
              }

              // Restart timer
              updateTimer();

              // Remove success message after 5 seconds
              setTimeout(() => {
                if (alertDiv.parentNode) {
                  alertDiv.parentNode.removeChild(alertDiv);
                }
              }, 5000);

              // Update expiration time input
              const newExpTime = Math.floor(Date.now() / 1000) + 300;
              if (otpExpTimeInput) {
                otpExpTimeInput.value = newExpTime;
              }
            } else {
              // Show error message
              const alertDiv = document.createElement('div');
              alertDiv.className = 'auth-alert auth-alert-error';
              alertDiv.innerHTML = '<div>' + data.message + '</div>';
              otpForm.parentNode.insertBefore(alertDiv, otpForm);

              // Remove error message after 5 seconds
              setTimeout(() => {
                if (alertDiv.parentNode) {
                  alertDiv.parentNode.removeChild(alertDiv);
                }
              }, 5000);
            }

            // Re-enable button
            resendBtn.disabled = false;
            resendBtn.innerHTML = originalText;
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Failed to resend OTP. Please try again.');
            resendBtn.disabled = false;
            resendBtn.innerHTML = originalText;
          });
      });
    }

    // Focus first input on load
    if (otpInputs.length > 0) {
      otpInputs[0].focus();
    }
    if (verifyBtn && timeLeft > 0) {
      verifyBtn.disabled = true;
    }
  </script>

</body>

</html>
