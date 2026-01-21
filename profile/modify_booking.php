<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
$page_title = 'Modify Booking | EatEase';

// Check if user is logged in before output starts
if (!isset($_SESSION['email'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get user ID from session
$user_email = $_SESSION['email'];
$user_query = "SELECT id FROM users WHERE email = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data['id'];
$user_stmt->close();

// Check if booking ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid booking ID.';
    header('Location: my-bookings.php');
    exit;
}

$booking_id = intval($_GET['id']);

// Fetch booking details
$booking_query = "SELECT b.*, h.name as restaurant_name, h.location 
                 FROM bookings b 
                 JOIN hotels h ON b.hotel_id = h.id 
                 WHERE b.id = ? AND b.user_id = ? AND b.status IN ('confirmed', 'pending')";
$booking_stmt = $mysqli->prepare($booking_query);
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $_SESSION['error'] = 'Booking not found or cannot be modified.';
    header('Location: my-bookings.php');
    exit;
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();

// Check modification window
$startTs = strtotime($booking['created_at']);
$errorMsg = 'Bookings can only be modified within 30 minutes of creation.';

if (!$startTs || (time() - $startTs) > (30 * 60)) {
    $_SESSION['error'] = $errorMsg;
    header('Location: my-bookings.php');
    exit;
}

// Fetch active restaurants for the dropdown
$restaurants_query = "SELECT id, name FROM hotels WHERE deleted_at IS NULL ORDER BY name ASC";
$restaurants_result = $mysqli->query($restaurants_query);

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modify_booking'])) {
    $guests = intval($_POST['guests']);
    $booking_date = trim($_POST['date']);
    $booking_time = trim($_POST['time']);
    $special_requests = trim($_POST['notes']);

    // Validation
    if (empty($guests) || empty($booking_date) || empty($booking_time)) {
        $errors[] = 'Please fill in all required fields.';
    } elseif ($guests < 1 || $guests > 20) {
        $errors[] = 'Number of guests must be between 1 and 20.';
    } elseif (strtotime($booking_date . ' ' . $booking_time) < time()) {
        $errors[] = 'Booking date and time cannot be in the past.';
    } else {
        // Update the booking
        // Determine new status (preserve confirmed if already confirmed)
        $new_status = ($booking['status'] === 'confirmed') ? 'confirmed' : 'pending';

        $update_query = "UPDATE bookings SET 
                        number_of_guests = ?, 
                        booking_date = ?, 
                        booking_time = ?, 
                        special_requests = ?,
                        status = ?,
                        updated_at = NOW()
                        WHERE id = ? AND user_id = ?";

        $update_stmt = $mysqli->prepare($update_query);
        $update_stmt->bind_param("issssii", $guests, $booking_date, $booking_time, $special_requests, $new_status, $booking_id, $user_id);

        if ($update_stmt->execute()) {
            $msg = 'Booking updated successfully!';
            if ($new_status === 'pending') {
                $msg .= ' It is pending confirmation from the restaurant.';
            }
            $_SESSION['success'] = $msg;
            header('Location: my-bookings.php');
            exit;
        } else {
            $errors[] = 'Failed to update booking. Please try again.';
        }
        $update_stmt->close();
    }
}
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Modify Booking</h1>
        <a href="my-bookings.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to My Bookings</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="booking-form-container">
        <form method="POST" class="booking-form">
            <div class="form-group">
                <label for="restaurant">Restaurant</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($booking['restaurant_name']); ?>" disabled>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date">Date <span class="required">*</span></label>
                    <input type="date"
                        id="date"
                        name="date"
                        class="form-control"
                        value="<?php echo isset($_POST['date']) ? htmlspecialchars($_POST['date']) : $booking['booking_date']; ?>"
                        min="<?php echo date('Y-m-d'); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="time">Time <span class="required">*</span></label>
                    <input type="time"
                        id="time"
                        name="time"
                        class="form-control"
                        value="<?php echo isset($_POST['time']) ? htmlspecialchars($_POST['time']) : substr($booking['booking_time'], 0, 5); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="guests">Number of Guests <span class="required">*</span></label>
                    <select id="guests" name="guests" class="form-control" required>
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (isset($_POST['guests']) ? $_POST['guests'] : $booking['number_of_guests']) == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> person<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Special Requests (Optional)</label>
                <textarea id="notes"
                    name="notes"
                    class="form-control"
                    rows="3"
                    placeholder="Any special requirements or requests?"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : htmlspecialchars($booking['special_requests']); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" name="modify_booking" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Booking
                </button>
                <a href="my-bookings.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
    .booking-form-container {
        max-width: 700px;
        margin: 30px auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .page-header h1 {
        margin: 0;
        color: #333;
        font-size: 28px;
    }

    .btn-back {
        background: #f8f9fa;
        color: #333;
        border: 1px solid #ddd;
        padding: 8px 15px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 14px;
    }

    .btn-back:hover {
        background: #e9ecef;
    }

    .required {
        color: #dc3545;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -10px;
    }

    .form-group {
        flex: 1;
        min-width: 200px;
        margin: 0 10px 20px;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary {
        background-color: #ff4757;
        color: white;
    }

    .btn-primary:hover {
        background-color: #ff6b81;
    }

    .btn-outline {
        background: none;
        border: 1px solid #ddd;
        color: #333;
    }

    .btn-outline:hover {
        background: #f8f9fa;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .alert ul {
        margin: 0;
        padding-left: 20px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set minimum time for today's date
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('date');
        const timeInput = document.getElementById('time');

        // If selected date is today, set minimum time to current time + 1 hour
        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            const now = new Date();
            const currentDate = now.toISOString().split('T')[0];

            if (selectedDate === currentDate) {
                // Set minimum time to current time + 1 hour
                const currentHour = now.getHours();
                const currentMinute = now.getMinutes();
                const minHour = currentHour + 1;

                // Format time to HH:MM
                const minTime = `${minHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
                timeInput.min = minTime;

                // If current time is after the selected time, update it to the minimum time
                if (timeInput.value < minTime) {
                    timeInput.value = minTime;
                }
            } else {
                // Reset min time for future dates
                timeInput.removeAttribute('min');
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>