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

$page_title = 'Deleted Users History | EatEase';

include '../includes/header.php';

// Handle restore user
$restore_success = '';
$restore_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_user'])) {
  $user_id = intval($_POST['user_id'] ?? 0);
  if ($user_id > 0) {
    $stmt = $mysqli->prepare("UPDATE users SET deleted_at = NULL WHERE id = ? AND role = 'user'");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
      $restore_success = 'User restored successfully.';
    } else {
      $restore_error = 'Failed to restore user.';
    }
    $stmt->close();
  }
}

// Get deleted users
$deleted_users = [];
$deleted_query = "
  SELECT id, first_name, last_name, email, phone, deleted_at
  FROM users
  WHERE deleted_at IS NOT NULL AND role = 'user'
  ORDER BY deleted_at DESC
";
$deleted_result = $mysqli->query($deleted_query);
if ($deleted_result) {
  while ($row = $deleted_result->fetch_assoc()) {
    $deleted_users[] = $row;
  }
}
?>

<link rel="stylesheet" href="../assets/css/admin-dashboard.css">

<div class="admin-dashboard">
  <div class="container">
    <!-- Header -->
    <div class="dashboard-header">
      <div>
        <h1>Deleted Users History</h1>
        <p class="muted">View and restore deleted customers</p>
      </div>
      <div>
        <a href="dashboard.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;">
          <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <a href="manage_users.php" style="display: inline-block; padding: 10px 20px; background: #43e97b; color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
          <i class="fas fa-users"></i> Manage Users
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

    <!-- Deleted Users Table -->
    <div class="card" style="margin-top: 2rem;">
      <div class="card-header">
        <h3>Deleted Users</h3>
        <p class="muted">Total: <?php echo count($deleted_users); ?> deleted user(s)</p>
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
            <?php if (!empty($deleted_users)): ?>
              <?php foreach ($deleted_users as $user): ?>
                <tr style="border-bottom: 1px solid #eee;">
                  <td style="padding: 12px;">
                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                  </td>
                  <td style="padding: 12px;"><?php echo htmlspecialchars($user['email']); ?></td>
                  <td style="padding: 12px;"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                  <td style="padding: 12px;">
                    <?php 
                    if ($user['deleted_at']) {
                      echo date('M d, Y H:i', strtotime($user['deleted_at']));
                    } else {
                      echo 'N/A';
                    }
                    ?>
                  </td>
                  <td style="padding: 12px; text-align: center;">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore this user?');">
                      <input type="hidden" name="restore_user" value="1">
                      <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                      <button type="submit" style="background: #43e97b; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
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
                  <p style="margin: 0;">No deleted users found.</p>
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
