<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['email'])) {
  header('Location: ../auth/login.php');
  exit;
}

$sessionRole = $_SESSION['role'] ?? 'user';
if ($sessionRole !== 'restaurant_owner') {
  header('Location: ../index.php');
  exit;
}

$page_title = 'Restaurant Dashboard | EatEase';

// Get restaurant owner user ID
$user_email = $_SESSION['email'];
$user_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$owner_data = $user_result->fetch_assoc();
$owner_id = $owner_data['id'];
$user_stmt->close();

// Get all restaurants owned by this user
$restaurants_stmt = $mysqli->prepare("SELECT id, name, cuisine_type, location FROM hotels WHERE owner_id = ? ORDER BY created_at DESC");
$restaurants_stmt->bind_param("i", $owner_id);
$restaurants_stmt->execute();
$restaurants_result = $restaurants_stmt->get_result();
$owner_restaurants = [];
while ($row = $restaurants_result->fetch_assoc()) {
  $owner_restaurants[] = $row;
}
$restaurants_stmt->close();

$restaurant_ids = !empty($owner_restaurants) ? array_column($owner_restaurants, 'id') : [];
$total_restaurants = count($owner_restaurants);

// Get booking statistics
$total_bookings = 0;
$pending_bookings = 0;
$confirmed_bookings = 0;
$completed_bookings = 0;
$cancelled_bookings = 0;

