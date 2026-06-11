</main>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="brand-name">Alex Theatre</div>
                <div class="brand-tag">Alexandria's Independent Cinema</div>
                <p><?= e(SITE_ADDRESS) ?><br>
                Phone: <a href="tel:<?= e(SITE_PHONE) ?>"><?= e(SITE_PHONE) ?></a></p>
                <div class="social-links">
                    <a href="<?= FACEBOOK_URL ?>" target="_blank" rel="noopener">Facebook</a>
                    <a href="<?= INSTAGRAM_URL ?>" target="_blank" rel="noopener">Instagram</a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?= url() ?>">Now Showing</a></li>
                    <li><a href="<?= url('senior-movie.php') ?>">Free Senior Movie</a></li>
                    <li><a href="<?= url('concessions.php') ?>">Concessions</a></li>
                    <li><a href="<?= url('events.php') ?>">Events</a></li>
                    <li><a href="<?= url('private-screenings.php') ?>">Private Screenings</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Visit Us</h4>
                <ul>
                    <li><a href="<?= url('contact.php') ?>">Visit &amp; Contact</a></li>
                    <li><a href="<?= FORM_EMPLOYMENT ?>" target="_blank" rel="noopener">Employment</a></li>
                    <li><a href="<?= e(TICKETS_URL) ?>">Buy Tickets</a></li>
                    <li><a href="<?= url('privacy') ?>">Privacy Policy</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Alex Movie Theatre · Alexandria, Indiana</span>
            <span>$5 Adults &bull; $3 Children</span>
        </div>
    </div>
</footer>

<script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>
