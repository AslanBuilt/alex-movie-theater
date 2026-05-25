# Alex Movie Theatre — Production Handoff

## Project Overview

The Alex Movie Theatre site is a vanilla PHP 8 application for a two-screen independent cinema in Alexandria, Indiana. It is now database-backed (MySQL) with an admin panel for managing movies, showtimes, events, and senior showings. Public pages render from the database with a static fallback if the database is unreachable. The site deploys automatically to `https://parityrfp.com/cs/alex-movie-theater/` via GitHub Actions FTP on every push to `master`.

## MySQL Setup (cPanel walkthrough)

Follow these steps the first time the site is deployed to production:

1. **Log in to cPanel** at the parityrfp.com hosting control panel.
2. **Create the database.** Open *MySQL Databases*. Create a new database named `parityr1_alex_movies` (or your chosen name — cPanel will prefix it with `parityr1_`). Then create a new MySQL user with a strong password and grant it **ALL PRIVILEGES** on that database.
3. **Open phpMyAdmin** from cPanel and select the database you just created.
4. **Import the schema.** Click *Import*, choose `database/schema.sql` from your local clone of this repo, and run it. This creates the tables.
5. **Import the seed data.** Click *Import* again, choose `database/seed.sql`, and run it. This loads the initial movie list, showtimes, and a default admin user (`admin` / `changeme123`). Note: `seed.sql` is excluded from automated deploys so re-deploys never clobber live data.
6. **Create `config/database.php` on the server.** Use cPanel's *File Manager* to copy `config/database.example.php` to `config/database.php` inside `/cs/alex-movie-theater/config/`. Edit the new file and fill in the database name, username, password, and host (`localhost` for cPanel). This file is `.gitignored` and excluded from FTP deploys so it is never overwritten.

## GitHub Actions Secrets

The deploy workflow (`.github/workflows/deploy.yml`) requires three repository secrets. Set them under *Settings → Secrets and variables → Actions* in GitHub, or via `gh secret set`:

| Secret | Value |
|--------|-------|
| `FTP_HOST` | `72.167.208.71` |
| `FTP_USERNAME` | `DW@parityrfp.com` |
| `FTP_PASSWORD` | (from password manager) |

```bash
gh secret set FTP_HOST --body "72.167.208.71"
gh secret set FTP_USERNAME --body "DW@parityrfp.com"
gh secret set FTP_PASSWORD --body "<paste-password-here>"
```

## Admin Panel Access

- **URL:** `https://parityrfp.com/cs/alex-movie-theater/admin/`
- **Default username:** `admin`
- **Default password:** `changeme123`

**CHANGE IMMEDIATELY** on first login. The default credentials are in `database/seed.sql` and are flagged as a critical FAIL in `docs/PRODUCTION-AUDIT.md`. Until the password is changed, anyone who reads the repo can sign in to your admin panel.

The admin panel uses session-based auth with CSRF tokens on all state-changing forms. It manages:

- Movies (poster, title, rating, synopsis, dates active)
- Showtimes
- Events
- Senior Movie showings

## Formspree

The contact form posts to Formspree form id `xaqkjakn`. No SMTP setup is required on the server. If the form id changes, update the action URL in `public/contact.php`.

## Deprecated `docs/*.html`

The seven static `.html` files under `docs/` (concessions, contact, events, index, location, private-screenings, senior-movie) were the original GitHub Pages preview mirror. They are now out of date and should be removed in a follow-up commit. The live site lives at the parityrfp URL above; the GitHub Pages link in the README is preserved only as a coarse visual reference.

**TODO:** delete `docs/*.html` and the GitHub Pages preview link once the live site is confirmed working in production.

## Post-Deploy Verification Checklist

After the first successful Actions run, verify the production deploy by walking this list:

1. **Site loads** — `https://parityrfp.com/cs/alex-movie-theater/` returns 200 and renders the homepage with movies from the database.
2. **Admin login works** — `https://parityrfp.com/cs/alex-movie-theater/admin/` shows a login form, and `admin` / `changeme123` (or your changed password) signs in successfully.
3. **Create a test movie** — From the admin panel, create a test movie, confirm it appears on the homepage, then delete it.
4. **Contact form submits** — Submit a test message via the contact page and confirm it arrives in the Formspree inbox.
5. **No 500s in `logs/php-errors.log`** — `tail` the log via cPanel's File Manager (or SSH) and confirm there are no fatal errors after browsing every page.
