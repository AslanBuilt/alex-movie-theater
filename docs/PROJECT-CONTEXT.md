# Alex Theater — Master Project Context
**Last updated:** 2026-07-10
**Repo:** https://github.com/2KTay/alex-movie-theater
**Live site:** https://parityrfp.com/cs/alex-movie-theater/
**Status:** Demo-ready, pre-launch

---

## 1. What This Project Is

A full operating system for The Alex Theater — an independent movie theater in Alexandria, Indiana. Built by Aslan Advisors.

This is not just a website. It is a complete theater management platform:
- Public-facing ticket and concession purchasing
- QR code ticket generation and door scanning
- Customer self-serve ordering kiosk
- Employee point-of-sale register
- Order fulfillment display (kitchen-screen style)
- Admin panel with movies, showtimes, concessions, events, reports
- Inventory management with reorder alerts

**Demo flow:** Public site → buy tickets → QR codes → scan at door → concession kiosk → fulfillment board → admin reports

---

## 2. Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.0+ vanilla — no frameworks, no Composer |
| Database | MySQL 5.7+ |
| Frontend | HTML, CSS, vanilla JavaScript — no React, no Vue, no npm |
| Hosting | GoDaddy cPanel on parityrfp.com (staging) |
| Deployment | GitHub Actions FTP deploy |
| Payments | Stripe (cURL, no SDK) — currently test mode |
| Forms | Formspree |
| Email | PHP mail() fallback — SendGrid integration built, needs API key |
| QR codes | Custom PHP library (GD extension required — enabled in cPanel) |
| Charts | Chart.js 4.5.0 + chartjs-plugin-datalabels 2.2.0 from cdnjs |

**Non-negotiable constraints:**
- No Composer
- No npm
- No React or Vue
- No external databases
- No new hosting accounts
- Everything in one MySQL database (name in GitHub secrets / local project notes — not documented here)

---

## 3. Repository Structure

```
public/                    ← web root (deploy.yml FTPs this to server)
  index.php                ← public homepage
  movie.php                ← movie detail + ticket purchase
  tickets.php              ← now showing list
  checkin.php              ← QR ticket check-in kiosk
  fulfillment.php          ← order fulfillment display
  senior-movie.php         ← senior showings page
  events.php               ← events page
  concessions.php          ← concessions menu
  checkout.php / order.php / confirmation.php  ← online purchase flow
  kiosk/
    index.php              ← customer self-serve ordering kiosk (5-screen flow)
  pos/
    index.php              ← employee POS product grid (post-login)
    login.php              ← employee PIN login
  admin/
    index.php              ← admin dashboard
    movies.php / movie-edit.php / movie-delete.php
    showtimes.php / showtime-edit.php / showtime-delete.php / showtime-scheduler.php
    concessions.php / concession-edit.php / concession-delete.php / concession-options.php / concession-stock.php
    events.php / event-edit.php / event-delete.php
    senior-showings.php / senior-showing-edit.php / senior-showing-delete.php
    reports.php            ← sales + inventory reports with Chart.js
    transactions.php / transaction-view.php / transaction-void.php
    occupancy.php          ← fire marshal occupancy report
    orders.php
    api/
      movies-reorder.php / concessions-reorder.php  ← drag-to-reorder endpoints
      reports-data.php     ← single JSON endpoint for all 6 charts
  api/
    webhooks/stripe.php    ← Stripe webhook handler (payment → QR mint)
    kiosk-checkout.php     ← kiosk purchase API (no auth, rate limited)
    pos-checkout.php       ← POS purchase API (requires PIN session)
    checkin.php            ← QR token validation (atomic UPDATE)
    fulfillment.php        ← fulfillment board data API
    cart.php / checkout.php / order-customer.php / pos-stock.php
  includes/
    config.php — NOTE: does not exist here; the real config lives at public/config/config.php (see below)
    Database.php           ← PDO singleton; getInstance() returns PDO directly
    AdminAuth.php          ← admin session auth — instance class, not static
    PosAuth.php            ← employee PIN auth + verifyPinOnly()
    Mailer.php             ← email (SendGrid preferred, PHP mail() fallback)
    QrCode.php              ← QR generation via GD
    TransactionRepo.php     ← transaction queries, daily order number assignment
    MovieRepo.php / ConcessionRepo.php / ShowtimeRepo.php / EventRepo.php / InventoryRepo.php / TicketTokenRepo.php
    RateLimiter.php / StripeService.php / OrderEmail.php / helpers.php
  assets/
    js/  main.js, kiosk.js, admin-movies.js, admin-charts.js, admin-upload.js
    css/ main.css, kiosk.css, pos.css, fulfillment.css, admin.css, admin-print.css, checkin.css
  uploads/
    movies/ (posters live in assets/images/posters/, not uploads/) / concessions/ / events/
config/
  config.php — the real, live config is public/config/config.php (gitignored); example files (database.example.php, stripe.example.php, sendgrid.example.php) are committed for reference
database/
  schema.sql               ← reference schema (may drift from live — verify with SHOW COLUMNS before trusting)
  seed.sql
scripts/
  db-migrate.php           ← idempotent migration runner (auto-runs on every deploy)
.github/workflows/deploy.yml  ← FTP deploy + DB migrate + retire old files
```

