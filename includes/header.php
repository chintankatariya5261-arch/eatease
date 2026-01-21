<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config/config.php';
$base_path = (strpos($_SERVER['PHP_SELF'], '/auth/') !== false
  || strpos($_SERVER['PHP_SELF'], '/restaurants/') !== false
  || strpos($_SERVER['PHP_SELF'], '/profile/') !== false
  || strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
$sessionRole = $_SESSION['role'] ?? null;
$canManageRestaurants = in_array($sessionRole, ['restaurant_owner', 'admin'], true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo isset($page_title) ? $page_title : 'EatEase - Home'; ?></title>
  <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css" />
  <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/header.css" />
  <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/footer.css" />
  <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/home.css" />
  <?php endif; ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

  <!-- Navigation -->
  <nav class="navbar">
    <div class="nav-container">
      <a class="nav-logo" href="<?php echo $base_path; ?>index.php">
        <i class="fas fa-utensils"></i>
        <span>EatEase</span>
      </a>
      <ul class="nav-menu" id="navMenu">
        <?php if ($sessionRole !== 'restaurant_owner' && $sessionRole !== 'admin'): ?>
          <li><a href="<?php echo $base_path; ?>index.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">Home</a></li>
        <?php endif; ?>
        <?php if ($sessionRole !== 'admin'): ?>
          <li><a href="<?php echo $base_path; ?>restaurants.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['restaurants.php', 'sayaji.php', 'amritsari_hatti.php', 'bella_italia.php', 'spice_garden.php', 'ocean_breeze.php', 'rajkot_food_court.php', 'gujarat_bhavan.php', 'royal_palace.php', 'spice_route.php', 'heritage_kitchen.php'])) ? 'active' : ''; ?>">Restaurants</a></li>
        <?php endif; ?>
        <?php if ($sessionRole !== 'restaurant_owner' && $sessionRole !== 'admin'): ?>
          <li><a href="<?php echo $base_path; ?>booktable.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'booktable.php') ? 'active' : ''; ?>">Book Table</a></li>
        <?php endif; ?>
        <?php if ($canManageRestaurants && $sessionRole !== 'admin'): ?>
          <li><a href="<?php echo $base_path; ?>restaurants/manage.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage.php' && strpos($_SERVER['PHP_SELF'], '/restaurants/') !== false) ? 'active' : ''; ?>">Add Restaurant</a></li>
        <?php endif; ?>
        <?php if ($sessionRole === 'restaurant_owner'): ?>
          <li><a href="<?php echo $base_path; ?>restaurants/dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/restaurants/') !== false) ? 'active' : ''; ?>">Dashboard</a></li>
          <li><a href="<?php echo $base_path; ?>restaurants/bookings.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'bookings.php' && strpos($_SERVER['PHP_SELF'], '/restaurants/') !== false) ? 'active' : ''; ?>">Bookings</a></li>
        <?php elseif (isset($_SESSION['email']) && $sessionRole !== 'admin'): ?>
          <li><a href="<?php echo $base_path; ?>profile/my-bookings.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'my-bookings.php' && strpos($_SERVER['PHP_SELF'], '/profile/') !== false) ? 'active' : ''; ?>">My Bookings</a></li>
        <?php endif; ?>
        <?php
        if ($sessionRole === 'admin'): ?>
          <li><a href="<?php echo $base_path; ?>admin/dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>">Admin</a></li>
        <?php endif; ?>
      </ul>
      <div class="nav-buttons" id="authButtons">
        <?php if (!isset($_SESSION['email'])): ?>
          <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/restaurants/') !== false) ? '../auth/login.php' : 'auth/login.php'; ?>" class="btn-secondary">Sign In</a>
        <?php else: ?>
          <div class="profile-dropdown">
            <button class="profile-btn" id="profileBtn">
              <div class="profile-avatar">
                <i class="fas fa-user"></i>
              </div>
              <span class="profile-name"><?php echo htmlspecialchars(explode(' ', $_SESSION['name'])[0]); ?></span>
              <i class="fas fa-chevron-down"></i>
            </button>
            <div class="profile-menu" id="profileMenu">
              <div class="profile-menu-header">
                <div class="profile-avatar-large">
                  <i class="fas fa-user"></i>
                </div>
                <div>
                  <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                  <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                </div>
              </div>
              <div class="profile-menu-divider"></div>
              <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/restaurants/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../profile/profile.php' : 'profile/profile.php'; ?>" class="profile-menu-item">
                <i class="fas fa-user-circle"></i>
                <div>
                  <strong>Profile</strong>
                  <span>Edit your details</span>
                </div>
              </a>
              <div class="profile-menu-divider"></div>
              <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/auth/') !== false || strpos($_SERVER['PHP_SELF'], '/restaurants/') !== false || strpos($_SERVER['PHP_SELF'], '/profile/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../auth/logout.php' : 'auth/logout.php'; ?>" class="profile-menu-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <div>
                  <strong>Sign Out</strong>
                  <span>Logout from account</span>
                </div>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>

  <script>
    // Mobile Menu Toggle
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');

    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('active');
      navMenu.classList.toggle('active');
    });

    // Close menu when clicking on a link
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        navMenu.classList.remove('active');
      });
    });

    // Navbar scroll effect
    window.addEventListener('scroll', () => {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // Profile Dropdown Toggle
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');

    if (profileBtn && profileMenu) {
      profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileMenu.classList.toggle('active');
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
          profileMenu.classList.remove('active');
        }
      });

      // Prevent dropdown from closing when clicking inside
      profileMenu.addEventListener('click', (e) => {
        e.stopPropagation();
      });
    }
  </script>