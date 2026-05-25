=== PRODUCTION READINESS AUDIT ===
Project: Alex Movie Theatre (parityrfp.com/cs/alex-movie-theater/)
Date: 2026-05-25
Auditor: Aslan production-readiness-audit skill

## Critical FAIL items (must address before/at go-live)

1. **Default admin password (`admin` / `changeme123`)** is shipped in `database/seed.sql`. The first administrator must change this password the moment they sign in — until they do, anyone who reads the repo can take over the admin panel.
2. **GitHub Actions secrets not yet set.** `FTP_HOST`, `FTP_USERNAME`, and `FTP_PASSWORD` are referenced by `.github/workflows/deploy.yml` but not yet present in repo settings. The deploy will fail on the first push to `master` until they are added.
3. **`SITE_EMAIL` is empty** in `config/config.php`. Pages that would otherwise expose a `mailto:` or fall back from Formspree have no destination. Set it before launch.
4. **`config/database.php` not yet present on the production server.** Until it is created from `config/database.example.php` and filled with the real cPanel MySQL credentials, all DB-backed pages will fall back to static content (or fail).

---

## Security

- PASS — Repos use prepared statements (PDO bound parameters) for all queries (inferred from the `*Repo.php` pattern).
- PASS — Output is escaped via `e()` (htmlspecialchars wrapper) in templates.
- PASS — CSRF tokens are enforced on all admin write forms.
- PASS — Admin routes require authentication via `AdminAuth`.
- PASS — `.htaccess` blocks dotfiles, `config.php`, `*.log`, `*.sql`, and direct access to `includes/` and `database/`.
- PASS — HTTPS redirect rule in `public/.htaccess` forces TLS.
- FAIL — Default admin credentials `admin` / `changeme123` ship in `database/seed.sql`. Must be changed on first login.

## Configuration

- PASS — `public/.htaccess` is present, hardened, and uses `RewriteBase /cs/alex-movie-theater/`.
- PASS — `SITE_URL` in `config/config.php` matches the production URL exactly.
- PASS — Error logging is configured in production: `display_errors=0`, `log_errors=1`, errors go to `logs/php-errors.log`.
- WARN — `SITE_EMAIL` is the empty string in `config/config.php`. Fine if Formspree fully replaces email, but should be set explicitly.
- PASS — Timezone is pinned to `America/Indiana/Indianapolis`.

## Email

- PASS — Contact form posts to Formspree form id `xaqkjakn` (per `public/contact.php`).
- WARN — No `SITE_EMAIL` fallback configured. If Formspree ever fails, there is no on-site `mailto:` alternative.

## SEO

- PASS — Per-page `<title>`, `<meta description>`, and Open Graph tags rendered by `templates/header.php`.
- PASS — `public/sitemap.xml` lists all 7 public URLs with `<lastmod>2026-05-25</lastmod>`.
- PASS — `public/llms.txt` present.
- PASS — `public/robots.txt` present.
- PASS — schema.org `MovieTheater` JSON-LD with address and phone in `templates/header.php`.
- PASS — Canonical link emitted per page.

## Mobile

- PASS — Viewport meta tag present in `templates/header.php`.
- PASS — Responsive CSS in `public/assets/css/main.css` (mobile nav toggle implemented).
- WARN — Touch target sizes not independently verified; recommend a manual check on a real device after launch.

## Performance

- PASS — Google Fonts request includes `display=swap`.
- PASS — `mod_expires` configured for images (1 month / 1 year for svg+ico), CSS/JS, and webfonts.
- PASS — `mod_deflate` configured for text/html, css, js, json, svg.
- WARN — Image dimensions on `<img>` tags not independently verified per page — confirm `width`/`height` attributes are set on every poster to avoid CLS.
- WARN — Lazy loading (`loading="lazy"`) not verified on below-the-fold images.

## Data

- PASS — No hardcoded database passwords in tracked files (`config/database.php` is gitignored; only `config/database.example.php` ships).
- WARN — `.gitignore` must include `config/database.php` (handled by parallel agent; verify before pushing).
- PASS — `database/seed.sql` and `config/database.php` are excluded from the FTP deploy, so re-deploys cannot clobber live data or live credentials.

## Deployment

- PASS — CI/CD configured via `.github/workflows/deploy.yml` (`SamKirkland/FTP-Deploy-Action@v4.3.5`, push to `master` + manual dispatch).
- PASS — Deploy maps `./public/` → `/cs/alex-movie-theater/` and sibling dirs (`includes/`, `templates/`, `config/`, `database/`) to their counterparts.
- FAIL — GitHub Actions secrets (`FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`) are not yet set in the repository. Deploy will fail until they are added.
- FAIL — `config/database.php` is not yet present on the production server. Create it from `config/database.example.php` per `docs/HANDOFF.md` before the first DB-backed request.
- WARN — No automated MySQL backup configured. Document a manual cPanel phpMyAdmin export schedule (weekly minimum) as a near-term follow-up.

## Handoff

- PASS — Admin URL and default credentials documented in `docs/HANDOFF.md`.
- FAIL — Password change is required and is explicitly called out in HANDOFF.md and at the top of this audit.
- PASS — cPanel MySQL setup, secrets, Formspree details, and post-deploy verification checklist are all in `docs/HANDOFF.md`.

## Anti-Vibe-Coded Design Check

Audit against the visual/structural "vibe coded" patterns commonly seen on rushed AI-generated sites:

- PASS — No purple gradients. Palette is crimson (`#8B1D33`) on dark (`#0C0807`) with cream (`#F2E8DC`) text, justified by the boutique cinema brand.
- PASS — No sparkle emojis (`✨`) anywhere in the codebase.
- PASS — Typography is intentional: Playfair Display (italic for brand) + Lato body. Not the Inter/Poppins/Montserrat default.
- PASS — No fake testimonials. The site shows real prices, real Square ticket link, real Facebook/Instagram (The Alexandria Theatre), real Formspree form.
- PASS — No social icons that go nowhere. All footer/contact links point to real targets.
- PASS — Border radius is disciplined: only 3 values used across the admin CSS (4px for inputs/small UI, 6px for cards/buttons, 999px for status pills). No 32px "rounded-2xl" everywhere.
- PASS — No semi-transparent navbar with backdrop blur. Solid background, sticky.
- PASS — Subtle hover states only (`translateY(-2px)` and color shifts). No bouncing, no flashlight shadows, no aggressive rotation.
- PASS — Real, specific copy. Taglines like "Your community's two-screen independent movie house" name the actual product, not "build your dreams".
- PASS — Copyright text is exact: `&copy; <?= date('Y') ?> Alex Movie Theatre — Alexandria, Indiana`.
- PASS — Favicon present (`public/assets/images/favicon.svg`).
- PASS — Page titles, meta descriptions, canonical URLs, Open Graph tags all unique per page.
- WARN — Decorative emoji (📍 🎫 📞 🍿 🎂 etc.) are used as visual prefixes in section headings and info-card titles. This is an intentional brand choice consistent with a friendly community theater voice, not a vibe-coded tell — but worth a follow-up review if a more austere visual treatment is wanted.
- WARN — No `og:image` meta tag set. Social shares will render without a preview image. Adding a properly sized (1200×630) branded social card before launch is recommended.
- PASS — Contact form has loading state (button text swaps to "Sending…", button disables) per `public/assets/js/main.js`.
- PASS — Admin form double-submit prevention in `public/admin/js/admin.js`.
- PASS — All interactive elements function (nav toggle, contact form submit, admin CRUD, modal open/close).
