<?php
// Redirect restaurant owners to their dashboard
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'restaurant_owner') {
  header('Location: restaurants/dashboard.php');
  exit;
}
include 'includes/header.php';
$featured_restaurants = [];
$has_hidden_col = false;
try {
  $col_res = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'hidden_at'");
  if ($col_res && $col_res->num_rows > 0) {
    $has_hidden_col = true;
  }
} catch (Throwable $th) {
}
$featured_sql = $has_hidden_col
  ? "
  SELECT h.id, h.name, h.cuisine_type, h.location, h.price_range, h.image_url,
         COALESCE(ROUND(AVG(r.rating), 1), 4.5) AS avg_rating
  FROM hotels h
  LEFT JOIN ratings r ON r.hotel_id = h.id AND r.hidden_at IS NULL
  WHERE h.deleted_at IS NULL
  GROUP BY h.id, h.name, h.cuisine_type, h.location, h.price_range, h.image_url
  ORDER BY avg_rating DESC, h.created_at DESC
  LIMIT 3
"
  : "
  SELECT h.id, h.name, h.cuisine_type, h.location, h.price_range, h.image_url,
         COALESCE(ROUND(AVG(r.rating), 1), 4.5) AS avg_rating
  FROM hotels h
  LEFT JOIN ratings r ON r.hotel_id = h.id
  WHERE h.deleted_at IS NULL
  GROUP BY h.id, h.name, h.cuisine_type, h.location, h.price_range, h.image_url
  ORDER BY avg_rating DESC, h.created_at DESC
  LIMIT 3
";
$fr_stmt = $mysqli->query($featured_sql);
if ($fr_stmt) {
  while ($row = $fr_stmt->fetch_assoc()) {
    $featured_restaurants[] = $row;
  }
}
?>

<!-- Hero Section -->
<section class="hero-section">
  <div class="hero-overlay"></div>
  <div class="container hero-content">
    <div class="hero-text">
      <span class="hero-badge">
        <i class="fas fa-star"></i> Rajkot's #1 Dining Platform
      </span>
      <h1 class="hero-title">
        Discover & Reserve<br />
        <span class="gradient-text">Unforgettable</span> Dining
      </h1>
      <p class="hero-description">
        From intimate date nights to grand celebrations—book the perfect table at Rajkot's finest restaurants.
        Instant confirmations, exclusive perks, and seamless experiences.
      </p>
      <div class="hero-actions">
        <a href="restaurants.php" class="btn-primary-large">
          <i class="fas fa-search"></i> Explore Restaurants
        </a>
        <a href="booktable.php" class="btn-secondary-large">
          <i class="fas fa-calendar-alt"></i> Book a Table
        </a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat-item">
          <strong>500+</strong>
          <span>Restaurants</span>
        </div>
        <div class="hero-stat-item">
          <strong>12K+</strong>
          <span>Monthly Diners</span>
        </div>
        <div class="hero-stat-item">
          <strong>4.9★</strong>
          <span>Average Rating</span>
        </div>
      </div>
    </div>
    <div class="hero-image">
      <div class="hero-card hero-card-1">
        <i class="fas fa-utensils"></i>
        <span>Fine Dining</span>
      </div>
      <div class="hero-card hero-card-2">
        <i class="fas fa-cocktail"></i>
        <span>Bars & Lounges</span>
      </div>
      <div class="hero-card hero-card-3">
        <i class="fas fa-birthday-cake"></i>
        <span>Celebrations</span>
      </div>
    </div>
  </div>
</section>

<!-- Featured Restaurants -->
<?php if (!empty($featured_restaurants)): ?>
  <section class="featured-section">
    <div class="container">
      <div class="section-header">
        <div>
          <p class="section-eyebrow">
            <i class="fas fa-fire"></i> Trending Now
          </p>
          <h2 class="section-title">Featured <span class="gradient-text">Restaurants</span></h2>
          <p class="section-subtitle">Handpicked venues with exceptional cuisine and ambiance</p>
        </div>
        <a href="restaurants.php" class="view-all-link">
          View All <i class="fas fa-arrow-right"></i>
        </a>
      </div>

      <div class="featured-grid">
        <?php foreach ($featured_restaurants as $fr): ?>
          <?php
          $img_src = !empty($fr['image_url']) ? $fr['image_url'] : 'assets/images/restaurants/sayaji.jpg';
          $rating = isset($fr['avg_rating']) ? number_format($fr['avg_rating'], 1) : '4.5';
          ?>
          <div class="featured-card">
            <div class="featured-card-image" style="background-image: url('<?php echo htmlspecialchars($img_src); ?>'); background-size: cover; background-position: center;">
              <div class="featured-badge">
                <i class="fas fa-star"></i> <?php echo $rating; ?>
              </div>
              <div class="featured-overlay">
                <a href="restaurant_details.php?id=<?php echo $fr['id']; ?>" class="featured-btn">View Details</a>
              </div>
            </div>
            <div class="featured-card-content">
              <div class="featured-header">
                <h3><?php echo htmlspecialchars($fr['name']); ?></h3>
                <span class="featured-price"><?php echo htmlspecialchars($fr['price_range']); ?></span>
              </div>
              <p class="featured-cuisine">
                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($fr['cuisine_type']); ?> • Fine Dining
              </p>
              <p class="featured-location"><?php echo htmlspecialchars($fr['location']); ?></p>
              <div class="featured-meta">
                <span><i class="fas fa-clock"></i> 11:00 AM - 11:00 PM</span>
                <span><i class="fas fa-users"></i> Up to 20 guests</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- How It Works -->
