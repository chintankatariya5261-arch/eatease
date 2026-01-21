<?php
$page_title = 'My Profile | EatEase';
include '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
  header('Location: ../auth/login.php');
  exit;
}

$errors = [];
$success = '';

// Get current user details
$user_email = $_SESSION['email'];
$user_query = "SELECT id, email, first_name, last_name, city, phone FROM users WHERE email = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data) {
  header('Location: ../auth/login.php');
  exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  $first_name = trim($_POST['first_name']);
  $last_name = trim($_POST['last_name']);
  $city = trim($_POST['city']);
  $phone = trim($_POST['phone']);
  $email = trim($_POST['email']);

  // Validation
  if (empty($first_name) || empty($last_name) || empty($city) || empty($phone) || empty($email)) {
    $errors[] = 'All fields are required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
  } elseif (!preg_match('/^[A-Za-z][A-Za-z0-9._%+-]*@gmail\.com$/', $email)) {
    $errors[] = 'Please enter a Gmail address that starts with a letter.';
  } elseif (strlen($phone) !== 10) {
    $errors[] = 'Phone number must be exactly 10 digits.';
  } else {
    // Check if email or phone is already taken by another user
    $check_stmt = $mysqli->prepare('SELECT id FROM users WHERE (email = ? OR phone = ?) AND id != ? LIMIT 1');
    $check_stmt->bind_param('ssi', $email, $phone, $user_data['id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
      $errors[] = 'Email or phone number is already registered by another user.';
    } else {
      // Update user profile
      $update_stmt = $mysqli->prepare("UPDATE users SET first_name = ?, last_name = ?, city = ?, phone = ?, email = ?, updated_at = NOW() WHERE id = ?");
      $update_stmt->bind_param("sssssi", $first_name, $last_name, $city, $phone, $email, $user_data['id']);
      
      if ($update_stmt->execute()) {
        // Update session variables
        $_SESSION['email'] = $email;
        $_SESSION['name'] = $first_name . ' ' . $last_name;
        
        $success = 'Profile updated successfully!';
        
        // Refresh user data
        $user_data['first_name'] = $first_name;
        $user_data['last_name'] = $last_name;
        $user_data['city'] = $city;
        $user_data['phone'] = $phone;
        $user_data['email'] = $email;
      } else {
        $errors[] = 'Failed to update profile. Please try again.';
      }
      $update_stmt->close();
    }
    $check_stmt->close();
  }
}
?>

<link rel="stylesheet" href="../assets/css/profile.css"/>

<!-- Hero Section -->
<section class="profile-hero">
  <div class="container">
    <div class="profile-hero-content">
      <h1>My Profile</h1>
      <p>Manage your personal information and account details</p>
    </div>
  </div>
</section>

<!-- Profile Section -->
<section class="profile-section">
  <div class="container">
    <div class="profile-container">
      <!-- Profile Card -->
      <div class="profile-card">
        <div class="profile-header">
          <div class="profile-avatar-large">
            <i class="fas fa-user"></i>
          </div>
          <div class="profile-info">
            <h2><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h2>
            <p><?php echo htmlspecialchars($user_data['email']); ?></p>
          </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($errors)): ?>
          <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
          </div>
        <?php endif; ?>

        <!-- Profile Form -->
        <form method="POST" class="profile-form">
          <div class="form-group">
            <label for="first_name">
              <i class="fas fa-user"></i> First Name
            </label>
            <input 
              type="text" 
              id="first_name" 
              name="first_name" 
              value="<?php echo htmlspecialchars($user_data['first_name']); ?>" 
              required
              placeholder="Enter your first name"
            >
          </div>

          <div class="form-group">
            <label for="last_name">
              <i class="fas fa-user"></i> Last Name
            </label>
            <input 
              type="text" 
              id="last_name" 
              name="last_name" 
              value="<?php echo htmlspecialchars($user_data['last_name']); ?>" 
              required
              placeholder="Enter your last name"
            >
          </div>

          <div class="form-group">
            <label for="email">
              <i class="fas fa-envelope"></i> Email Address
            </label>
            <input 
              type="email" 
              id="email" 
              name="email" 
              value="<?php echo htmlspecialchars($user_data['email']); ?>" 
              required
              placeholder="Enter your email address"
            >
          </div>

          <div class="form-group">
            <label for="phone">
              <i class="fas fa-phone"></i> Phone Number
            </label>
            <input 
              type="tel" 
              id="phone" 
              name="phone" 
              value="<?php echo htmlspecialchars($user_data['phone']); ?>" 
              required
              maxlength="10"
              pattern="[0-9]{10}"
              placeholder="Enter your 10-digit phone number"
            >
          </div>

          <div class="form-group">
            <label for="city">
              <i class="fas fa-map-marker-alt"></i> City
            </label>
            <input 
              type="text" 
              id="city" 
              name="city" 
              value="<?php echo htmlspecialchars($user_data['city']); ?>" 
              required
              placeholder="Enter your city"
            >
          </div>

          <div class="form-actions">
            <button type="submit" name="update_profile" class="btn-primary">
              <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="../index.php" class="btn-secondary">
              <i class="fas fa-times"></i> Cancel
            </a>
          </div>
        </form>
      </div>

      <!-- Quick Links -->
      <div class="profile-sidebar">
        <div class="sidebar-card">
          <h3>Quick Links</h3>
          <ul class="sidebar-links">
            <li>
              <a href="my-bookings.php">
                <i class="fas fa-calendar-check"></i>
                <span>My Bookings</span>
                <i class="fas fa-chevron-right"></i>
              </a>
            </li>
            <li>
              <a href="../booktable.php">
                <i class="fas fa-utensils"></i>
                <span>Book a Table</span>
                <i class="fas fa-chevron-right"></i>
              </a>
            </li>
            <li>
              <a href="../restaurants.php">
                <i class="fas fa-store"></i>
                <span>Browse Restaurants</span>
                <i class="fas fa-chevron-right"></i>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
  // Phone number validation - only allow numbers
  document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
  });
</script>

<?php include '../includes/footer.php'; ?>

