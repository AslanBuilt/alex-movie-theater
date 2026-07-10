# Alex Theater — Project Rules

## Stack
PHP 8.0+ vanilla · MySQL 5.7+ · HTML/CSS/vanilla JS
No Composer · No npm · No React · No Vue

## Deploy
GitHub Actions FTP → parityrfp.com
Push = deploy. Never push without explicit go-ahead.

## Credentials
FTP: GitHub Actions secrets (FTP_USERNAME, FTP_PASSWORD)
DB: public/config/config.php (gitignored)
Stripe: config/stripe.php (gitignored)
SendGrid: config/sendgrid.php (gitignored)

## Standing rules
1. No git push to master without explicit go-ahead
2. No DB migration on production without explicit go-ahead
3. No FTP deploy without explicit go-ahead
4. Show diffs before any production action
5. One-shot diagnostic scripts: upload → run → delete immediately
6. Read every file you'll touch before editing any of them — batch reads, then edits

## Live site
https://parityrfp.com/cs/alex-movie-theater/

## DB
r5nok0izu6hd_alex on parityrfp.com
