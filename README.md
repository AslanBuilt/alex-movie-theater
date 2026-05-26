# Alex Movie Theatre — Website

Website for **Alex Movie Theatre** in Alexandria, Indiana. Two-screen independent cinema with affordable tickets. Database-backed with a full admin panel for managing movies, showtimes, events, and senior showings.

---

## Live Links

| | URL |
|---|---|
| **Public Site (GitHub Pages)** | https://2ktay.github.io/alex-movie-theater/ |
| **Admin Panel (GitHub Pages)** | https://2ktay.github.io/alex-movie-theater/admin/ |
| **Production Site (PHP/MySQL)** | https://parityrfp.com/cs/alex-movie-theater/ |
| **Production Admin** | https://parityrfp.com/cs/alex-movie-theater/admin/ |

> GitHub Pages serves the static version (no database). The production site on parityrfp.com runs full PHP + MySQL with live data.

---

## Pages

| Page | File |
|------|------|
| Now Showing | `index` |
| Senior Movie | `senior-movie` |
| Concessions | `concessions` |
| Events | `events` |
| Private Screenings | `private-screenings` |
| Location & Parking | `location` |
| Contact | `contact` |
| Admin Panel | `admin/` |

---

## Tech Stack

- PHP 8.0+
- MySQL 5.7+ (cPanel-managed)
- Vanilla HTML / CSS / JavaScript — no frameworks
- Fonts: Oswald + Playfair Display + Lato (Google Fonts)
- Contact form: Formspree (`xaqkjakn`)
- Deployed via GitHub Actions FTP to parityrfp.com

---

## Admin Panel

Lives at `/admin` — lets staff manage movies, showtimes, events, and senior showings.

**Default credentials:** `admin` / `changeme123`
**Change the password immediately on first login.**

---

## Database Setup

1. In cPanel on parityrfp.com, create a MySQL database (e.g. `aslanadv_alextheatre`) and a user with full privileges
2. Import `database/schema.sql` then `database/seed.sql` via phpMyAdmin
3. Copy `config/database.example.php` to `config/database.php` on the server and fill in the real credentials

---

## Deployment (Production)

Auto-deploys to parityrfp.com on every push to `master` via GitHub Actions.

### Required GitHub Secrets

Go to: **https://github.com/2KTay/alex-movie-theater/settings/secrets/actions**
Click **New repository secret** for each:

| Secret | Value |
|--------|-------|
| `FTP_HOST` | `72.167.208.71` |
| `FTP_USERNAME` | `DW@parityrfp.com` |
| `FTP_PASSWORD` | *(FTP password — find in cPanel on parityrfp.com under FTP Accounts)* |

Once all three secrets are added, push any commit to `master` to trigger a deploy.

---

## GitHub Pages (Static Preview)

Served from the `docs/` folder. Enable once at:
**https://github.com/2KTay/alex-movie-theater/settings/pages**
→ Source: `master` branch, `/docs` folder → Save

Updates automatically on every push to `master`.

---

## Project Structure

```
alex-movie-theater/
├── .github/workflows/deploy.yml   — GitHub Actions FTP deploy
├── public/                        — Web root (PHP site)
│   ├── index.php
│   ├── senior-movie.php
│   ├── concessions.php
│   ├── events.php
│   ├── private-screenings.php
│   ├── location.php
│   ├── contact.php
│   ├── tickets.php
│   ├── admin/                     — Admin panel (CSRF + session auth)
│   └── assets/
│       ├── css/main.css
│       ├── js/main.js
│       └── images/
├── docs/                          — Static GitHub Pages version
│   ├── index.html
│   ├── admin/index.html
│   └── assets/
├── config/
│   ├── config.php                 — Site constants (URL, phone, form links)
│   ├── database.example.php
│   └── database.php               — gitignored, created on server
├── database/
│   ├── schema.sql
│   └── seed.sql
├── includes/
│   ├── helpers.php
│   ├── Database.php
│   ├── AdminAuth.php
│   └── *Repo.php
└── templates/
    ├── header.php
    └── footer.php
```

---

## Client Info

- **Theatre:** Alex Movie Theatre
- **Address:** 407 N. Harrison Street, Alexandria, IN 46001
- **Phone:** (765) 620-9093
- **Contact Form:** Formspree `xaqkjakn`
- **Admin default login:** `admin` / `changeme123`
