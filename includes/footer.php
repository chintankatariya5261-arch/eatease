<!-- Footer -->
<footer class="footer">
  <div class="container">
    <!-- Footer Content -->
    <div class="footer-content">
      <!-- Brand Section -->
      <div class="footer-brand">
        <a href="<?php echo $base_path; ?>index.php" class="footer-logo">
          <i class="fas fa-utensils"></i>
          <span>EatEase</span>
        </a>
        <p class="footer-description">
          Discover and reserve tables at Rajkot's finest restaurants. From intimate dinners to grand celebrations, 
          we make every dining experience unforgettable.
        </p>
        <div class="footer-social">
          <a href="#" class="social-link" aria-label="Facebook">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="#" class="social-link" aria-label="Instagram">
            <i class="fab fa-instagram"></i>
          </a>
          <a href="#" class="social-link" aria-label="Twitter">
            <i class="fab fa-twitter"></i>
          </a>
          <a href="#" class="social-link" aria-label="LinkedIn">
            <i class="fab fa-linkedin-in"></i>
          </a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="footer-section">
        <h4>Quick Links</h4>
        <ul class="footer-links">
          <li><a href="<?php echo $base_path; ?>index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
          <li><a href="<?php echo $base_path; ?>restaurants.php"><i class="fas fa-chevron-right"></i> Restaurants</a></li>
          <li><a href="<?php echo $base_path; ?>booktable.php"><i class="fas fa-chevron-right"></i> Book a Table</a></li>
        </ul>
      </div>

      <!-- Contact Info -->
      <div class="footer-section">
        <h4>Contact Us</h4>
        <ul class="footer-links">
          <li><i class="fas fa-envelope"></i> eatease0@gmail.com</li>
          <li><i class="fas fa-phone"></i> 6351010234</li>
          <li><i class="fas fa-clock"></i> Mon – Sat: 9:00 AM – 8:00 PM</li>
          <li><i class="fas fa-clock"></i> Sun: 10:00 AM – 4:00 PM</li>
        </ul>
      </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
      <div>
        <p>© <span id="year"></span> EatEase. All rights reserved.</p>
      </div>
      <div class="footer-badge">
        <i class="fas fa-star"></i>
        <span>Trusted by 12,000+ Diners</span>
      </div>
    </div>
  </div>
</footer>

<script>
  document.getElementById('year').textContent = new Date().getFullYear();
</script>
</body>
</html>
