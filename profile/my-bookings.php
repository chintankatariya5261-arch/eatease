<?php
$page_title = 'My Bookings | EatEase';
include '../includes/header.php';

// Check if user is logged in
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

// Fetch bookings from database with hotel details
$query = "SELECT 
    b.id,
    b.booking_date,
    b.booking_time,
    b.number_of_guests,
    b.special_requests,
    b.status,
    b.created_at,
    b.updated_at,
    UNIX_TIMESTAMP(b.created_at) as created_ts,
    h.id as hotel_id,
    h.name as restaurant_name,
    h.cuisine_type,
    h.location
  FROM bookings b
  JOIN hotels h ON b.hotel_id = h.id 
  WHERE b.user_id = ?
  ORDER BY b.created_at DESC";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
  // Determine status based on date
  $booking_date = strtotime($row['booking_date']);
  $today = strtotime(date('Y-m-d'));

  // Auto-update status if booking date has passed and status is not cancelled
  if ($booking_date < $today && $row['status'] !== 'cancelled') {
    $row['status'] = 'completed';
  }

  $bookings[] = [
    'display_id' => 'BK' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
    'numeric_id' => $row['id'],
    'hotel_id' => $row['hotel_id'],
    'restaurant' => $row['restaurant_name'],
    'cuisine' => $row['cuisine_type'],
    'date' => $row['booking_date'],
    'time' => $row['booking_time'],
    'guests' => $row['number_of_guests'],
    'status' => $row['status'],
    'location' => $row['location'],
    'special_requests' => $row['special_requests'],
    'created_at' => $row['created_at'],
    'updated_at' => $row['updated_at'],
    'updated_ts' => !empty($row['updated_at']) ? strtotime($row['updated_at']) : 0,
    'created_ts' => isset($row['created_ts']) ? (int)$row['created_ts'] : strtotime($row['created_at'])
  ];
}

$stmt->close();
?>

<link rel="stylesheet" href="../assets/css/my-bookings.css" />

<!-- Hero Section -->
<section class="bookings-hero">
  <div class="container">
    <div class="bookings-hero-content">
      <h1>My Reservations</h1>
      <p>Manage your dining experiences and track your bookings</p>
    </div>
  </div>
</section>

<?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
  <div id="alertBar" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; display: flex; justify-content: center; padding: 12px;">
    <?php if (isset($_SESSION['success'])): ?>
      <div style="max-width: 960px; width: 100%; margin: 0 16px; background: #e6f4ea; color: #1e7e34; border: 1px solid #c6e6ce; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['success']); ?></span>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
      <div style="max-width: 960px; width: 100%; margin: 0 16px; background: #fdecea; color: #b02a37; border: 1px solid #f5c2c7; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
  </div>
  <script>
    setTimeout(() => {
      const bar = document.getElementById('alertBar');
      if (!bar) return;
      bar.style.transition = 'opacity 500ms ease, transform 500ms ease';
      bar.style.opacity = '0';
      bar.style.transform = 'translateY(-10px)';
      setTimeout(() => {
        bar.remove();
      }, 700);
    }, 6000);
  </script>
<?php endif; ?>

