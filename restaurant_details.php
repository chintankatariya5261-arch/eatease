<?php
session_start();
require_once __DIR__ . '/config/config.php';

$hotel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hotel_id <= 0) {
  header("Location: restaurants.php");
  exit;
}

// Fetch hotel details
$stmt = $mysqli->prepare("SELECT * FROM hotels WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$result = $stmt->get_result();
$hotel = $result->fetch_assoc();
$stmt->close();

if (!$hotel) {
  // Hotel not found or deleted
  header("Location: restaurants.php");
  exit;
}

$page_title = htmlspecialchars($hotel['name']) . ' - EatEase';

// Get user's existing rating if logged in
$user_rating = null;
$has_hidden_col = false;
try {
  $col_res = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'hidden_at'");
  if ($col_res && $col_res->num_rows > 0) {
    $has_hidden_col = true;
  }
} catch (Throwable $th) {
}
if (isset($_SESSION['email'])) {
  $user_email = $_SESSION['email'];
  $user_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
  $user_stmt->bind_param("s", $user_email);
  $user_stmt->execute();
  $user_result = $user_stmt->get_result();
  $user_data = $user_result->fetch_assoc();
  $user_stmt->close();

  if ($user_data) {
    $user_id = $user_data['id'];
    $check_stmt = $mysqli->prepare("SELECT rating, review FROM ratings WHERE user_id = ? AND hotel_id = ?");
    $check_stmt->bind_param("ii", $user_id, $hotel_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $user_rating = $check_result->fetch_assoc();
    $check_stmt->close();
  }
}

// Compute rating stats
$avg_rating = 4.5;
$total_ratings = 0;
$stats_sql = $has_hidden_col
  ? "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM ratings WHERE hotel_id = ? AND hidden_at IS NULL"
  : "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM ratings WHERE hotel_id = ?";
$stats_stmt = $mysqli->prepare($stats_sql);
$stats_stmt->bind_param("i", $hotel_id);
$stats_stmt->execute();
$stats_res = $stats_stmt->get_result();
if ($row = $stats_res->fetch_assoc()) {
  $avg_rating = $row['avg_rating'] !== null ? round(floatval($row['avg_rating']), 1) : 4.5;
  $total_ratings = intval($row['total']);
}
$stats_stmt->close();

$reviews = [];
$reviews_sql = $has_hidden_col
  ? "SELECT rating, review, created_at FROM ratings WHERE hotel_id = ? AND hidden_at IS NULL ORDER BY created_at DESC"
  : "SELECT rating, review, created_at FROM ratings WHERE hotel_id = ? ORDER BY created_at DESC";
$reviews_stmt = $mysqli->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $hotel_id);
$reviews_stmt->execute();
$reviews_res = $reviews_stmt->get_result();
while ($row = $reviews_res->fetch_assoc()) {
  $reviews[] = $row;
}
$reviews_stmt->close();

// Fetch menu items for this restaurant
$menu_items = [];
$menu_stmt = $mysqli->prepare("SELECT id, name, description, price, image_path FROM menu_items WHERE hotel_id = ? ORDER BY created_at DESC");
$menu_stmt->bind_param("i", $hotel_id);
$menu_stmt->execute();
$menu_res = $menu_stmt->get_result();
while ($row = $menu_res->fetch_assoc()) {
  $menu_items[] = $row;
}
$menu_stmt->close();

include 'includes/header.php';
?>

<style>
  .restaurant-page-header {
    background: linear-gradient(135deg, var(--brand1), var(--brand2));
    color: #fff;
    padding: 120px 0 60px;
    text-align: center;
    position: relative;
    overflow: hidden;
  }

  .restaurant-page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
    opacity: 0.3;
  }

  .restaurant-page-header-content {
    position: relative;
    z-index: 1;
  }

  .restaurant-page-name {
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 800;
    margin: 0 0 10px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
  }

  .restaurant-page-meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    font-size: 1.1rem;
  }

  .restaurant-page-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    padding: 8px 16px;
    border-radius: 999px;
    backdrop-filter: blur(10px);
  }

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }

  .restaurant-details {
    padding: 40px 0;
  }

  .description-box {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
  }

  .description-box h2 {
    margin-top: 0;
    color: var(--dark);
    margin-bottom: 15px;
  }

  .menu-section {
    margin-top: 30px;
  }

  .menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
  }

  .menu-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .menu-image {
    width: 100%;
    height: 140px;
    object-fit: cover;
    display: block;
  }

  .menu-content {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .menu-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }

  .menu-title {
    font-weight: 700;
    font-size: 1rem;
    margin: 0;
  }

  .menu-price {
    color: #667eea;
    font-weight: 700;
  }

  .menu-desc {
    color: #666;
    font-size: 0.9rem;
    min-height: 36px;
  }

  .cta-book-container {
    margin: 32px 0;
  }

  .cta-book-inner {
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .cta-book-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 24px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--brand1), var(--brand2));
    color: #fff;
    font-weight: 700;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.35);
    text-decoration: none;
    transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    margin-left: 220px;
  } 

  .cta-book-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 26px rgba(102, 126, 234, 0.45);
    opacity: 0.95;
  }