Note: this tree is curated for orientation, not exhaustive — `find public -name "*.php"` for the full list.

---

## 4. Database

**Connection:** `Database::getInstance()` returns a PDO object directly — not a wrapper. Always call it as `$pdo = Database::getInstance()`.

**Key tables:**

| Table | Purpose |
|-------|---------|
| `movies` | Movie catalog. Status: now_showing/coming_soon/archived. `sort_order` controls public display order. `poster_path` (not `poster_url`). |
| `showtimes` | Individual showtime instances with date, time, capacity, tickets_sold |
| `transactions` | Every purchase. `source_channel`: website/staff_register/kiosk. `daily_order_number` resets midnight. `gateway_ref` (Stripe reference) exists. |
| `transaction_items` | Line items. `item_type`: ticket/concession. `selected_option`: Adult/Child for tickets, flavor/brand for concessions. |
| `ticket_tokens` | One row per physical ticket. `token_status`: valid/used/voided. Atomic UPDATE for check-in. |
| `concessions` | Products. Has `stock_quantity` and `reorder_point`. |
| `concession_options` | Product variants (flavors, candy brands) |
| `events` | Theater events with `image_path`, `event_date`, `event_time` |
| `senior_showings` | Senior discount showings. Columns: `showing_date`, `showing_time` (varchar). |
| `admin_users` | Admin accounts. Password via `password_hash()`/`password_verify()` |
| `employees` | POS staff. PIN via `password_hash()`/`password_verify()`. `failed_attempts`, `locked_until`. |
| `admin_login_attempts` | IP-based lockout for admin login (5 attempts / 10 min) |
| `daily_order_counters` | Counter per date for daily_order_number assignment |
| `inventory_log` | Every stock change with reason and transaction reference |
| `password_reset_tokens` | Single-use tokens for admin password reset |
| `webhook_events` | Stripe webhook idempotency tracking |
| `concession_orders` | Legacy/supporting concession order table — check schema.sql before assuming its role |

