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

$page_title = 'Manage Bookings | EatEase';

// Get restaurant owner user ID
$user_email = $_SESSION['email'];
$user_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$owner_data = $user_result->fetch_assoc();
$owner_id = $owner_data['id'];
$user_stmt->close();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
  $booking_id = intval($_POST['booking_id']);
  $new_status = trim($_POST['status']);

  $allowed_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
  if (in_array($new_status, $allowed_statuses, true)) {
    // Verify booking belongs to owner's restaurant
    $verify_stmt = $mysqli->prepare("SELECT b.id FROM bookings b JOIN hotels h ON b.hotel_id = h.id WHERE b.id = ? AND h.owner_id = ?");
    $verify_stmt->bind_param("ii", $booking_id, $owner_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows > 0) {
      $update_stmt = $mysqli->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
      $update_stmt->bind_param("si", $new_status, $booking_id);
      $update_stmt->execute();
      $update_stmt->close();
      $_SESSION['success'] = 'Booking status updated successfully.';
    } else {
      $_SESSION['error'] = 'Unauthorized: This booking does not belong to your restaurant.';
    }
    $verify_stmt->close();

    header('Location: bookings.php');
    exit;
  }
}

// Get all active restaurants owned by this user
$restaurants_stmt = $mysqli->prepare("SELECT id, name FROM hotels WHERE owner_id = ? AND deleted_at IS NULL ORDER BY name ASC");
$restaurants_stmt->bind_param("i", $owner_id);
$restaurants_stmt->execute();
$restaurants_result = $restaurants_stmt->get_result();
$owner_restaurants = [];
$restaurant_ids = [];
while ($row = $restaurants_result->fetch_assoc()) {
  $owner_restaurants[$row['id']] = $row['name'];
  $restaurant_ids[] = $row['id'];
}
$restaurants_stmt->close();

// Filter handling
$allowed_filters = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, $allowed_filters, true)) {
  $filter = 'all';
}

$filter_labels = [
  'all' => 'all bookings',
  'pending' => 'pending bookings',
  'confirmed' => 'confirmed bookings',
  'completed' => 'completed bookings',
  'cancelled' => 'cancelled bookings'
];

// Fetch bookings
$bookings = [];
if (!empty($restaurant_ids) && count($restaurant_ids) > 0) {
  $placeholders = str_repeat('?,', count($restaurant_ids) - 1) . '?';

  if ($filter === 'all') {
    $bookings_query = "SELECT 
      b.id,
      b.booking_date,
      b.booking_time,
      b.number_of_guests,
      b.special_requests,
      b.status,
      b.created_at,
      u.first_name,
      u.last_name,
      u.email,
      u.phone,
      h.id as hotel_id,
      h.name as hotel_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN hotels h ON b.hotel_id = h.id
    WHERE b.hotel_id IN ($placeholders)
    ORDER BY b.booking_date DESC, b.booking_time DESC";

    $types = str_repeat('i', count($restaurant_ids));
    $bookings_stmt = $mysqli->prepare($bookings_query);
    if ($bookings_stmt) {
      $bookings_stmt->bind_param($types, ...$restaurant_ids);
      $bookings_stmt->execute();
      $bookings_result = $bookings_stmt->get_result();
      while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
      }
      $bookings_stmt->close();
    }
  } else {
    $bookings_query = "SELECT 
      b.id,
      b.booking_date,
      b.booking_time,
      b.number_of_guests,
      b.special_requests,
      b.status,
      b.created_at,
      u.first_name,
      u.last_name,
      u.email,
      u.phone,
      h.id as hotel_id,
      h.name as hotel_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN hotels h ON b.hotel_id = h.id
    WHERE b.hotel_id IN ($placeholders) AND b.status = ?
    ORDER BY b.booking_date DESC, b.booking_time DESC";

    $types = str_repeat('i', count($restaurant_ids)) . 's';
    $bookings_stmt = $mysqli->prepare($bookings_query);
    if ($bookings_stmt) {
      $bookings_stmt->bind_param($types, ...array_merge($restaurant_ids, [$filter]));
      $bookings_stmt->execute();
      $bookings_result = $bookings_stmt->get_result();
      while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
      }
      $bookings_stmt->close();
    }
  }
}

