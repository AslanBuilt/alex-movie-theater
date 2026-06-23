</main>

<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="brand-name">The Alex</div>
        <div class="brand-tag">Alexandria's Independent Cinema</div>
        <p>407 N. Harrison Street, Alexandria, IN 46001<br>Phone: <a href="tel:765-620-9093">(765) 620-9093</a></p>
        <div class="social-links">
          <a href="https://www.facebook.com/TheAlexandriaTheatre" target="_blank" rel="noopener">Facebook</a>
          <a href="https://www.instagram.com/the.alextheatre" target="_blank" rel="noopener">Instagram</a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="index.php">Now Showing</a></li>
          <li><a href="senior-movie.php">Free Senior Movie</a></li>
          <li><a href="concessions.php">Concessions</a></li>
          <li><a href="events.php">Events</a></li>
          <li><a href="private-screenings.php">Private Screenings</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Visit Us</h4>
        <ul>
          <li><a href="location.php">Location &amp; Contact</a></li>
          <li><a href="https://docs.google.com/forms/d/e/1FAIpQLSeIx_YNZ91tXNZ2PvcmIRTIoVUjqDo56f3cjPNgs2z9OWspww/viewform" target="_blank" rel="noopener">Employment</a></li>
          <li><a href="tickets.php">Buy Tickets</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> The Alex &middot; Alexandria, Indiana</span>
      <span>$5 Adults &bull; $3 Children</span>
    </div>
  </div>
</footer>

<!-- Cart overlay + drawer -->
<div class="cart-overlay" id="cartOverlay" aria-hidden="true"></div>

<aside class="cart-drawer" id="cartDrawer" aria-label="Shopping cart" aria-hidden="true">
  <div class="cart-drawer-header">
    <h2 class="cart-drawer-title">Your Order</h2>
    <button class="cart-drawer-close" id="cartClose" aria-label="Close cart">&times;</button>
  </div>
  <div class="cart-drawer-body" id="cartBody">
    <p class="cart-empty">Your cart is empty.</p>
  </div>
  <div class="cart-drawer-footer" id="cartFooter" style="display:none">
    <div class="cart-total-row">
      <span>Total</span>
      <span id="cartTotalDisplay">$0.00</span>
    </div>
    <a href="checkout.php" class="btn btn-crimson" style="width:100%;display:block;text-align:center;">Proceed to Checkout</a>
  </div>
</aside>

<!-- Mobile sticky cart bar -->
<div class="cart-mobile-bar" id="cartMobileBar" style="display:none">
  <button class="cart-mobile-bar-btn" id="cartMobileBarBtn">
    <span id="cartMobileCount">0</span> item(s) &bull; <span id="cartMobileTotalDisplay">$0.00</span> &bull; <strong>View Order</strong>
  </button>
</div>

<script src="assets/js/main.js"></script>
<script src="assets/js/cart.js"></script>
</body>
</html>