**Critical column names (verified against schema.sql — do not assume):**
- `poster_path` NOT `poster_url`
- `senior_showings.showing_date` / `showing_time` NOT `show_date` / `show_time`
- `transactions.gateway_ref` DOES exist (added this session — do not assume it's missing)
- `AdminAuth` is an instance class: `(new AdminAuth($db))->requireAuth()` NOT static

---

## 5. Operational Screens

| Screen | URL | Access | Purpose |
|--------|-----|--------|---------|
| Public website | `/` | Public | Homepage, now showing, events |
| Movie + tickets | `/movie.php?id=N` | Public | Showtimes, Adult/Child ticket purchase |
| Ticket check-in | `/checkin.php` | No login | QR scan at theater entrance |
| Customer kiosk | `/kiosk/` | No login | Self-serve concession + ticket ordering |
| Employee POS | `/pos/` | PIN login | Walk-up order register |
| Fulfillment board | `/fulfillment.php` | No login | Back-of-house order queue |
| Admin panel | `/admin/` | Password login | Full management |

**Live base URL:** `https://parityrfp.com/cs/alex-movie-theater/`

---

## 6. Credentials & Configuration

Credential **values** are intentionally not in this document (it's committed to a public repo). Locations only:
- Admin login, employee PIN, DB name: see local project notes (`7102026Context` folder) or ask the project owner.
- Stripe: test mode currently. Webhook registered at `/api/webhooks/stripe.php`. Secret in `config/stripe.php` (gitignored).
- Stripe test card (public Stripe documentation value, not a secret): `4242 4242 4242 4242` / any future date / any CVC
- SendGrid: integration built in `Mailer.php`. Needs `SENDGRID_API_KEY` + `MAIL_FROM` GitHub secrets. Falls back to PHP `mail()` without them (works but lands in spam).
- FTP: credentials live in GitHub Actions secrets only (`FTP_USERNAME`, `FTP_PASSWORD`, `FTP_HOST`) — never in code.
- DB: credentials in `public/config/config.php` (gitignored) / GitHub secrets.

---

## 7. Key Architecture Decisions

**Ticket pricing — single source of truth:**
`TICKET_PRICE_ADULT` and `TICKET_PRICE_CHILD` defined in `public/config/config.php`. Never hardcode these anywhere else. Helper: `ticketPrice()` / `normalizeTicketAge()` in `helpers.php`.

**QR codes:**
- Generated server-side using GD extension (must be enabled in cPanel — was a major bug)
- Tokens are opaque random bytes — encode nothing
- Check-in: one atomic `UPDATE WHERE token_status='valid'` — never SELECT then UPDATE

**Payment flow:**
- Online: Stripe PaymentIntent → webhook confirms → mints QR tokens
- POS: mock card confirm → immediate transaction record → mints QR tokens
- Kiosk: same as POS pattern — no Stripe hardware required

**Fulfillment board:**
- Shows orders with at least one concession item
- Ticket-only orders deliberately excluded — nothing to prepare
- `source_channel` distinguishes website/staff_register/kiosk, shown as colored badges
- Filter tabs (`data-filter` on `.filter-tab`, `data-channel` on `.order-card`) — matching values: `all`, `website`, `staff_register`, `kiosk`

**Daily order numbers:**
- `daily_order_counters` table, resets at midnight
- Assigned in: Stripe webhook, pos-checkout.php, kiosk-checkout.php (all three wired as of this session)
- Falls back to `transaction_ref` if NULL (historical orders)

**Admin auth:**
- Instance class: `$auth = new AdminAuth($db); $auth->requireAuth();`
- 8-hour session timeout (`ADMIN_SESSION_TTL`, defined in `public/config/config.php`)
- IP-based lockout in `admin_login_attempts` table (5 attempts / 10 min)
- Known issue: relative redirect in `requireAuth()` can break for `admin/api/*.php` files depending on nesting — a JS content-type sniff workaround exists in `admin-charts.js`; the underlying redirect was not fixed as of this session.

**ConcessionRepo::getAll()** sorts by category first — drag-to-reorder is scoped within-category only (fixed this session; cross-category drags are blocked in JS with visual feedback).

---

## 8. Deployment

**Every push to `origin/master` triggers:**
1. PHP lint gate (aborts before any upload on a syntax error)
2. Generation of `database.php` / `stripe.php` / `sendgrid.php` from GitHub secrets (skips safely if unset)
3. FTP sync of `public/` to web root (uploads/overwrites only — **never deletes** removed files)
4. Explicit `DELE` of a hardcoded list of retired diagnostic files (deploy.yml) — anything removed from the repo that was ever live must be added to this list or it lingers reachable forever
5. `scripts/db-migrate.php`: uploaded, run once via HTTP, result captured, then deleted — idempotent, auto-runs every deploy, **not a separate manual step**
6. Deploy fails loudly (exit 1) if the migration or DB init step returns non-2xx

**Verify a deploy landed:**
```bash
curl -s -o /dev/null -w "%{http_code}" "https://parityrfp.com/cs/alex-movie-theater/<path>"
```
Poll `https://api.github.com/repos/2KTay/alex-movie-theater/actions/runs?per_page=1` for `status`/`conclusion` rather than assuming a push finished deploying.

**Standing rule — non-negotiable:**
Nothing goes to production without explicit human go-ahead:
- No `git push` without approval
- No DB migration on production without approval (though it auto-runs on every deploy regardless — this rule governs *triggering a deploy*, not a separate migration step)
- No FTP deploy without approval
- Show diffs before any production action

**One-shot diagnostic scripts:**
Never commit a diagnostic/inspector script to the repo — anything committed and pushed goes live and the FTP deploy never deletes it later. Write locally, upload via a single manual FTP command, run once, delete immediately via FTP, and add its filename to deploy.yml's retired-files list as a backstop. (Two incidents this session: `setup.php`, `_moviecheck.php` — both were forgotten diagnostic scripts left live.)

---

## 9. What's Built (Verified This Session)

- [x] Public website — homepage, movie carousel, now showing, concessions, events, senior page, location/contact
- [x] Ticket purchase — Adult/Child pricing, Stripe checkout, QR code generation
- [x] Email confirmation — sends via PHP mail() (lands in spam without SendGrid configured)
- [x] QR check-in kiosk
- [x] Customer self-serve kiosk — 5-screen flow, tickets + concessions, PIN-gated cash, QR output — live and returning 200
- [x] Employee POS — PIN login, product grid, cart, cash/card checkout
- [x] Order fulfillment board — filter tabs (fixed this session), time escalation, mark complete
- [x] Daily order numbers — wired into webhook, POS, and kiosk this session
- [x] Identity banners — on kiosk, POS, checkin, fulfillment (added this session)
- [x] Admin: movies + combined showtime form + drag-to-reorder
- [x] Admin: concessions + image upload + drag-to-reorder (within-category, fixed this session)
- [x] Admin: events + image upload + `event_time` field (added this session)
- [x] Admin: senior showings add/edit
- [x] Admin: 6 Chart.js reports with single JSON endpoint
- [x] Admin: transactions, occupancy pages exist (not deeply audited this session)
- [x] Admin: IP-based login lockout + 8-hour session timeout
- [x] Inventory management with reorder alerts
- [x] Formspree contact + rental forms
- [x] Kiosk concession-grid scroll fix — `body{overflow:hidden}` was blocking scroll on menu screens with many items; added `overflow-y:auto` to `.kiosk-screen.show`
- [x] Movie poster upload — real root cause found and fixed: the `fileinfo` PHP extension is missing on this host, so `finfo_open()` threw a fatal error on every upload attempt regardless of file size/type. Replaced with `getimagesize()` (core PHP, no extension dependency, still reads real file bytes rather than trusting client-supplied MIME type)
- [x] Admin reports — 8 charts total: revenue this-week-vs-last-week and this-month-vs-last-month (mutually exclusive, toggled by the range selector — 6 visible at once in Sales Report), revenue by category, top 5 movies, top 5 concessions, daily transactions by channel, tickets sold vs. scanned, plus inventory stock levels on its own tab. Category/movies/concessions are genuinely range-scoped (query bound to the selected date range); daily-transactions and tickets-scanned are deliberately fixed-period operational metrics, same convention as the inventory chart — see reports-data.php's top-of-file comment
- [x] Reports chart titles reflect the selected range for the 3 range-scoped charts (e.g. "Top 5 Movies (Tickets Sold) — This Week")
- [x] Revenue-by-category donut — no-sales categories no longer render overlapping labels (label suppressed for $0 slices; still shown in tooltip + data table)
- [x] Print Report — canvas-to-image snapshot triggered from the button click (not the native `beforeprint` event, which raced against the browser's paint and produced blank charts). Charts print with their on-screen dark-background colors baked in rather than being recolored for print — recoloring live Chart.js instances via mutate-then-`update()` caused two confirmed crashes (a stack overflow in Chart.js internals, and a "cannot convert object to primitive" thrown from the `money()` formatter) — full black-on-white recolor deferred, see open items
- [x] Deploy pipeline — `db-init.php` and `db-migrate.php` HTTP calls were both intermittently blocked with HTTP 415 by this host's WAF; added a browser User-Agent header to both, plus `continue-on-error` on the init health-check step so a blocked init check can never again silently skip the actual schema migration

---

## 10. What's Still Open

| Item | Priority | Notes |
|------|----------|-------|
| SendGrid email | High | Integration built, needs API key + verified sender in GitHub secrets |
| Stripe live mode | Pre-launch | Switch test → live keys, register live webhook |
| Concession photos | Medium | Most products still missing real photos |
| FTP credential rotation | Security | See local project notes — not detailed here |
| `.htaccess` Apache 2.4 syntax | Fixed this session | Was `Deny from all` (2.2), now `Require all denied` |
| AdminAuth relative redirect for `admin/api/*.php` | Open | Workaround in JS, root cause not fixed |
| schema.sql drift | Technical debt | May not perfectly match live DB — verify with `SHOW COLUMNS` before trusting |
| Stale worktree/branch cleanup | Housekeeping | Multiple `fix/kiosk-agent-*` and `worktree-agent-*` branches from this session's parallel-agent work should be deleted once confirmed merged |
| `fix/location-parking-layout` branch | Review needed | Unmerged — moves Parking Options into the right column on the location page. Not reviewed or deployed; needs a look before merging to master (kept separate from the kiosk/reports fixes shipped this session). |
| Day-grid showtime scheduler | Next session | Owner needs multiple independent times per day, different per day, via a date-range grid with per-day "+ Add Time". Full brief (UI, JS, CSS, backend endpoint sketch) written up; not started. Real schema: `showtimes` has no `screen` column (screen lives on `movies`) — confirmed via schema.sql, do not add one to the INSERT. |
| Print report — full black-on-white recolor | Deferred | Current version prints with the on-screen dark background preserved (legible, just not traditional monochrome). A true recolor requires rendering print-only chart instances on hidden canvases rather than mutating the live ones (which is what caused the two print crashes this session) — real rework, touches all chart render functions. |
| `db-migrate.php` WAF 415 | Intermittent | User-Agent header fixed it once, then it recurred on a later deploy with the identical fast-fail signature — the WAF's blocking isn't fully deterministic. Doesn't currently block anything (no pending schema migrations), but deploys should be spot-checked for it. |
| Child ticket pricing — reported missing | Unreproduced | Renato saw only the $5 adult price during the July 7 demo. Verified 2026-07-16: `movie.php` already renders both Adult and Child cards live for movie IDs 1, 2, 5 (current now-showing lineup) — code and deploy are correct today. If it recurs, get the specific movie ID and whether it uses dated showtimes or legacy label/times — the legacy branch (`movie.php`, `elseif (!empty($legShowtimes))`) has no ticket-type selector at all and may be what was seen. |
| POS "requires incognito" cookie issue | Unreproduced | Verified 2026-07-16: admin and POS already use separate session cookies (`ALEX_ADMIN_SESS` / `ALEX_POS_SESS`), confirmed via live `Set-Cookie` headers on both login pages — no code conflict exists. If incognito is still needed, it's most likely a leftover `PHPSESSID` cookie from before this separation existed, still cached in the affected browser profile. Fix is clearing parityrfp.com cookies in that browser, not a code change. |

---

## 11. Aslan Skills

Skills are at `C:\Users\Aslan\Desktop\aslan-skills-master\aslan-skills-master\skills\` (note the nested folder — a common path mistake).

Load by reading files directly:
```bash
cat "C:\Users\Aslan\Desktop\aslan-skills-master\aslan-skills-master\skills\<skill-name>\SKILL.md"
```

Key skills for this project: `aslan`, `backend-php-standards`, `backend-php-security`, `backend-commerce-concurrency`, `backend-stripe`, `backend-notifications`, `client-transactional-email`, `frontend-form-patterns`, `frontend-feedback-system`, `frontend-file-upload`, `infra-deploy-manual`, `security-hardening`, `quality-production-readiness`, `quality-testing-validation`.

- `agentic-qa-loop` — QA loop, engineer/reviewer pattern,
  danger checks, repeat offender rule. Load at start of every session.

---

## 12. Known Gotchas (things that burned us this session)

1. **GD extension** — must be enabled in cPanel for QR codes to render.
2. **Config path depth** — files in `public/kiosk/` or `public/pos/` use `__DIR__ . '/../config/config.php'`. Root-level `public/` files use `__DIR__ . '/config/config.php'`. Never `/../../public/config/config.php` — that path only exists in the git checkout, not on the deployed server (deploy.yml flattens `public/`'s contents onto the web root). This exact bug caused `/kiosk/` to 500 in production this session.
3. **FTP deploy never deletes** — removing a file from the repo does NOT remove it from the live server. Add it to deploy.yml's retired-files list (uses GitHub secrets, no plaintext creds) or it stays reachable indefinitely.
4. **Diagnostic scripts must never be committed** — they deploy to production and never get cleaned up automatically. Two incidents this session (`setup.php`, `_moviecheck.php`) both came from this.
5. **AdminAuth is instance, not static** — `(new AdminAuth($db))->requireAuth()`.
6. **`Database::getInstance()` returns PDO directly** — not a wrapper.
7. **`gateway_ref` exists on `transactions`** — don't assume otherwise.
8. **Git worktree isolation is not automatic in every case** — one agent this session had its `git checkout` land in the main working directory instead of its assigned isolated worktree, nearly causing a wrong-branch commit. Verify `git worktree list` and `git branch --show-current` after dispatching parallel agents, don't just trust the isolation flag.
9. **Sequential shell commands without `&&` keep running after a failure** — a failed `git checkout` in a multi-line script did not stop the following `git merge` commands from running against the wrong branch. Chain with `&&` or check exit codes explicitly when a later command depends on an earlier one succeeding.
10. **Merge order matters** — merge fix branches into the feature branch first, verify the combined log, then merge the feature branch into master. Don't let a broken checkout collapse that into one accidental step.

---

## 13. Next Session Checklist

```bash
# 1. Check current state
git status --short
git log --oneline -5
git log origin/master..HEAD --oneline

# 2. Check live site is up
curl -s -o /dev/null -w "%{http_code}" "https://parityrfp.com/cs/alex-movie-theater/"

# 3. Read project rules
cat CLAUDE.md
cat .claude/project-rules.md
```

Then read only the files relevant to your task. Don't load everything — skills and this document are the map, not a substitute for reading the specific code you're about to change.
