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

$page_title = 'Manage Users | EatEase';
$success_msg = '';
$error_msg = '';

// Handle Form Submission (Add/Update User)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_msg = 'Name and Email are required.';
        } else {
            // Update query
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $mysqli->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ?, updated_at = NOW() WHERE id = ? AND role = 'user'");
                $update_stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $hashed_password, $user_id);
            } else {
                $update_stmt = $mysqli->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ? AND role = 'user'");
                $update_stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);
            }

            if ($update_stmt->execute()) {
                $success_msg = 'User updated successfully.';
            } else {
                $error_msg = 'Failed to update user. Email might be in use.';
            }
            $update_stmt->close();
        }
    }
}

// Handle Soft Delete
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    // Soft delete: set deleted_at to current timestamp
    $del_stmt = $mysqli->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND role = 'user'");
    $del_stmt->bind_param("i", $delete_id);
    if ($del_stmt->execute()) {
        $success_msg = 'User deleted successfully.';
    } else {
        $error_msg = 'Failed to delete user.';
    }
    $del_stmt->close();
}

// Fetch all active users
$users_query = "SELECT id, first_name, last_name, email, phone, created_at FROM users WHERE role = 'user' AND deleted_at IS NULL ORDER BY created_at DESC";
$users_result = $mysqli->query($users_query);
$users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/admin-dashboard.css">

<div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="dashboard-header" style="margin-bottom: 2rem;">
        <div>
            <h1>Manage Users</h1>
            <p class="muted">View and manage registered customers</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-primary" style="text-decoration: none; display: inline-block; background: #667eea; color: white; padding: 10px 20px; border-radius: 6px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    <?php
    try {
        $col = $mysqli->query("SHOW COLUMNS FROM users LIKE 'deleted_at'");
        if ($col && $col->num_rows == 0) {
            $mysqli->query("ALTER TABLE users ADD COLUMN deleted_at datetime DEFAULT NULL AFTER updated_at");
            $mysqli->query("ALTER TABLE users ADD INDEX idx_users_deleted_at (deleted_at)");
        }
    } catch (Throwable $th) {
    }
    $user_stats = ['total' => 0, 'active' => 0, 'deleted' => 0];
    if ($res = $mysqli->query("SELECT COUNT(*) AS total, SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS deleted FROM users WHERE role = 'user'")) {
        $user_stats = $res->fetch_assoc() ?: $user_stats;
    }
    ?>
    <div class="stats-grid" style="margin-bottom: 1.5rem;">
        <a href="manage_users.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo intval($user_stats['total']); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
        </a>
        <a href="manage_users.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo intval($user_stats['active']); ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
        </a>
        <a href="deleted_users.php" style="text-decoration: none; color: inherit; display: block;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo intval($user_stats['deleted']); ?></h3>
                    <p>Deleted Users</p>
                </div>
            </div>
        </a>
    </div>

    <?php if ($success_msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div style="background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; text-align: left;">
                    <th style="padding: 15px; border-bottom: 1px solid #eee;">Name</th>
                    <th style="padding: 15px; border-bottom: 1px solid #eee;">Email</th>
                    <th style="padding: 15px; border-bottom: 1px solid #eee;">Phone</th>
                    <th style="padding: 15px; border-bottom: 1px solid #eee;">Joined</th>
                    <th style="padding: 15px; border-bottom: 1px solid #eee;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" style="padding: 30px; text-align: center; color: #666;">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 15px;">
                                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                            </td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                            <td style="padding: 15px;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td style="padding: 15px;">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" style="background: #4facfe; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?delete_id=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');" style="background: #ff6b6b; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13.33px; display: inline-block;">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; position: relative;">
        <span onclick="closeEditModal()" style="position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 20px;">&times;</span>
        <h2 style="margin-top: 0; margin-bottom: 20px;">Update User</h2>
        <form method="POST">
            <input type="hidden" name="update_user" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">First Name</label>
                <input type="text" name="first_name" id="edit_first_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Last Name</label>
                <input type="text" name="last_name" id="edit_last_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                <input type="email" name="email" id="edit_email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Phone</label>
                <input type="text" name="phone" id="edit_phone" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <button type="submit" style="width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 600; cursor: pointer;">Save Changes</button>
        </form>
    </div>
</div>


<script>
    function openEditModal(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_first_name').value = user.first_name;
        document.getElementById('edit_last_name').value = user.last_name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_phone').value = user.phone || '';

        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
</script>

<?php include '../includes/footer.php'; ?>
