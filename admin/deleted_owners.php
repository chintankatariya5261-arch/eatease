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

$page_title = 'Deleted Owners History | EatEase';

try {
  $check_column = $mysqli->query("SHOW COLUMNS FROM users LIKE 'deleted_at'");
  if ($check_column && $check_column->num_rows == 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN deleted_at datetime DEFAULT NULL AFTER updated_at");
    $mysqli->query("ALTER TABLE users ADD INDEX idx_users_deleted_at (deleted_at)");
  }
} catch (Exception $e) {
}

include '../includes/header.php';

// Handle restore owner
$restore_success = '';
$restore_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_owner'])) {
  $owner_id = intval($_POST['owner_id'] ?? 0);
  if ($owner_id > 0) {
    $stmt = $mysqli->prepare("UPDATE users SET deleted_at = NULL WHERE id = ? AND role = 'restaurant_owner'");
    $stmt->bind_param("i", $owner_id);
    if ($stmt->execute()) {
      $restore_success = 'Owner restored successfully.';
    } else {
      $restore_error = 'Failed to restore owner.';
    }
    $stmt->close();
  }
}

// Get deleted owners
$deleted_owners = [];
$deleted_query = "
  SELECT id, first_name, last_name, email, phone, deleted_at
  FROM users
  WHERE deleted_at IS NOT NULL AND role = 'restaurant_owner'
  ORDER BY deleted_at DESC
";
$deleted_result = $mysqli->query($deleted_query);
if ($deleted_result) {
  while ($row = $deleted_result->fetch_assoc()) {
    $deleted_owners[] = $row;
  }
}
?>

<link rel="stylesheet" href="../assets/css/admin-dashboard.css">

<div class="admin-dashboard">
  <div class="container">
    <!-- Header -->
    <div class="dashboard-header">
      <div>
        <h1>Deleted Owners History</h1>
        <p class="muted">View and restore deleted restaurant owners</p>
      </div>
      <div>
        <a href="dashboard.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;">
          <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <a href="manage_owners.php" style="display: inline-block; padding: 10px 20px; background: #43e97b; color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
          <i class="fas fa-users-cog"></i> Manage Owners
        </a>
      </div>
    </div>

    <?php if ($restore_success): ?>
      <div style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 6px; margin-bottom: 1.5rem; border: 1px solid #c3e6cb;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($restore_success); ?>
      </div>
    <?php endif; ?>

    <?php if ($restore_error): ?>
      <div style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 6px; margin-bottom: 1.5rem; border: 1px solid #f5c6cb;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($restore_error); ?>
      </div>
    <?php endif; ?>

    <!-- Deleted Owners Table -->
    <div class="card" style="margin-top: 2rem;">
      <div class="card-header">
        <h3>Deleted Owners</h3>
        <p class="muted">Total: <?php echo count($deleted_owners); ?> deleted owner(s)</p>
      </div>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
              <th style="padding: 12px; text-align: left;">Name</th>
              <th style="padding: 12px; text-align: left;">Email</th>
              <th style="padding: 12px; text-align: left;">Phone</th>
              <th style="padding: 12px; text-align: left;">Deleted At</th>
              <th style="padding: 12px; text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($deleted_owners)): ?>
              <?php foreach ($deleted_owners as $owner): ?>
                <tr style="border-bottom: 1px solid #eee;">
                  <td style="padding: 12px;">
                    <strong><?php echo htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name']); ?></strong>
                  </td>
                  <td style="padding: 12px;"><?php echo htmlspecialchars($owner['email']); ?></td>
                  <td style="padding: 12px;"><?php echo htmlspecialchars($owner['phone'] ?? 'N/A'); ?></td>
                  <td style="padding: 12px;">
                    <?php
                    if ($owner['deleted_at']) {
                      echo date('M d, Y H:i', strtotime($owner['deleted_at']));
                    } else {
                      echo 'N/A';
                    }
                    ?>
                  </td>
                  <td style="padding: 12px; text-align: center;">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore this owner?');">
                      <input type="hidden" name="restore_owner" value="1">
                      <input type="hidden" name="owner_id" value="<?php echo $owner['id']; ?>">
                      <button type="submit" style="background: #43e97b; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                        <i class="fas fa-undo"></i> Restore
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="padding: 40px; text-align: center; color: #999;">
                  <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                  <p style="margin: 0;">No deleted owners found.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>