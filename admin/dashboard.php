<?php
require_once __DIR__ . '/../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['email'])) {
  header('Location: ../auth/login.php');
  exit;
}

// Get user role
$user_email = $_SESSION['email'];
$user_stmt = $mysqli->prepare("SELECT role FROM users WHERE email = ?");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Check if user is admin
if (!$user_data || $user_data['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}


$page_title = 'Admin Dashboard | EatEase';
include '../includes/header.php';

// Get statistics
$stats_query = "
  SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
  FROM bookings
";
$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<link rel="stylesheet" href="../assets/css/admin-dashboard.css">

<div class="admin-dashboard">
  <div class="container">
    <!-- Header -->
    <div class="dashboard-header">
      <div>
        <h1>Admin Dashboard</h1>
        <p class="muted">Overview of restaurant statistics</p>
      </div>
    </div>

    <!-- Quick Actions (Stat Card style) -->
    <div class="stats-grid" style="margin-top: 10px; margin-bottom: 24px;">
      <a href="../restaurants/manage.php" style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-utensils"></i>
          </div>
          <div class="stat-content">
            <div class="action-title">Manage<br>Restaurants</div>
            <p>View and edit listings</p>
          </div>
        </div>
      </a>
      <a href="manage_owners.php" style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-users-cog"></i>
          </div>
          <div class="stat-content">
            <div class="action-title">Manage<br>Owners</div>
            <p>Administer owner accounts</p>
          </div>
        </div>
      </a>
      <a href="manage_users.php" style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-users"></i>
          </div>
          <div class="stat-content">
            <div class="action-title">Manage<br>Users</div>
            <p>View and manage customers</p>
          </div>
        </div>
      </a>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <a href="bookings_details.php?status=all" style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $stats['total_bookings']; ?></h3>
            <p>Total Bookings</p>
          </div>
        </div>
      </a>

      <a href="bookings_details.php?status=pending" style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>Pending Bookings</p>
          </div>
        </div>
      </a>

      <a href="bookings_details.php?status=confirmed" style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $stats['confirmed']; ?></h3>
            <p>Confirmed Bookings</p>
          </div>
        </div>
      </a>
    </div>

    <?php
    $reviews = [];
    $reviews_query = "
      SELECT r.id, r.rating, r.review, r.created_at,
             h.name AS hotel_name,
             CONCAT_WS(' ', u.first_name, u.last_name) AS user_name
      FROM ratings r
      JOIN hotels h ON r.hotel_id = h.id
      LEFT JOIN users u ON r.user_id = u.id
      ORDER BY r.created_at DESC
      LIMIT 20
    ";
    if ($res = $mysqli->query($reviews_query)) {
      while ($row = $res->fetch_assoc()) {
        $reviews[] = $row;
      }
    }
    ?>

    <div class="section-card" style="margin-top: 2rem;">
      <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Recent Reviews</h2>
        <span class="muted" style="font-size: 0.9rem;"><?php echo count($reviews); ?> items</span>
      </div>
      <?php if (!empty($reviews)): ?>
        <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
          <?php foreach ($reviews as $rv): ?>
            <div style="border: 1px solid #eee; border-radius: 10px; padding: 14px; display: grid; grid-template-columns: 1fr auto; align-items: start;">
              <div>
                <div style="font-weight: 600;"><?php echo htmlspecialchars($rv['hotel_name']); ?></div>
                <div style="margin: 6px 0; color: #444;"><?php echo htmlspecialchars($rv['review']); ?></div>
                <div style="display: flex; gap: 10px; color: #777; font-size: 0.85rem;">
                  <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(trim((string)($rv['user_name'] ?? '')) !== '' ? $rv['user_name'] : 'Anonymous'); ?></span>
                  <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($rv['created_at']))); ?></span>
                </div>
              </div>
              <div style="text-align: right;">
                <span style="display: inline-block; background: #f7f7ff; color: #667eea; border-radius: 999px; padding: 6px 10px; font-weight: 600;">
                  <i class="fas fa-star"></i> <?php echo number_format((float)$rv['rating'], 1); ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state" style="text-align: center; padding: 24px;">
          <i class="fas fa-inbox" style="font-size: 40px; opacity: 0.3;"></i>
          <p class="muted">No reviews found</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
