<?php
$page_title = 'Book a Table | EatEase';
include 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
  header('Location: auth/login.php');
  exit;
}

// Prevent restaurant owners from booking tables
$user_role = $_SESSION['role'] ?? 'user';
if ($user_role === 'restaurant_owner') {
  header('Location: index.php');
  exit;
}

// Get user details
$user_email = $_SESSION['email'];
$user_stmt = $mysqli->prepare("SELECT id, first_name, last_name, phone FROM users WHERE email = ?");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Fetch active restaurants (exclude soft-deleted) in alphabetical order
$restaurants_query = "SELECT id, name, cuisine_type, location FROM hotels WHERE deleted_at IS NULL ORDER BY name ASC";
$restaurants_result = $mysqli->query($restaurants_query);

// Verify restaurants are fetched
if (!$restaurants_result) {
  die("Error fetching restaurants: " . $mysqli->error);
}

$errors = [];
$success = '';

$booking_success = false;
$booking_id_display = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $restaurant_id = intval($_POST['restaurant']);
  $guests = intval($_POST['guests']);
  $booking_date = $_POST['date'];
  $booking_time = $_POST['time'];
  $special_requests = trim($_POST['notes']);

  // Validation
  if (empty($restaurant_id) || empty($guests) || empty($booking_date) || empty($booking_time)) {
    $errors[] = 'Please fill in all required fields.';
  } elseif ($guests < 1 || $guests > 20) {
    $errors[] = 'Number of guests must be between 1 and 20.';
  } elseif (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
    $errors[] = 'Booking date cannot be in the past.';
  } else {
    // Insert booking into database
    $stmt = $mysqli->prepare("INSERT INTO bookings (user_id, hotel_id, booking_date, booking_time, number_of_guests, special_requests, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");

    if (!$stmt) {
      $errors[] = 'Failed to prepare booking. Please try again. (DB)';
    } else {
      $stmt->bind_param("iissis", $user_data['id'], $restaurant_id, $booking_date, $booking_time, $guests, $special_requests);

      if ($stmt->execute()) {
        $booking_id = $stmt->insert_id;
        $booking_id_display = 'BK' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
        $booking_success = true;
      } else {
        $errors[] = 'Failed to create booking. Please try again.';
      }
      $stmt->close();
    }
  }
}
?>

<link rel="stylesheet" href="assets/css/booktable.css" />

