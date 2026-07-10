# Alex Movie Theater — Project Rules

## Stack
PHP 8.0+ vanilla, MySQL 5.7+, HTML/CSS/vanilla JS. No Composer, no npm, no frontend framework.

## Deploy
GitHub Actions → FTP → `parityrfp.com/cs/alex-movie-theater/`. Push to `master` = live deploy.

## Credentials (locations, not values)
- DB: GitHub secrets (`DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASSWORD`) → generates `public/config/database.php` at deploy (gitignored)
- Stripe: GitHub secrets → `public/config/stripe.php` (gitignored)
- SendGrid: GitHub secrets (`SENDGRID_API_KEY`/`MAIL_FROM`/`MAIL_FROM_NAME`) → `public/config/sendgrid.php` (gitignored)
- FTP: GitHub Actions secrets only, never in code

## Standing rules
1. Nothing to production — push to master, DB migration, FTP deploy — without explicit go-ahead. Every time, no standing pre-authorization.
2. Show diffs before any production action.
3. Read every file you'll touch before editing any of them. Batch reads first, then edits — never read-edit-read-edit.
4. Commit messages under 72 characters. Details belong in the PR description, not the commit body.
5. One-shot diagnostic scripts: upload, run, delete immediately. Never leave one committed to the repo — see the `_moviecheck.php` incident.

## Everything else
Skills live at `C:\Users\Aslan\Desktop\aslan-skills-master\aslan-skills-master\skills\` — load the relevant one on demand; don't work from memory of what it probably says.
