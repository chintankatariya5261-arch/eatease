<?php
$page_title = 'Restaurants - EatEase';
include 'includes/header.php';

// Fetch distinct cuisines for filter
$cuisines = [];
$c_stmt = $mysqli->query("SELECT DISTINCT cuisine_type FROM hotels WHERE deleted_at IS NULL AND cuisine_type IS NOT NULL ORDER BY cuisine_type");
if ($c_stmt) {
  while ($row = $c_stmt->fetch_assoc()) {
    $cuisines[] = $row['cuisine_type'];
  }
}

// Fetch all active restaurants
$restaurants = [];
$r_stmt = $mysqli->query("SELECT * FROM hotels WHERE deleted_at IS NULL ORDER BY created_at DESC");
if ($r_stmt) {
  while ($row = $r_stmt->fetch_assoc()) {
    $restaurants[] = $row;
  }
}

// Fetch user favorites if logged in
$user_favorites = [];
if (isset($_SESSION['user_id'])) {
  $u_id = $_SESSION['user_id'];
  $f_stmt = $mysqli->prepare("SELECT hotel_id FROM favorites WHERE user_id = ?");
  $f_stmt->bind_param("i", $u_id);
  $f_stmt->execute();
  $f_result = $f_stmt->get_result();
  while ($row = $f_result->fetch_assoc()) {
    $user_favorites[] = $row['hotel_id'];
  }
  $f_stmt->close();
} elseif (isset($_SESSION['email'])) {
  // Fallback if user_id not in session (though login usually sets it)
  $u_email = $_SESSION['email'];
  $u_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
  $u_stmt->bind_param("s", $u_email);
  $u_stmt->execute();
  $u_res = $u_stmt->get_result();
  if ($u_row = $u_res->fetch_assoc()) {
    $u_id = $u_row['id'];
    $f_stmt = $mysqli->prepare("SELECT hotel_id FROM favorites WHERE user_id = ?");
    $f_stmt->bind_param("i", $u_id);
    $f_stmt->execute();
    $f_result = $f_stmt->get_result();
    while ($row = $f_result->fetch_assoc()) {
      $user_favorites[] = $row['hotel_id'];
    }
    $f_stmt->close();
  }
  $u_stmt->close();
}
?>

<link rel="stylesheet" href="assets/css/restaurants.css" />
<style>
  .favorite-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.2rem;
    color: #ccc;
    transition: color 0.3s ease;
    margin-left: 10px;
  }

  .favorite-btn.active {
    color: #ff4757;
  }

  .favorite-btn:hover {
    transform: scale(1.1);
  }

  .restaurant-name-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
</style>

<!-- Hero Section -->
<section class="restaurants-hero">
  <div class="container restaurants-hero-content">
    <p class="section-eyebrow">
      <i class="fas fa-location-dot"></i> Rajkot's Finest
    </p>
    <h1>Discover Incredible <br />Dining Experiences</h1>
    <p>Explore curated restaurants with exceptional cuisine, ambiance, and service. Every venue is personally vetted by our team.</p>
  </div>
</section>

<!-- Filters Section -->
<section class="filters-section">
  <div class="container">
    <div class="filters-container">
      <div class="filter-group">
        <label for="cuisine-filter">Cuisine</label>
        <select id="cuisine-filter" class="filter-select">
          <option value="all">All Cuisines</option>
          <?php foreach ($cuisines as $cuisine): ?>
            <option value="<?php echo strtolower(str_replace(' ', '-', $cuisine)); ?>"><?php echo htmlspecialchars($cuisine); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="price-filter">Price Range</label>
        <select id="price-filter" class="filter-select">
          <option value="all">All Prices</option>
          <option value="budget">$$ - Budget</option>
          <option value="moderate">$$$ - Moderate</option>
          <option value="premium">$$$$ - Premium</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="rating-filter">Rating</label>
        <select id="rating-filter" class="filter-select">
          <option value="all">All Ratings</option>
          <option value="4.5">4.5+ Stars</option>
          <option value="4.0">4.0+ Stars</option>
          <option value="3.5">3.5+ Stars</option>
        </select>
      </div>

      <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="search-input" placeholder="Search restaurants...">
      </div>

      <span class="results-count" id="results-count"><?php echo count($restaurants); ?> Restaurants</span>
    </div>
  </div>
</section>