if (!empty($restaurant_ids) && count($restaurant_ids) > 0) {
  $placeholders = str_repeat('?,', count($restaurant_ids) - 1) . '?';
  $stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings WHERE hotel_id IN ($placeholders)";

  $stats_stmt = $mysqli->prepare($stats_query);
  if ($stats_stmt) {
    $types = str_repeat('i', count($restaurant_ids));
    $stats_stmt->bind_param($types, ...$restaurant_ids);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    $stats_stmt->close();

    if ($stats) {
      $total_bookings = intval($stats['total'] ?? 0);
      $pending_bookings = intval($stats['pending'] ?? 0);
      $confirmed_bookings = intval($stats['confirmed'] ?? 0);
      $completed_bookings = intval($stats['completed'] ?? 0);
      $cancelled_bookings = intval($stats['cancelled'] ?? 0);
    }
  }

  $ratings_map = [];
  $has_hidden_col = false;
  try {
    $col_res = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'hidden_at'");
    if ($col_res && $col_res->num_rows > 0) {
      $has_hidden_col = true;
    }
  } catch (Throwable $th) {
  }
  $ratings_query = $has_hidden_col
    ? "SELECT hotel_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_ratings FROM ratings WHERE hidden_at IS NULL AND hotel_id IN ($placeholders) GROUP BY hotel_id"
    : "SELECT hotel_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_ratings FROM ratings WHERE hotel_id IN ($placeholders) GROUP BY hotel_id";
  $ratings_stmt = $mysqli->prepare($ratings_query);
  if ($ratings_stmt) {
    $types = str_repeat('i', count($restaurant_ids));
    $ratings_stmt->bind_param($types, ...$restaurant_ids);
    $ratings_stmt->execute();
    $ratings_result = $ratings_stmt->get_result();
    while ($row = $ratings_result->fetch_assoc()) {
      $ratings_map[(int)$row['hotel_id']] = [
        'avg_rating' => (float)$row['avg_rating'],
        'total_ratings' => (int)$row['total_ratings'],
      ];
    }
    $ratings_stmt->close();
  }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/restaurant-dashboard.css">

<section class="restaurant-dashboard">
  <div class="container">
    <div class="dashboard-header">
      <div>
        <h1>Restaurant Dashboard</h1>
        <p class="muted">Manage your restaurants and bookings</p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <a href="bookings.php?filter=all" class="stat-link">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $total_bookings; ?></h3>
            <p>Total Bookings</p>
          </div>
        </div>
      </a>

      <a href="bookings.php?filter=pending" class="stat-link">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $pending_bookings; ?></h3>
            <p>Pending</p>
          </div>
        </div>
      </a>

      <a href="bookings.php?filter=confirmed" class="stat-link">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $confirmed_bookings; ?></h3>
            <p>Confirmed</p>
          </div>
        </div>
      </a>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="bookings.php" class="action-card">
        <i class="fas fa-list-alt"></i>
        <div>
          <h3>Manage Bookings</h3>
          <p>View and manage all bookings for your restaurants</p>
        </div>
        <i class="fas fa-arrow-right"></i>
      </a>
      <a href="manage.php" class="action-card">
        <i class="fas fa-store"></i>
        <div>
          <h3>Manage Menu</h3>
          <p>Add or edit menu items</p>
        </div>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <!-- Recent Restaurants -->
    <?php if (!empty($owner_restaurants)): ?>
      <div class="section-card">
        <div class="section-header">
          <h2>My Restaurants</h2>
          <a href="manage.php" class="text-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="restaurants-grid">
          <?php foreach (array_slice($owner_restaurants, 0, 6) as $restaurant): ?>
            <div class="restaurant-item" style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
              <div>
                <a href="../restaurant_details.php?id=<?php echo $restaurant['id']; ?>" style="text-decoration: none; color: inherit;">
                  <h3><?php echo htmlspecialchars($restaurant['name']); ?></h3>
                </a>
                <p class="cuisine"><?php echo htmlspecialchars($restaurant['cuisine_type']); ?></p>
                <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($restaurant['location']); ?></p>
                <?php
                $hid = (int)$restaurant['id'];
                $avg_r = isset($ratings_map[$hid]['avg_rating']) ? number_format($ratings_map[$hid]['avg_rating'], 1) : (isset($restaurant['avg_rating']) ? number_format((float)$restaurant['avg_rating'], 1) : '4.5');
                $tot_r = isset($ratings_map[$hid]['total_ratings']) ? (int)$ratings_map[$hid]['total_ratings'] : (isset($restaurant['total_ratings']) ? (int)$restaurant['total_ratings'] : 0);
                ?>
                <div style="margin-top: 6px; display: flex; gap: 12px; align-items: center; color: #555;">
                  <span style="display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-star" style="color: #f1c40f;"></i> <?php echo $avg_r; ?>
                  </span>
                  <span><?php echo $tot_r; ?> reviews</span>
                </div>
                <?php
                $recent_reviews = [];
                $rr_sql = $has_hidden_col
                  ? "SELECT rating, review, created_at FROM ratings WHERE hotel_id = ? AND hidden_at IS NULL AND review IS NOT NULL AND review <> '' ORDER BY created_at DESC LIMIT 2"
                  : "SELECT rating, review, created_at FROM ratings WHERE hotel_id = ? AND review IS NOT NULL AND review <> '' ORDER BY created_at DESC LIMIT 2";
                $rr_stmt = $mysqli->prepare($rr_sql);
                if ($rr_stmt) {
                  $rr_stmt->bind_param("i", $hid);
                  $rr_stmt->execute();
                  $rr_res = $rr_stmt->get_result();
                  while ($rr = $rr_res->fetch_assoc()) {
                    $recent_reviews[] = $rr;
                  }
                  $rr_stmt->close();
                }
                if (!empty($recent_reviews)):
                ?>
                  <div style="margin-top: 8px; display: grid; gap: 6px;">
                    <?php foreach ($recent_reviews as $rv): ?>
                      <div style="font-size: 0.9rem; color: #666;">
                        <span style="color: #667eea; font-weight: 600;"><i class="fas fa-star"></i> <?php echo number_format((float)$rv['rating'], 1); ?></span>
                        <?php echo htmlspecialchars(mb_strimwidth($rv['review'], 0, 120, '…')); ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div>
                <a href="manage.php?edit_restaurant=<?php echo $restaurant['id']; ?>" class="btn-secondary" style="white-space: nowrap;">
                  <i class="fas fa-edit"></i> Edit
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-store"></i>
        <h3>No Restaurants Yet</h3>
        <p>Get started by adding your first restaurant</p>
        <a href="manage.php" class="btn-primary">
          <i class="fas fa-plus"></i> Add Restaurant
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include '../includes/footer.php'; ?>