<!-- Bookings Section -->
<section class="bookings-section">
  <div class="container">
    <!-- Filter Tabs -->
    <div class="bookings-tabs">
      <button class="tab-btn active" data-filter="all">All Bookings</button>
      <button class="tab-btn" data-filter="upcoming">Upcoming</button>
      <button class="tab-btn" data-filter="completed">Completed</button>
      <button class="tab-btn" data-filter="cancelled">Cancelled</button>
    </div>

    <!-- Bookings Grid -->
    <div class="bookings-grid" id="bookingsGrid">
      <?php if (empty($bookings)): ?>
        <!-- Show empty state if no bookings -->
        <div class="empty-state-initial">
          <i class="fas fa-calendar-times"></i>
          <h3>No Bookings Yet</h3>
          <p>You haven't made any reservations yet. Start exploring our amazing restaurants!</p>
          <a href="../restaurants.php" class="btn-primary-large">
            <i class="fas fa-search"></i> Browse Restaurants
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
          <div class="booking-card" data-status="<?php echo htmlspecialchars($booking['status']); ?>">
            <div class="booking-header">
              <div>
                <h3><?php echo htmlspecialchars($booking['restaurant']); ?></h3>
                <p class="booking-cuisine"><?php echo htmlspecialchars($booking['cuisine']); ?></p>
              </div>
              <span class="status-badge status-<?php echo htmlspecialchars($booking['status']); ?>">
                <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
              </span>
            </div>

            <div class="booking-details">
              <div class="booking-detail-item">
                <i class="fas fa-calendar"></i>
                <div>
                  <strong>Date</strong>
                  <span><?php echo date('D, M j, Y', strtotime($booking['date'])); ?></span>
                </div>
              </div>

              <div class="booking-detail-item">
                <i class="fas fa-clock"></i>
                <div>
                  <strong>Time</strong>
                  <span><?php echo date('g:i A', strtotime($booking['time'])); ?></span>
                </div>
              </div>

              <div class="booking-detail-item">
                <i class="fas fa-users"></i>
                <div>
                  <strong>Guests</strong>
                  <span><?php echo $booking['guests']; ?> people</span>
                </div>
              </div>

              <div class="booking-detail-item">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                  <strong>Location</strong>
                  <span><?php echo htmlspecialchars($booking['location']); ?></span>
                </div>
              </div>
            </div>

            <?php if (!empty($booking['special_requests'])): ?>
              <div class="booking-special-requests">
                <strong><i class="fas fa-info-circle"></i> Special Requests:</strong>
                <p><?php echo htmlspecialchars($booking['special_requests']); ?></p>
              </div>
            <?php endif; ?>

            <div class="booking-footer">
              <span class="booking-id">Booking ID: <?php echo htmlspecialchars($booking['display_id']); ?></span>
              <div class="booking-actions">
                <?php
                $created_ts = (int)$booking['created_ts'];
                $elapsed = time() - $created_ts;
                $locked = $elapsed > (30 * 60);
                ?>
                <?php if ($booking['status'] === 'pending'): ?>
                  <?php if (!$locked): ?>
                    <a href="modify_booking.php?id=<?php echo (int) $booking['numeric_id']; ?>" class="btn-action btn-modify">
                      <i class="fas fa-edit"></i> Modify
                    </a>
                    <button class="btn-action btn-cancel" data-booking-id="<?php echo (int) $booking['numeric_id']; ?>">
                      <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn-action btn-pay" data-booking-id="<?php echo (int) $booking['numeric_id']; ?>">
                      <i class="fas fa-credit-card"></i> Pay ₹100
                    </button>
                    <div class="booking-timer" data-created-ts="<?php echo (int) $created_ts; ?>" aria-label="Time remaining to complete payment">
                      <?php echo gmdate('i:s', max(0, 1800 - $elapsed)); ?>
                    </div>
                  <?php else: ?>
                    <div class="booking-expired" style="color: #ef4444; font-weight: 600; padding: 8px 12px; background: #fee2e2; border-radius: 8px;">
                      <i class="fas fa-exclamation-circle"></i> Booking Expired
                    </div>
                  <?php endif; ?>
                <?php elseif ($booking['status'] === 'confirmed'): ?>
                  <div class="confirmation-details" style="display:flex;align-items:center;gap:6px;color:#059669;font-weight:600;font-size:0.9em;">
                    <i class="fas fa-check-circle"></i>
                    <span>Confirmed</span>
                  </div>
                  <?php if (!$locked): ?>
                    <?php $remaining = 1800 - $elapsed; ?>
                    <a href="modify_booking.php?id=<?php echo (int) $booking['numeric_id']; ?>" class="btn-action btn-modify">
                      <i class="fas fa-edit"></i> Modify
                    </a>
                    <div class="booking-timer" data-created-ts="<?php echo (int) $created_ts; ?>" aria-label="Time remaining to modify booking">
                      <?php echo gmdate('i:s', max(0, $remaining)); ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'completed'): ?>
                  <a href="../restaurant_details.php?id=<?php echo (int)$booking['hotel_id']; ?>#review" class="btn-action btn-review">
                    <i class="fas fa-star"></i> Write Review
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (!empty($bookings)): ?>
      <div class="empty-state" id="emptyState" style="display: none;">
        <i class="fas fa-folder-open"></i>
        <h3>No bookings in this view</h3>
        <p>Try switching tabs or explore more restaurants to plan another visit.</p>
        <a href="../restaurants.php" class="btn-primary-large">
          <i class="fas fa-search"></i> Browse Restaurants
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
  // Razorpay checkout
  const loadRazorpay = (() => {
    let loaded = false;
    return (cb) => {
      if (loaded) {
        cb();
        return;
      }
      const s = document.createElement('script');
      s.src = 'https://checkout.razorpay.com/v1/checkout.js';
      s.onload = () => {
        loaded = true;
        cb();
      };
      document.head.appendChild(s);
    };
  })();
  // Tab filtering
  const tabBtns = document.querySelectorAll('.tab-btn');
  const bookingCards = document.querySelectorAll('.booking-card');
  const emptyState = document.getElementById('emptyState');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      // Remove active class from all tabs
      tabBtns.forEach(tab => tab.classList.remove('active'));
      // Add active class to clicked tab
      btn.classList.add('active');

      const filter = btn.dataset.filter;
      let visibleCount = 0;

      bookingCards.forEach(card => {
        const status = card.dataset.status;

        if (filter === 'all') {
          card.style.display = 'block';
          visibleCount++;
        } else if (filter === 'upcoming' && (status === 'confirmed' || status === 'pending')) {
          card.style.display = 'block';
          visibleCount++;
        } else if (filter === status) {
          card.style.display = 'block';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      // Show/hide empty state
      if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? 'flex' : 'none';
      }
    });
  });

  const formatTime = s => {
    if (s < 0) s = 0;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    const mm = m.toString().padStart(2, '0');
    const ss = sec.toString().padStart(2, '0');
    return `${mm}:${ss}`;
  };

  const SERVER_NOW = <?php echo time(); ?>;
  const CLIENT_OFFSET = Math.floor(Date.now() / 1000) - SERVER_NOW;

  const computeRemaining = (createdTs) => {
    const serverAlignedNow = Math.floor(Date.now() / 1000) - CLIENT_OFFSET;
    const elapsed = serverAlignedNow - createdTs;
    let remaining = 1800 - (elapsed > 0 ? elapsed : 0);
    if (remaining > 1800) remaining = 1800;
    if (remaining < 0) remaining = 0;
    return remaining;
  };

  document.querySelectorAll('.booking-timer').forEach(timer => {
    const createdTs = parseInt(timer.dataset.createdTs, 10);
    if (isNaN(createdTs)) return;

    const tick = () => {
      const remaining = computeRemaining(createdTs);

      if (remaining > 0) {
        timer.textContent = formatTime(remaining);
        if (remaining < 300) {
          timer.classList.add('timer-warning');
        } else {
          timer.classList.remove('timer-warning');
        }
      } else {
        timer.textContent = 'Expired';
        timer.classList.remove('timer-warning');
        timer.classList.add('timer-expired');
        const actions = timer.closest('.booking-actions');
        if (actions) {
          // Hide buttons on expiry
          actions.querySelectorAll('.btn-modify, .btn-pay, .btn-cancel').forEach(btn => btn.style.display = 'none');

          // If in pending state, show expired message if not present
          if (!actions.querySelector('.booking-expired-msg')) {
            const msg = document.createElement('div');
            msg.className = 'booking-expired-msg';
            msg.style.cssText = 'color: #ef4444; font-weight: 600; padding: 8px 12px; background: #fee2e2; border-radius: 8px; width: 100%; text-align: center; margin-top: 8px;';
            msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Booking Expired';
            actions.appendChild(msg);
            timer.style.display = 'none';
          }
        }
        clearInterval(intervalId);
      }
    };

    tick();
    const intervalId = setInterval(tick, 1000);
  });

  document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', async () => {
      const bookingId = btn.dataset.bookingId;
      if (!bookingId) {
        return;
      }

      const confirmed = confirm('Are you sure you want to cancel this booking?');
      if (!confirmed) {
        return;
      }

      btn.disabled = true;

      try {
        const response = await fetch('cancel_booking.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `booking_id=${encodeURIComponent(bookingId)}`
        });

        if (!response.ok) {
          throw new Error('Network response was not ok');
        }

        const data = await response.json();
        alert(data.message || 'Request completed.');

        if (data.success) {
          window.location.reload();
        } else {
          btn.disabled = false;
        }
      } catch (error) {
        alert('Unable to cancel the booking right now. Please try again.');
        btn.disabled = false;
      }
    });
  });

  document.querySelectorAll('.btn-pay').forEach(btn => {
    btn.addEventListener('click', async () => {
      const bookingId = btn.dataset.bookingId;
      if (!bookingId) return;
      btn.disabled = true;
      try {
        const resp = await fetch('../payments/create.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `booking_id=${encodeURIComponent(bookingId)}`
        });
        const data = await resp.json();
        if (!data.success) {
          alert(data.message || 'Unable to start payment.');
          btn.disabled = false;
          return;
        }
        loadRazorpay(() => {
          const rzp = new Razorpay({
            key: data.key_id,
            amount: data.amount,
            currency: data.currency,
            name: 'EatEase',
            description: `Booking Payment #${data.booking_id}`,
            order_id: data.order_id,
            prefill: {
              name: data.user?.name || '',
              email: data.user?.email || '',
              contact: data.user?.contact || ''
            },
            theme: {
              color: '#667eea'
            },
            handler: async function(response) {
              try {
                const verifyResp = await fetch('../payments/verify.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                  },
                  body: `razorpay_order_id=${encodeURIComponent(response.razorpay_order_id)}&razorpay_payment_id=${encodeURIComponent(response.razorpay_payment_id)}&razorpay_signature=${encodeURIComponent(response.razorpay_signature)}&payment_id=${encodeURIComponent(data.payment_id)}&booking_id=${encodeURIComponent(data.booking_id)}`
                });
                const verifyData = await verifyResp.json();
                alert(verifyData.message || 'Payment processed.');
                if (verifyData.success) {
                  window.location.reload();
                } else {
                  btn.disabled = false;
                }
              } catch (e) {
                alert('Unable to verify payment. Please contact support.');
                btn.disabled = false;
              }
            }
          });
          rzp.on('payment.failed', function() {
            alert('Payment failed. Please try again.');
            btn.disabled = false;
          });
          rzp.on('modal.closed', function() {
            btn.disabled = false;
          });
          rzp.open();
        });
      } catch (e) {
        alert('Unable to start payment right now.');
        btn.disabled = false;
      }
    });
  });
</script>

<?php include '../includes/footer.php'; ?>