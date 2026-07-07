</main>

<!-- Cart toast -->
<div class="cart-toast" id="cartToast" role="status" aria-live="polite">
  <span class="cart-toast-icon">✓</span>
  <span class="cart-toast-text" id="cartToastText">Added to cart</span>
</div>

<!-- Mobile floating cart button (fixed, only on purchase pages) -->
<button class="cart-fab" id="cartFab" aria-label="View cart" style="display:none">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
  <span class="cart-fab-count" id="cartFabCount">0</span>
</button>

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
          <li><a href="location.php#employment">Employment</a></li>
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
    <div class="cart-drawer-title-group">
      <h2 class="cart-drawer-title">Your Order</h2>
      <span class="cart-drawer-count" id="cartItemCount">0 items</span>
    </div>
    <button class="cart-drawer-close" id="cartClose" aria-label="Close cart">
      <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="cart-drawer-body" id="cartBody">
    <div class="cart-empty">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      <p>Your cart is empty</p>
      <span>Add something from the concessions menu</span>
    </div>
  </div>
  <div class="cart-drawer-footer" id="cartFooter" style="display:none">
    <div class="cart-total-row">
      <span>Subtotal</span>
      <span id="cartTotalDisplay">$0.00</span>
    </div>
    <a href="checkout.php" class="btn btn-crimson cart-checkout-btn">Proceed to Checkout</a>
    <button class="cart-continue-btn" id="cartContinue">Continue Shopping</button>
  </div>
</aside>

<!-- Mobile sticky cart bar -->
<div class="cart-mobile-bar" id="cartMobileBar" style="display:none">
  <button class="cart-mobile-bar-btn" id="cartMobileBarBtn">
    <span id="cartMobileCount">0</span> item(s) &bull; <span id="cartMobileTotalDisplay">$0.00</span> &bull; <strong>View Order</strong>
  </button>
</div>

<?php
// ── Structured data: the theater as a local business (site-wide) ──────────────
// MovieTheater is a LocalBusiness subtype, so this powers Google's local/knowledge
// panel. Built from SITE_* constants so it stays in sync with the footer above.
$ldBusiness = [
    '@context'    => 'https://schema.org',
    '@type'       => 'MovieTheater',
    'name'        => SITE_NAME,
    'url'         => SITE_URL,
    'telephone'   => '+1-' . SITE_PHONE,
    'image'       => SITE_URL . 'assets/images/logo.webp',
    'priceRange'  => '$',
    'address'     => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => '407 N. Harrison Street',
        'addressLocality' => 'Alexandria',
        'addressRegion'   => 'IN',
        'postalCode'      => '46001',
        'addressCountry'  => 'US',
    ],
    'sameAs'      => [FACEBOOK_URL, INSTAGRAM_URL],
];
?>
<script type="application/ld+json"><?= json_encode($ldBusiness, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
<script src="assets/js/cart.js?v=<?= @filemtime(__DIR__ . '/../assets/js/cart.js') ?>"></script>
</body>
</html>
