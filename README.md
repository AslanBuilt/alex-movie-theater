# Alex Movie Theatre — Website Rebuild

A rebuilt website for **Alex Movie Theatre** in Alexandria, Indiana. Two-screen independent cinema serving the local community with affordable tickets. The site is now database-backed with an admin panel for managing movies, showtimes, events, and senior showings.

## Live Site

**https://parityrfp.com/cs/alex-movie-theater/**

Admin panel: **https://parityrfp.com/cs/alex-movie-theater/admin/**

> A static GitHub Pages mirror previously lived at https://2ktay.github.io/alex-movie-theater/ but is no longer maintained — the live PHP site above is the source of truth.

## Pages

| Page | URL |
|------|-----|
| Now Showing | [/](https://parityrfp.com/cs/alex-movie-theater/) |
| Senior Movie | [/senior-movie](https://parityrfp.com/cs/alex-movie-theater/senior-movie) |
| Concessions | [/concessions](https://parityrfp.com/cs/alex-movie-theater/concessions) |
| Events | [/events](https://parityrfp.com/cs/alex-movie-theater/events) |
| Private Screenings | [/private-screenings](https://parityrfp.com/cs/alex-movie-theater/private-screenings) |
| Location & Parking | [/location](https://parityrfp.com/cs/alex-movie-theater/location) |
| Contact | [/contact](https://parityrfp.com/cs/alex-movie-theater/contact) |
| Admin | [/admin](https://parityrfp.com/cs/alex-movie-theater/admin/) |

## Tech Stack

- PHP 8.0+
- MySQL 5.7+ (cPanel-managed database)
- Vanilla HTML / CSS / JavaScript
- Admin Panel at `/admin` (CSRF-protected, session auth)
- Deployed via GitHub Actions FTP to parityrfp.com

## Admin Panel

The admin panel lives at `/admin` and lets staff manage:

- Movies (now-showing posters, titles, descriptions, ratings)
- Showtimes
- Events
- Senior Movie showings

**Default credentials:** `admin` / `changeme123`

**Change this password immediately on first login.** The default is documented in `docs/HANDOFF.md` and flagged as a critical FAIL item in the production audit.

## Database Setup

See [`docs/HANDOFF.md`](docs/HANDOFF.md) for the full cPanel walkthrough: creating the MySQL database and user, importing `database/schema.sql` then `database/seed.sql` via phpMyAdmin, and copying `config/database.example.php` to `config/database.php` on the server with the live credentials.

## Deployment

Deployment is automated via GitHub Actions (`.github/workflows/deploy.yml`). Every push to `master` triggers an FTP deploy to `/cs/alex-movie-theater/` on parityrfp.com.

### REQUIRED: Add GitHub Secrets (site won't deploy without these)

1. Go to **https://github.com/2KTay/alex-movie-theater/settings/secrets/actions**
2. Click **New repository secret** for each of the following:

| Secret name | Value |
|-------------|-------|
| `FTP_HOST` | `72.167.208.71` |
| `FTP_USERNAME` | `DW@parityrfp.com` |
| `FTP_PASSWORD` | *(FTP password from cPanel → FTP Accounts on parityrfp.com)* |

3. After adding all three, push any commit to `master` (or go to **Actions → Run workflow**) to trigger a deploy.

The workflow uploads `public/` to the web root and `includes/`, `templates/`, `config/` (without `database.php`), and `database/` (without `seed.sql`) to sibling directories.

## Design

- **Theme:** Boutique cinema — dark warm background, deep burgundy/crimson accents, warm cream text
- **Typography:** Playfair Display (headings) + Lato (body)
- **Inspired by:** Independent/boutique cinema aesthetics, not big chain templates
- **Built with:** Aslan Skills (`project-scaffolding`, `professional-frontend-design`, `seo-sao-optimization`, `ftp-deploy-parity`, `formspree-integration`, `htaccess-management`, `production-readiness-audit`)

## Project Structure

```
alex-movie-theater/
├── .github/
│   └── workflows/
│       └── deploy.yml      # GitHub Actions FTP deploy
├── public/                 # Web root — all pages and assets
│   ├── index.php
│   ├── senior-movie.php
│   ├── concessions.php
│   ├── events.php
│   ├── private-screenings.php
│   ├── location.php
│   ├── contact.php
│   ├── .htaccess
│   ├── robots.txt
│   ├── sitemap.xml
│   ├── llms.txt
│   ├── admin/              # Admin panel (CSRF + session auth)
│   └── assets/
│       ├── css/main.css
│       ├── js/main.js
│       └── images/         # Movie posters, favicon.svg
├── config/
│   ├── config.php          # Site constants (URL, phone, form links)
│   ├── database.example.php
│   └── database.php        # gitignored — created on server
├── database/
│   ├── schema.sql
│   └── seed.sql
├── includes/
│   ├── helpers.php         # Utility functions (e, asset, url)
│   ├── Database.php
│   ├── AdminAuth.php
│   └── *Repo.php           # Movie / Event / Showtime repositories
└── templates/
    ├── header.php
    └── footer.php
```

## Client Info

- **Theatre:** Alex Movie Theatre
- **Address:** 407 N. Harrison Street, Alexandria, IN 46001
- **Phone:** 765-620-9093
- **Tickets:** Square Online Store (being replaced with on-site Stripe checkout)
- **Contact Form:** Formspree (`xaqkjakn`)
