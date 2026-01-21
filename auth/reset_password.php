<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$errors = [];
$success = '';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
  $email = trim($_POST['email'] ?? '');
  $otp = trim($_POST['otp'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  if ($email === '' || $otp === '' || $password === '' || $confirmPassword === '') {
    $errors[] = 'All fields are required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
  } elseif (!ctype_digit($otp) || strlen($otp) !== 6) {
    $errors[] = 'OTP must be exactly 6 digits.';
  } elseif ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
  } elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters long.';
  } else {
    $stmt = $mysqli->prepare('SELECT id, otp, otp_exp FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
      $errors[] = 'No account found with this email address.';
    } elseif (!$user['otp'] || !$user['otp_exp']) {
      $errors[] = 'No OTP request found. Please generate a new code.';
    } elseif (strtotime($user['otp_exp']) < time()) {
      $errors[] = 'OTP has expired. Please request a new code.';
    } elseif ((string)$user['otp'] !== $otp) {
      $errors[] = 'Incorrect OTP. Please double-check the code.';
    } else {
      $stmt = $mysqli->prepare('UPDATE users SET password_plain = ?, otp = NULL, otp_exp = NULL WHERE id = ?');
      $stmt->bind_param('si', $password, $user['id']);
      if ($stmt->execute()) {
        $success = 'Password updated successfully. You can now <a href="login.php">sign in</a>.';
      } else {
        $errors[] = 'Failed to update password. Please try again.';
      }
      $stmt->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | EatEase</title>
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
        <h1>Reset Password</h1>
        <p>Enter the OTP sent to your Gmail and set a new password</p>
      </div>

      <?php if ($errors): ?>
        <div class="auth-alert auth-alert-error">
          <?php foreach ($errors as $e) { echo '<div>'.htmlspecialchars($e).'</div>'; } ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="auth-alert auth-alert-success">
          <?php echo $success; ?>
        </div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" action="" class="auth-form">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

        <div class="form-group">
          <label for="emailDisplay">Email</label>
          <input 
            type="email" 
            id="emailDisplay"
            class="form-input" 
            value="<?php echo htmlspecialchars($email); ?>"
            disabled
          >
          <small style="display:block;margin-top:6px;color:#6b7280;">Not your email? <a href="forgot_password.php">Go back</a>.</small>
        </div>

        <div class="form-group">
          <label for="otp">OTP</label>
          <input 
            type="text" 
            id="otp" 
            name="otp" 
            class="form-input" 
            maxlength="6"
            placeholder="6-digit code"
            value="<?php echo isset($_POST['otp']) ? htmlspecialchars($_POST['otp']) : ''; ?>"
            required
          >
        </div>

        <div class="form-group">
          <label for="password">New Password</label>
          <div class="password-group">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-input" 
              placeholder="Create a new password"
              required
            >
            <button type="button" class="password-toggle" onclick="togglePassword('password','toggleIcon1')">
              <i class="fas fa-eye" id="toggleIcon1"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <div class="password-group">
            <input 
              type="password" 
              id="confirm_password" 
              name="confirm_password" 
              class="form-input" 
              placeholder="Re-enter new password"
              required
            >
            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password','toggleIcon2')">
              <i class="fas fa-eye" id="toggleIcon2"></i>
            </button>
          </div>
        </div>

        <button type="submit" name="reset_password" class="btn-auth">
          Update Password
        </button>
      </form>
      <?php endif; ?>

      <div class="auth-footer">
        Back to <a href="login.php">Sign In</a>
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
</script>

</body>
</html>