$bookings_count = count($bookings);

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/restaurant-dashboard.css">

<section class="restaurant-dashboard">
  <div class="container">
    <div class="dashboard-header">
      <div>
        <h1>Manage Bookings</h1>
        <p class="muted">View and manage all bookings for your restaurants</p>
      </div>
      <a href="dashboard.php" class="btn-outline">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
      </a>
    </div>

    <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
      <div class="flash-messages">
        <?php if (isset($_SESSION['success'])): ?>
          <div class="flash-message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success']); ?></span>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
          <div class="flash-message error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="bookings-tabs">
      <a href="bookings.php?filter=all" class="tab-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Bookings</a>
      <a href="bookings.php?filter=pending" class="tab-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
      <a href="bookings.php?filter=confirmed" class="tab-btn <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
      <a href="bookings.php?filter=completed" class="tab-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
      <a href="bookings.php?filter=cancelled" class="tab-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
    </div>

    <div class="filter-summary">
      <p>Showing <?php echo $bookings_count; ?> <?php echo $filter_labels[$filter]; ?></p>
    </div>

    <!-- Bookings Table -->
    <div class="table-container">
      <table class="bookings-table">
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Customer</th>
            <th>Restaurant</th>
            <th>Date & Time</th>
            <th>Guests</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($bookings)): ?>
            <?php foreach ($bookings as $booking): ?>
              <tr class="booking-row" data-status="<?php echo htmlspecialchars($booking['status']); ?>">
                <td>
                  <strong>BK<?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                </td>
                <td>
                  <div class="customer-info">
                    <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                    <span class="customer-email"><?php echo htmlspecialchars($booking['email']); ?></span>
                    <span class="customer-phone"><?php echo htmlspecialchars($booking['phone']); ?></span>
                  </div>
                </td>
                <td>
                  <strong><?php echo htmlspecialchars($booking['hotel_name']); ?></strong>
                </td>
                <td>
                  <div class="datetime-info">
                    <span class="booking-date"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                    <span class="booking-time"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></span>
                  </div>
                </td>
                <td>
                  <span class="guests-badge"><?php echo $booking['number_of_guests']; ?> <?php echo $booking['number_of_guests'] == 1 ? 'Guest' : 'Guests'; ?></span>
                </td>
                <td>
                  <span class="status-badge status-<?php echo $booking['status']; ?>">
                    <?php echo ucfirst($booking['status']); ?>
                  </span>
                </td>
                <td>
                  <form method="POST" class="status-form">
                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                    <input type="hidden" name="update_status" value="1">
                    <select name="status" class="status-select">
                      <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                      <option value="confirmed" <?php echo $booking['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                      <option value="completed" <?php echo $booking['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                      <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn-update">
                      <i class="fas fa-check"></i> Update
                    </button>
                  </form>
                </td>
              </tr>
              <?php if (!empty($booking['special_requests'])): ?>
                <tr class="special-requests-row">
                  <td colspan="7">
                    <div class="special-requests">
                      <strong><i class="fas fa-comment-dots"></i> Special Requests:</strong>
                      <p><?php echo htmlspecialchars($booking['special_requests']); ?></p>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="no-bookings">
                <i class="fas fa-calendar-times"></i>
                <?php if (empty($restaurant_ids)): ?>
                  <p>You don't have any restaurants yet. <a href="manage.php">Add a restaurant</a> to start receiving bookings.</p>
                <?php else: ?>
                  <p><?php echo $filter === 'all' ? 'No bookings found for your restaurants yet.' : 'No bookings match this filter.'; ?></p>
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php include '../includes/footer.php'; ?>