</style>

<header class="restaurant-page-header">
  <div class="container restaurant-page-header-content">
    <h1 class="restaurant-page-name"><?php echo htmlspecialchars($hotel['name']); ?></h1>
    <div class="restaurant-page-meta">
      <div class="restaurant-page-meta-item">
        <i class="fas fa-utensils"></i> <?php echo htmlspecialchars($hotel['cuisine_type']); ?>
      </div>
      <div class="restaurant-page-meta-item">
        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hotel['location']); ?>
      </div>
      <div class="restaurant-page-meta-item">
        <i class="fas fa-star"></i> <?php echo number_format($avg_rating, 1); ?> (<?php echo $total_ratings; ?> reviews)
      </div>
      <?php
      $show_edit = false;
      if (isset($_SESSION['role']) && $_SESSION['role'] === 'restaurant_owner' && isset($user_data['id'])) {
        $show_edit = ($hotel['owner_id'] ?? null) === $user_data['id'];
      }
      ?>
      <?php if ($show_edit): ?>
        <a href="restaurants/manage.php?edit_restaurant=<?php echo $hotel_id; ?>" class="restaurant-page-meta-item" style="text-decoration: none; color: #fff;">
          <i class="fas fa-edit"></i> Edit
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="restaurant-details">
  <div class="container">
    <div class="description-box">
      <h2>About <?php echo htmlspecialchars($hotel['name']); ?></h2>
      <p><?php echo nl2br(htmlspecialchars($hotel['description'])); ?></p>

      <div style="margin-top: 20px;">
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($hotel['phone']); ?></p>
        <p><strong>Price Range:</strong> <?php echo htmlspecialchars($hotel['price_range']); ?></p>
      </div>

      <?php if (!empty($hotel['image_url'])): ?>
        <div style="margin-top: 20px;">
          <img src="<?php echo htmlspecialchars($hotel['image_url']); ?>" alt="<?php echo htmlspecialchars($hotel['name']); ?>" style="max-width: 100%; border-radius: 8px;">
        </div>
      <?php endif; ?>
    </div>

    <div class="menu-section">
      <h2 style="margin-bottom: 16px;">Menu</h2>
      <?php if (!empty($menu_items)): ?>
        <div class="menu-grid">
          <?php foreach ($menu_items as $item): ?>
            <?php
            $img_src = !empty($item['image_path']) ? $item['image_path'] : 'assets/images/restaurants/sayaji.jpg';
            ?>
            <div class="menu-card">
              <img class="menu-image" src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
              <div class="menu-content">
                <div class="menu-title-row">
                  <h3 class="menu-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                  <span class="menu-price">₹<?php echo number_format(floatval($item['price']), 2); ?></span>
                </div>
                <?php if (!empty($item['description'])): ?>
                  <p class="menu-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color: #666;">No menu items available for this restaurant.</p>
      <?php endif; ?>
    </div>


  </div>
</section>