<section class="booking-hero">
  <div class="container">
    <div class="booking-hero-content">
      <p class="hero-kicker"><i class="fas fa-bolt"></i> Instant confirmations in under 60 seconds</p>
      <h1>Book the perfect table for every occasion</h1>
      <p class="hero-subtitle">
        Whether it is an intimate dinner, client lunch or a team celebration, curate the entire dining
        experience in one place. Real-time availability, chef notes, seating preferences and more—no phone calls needed.
      </p>
      <div class="hero-meta">
        <div>
          <span class="meta-label">Top partners</span>
          <strong>500+ curated venues</strong>
        </div>
        <div>
          <span class="meta-label">Guests hosted</span>
          <strong>12,000+ monthly diners</strong>
        </div>
        <div>
          <span class="meta-label">Satisfaction</span>
          <strong>4.9/5 rating</strong>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="booking-container">
  <div class="container booking-grid">
    <div class="card form-card">
      <div class="form-header">
        <div>
          <h2>Reserve your table</h2>
          <p class="muted">Choose a restaurant, pick a slot and we will handle the rest.</p>
        </div>
        <span class="badge-soft">No booking fees</span>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-error show" style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
          <?php foreach ($errors as $error): ?>
            <div style="color: #991b1b;"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($booking_success): ?>
        <div class="alert alert-success show" style="background: #d1fae5; border-left: 4px solid #22c55e; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
          <div style="color: #065f46;">
            <strong>✓ Reservation Confirmed!</strong>
            <p style="margin: 8px 0 0;">Booking ID: <strong><?php echo htmlspecialchars($booking_id_display); ?></strong></p>
            <p style="margin: 4px 0 0; font-size: 14px;">Redirecting to your bookings...</p>
            <?php if (!empty($restaurant_id)): ?>
              <p style="margin: 8px 0 0;">
                <a href="restaurant_details.php?id=<?php echo (int)$restaurant_id; ?>#review" style="color:#0f766e; font-weight:600; text-decoration:none;">
                  <i class="fas fa-star"></i> Write a review for this restaurant
                </a>
              </p>
            <?php endif; ?>
          </div>
        </div>
        <script>
          setTimeout(function() {
            window.location.href = 'profile/my-bookings.php';
          }, 2000);
        </script>
      <?php endif; ?>

      <form method="POST" action="" class="form-card" id="bookingForm">
        <div class="grid-2">
          <div class="form-group">
            <label for="restaurant">Restaurant *</label>
            <select id="restaurant" name="restaurant" required>
              <option value="">Select restaurant</option>
              <?php
              $preselected_id = isset($_GET['restaurant_id']) ? intval($_GET['restaurant_id']) : 0;
              // Reset result pointer to ensure we get all restaurants
              if ($restaurants_result->num_rows > 0) {
                $restaurants_result->data_seek(0);
              }
              while ($restaurant = $restaurants_result->fetch_assoc()):
                $selected = ($restaurant['id'] == $preselected_id) ? 'selected' : '';
              ?>
                <option value="<?php echo $restaurant['id']; ?>" <?php echo $selected; ?>>
                  <?php echo htmlspecialchars($restaurant['name']); ?> • <?php echo htmlspecialchars($restaurant['cuisine_type']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="guests">Guests *</label>
            <input id="guests" name="guests" type="number" min="1" max="20" value="2" required>
          </div>
          <div class="form-group">
            <label for="date">Date *</label>
            <input id="date" name="date" type="date" min="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group">
            <label for="time">Time *</label>
            <input id="time" name="time" type="time" required>
          </div>
          <div class="form-group full">
            <label for="notes">Special requests</label>
            <textarea id="notes" name="notes" placeholder="Allergies, dietary restrictions, special occasions, etc."></textarea>
          </div>
        </div>

        <button type="submit" name="book_table" class="btn-book" id="submitBtn">
          <i class="fas fa-calendar-check" id="btnIcon"></i>
          <span id="btnText">Confirm reservation</span>
          <span id="btnLoader" style="display: none;">
            <i class="fas fa-spinner fa-spin"></i> Processing...
          </span>
        </button>
      </form>

      <script>
        (function() {
          const form = document.getElementById('bookingForm');
          const submitBtn = document.getElementById('submitBtn');
          const btnIcon = document.getElementById('btnIcon');
          const btnText = document.getElementById('btnText');
          const btnLoader = document.getElementById('btnLoader');

          if (form && submitBtn) {
            form.addEventListener('submit', function() {
              // Show loading state
              submitBtn.disabled = true;
              btnIcon.style.display = 'none';
              btnText.style.display = 'none';
              btnLoader.style.display = 'inline';
              submitBtn.style.opacity = '0.7';
              submitBtn.style.cursor = 'not-allowed';
            });
          }
        })();
      </script>
    </div>

    <aside class="card booking-summary">
      <div class="info-chip">
        <i class="fas fa-shield-check"></i>
        Verified partners only
      </div>
      <h3>What to expect</h3>
      <ul class="feature-list">
        <li><i class="fas fa-check"></i> Real-time table availability and holds</li>
        <li><i class="fas fa-check"></i> Chef notes passed directly to the kitchen</li>
        <li><i class="fas fa-check"></i> Dedicated concierge for groups above 8</li>
        <li><i class="fas fa-check"></i> Live SMS + email updates for every guest</li>
      </ul>



      <div class="timeline">
        <h4>How it flows</h4>
        <div class="timeline-item">
          <span>01</span>
          <div>
            <strong>Instant confirmation</strong>
            <p>Restaurant acknowledges within 60 seconds.</p>
          </div>
        </div>
        <div class="timeline-item">
          <span>02</span>
          <div>
            <strong>Concierge follow-up</strong>
            <p>We share seating plan, parking tips, and arrival reminders.</p>
          </div>
        </div>
        <div class="timeline-item">
          <span>03</span>
          <div>
            <strong>Dining insights</strong>
            <p>Track spend, feedback, and loyalty perks after the meal.</p>
          </div>
        </div>
      </div>

    </aside>
  </div>
</section>

<section class="booking-steps">
  <div class="container">
    <div class="steps-header">
      <p class="section-eyebrow"><i class="fas fa-route"></i> Seamless hospitality workflow</p>
      <h2 class="section-title">Designed for planners who obsess over <span>details</span></h2>
      <p class="muted">Collaborate with restaurant teams, manage guest notes, and stay on top of every milestone.</p>
    </div>
    <div class="steps-grid">
      <article class="step-card">
        <i class="fas fa-magnifying-glass-location"></i>
        <h3>Discover</h3>
        <p>Filter by cuisine, ambience, dietary tags and seating layouts. Pin preferred chefs and sommeliers.</p>
        <span class="badge-soft">AI recommendations</span>
      </article>
      <article class="step-card">
        <i class="fas fa-headset"></i>
        <h3>Collaborate</h3>
        <p>Share run-of-show notes with the restaurant team, attach floor plans, and assign approvals.</p>
        <span class="badge-soft">Shared playbooks</span>
      </article>
      <article class="step-card">
        <i class="fas fa-receipt"></i>
        <h3>Track</h3>
        <p>Automatic reminders, spend tracking and guest feedback—rolled into a single dashboard.</p>
        <span class="badge-soft">Post-event insights</span>
      </article>
    </div>
  </div>
</section>

<section class="testimonial-section">
  <div class="container testimonial-grid">
    <article class="testimonial-card">
      <p>“We host 40+ executive dinners a quarter. EatEase keeps every request organized—from wine pairings to AV notes.”</p>
      <div class="testimonial-author">
        <strong>Riya Patel</strong>
        <span>Events Lead, Northshore Capital</span>
      </div>
    </article>
    <article class="testimonial-card">
      <p>“Our guests love the proactive updates. The concierge spotted a dietary conflict before it became a problem.”</p>
      <div class="testimonial-author">
        <strong>Michael Rao</strong>
        <span>COO, Bloom Studios</span>
      </div>
    </article>
    <article class="testimonial-card">
      <p>“Five minutes to confirm a 10-person table on a Friday evening. That alone keeps us coming back.”</p>
      <div class="testimonial-author">
        <strong>Sanjana Iyer</strong>
        <span>Founder, Gather & Co.</span>
      </div>
    </article>
  </div>
</section>

<?php include 'includes/footer.php'; ?>