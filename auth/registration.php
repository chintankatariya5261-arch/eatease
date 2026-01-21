<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
  $email = trim($_POST['userid']);
  $firstName = trim($_POST['first_name']);
  $lastName = trim($_POST['last_name']);
  $city = trim($_POST['city']);
  $password = $_POST['password'];
  $confirmPassword = $_POST['confirm_password'];
  $phone = trim($_POST['phone']);
  $userType = isset($_POST['user_type']) ? $_POST['user_type'] : 'user'; // Default to 'user' if not set
  $role = ($userType === 'restaurant') ? 'restaurant_owner' : 'user';

  // Validation
  if (empty($email) || empty($firstName) || empty($lastName) || empty($city) || empty($password) || empty($confirmPassword) || empty($phone)) {
    $errors[] = 'All fields are required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
  } elseif (!preg_match('/@gmail\.com$/i', $email)) {
    $errors[] = 'Only Gmail addresses are allowed (e.g., example@gmail.com)';
  } elseif (preg_match('/^[0-9]/', $email)) {
    $errors[] = 'Email cannot start with a number';
  } elseif ($email !== strtolower($email)) {
    $errors[] = 'Email must be in lowercase letters.';
  } elseif ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
  } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[\W]/', $password)) {
    $errors[] = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
  } elseif (strlen($phone) !== 10) {
    $errors[] = 'Phone number must be exactly 10 digits.';
  } else {
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
      $errors[] = 'Email is already registered. Please use a different one.';
    }
    $stmt->close();
  }

  if (!$errors) {
    try {
      $idxRes = $mysqli->query("SHOW INDEX FROM users WHERE Key_name='uniq_users_phone'");
      if ($idxRes && $idxRes->num_rows > 0) {
        $mysqli->query("ALTER TABLE users DROP INDEX uniq_users_phone");
      }
    } catch (Throwable $th) {
    }
    $stmt = $mysqli->prepare("INSERT INTO users (email, first_name, last_name, city, phone, password_plain, status, role, created_at) VALUES (?, ?, ?, ?, ?, ?, 'unverified', ?, NOW())");
    $stmt->bind_param('sssssss', $email, $firstName, $lastName, $city, $phone, $password, $role);
    if (!$stmt->execute()) {
      $errors[] = 'Registration failed. Please try again.';
      $stmt->close();
    } else {
      $stmt->close();

      // Generate OTP and send email using PHPMailer
      $otp = random_int(100000, 999999);
      $otp_exp = date('Y-m-d H:i:s', strtotime('+5 minute'));

      $stmt = $mysqli->prepare("UPDATE users SET otp = ?, otp_exp = ? WHERE email = ?");
      $stmt->bind_param("sss", $otp, $otp_exp, $email);
      $stmt->execute();
      $stmt->close();

      // Send email using PHPMailer
      try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        // Enable debug logs when ?smtpdebug=1 is present
        if (isset($_GET['smtpdebug'])) {
          $mail->SMTPDebug = SMTP::DEBUG_SERVER;
          $mail->Debugoutput = function ($str) {
            error_log('PHPMailer: ' . $str);
          };
        }
        $mail->Host = 'mail.aerisgo.in'; // SMTP server
        $mail->Hostname = 'mail.aerisgo.in'; // HELO Hostname
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@aerisgo.in'; // SMTP username
        $mail->Password = 'AerisGo@2025*'; // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients (use authenticated domain as From)
        $mail->setFrom('no-reply@aerisgo.in', 'EatEase');
        $mail->addAddress($email, $firstName . ' ' . $lastName);

        // Content
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
                  <!-- Header with Gradient -->
                  <tr>
                    <td style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;'>
                      <div style='width: 64px; height: 64px; background: rgba(255, 255, 255, 0.2); border-radius: 16px; margin: 0 auto 20px; display: inline-flex; align-items: center; justify-content: center;'>
                        <span style='font-size: 32px; color: white;'>🍽️</span>
                      </div>
                      <h1 style='color: white; margin: 0; font-size: 28px; font-weight: 800;'>Welcome to EatEase!</h1>
                      <p style='color: rgba(255, 255, 255, 0.9); margin: 10px 0 0; font-size: 16px;'>Your dining journey starts here</p>
                    </td>
                  </tr>
                  
                  <!-- Content -->
                  <tr>
                    <td style='padding: 40px 30px;'>
                      <p style='color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px;'>Hi <strong>{$firstName}</strong>,</p>
                      <p style='color: #666; font-size: 15px; line-height: 1.6; margin: 0 0 30px;'>Thank you for joining EatEase! To complete your registration and start booking amazing dining experiences, please verify your email address with the code below:</p>
                      
                      <!-- OTP Box -->
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
                      
                      <!-- Timer Warning -->
                      <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; border-radius: 8px; margin: 20px 0;'>
                        <p style='color: #856404; margin: 0; font-size: 14px;'><strong>⏰ Important:</strong> This code will expire in <strong>5 minutes</strong>.</p>
                      </div>
                      
                      <p style='color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0 0;'>If you didn't create an account with EatEase, you can safely ignore this email.</p>
                    </td>
                  </tr>
                  
                  <!-- Features Section -->
                  <tr>
                    <td style='background: #f8f9fa; padding: 30px; border-top: 1px solid #e5e7eb;'>
                      <p style='color: #333; font-size: 15px; font-weight: 600; margin: 0 0 15px;'>What you can do with EatEase:</p>
                      <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                          <td style='padding: 8px 0;'>
                            <span style='color: #22c55e; font-size: 16px; margin-right: 8px;'>✓</span>
                            <span style='color: #666; font-size: 14px;'>Instant table confirmations</span>
                          </td>
                        </tr>
                        <tr>
                          <td style='padding: 8px 0;'>
                            <span style='color: #22c55e; font-size: 16px; margin-right: 8px;'>✓</span>
                            <span style='color: #666; font-size: 14px;'>Exclusive restaurant perks and offers</span>
                          </td>
                        </tr>
                        <tr>
                          <td style='padding: 8px 0;'>
                            <span style='color: #22c55e; font-size: 16px; margin-right: 8px;'>✓</span>
                            <span style='color: #666; font-size: 14px;'>Easy booking management</span>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  
                  <!-- Footer -->
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
        $mail->AltBody = "Welcome to EatEase!\n\nHi {$firstName},\n\nYour verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nIf you didn't create an account, please ignore this email.\n\nBest regards,\nThe EatEase Team";

        $mail->send();

        // Redirect to OTP verification page
        header("Location: otp.php?email=" . urlencode($email));
        exit();
      } catch (Exception $e) {
        $errors[] = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
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
  <title>Create Account | EatEase</title>
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
          <h1>Create Account</h1>
          <p>Join EatEase and start your dining journey</p>
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
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="POST" action="" class="auth-form">
          <div class="form-group">
            <label>I am a</label>
            <div class="radio-group" style="display: flex; gap: 20px; margin-top: 8px;">
              <label class="radio-label" style="display: flex; align-items: center; cursor: pointer;">
                <input
                  type="radio"
                  name="user_type"
                  value="user"
                  <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] === 'user') ? 'checked' : ''; ?>
                  style="margin-right: 8px;">
                <span>User</span>
              </label>
              <label class="radio-label" style="display: flex; align-items: center; cursor: pointer;">
                <input
                  type="radio"
                  name="user_type"
                  value="restaurant"
                  <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'restaurant') ? 'checked' : ''; ?>
                  style="margin-right: 8px;">
                <span>Restaurant Owner</span>
              </label>
            </div>
          </div>
          <div class="form-group">
            <label for="userid">Email</label>
            <input
              type="email"
              id="userid"
              name="userid"
              class="form-input"
              placeholder="Enter your email address"
              value="<?php echo isset($email) ? htmlspecialchars(strtolower($email)) : ''; ?>"
              pattern="[a-z][a-z0-9._%+-]+@gmail\.[a-z]{2,}$"
              title="Only Gmail addresses are allowed (e.g., example@gmail.com)"
              required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="first_name">First Name</label>
              <input
                type="text"
                id="first_name"
                name="first_name"
                class="form-input"
                placeholder="First name"
                value="<?php echo isset($firstName) ? htmlspecialchars($firstName) : ''; ?>"
                required>
            </div>

            <div class="form-group">
              <label for="last_name">Last Name</label>
              <input
                type="text"
                id="last_name"
                name="last_name"
                class="form-input"
                placeholder="Last name"
                value="<?php echo isset($lastName) ? htmlspecialchars($lastName) : ''; ?>"
                required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="city">City</label>
              <input
                type="text"
                id="city"
                name="city"
                class="form-input"
                placeholder="Your city"
                value="<?php echo isset($city) ? htmlspecialchars($city) : ''; ?>"
                required>
            </div>

            <div class="form-group">
              <label for="phone">Phone Number</label>
              <input
                type="tel"
                id="phone"
                name="phone"
                class="form-input"
                placeholder="10-digit number"
                maxlength="10"
                value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>"
                required>
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="password-group">
              <input
                type="password"
                id="password"
                name="password"
                class="form-input"
                placeholder="Create a password"
                required>
              <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                <i class="fas fa-eye" id="toggleIcon1"></i>
              </button>
            </div>
            <div id="pwdStrength" style="margin-top:8px;">
              <div style="height:8px;background:#e5e7eb;border-radius:6px;overflow:hidden;">
                <div id="pwdStrengthBar" style="height:8px;width:0%;background:#ef4444;border-radius:6px;transition:width .2s ease, background-color .2s ease;"></div>
              </div>
              <div id="pwdStrengthText" style="margin-top:6px;font-size:.85em;color:#6b7280;">Start typing to evaluate password strength</div>
            </div>
            <small style="color: #666; font-size: 0.8em; display: block; margin-top: 6px;">Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.</small>
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="password-group">
              <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                class="form-input"
                placeholder="Confirm your password"
                required>
              <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                <i class="fas fa-eye" id="toggleIcon2"></i>
              </button>
            </div>
          </div>

          <button type="submit" name="register" class="btn-auth">
            Create Account
          </button>
        </form>

        <!-- Footer -->
        <div class="auth-footer">
          Already have an account? <a href="login.php">Sign In</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    function togglePassword(inputId, iconId) {
      const passwordInput = document.getElementById(inputId);
      const toggleIcon = document.getElementById(iconId);

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

    // Email input handling
    const emailInput = document.getElementById('userid');

    // Convert to lowercase when typing
    emailInput.addEventListener('input', function(e) {
      const cursorPosition = this.selectionStart;
      this.value = this.value.toLowerCase();
      this.setSelectionRange(cursorPosition, cursorPosition);
    });

    // Final cleanup on blur
    emailInput.addEventListener('blur', function() {
      this.value = this.value.trim().toLowerCase();
    });

    // Phone number validation
    document.getElementById('phone').addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Name validation (letters only)
    document.getElementById('first_name').addEventListener('input', function(e) {
      this.value = this.value.replace(/[^A-Za-z\s]/g, '');
    });

    document.getElementById('last_name').addEventListener('input', function(e) {
      this.value = this.value.replace(/[^A-Za-z\s]/g, '');
    });

    // Allow spaces and hyphens in city names
    document.getElementById('city').addEventListener('input', function(e) {
      this.value = this.value.replace(/[^A-Za-z\s-]/g, '');
    });
  </script>
  <script>
    const pwdInput = document.getElementById('password');
    const strengthBar = document.getElementById('pwdStrengthBar');
    const strengthText = document.getElementById('pwdStrengthText');

    function getStrengthScore(p) {
      let s = 0;
      if (p.length >= 8) s++;
      if (/[A-Z]/.test(p)) s++;
      if (/[a-z]/.test(p)) s++;
      if (/[0-9]/.test(p)) s++;
      if (/[\W]/.test(p)) s++;
      return s;
    }

    function updateStrength() {
      const val = pwdInput.value || '';
      const score = getStrengthScore(val);
      const percent = Math.min(100, Math.max(0, Math.round((score / 5) * 100)));
      strengthBar.style.width = percent + '%';
      let color = '#ef4444';
      let label = 'Weak';
      if (score === 0) {
        color = '#ef4444';
        label = 'Weak';
      } else if (score <= 2) {
        color = '#f59e0b';
        label = 'Fair';
      } else if (score === 3 || score === 4) {
        color = '#3b82f6';
        label = 'Strong';
      } else if (score === 5) {
        color = '#10b981';
        label = 'Very strong';
      }
      strengthBar.style.backgroundColor = color;
      strengthText.textContent = 'Strength: ' + label;
    }

    pwdInput.addEventListener('input', updateStrength);
  </script>

</body>

</html>