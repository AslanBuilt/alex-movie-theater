# Alex Movie Theatre — Website Rebuild

A rebuilt website for **Alex Movie Theatre** in Alexandria, Indiana. Two-screen independent cinema serving the local community with affordable tickets. The site is now database-backed with an admin panel for managing movies, showtimes, events, and senior showings.

## Live Preview

**https://2ktay.github.io/alex-movie-theater/** (static mirror — may be out of sync; see the live site at https://parityrfp.com/cs/alex-movie-theater/)

## Pages

| Page | URL |
|------|-----|
| Now Showing | [/](https://2ktay.github.io/alex-movie-theater/) |
| Senior Movie | [/senior-movie.html](https://2ktay.github.io/alex-movie-theater/senior-movie.html) |
| Concessions | [/concessions.html](https://2ktay.github.io/alex-movie-theater/concessions.html) |
| Events | [/events.html](https://2ktay.github.io/alex-movie-theater/events.html) |
| Private Screenings | [/private-screenings.html](https://2ktay.github.io/alex-movie-theater/private-screenings.html) |
| Location & Parking | [/location.html](https://2ktay.github.io/alex-movie-theater/location.html) |
| Contact | [/contact.html](https://2ktay.github.io/alex-movie-theater/contact.html) |

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

Required repository secrets:

| Secret | Value |
|--------|-------|
| `FTP_HOST` | `72.167.208.71` |
| `FTP_USERNAME` | `DW@parityrfp.com` |
| `FTP_PASSWORD` | (private) |

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