<section class="how-it-works">
  <div class="container">
    <div class="section-header-center">
      <p class="section-eyebrow">
        <i class="fas fa-lightbulb"></i> Simple & Seamless
      </p>
      <h2 class="section-title">How It <span class="gradient-text">Works</span></h2>
      <p class="section-subtitle">Book your perfect dining experience in three easy steps</p>
    </div>

    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">1</div>
        <div class="step-icon">
          <i class="fas fa-search"></i>
        </div>
        <h3>Browse & Discover</h3>
        <p>Explore 500+ curated restaurants with detailed menus, photos, and real reviews from fellow diners.</p>
      </div>

      <div class="step-card">
        <div class="step-number">2</div>
        <div class="step-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <h3>Select & Reserve</h3>
        <p>Choose your preferred date, time, and party size. Get instant confirmation—no phone calls needed.</p>
      </div>

      <div class="step-card">
        <div class="step-number">3</div>
        <div class="step-icon">
          <i class="fas fa-utensils"></i>
        </div>
        <h3>Dine & Enjoy</h3>
        <p>Show up and savor exceptional cuisine. Your table is ready, and exclusive perks await you.</p>
      </div>
    </div>
  </div>
</section>

<!-- Why Choose Us -->
<section class="why-choose-us">
  <div class="container">
    <div class="why-choose-grid">
      <div class="why-choose-content">
        <p class="section-eyebrow">
          <i class="fas fa-award"></i> Premium Experience
        </p>
        <h2 class="section-title">Why Diners <span class="gradient-text">Love EatEase</span></h2>
        <p class="why-choose-description">
          We're not just a booking platform—we're your dining companion. From discovery to dessert,
          we ensure every moment is memorable.
        </p>

        <div class="features-list">
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-bolt"></i>
            </div>
            <div class="feature-content">
              <h4>Instant Confirmations</h4>
              <p>Book in seconds and receive immediate confirmation. No waiting, no hassle.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-gift"></i>
            </div>
            <div class="feature-content">
              <h4>Exclusive Perks</h4>
              <p>Enjoy complimentary appetizers, priority seating, and special occasion surprises.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <div class="feature-content">
              <h4>Trusted & Secure</h4>
              <p>Your data is safe with us. Book with confidence and peace of mind.</p>
            </div>
          </div>

          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-headset"></i>
            </div>
            <div class="feature-content">
              <h4>24/7 Support</h4>
              <p>Our concierge team is always ready to help with special requests and modifications.</p>
            </div>
          </div>
        </div>

        <a href="restaurants.php" class="btn-primary-large">
          Start Exploring <i class="fas fa-arrow-right"></i>
        </a>
      </div>

      <div class="why-choose-visual">
        <div class="visual-card visual-card-1">
          <i class="fas fa-star"></i>
          <h4>4.9/5 Rating</h4>
          <p>12,000+ Happy Diners</p>
        </div>
        <div class="visual-card visual-card-2">
          <i class="fas fa-utensils"></i>
          <h4>500+ Venues</h4>
          <p>Curated Collection</p>
        </div>
        <div class="visual-card visual-card-3">
          <i class="fas fa-clock"></i>
          <h4>60 Seconds</h4>
          <p>Average Booking Time</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
  <div class="container">
    <div class="cta-content">
      <h2>Ready to Discover Your Next Favorite Restaurant?</h2>
      <p>Join thousands of food lovers who trust EatEase for unforgettable dining experiences.</p>
      <div class="cta-buttons">
        <a href="restaurants.php" class="btn-white-large">
          <i class="fas fa-utensils"></i> Browse Restaurants
        </a>
        <a href="booktable.php" class="btn-outline-white-large">
          <i class="fas fa-calendar-plus"></i> Book Now
        </a>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