<?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'restaurant_owner'): ?>
  <section class="restaurant-details" style="padding-top: 0;">
    <div class="container cta-book-container">
      <div class="cta-book-inner">
        <a href="booktable.php?restaurant_id=<?php echo (int)$hotel_id; ?>" class="cta-book-btn">
          <i class="fas fa-calendar-plus"></i> Book Table
        </a>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'restaurant_owner'): ?>
  <section id="review" class="restaurant-details" style="padding-top: 0;">
    <div class="container">
      <div class="description-box">
        <h2>Reviews</h2>
        <?php if (isset($_SESSION['email'])): ?>
          <div class="rating-form" id="ratingForm" style="margin-bottom: 16px;">
            <div class="rating-message" id="ratingMessage"></div>
            <div class="current-rating-display" id="currentRatingDisplay" style="margin-bottom: 8px;">
              <?php if ($user_rating): ?>
                Your current rating: <?php echo $user_rating['rating']; ?> stars
              <?php else: ?>
                Share your experience with others
              <?php endif; ?>
            </div>
            <div class="rating-stars" id="ratingStars" style="font-size: 24px; color: #f1c40f; display: flex; gap: 8px; cursor: pointer;">
              <i class="fas fa-star rating-star" data-rating="1"></i>
              <i class="fas fa-star rating-star" data-rating="2"></i>
              <i class="fas fa-star rating-star" data-rating="3"></i>
              <i class="fas fa-star rating-star" data-rating="4"></i>
              <i class="fas fa-star rating-star" data-rating="5"></i>
            </div>
            <div class="rating-label" id="ratingLabel" style="margin-top: 6px;">Select a rating</div>
            <textarea class="rating-review" id="ratingReview" placeholder="Write a review (optional)" rows="4" style="width: 100%; margin-top: 10px;"><?php echo htmlspecialchars($user_rating['review'] ?? ''); ?></textarea>
            <button class="btn" id="ratingSubmitBtn" style="margin-top: 10px; background: #667eea; color: #fff;">Submit Rating</button>
          </div>
        <?php else: ?>
          <div class="login-prompt">
            <p>Please <a href="auth/login.php">login</a> to rate this restaurant</p>
          </div>
        <?php endif; ?>
        <?php if (!empty($reviews)): ?>
          <div id="reviewsList" style="display: grid; gap: 12px;">
            <?php foreach ($reviews as $rv): ?>
              <div style="border: 1px solid #eee; border-radius: 8px; padding: 12px; display: grid; grid-template-columns: auto 1fr; gap: 12px; align-items: start;">
                <div style="min-width: 90px; text-align: center;">
                  <span style="display: inline-block; background: #f7f7ff; color: #667eea; border-radius: 999px; padding: 6px 10px; font-weight: 600;">
                    <i class="fas fa-star"></i> <?php echo number_format((float)$rv['rating'], 1); ?>
                  </span>
                  <div style="color: #777; font-size: 0.8rem; margin-top: 6px;"><?php echo htmlspecialchars(date('Y-m-d', strtotime($rv['created_at']))); ?></div>
                </div>
                <div style="color: #444;">
                  <?php
                  $text = trim((string)$rv['review']);
                  if ($text === '') {
                    $text = 'No written review';
                  }
                  ?>
                  <?php echo htmlspecialchars($text); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p style="color: #666;">No reviews yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const toast = document.createElement('div');
    toast.id = 'toastBar';
    toast.style.position = 'fixed';
    toast.style.top = '0';
    toast.style.left = '0';
    toast.style.right = '0';
    toast.style.background = '#2ecc71';
    toast.style.color = '#fff';
    toast.style.padding = '12px 16px';
    toast.style.textAlign = 'center';
    toast.style.fontWeight = '600';
    toast.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
    toast.style.display = 'none';
    toast.style.zIndex = '9999';
    toast.textContent = 'Thanks for your review!';
    document.body.appendChild(toast);
    const ratingFormExists = document.getElementById('ratingForm') || document.getElementById('ratingStars') || document.getElementById('reviewsList');
    if (!ratingFormExists) {
      return;
    }
    const stars = document.querySelectorAll('.rating-star');
    const ratingLabel = document.getElementById('ratingLabel');
    const ratingSubmitBtn = document.getElementById('ratingSubmitBtn');
    const ratingReview = document.getElementById('ratingReview');
    const ratingMessage = document.getElementById('ratingMessage');
    const currentRatingDisplay = document.getElementById('currentRatingDisplay');
    const reviewsList = document.getElementById('reviewsList');
    let selectedRating = <?php echo $user_rating ? intval($user_rating['rating']) : 0; ?>;
    if (selectedRating > 0) {
      highlightStars(selectedRating, true);
      ratingLabel.textContent = 'Your review has been submitted';
      if (ratingSubmitBtn) {
        ratingSubmitBtn.disabled = true;
        ratingSubmitBtn.style.display = 'none';
      }
    }
    stars.forEach((star, index) => {
      star.addEventListener('mouseenter', function() {
        if (selectedRating > 0) return;
        const rating = index + 1;
        highlightStars(rating, false);
        setLabel(rating);
      });
      star.addEventListener('mouseleave', function() {
        if (selectedRating > 0) return;
        highlightStars(0, false);
        ratingLabel.textContent = 'Select a rating';
      });
      star.addEventListener('click', function() {
        const rating = index + 1;
        selectedRating = rating;
        highlightStars(rating, true);
        ratingLabel.textContent = 'You selected ' + rating + ' star' + (rating > 1 ? 's' : '');
      });
    });

    function highlightStars(rating, lock) {
      stars.forEach((star, idx) => {
        star.style.color = idx < rating ? '#f1c40f' : '#ddd';
        star.style.cursor = lock ? 'default' : 'pointer';
      });
    }

    function setLabel(rating) {
      const labels = {
        1: 'Poor',
        2: 'Fair',
        3: 'Good',
        4: 'Very Good',
        5: 'Excellent'
      };
      ratingLabel.textContent = labels[rating] + ' - ' + rating + ' star' + (rating > 1 ? 's' : '');
    }
    if (ratingSubmitBtn) {
      ratingSubmitBtn.addEventListener('click', function() {
        if (selectedRating === 0) {
          ratingMessage.textContent = 'Please select a rating';
          ratingMessage.className = 'rating-message error';
          setTimeout(() => {
            ratingMessage.className = 'rating-message';
          }, 3000);
          return;
        }
        ratingSubmitBtn.disabled = true;
        ratingSubmitBtn.textContent = 'Submitting...';
        const formData = new FormData();
        formData.append('hotel_id', <?php echo $hotel_id; ?>);
        formData.append('rating', selectedRating);
        formData.append('review', ratingReview.value);
        fetch('includes/process_rating.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              ratingMessage.textContent = 'Thanks for your review!';
              ratingMessage.className = 'rating-message success';
              currentRatingDisplay.textContent = 'Your rating: ' + selectedRating + ' stars';
              const metaItems = document.querySelectorAll('.restaurant-page-meta-item');
              if (metaItems && metaItems[2]) {
                metaItems[2].innerHTML = '<i class="fas fa-star"></i> ' + Number(data.avg_rating).toFixed(1) + ' (' + data.total_ratings + ' reviews)';
              }
              setTimeout(() => {
                ratingMessage.className = 'rating-message';
              }, 3000);
              ratingSubmitBtn.disabled = true;
              ratingSubmitBtn.style.display = 'none';
              toast.textContent = 'Thanks for submitting your review!';
              toast.style.display = 'block';
              setTimeout(() => {
                toast.style.display = 'none';
              }, 2500);
              const reviewText = ratingReview.value || 'No written review';
              const item = document.createElement('div');
              item.style.border = '1px solid #eee';
              item.style.borderRadius = '8px';
              item.style.padding = '12px';
              item.style.display = 'grid';
              item.style.gridTemplateColumns = 'auto 1fr';
              item.style.gap = '12px';
              item.style.alignItems = 'start';
              const left = document.createElement('div');
              left.style.minWidth = '90px';
              left.style.textAlign = 'center';
              const badge = document.createElement('span');
              badge.style.display = 'inline-block';
              badge.style.background = '#f7f7ff';
              badge.style.color = '#667eea';
              badge.style.borderRadius = '999px';
              badge.style.padding = '6px 10px';
              badge.style.fontWeight = '600';
              badge.innerHTML = '<i class=\"fas fa-star\"></i> ' + Number(selectedRating).toFixed(1);
              const dt = document.createElement('div');
              dt.style.color = '#777';
              dt.style.fontSize = '0.8rem';
              dt.style.marginTop = '6px';
              dt.textContent = new Date().toISOString().slice(0, 10);
              left.appendChild(badge);
              left.appendChild(dt);
              const right = document.createElement('div');
              right.style.color = '#444';
              right.textContent = reviewText;
              item.appendChild(left);
              item.appendChild(right);
              if (reviewsList) {
                reviewsList.insertBefore(item, reviewsList.firstChild);
              }
            } else {
              ratingMessage.textContent = data.message;
              ratingMessage.className = 'rating-message error';
              setTimeout(() => {
                ratingMessage.className = 'rating-message';
                ratingSubmitBtn.disabled = false;
                ratingSubmitBtn.textContent = 'Submit Rating';
              }, 3000);
            }
          })
          .catch(() => {
            ratingMessage.textContent = 'Something went wrong';
            ratingMessage.className = 'rating-message error';
            ratingSubmitBtn.disabled = false;
            ratingSubmitBtn.textContent = 'Submit Rating';
          });
      });
    }
  });
</script>

<?php include 'includes/footer.php'; ?>
