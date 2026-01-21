<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';

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

if (!$user_data || $user_data['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Booking Details | EatEase Admin';
include '../includes/header.php';

// Get filter from URL
$status_filter = $_GET['status'] ?? 'all';
$user_id_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$valid_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];

$where_clause = "WHERE 1=1";
$params = [];
$types = "";

if ($status_filter !== 'all' && in_array($status_filter, $valid_statuses)) {
    $where_clause .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($user_id_filter > 0) {
    $where_clause .= " AND b.user_id = ?";
    $params[] = $user_id_filter;
    $types .= "i";
}

// Fetch bookings with details
$query = "
    SELECT 
        b.id,
        b.booking_date,
        b.booking_time,
        b.number_of_guests,
        b.special_requests,
        b.status,
        b.created_at,
        u.first_name,
        u.last_name,
        u.email as user_email,
        u.phone as user_phone,
        h.name as restaurant_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN hotels h ON b.hotel_id = h.id
    $where_clause
    ORDER BY b.created_at DESC
";

$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();
?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <a href="dashboard.php" style="color: #666; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1><?php echo ucfirst($status_filter); ?> Bookings</h1>
            <p class="muted">Detailed view of booking records</p>
        </div>

        <div class="filter-group">
            <select onchange="window.location.href='?status='+this.value" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #ddd;">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
    </div>

    <div class="card" style="background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #edf2f7;">
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: #4a5568;">Booking ID</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: #4a5568;">Customer</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: #4a5568;">Restaurant</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: #4a5568;">Date & Time</th>
                        <th style="padding: 16px; text-align: center; font-weight: 600; color: #4a5568;">Guests</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: #4a5568;">Notes</th>
                        <th style="padding: 16px; text-align: center; font-weight: 600; color: #4a5568;">Status</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: #4a5568;">Contact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bookings) > 0): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr style="border-bottom: 1px solid #edf2f7; transition: background 0.2s;">
                                <td style="padding: 16px; color: #718096;">#<?php echo $booking['id']; ?></td>
                                <td style="padding: 16px;">
                                    <div style="font-weight: 500; color: #2d3748;"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></div>
                                    <div style="font-size: 0.875rem; color: #718096;"><?php echo htmlspecialchars($booking['user_email']); ?></div>
                                </td>
                                <td style="padding: 16px; font-weight: 500; color: #2d3748;"><?php echo htmlspecialchars($booking['restaurant_name']); ?></td>
                                <td style="padding: 16px;">
                                    <div style="color: #2d3748;"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></div>
                                    <div style="font-size: 0.875rem; color: #718096;"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></div>
                                </td>
                                <td style="padding: 16px; text-align: center; color: #4a5568;"><?php echo $booking['number_of_guests']; ?></td>
                                <td style="padding: 16px; color: #718096; font-size: 0.875rem; max-width: 200px;">
                                    <?php echo !empty($booking['special_requests']) ? htmlspecialchars($booking['special_requests']) : '-'; ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php
                                    $status_colors = [
                                        'pending' => '#f6ad55', // Orange
                                        'confirmed' => '#48bb78', // Green
                                        'completed' => '#4299e1', // Blue
                                        'cancelled' => '#f56565', // Red
                                    ];
                                    $color = $status_colors[$booking['status']] ?? '#cbd5e0';
                                    ?>
                                    <span style="background-color: <?php echo $color; ?>20; color: <?php echo $color; ?>; padding: 4px 12px; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; text-transform: capitalize;">
                                        <?php echo $booking['status']; ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; color: #4a5568;"><?php echo htmlspecialchars($booking['user_phone'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding: 32px; text-align: center; color: #718096;">
                                No bookings found for this category.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>