<!-- Restaurants Grid -->
<section class="restaurants-section">
  <div class="container">
    <div class="restaurants-grid" id="restaurants-grid">
      <?php foreach ($restaurants as $restaurant):
        // Map DB values to filter values
        $cuisine_val = strtolower(str_replace(' ', '-', $restaurant['cuisine_type']));

        $price_val = 'budget'; // Default
        if (strlen($restaurant['price_range']) >= 4) $price_val = 'premium';
        elseif (strlen($restaurant['price_range']) == 3) $price_val = 'moderate';
        elseif (strlen($restaurant['price_range']) <= 2) $price_val = 'budget';

        // Handle special characters in price range if any
        if (strpos($restaurant['price_range'], '$$$$') !== false) $price_val = 'premium';
        elseif (strpos($restaurant['price_range'], '$$$') !== false) $price_val = 'moderate';

        $is_fav = in_array($restaurant['id'], $user_favorites);

        // Image handling
        $img_src = !empty($restaurant['image_url']) ? $restaurant['image_url'] : 'assets/images/restaurants/sayaji.jpg'; // Fallback

        // Default rating if not present
        $rating = isset($restaurant['avg_rating']) ? $restaurant['avg_rating'] : 4.5;
        $open_display = !empty($restaurant['open_time']) ? date('g:i A', strtotime($restaurant['open_time'])) : null;
        $close_display = !empty($restaurant['close_time']) ? date('g:i A', strtotime($restaurant['close_time'])) : null;
        $hours_line = ($open_display && $close_display) ? ($open_display . ' - ' . $close_display) : 'Hours not set';
      ?>
        <div class="restaurant-card" data-cuisine="<?php echo $cuisine_val; ?>" data-price="<?php echo $price_val; ?>" data-rating="<?php echo $rating; ?>">
          <div class="restaurant-image">
            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($restaurant['name']); ?>">
            <div class="restaurant-badge">
              <i class="fas fa-star"></i> <?php echo number_format($rating, 1); ?>
            </div>
          </div>
          <div class="restaurant-info">
            <div class="restaurant-name-row">
              <h3 class="restaurant-name"><?php echo htmlspecialchars($restaurant['name']); ?></h3>
              <span class="restaurant-price" style="color: #667eea; font-weight: 600;"><?php echo htmlspecialchars($restaurant['price_range']); ?></span>
            </div>
            <p class="restaurant-cuisine" style="color: #667eea;">
              <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($restaurant['cuisine_type']); ?> • Fine Dining
            </p>
            <p class="restaurant-location" style="color: #666; font-size: 0.9rem; margin-bottom: 12px;"><?php echo htmlspecialchars($restaurant['location']); ?></p>

            <div style="border-top: 1px solid #eee; margin: 10px 0;"></div>

            <div class="restaurant-stats" style="display: flex; gap: 16px; color: #666; font-size: 0.85rem;">
              <span><i class="fas fa-clock" style="color: #667eea;"></i> <?php echo htmlspecialchars($hours_line); ?></span>
              <span><i class="fas fa-user-friends" style="color: #667eea;"></i> Up to 20 guests</span>
            </div>

            <div class="restaurant-meta" style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center;">
              <?php if (isset($_SESSION['user_id']) || isset($_SESSION['email'])): ?>
                <button class="favorite-btn <?php echo $is_fav ? 'active' : ''; ?>" onclick="toggleFavorite(<?php echo $restaurant['id']; ?>, this)" style="margin: 0;">
                  <i class="<?php echo $is_fav ? 'fas' : 'far'; ?> fa-heart"></i>
                </button>
              <?php else: ?>
                <span></span>
              <?php endif; ?>
              <a href="restaurant_details.php?id=<?php echo $restaurant['id']; ?>" class="restaurant-btn" style="padding: 8px 16px; font-size: 0.9rem;">
                View Details
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
  // Filter functionality
  const cuisineFilter = document.getElementById('cuisine-filter');
  const priceFilter = document.getElementById('price-filter');
  const ratingFilter = document.getElementById('rating-filter');
  const searchInput = document.getElementById('search-input');
  const restaurantCards = document.querySelectorAll('.restaurant-card');
  const resultsCount = document.getElementById('results-count');

  function filterRestaurants() {
    const cuisineValue = cuisineFilter.value;
    const priceValue = priceFilter.value;
    const ratingValue = parseFloat(ratingFilter.value);
    const searchValue = searchInput.value.toLowerCase();

    let visibleCount = 0;

    restaurantCards.forEach(card => {
      const cuisine = card.dataset.cuisine;
      const price = card.dataset.price;
      const rating = parseFloat(card.dataset.rating);
      const name = card.querySelector('.restaurant-name').textContent.toLowerCase();
      const location = card.querySelector('.restaurant-location').textContent.toLowerCase();

      const cuisineMatch = cuisineValue === 'all' || cuisine === cuisineValue;
      const priceMatch = priceValue === 'all' || price === priceValue;
      const ratingMatch = ratingFilter.value === 'all' || rating >= ratingValue;
      const searchMatch = searchValue === '' || name.includes(searchValue) || location.includes(searchValue);

      if (cuisineMatch && priceMatch && ratingMatch && searchMatch) {
        card.style.display = 'flex';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    resultsCount.textContent = `${visibleCount} Restaurant${visibleCount !== 1 ? 's' : ''}`;
  }

  cuisineFilter.addEventListener('change', filterRestaurants);
  priceFilter.addEventListener('change', filterRestaurants);
  ratingFilter.addEventListener('change', filterRestaurants);
  searchInput.addEventListener('input', filterRestaurants);

  // Favorite functionality
  function toggleFavorite(hotelId, btn) {
    const icon = btn.querySelector('i');

    fetch('includes/toggle_favorite.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          hotel_id: hotelId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (data.action === 'added') {
            btn.classList.add('active');
            icon.classList.remove('far');
            icon.classList.add('fas');
          } else {
            btn.classList.remove('active');
            icon.classList.remove('fas');
            icon.classList.add('far');
          }
        } else {
          alert(data.message || 'Error updating favorite');
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
  }
</script>

<?php include 'includes/footer.php'; ?>