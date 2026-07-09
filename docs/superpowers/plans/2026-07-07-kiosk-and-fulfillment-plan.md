# Customer Kiosk + Fulfillment Board Upgrade — Implementation Plan

> **For agentic workers:** Execute task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/superpowers/specs/2026-07-07-kiosk-and-fulfillment-design.md`
**Date:** 2026-07-07 (revised 2026-07-09 — ticket sales added to scope, cash-confirm PIN gate finalized)
**Status:** Approved (plan phase) — Q1 (daily order number: all channels, midnight reset), Q2 (cash confirm: PIN-gated), Q3 (ticket+concession scope) all answered 2026-07-09. **Not yet executed — awaiting explicit "go."**

**Goal:** Ship a self-serve kiosk at `/kiosk/index.php` selling **both tickets and concessions** (no customer login; a PIN gate applies only to the cash-confirm step) across a 5-screen flow: welcome → menu (concessions + tickets) → cart → payment → confirmation (with QR codes for any ticket lines). Upgrade `/fulfillment.php` so every channel (website, POS, kiosk) shows a short shout-able order number, with better at-a-distance readability and a source filter.

**Architecture:** Kiosk shares `concessions`/`showtimes`/`transactions`/`transaction_items`/`ticket_tokens`/`inventory_log` with the POS and website, via a new unauthenticated `api/kiosk-checkout.php` that mirrors **both** of `api/pos-checkout.php`'s atomic claim patterns — concession stock decrement AND showtime-capacity `SELECT ... FOR UPDATE` (never literally reusing the auth-gated POS endpoint — see spec's engineering-decision note). A new `daily_order_number` column, assigned via an atomic per-day counter table (resets at midnight server time), gets wired into all three paths that flip a transaction to `paid` (Stripe webhook, POS checkout, kiosk checkout) so "Order 47" means the same thing everywhere. Cash payments at the kiosk require a POS employee PIN, verified server-side via a new session-free primitive extracted from `PosAuth` (`verifyPinOnly()`) — `PosAuth::login()` itself is untouched.

**Decomposition rationale:** The load-bearing decision is the daily-order-number counter mechanism — every other visible feature (kiosk confirmation screen, fulfillment board display) depends on it existing and being race-safe. So Phase 1 builds and wires that first, in isolation, before any kiosk UI exists, using the two already-live checkout paths (webhook + POS) as the proving ground — if the counter is wrong, it's wrong in a place we can already test today. Phase 2 (kiosk-checkout API) is next because it's the highest-risk *new* code (an unauthenticated money-moving endpoint) and the kiosk UI is worthless without it. Phase 3 (kiosk front-end) is pure UI risk, not logic risk, so it comes after the API it depends on is proven. Phase 4 (fulfillment board) comes last because it's cosmetic/read-only and depends on real `daily_order_number` values existing to display.

**Tech Stack:** PHP 8.3 vanilla, MySQL (InnoDB), HTML/CSS/vanilla JS, GitHub Actions FTP deploy on push to `master`. No Composer, no npm, no frameworks.

**Testing approach:** Manual user-workflow verification per task (this project has no automated test suite or Playwright harness), `php -l` on every touched file before commit, a concurrency script for the atomic counter, and a full end-to-end walkthrough in Phase 5.

---

## Phase 1: Daily order number (schema + atomic counter + wire into existing channels)

### Task 1: Add the schema for daily order numbers

**Why this matters:** Tim's own words were "ORDER 47 READY" — the board currently shows a hex transaction ref nobody can shout across a room. This is the foundation every other visible piece of this build displays.

**Implements:** Spec §"The order-number gap" · no screen (schema/infra task)

**Files:**
- Modify: `database/schema.sql` — add `daily_order_number SMALLINT UNSIGNED NULL AFTER transaction_ref` to the `transactions` table; add a new `daily_order_counters` table.
- Create: `scripts/db-migrate.php` — add a migration step (this project runs `db-migrate.php` via the existing CI pipeline on every deploy per `.github/workflows/deploy.yml`; check the file for the established idempotent-migration pattern — likely `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` guarded by an information_schema check, since this host's MySQL may not support `IF NOT EXISTS` on `ADD COLUMN` directly).

**Schema:**
```sql
ALTER TABLE transactions
  ADD COLUMN daily_order_number SMALLINT UNSIGNED NULL AFTER transaction_ref;

CREATE TABLE daily_order_counters (
    order_date DATE NOT NULL PRIMARY KEY,
    next_number SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Acceptance (EARS):**
- *Ubiquitous*: The `transactions` table shall have a nullable `daily_order_number` column.
- *Ubiquitous*: A `daily_order_counters` table shall exist, keyed by date.

**Verification:**
- [ ] `db-migrate.php` runs clean against a copy of the live schema with no errors, and is safe to re-run (idempotent) — this project's CI runs it on every deploy, so a non-idempotent migration breaks every future deploy, not just this one.
- [ ] `SHOW COLUMNS FROM transactions LIKE 'daily_order_number'` returns the new column after deploy.

**parallel:** false

- [ ] **Step 1: Add the `ALTER TABLE` and `CREATE TABLE` to `database/schema.sql`** (for fresh installs)
- [ ] **Step 2: Add the guarded migration to `scripts/db-migrate.php`**, matching whatever idempotency pattern that file already uses for prior column additions (read the file first — do not write a new pattern)

---

### Task 2: Add `TransactionRepo::nextDailyOrderNumber()`

**Why this matters:** Mechanical — enables every checkout path (Task 3) to get a race-safe number without each one reimplementing the locking logic.

**Implements:** Spec §"The order-number gap" · no screen (backend task)

**Files:**
- Modify: `public/includes/TransactionRepo.php` — add one static method.

**Method:**
```php
/** Atomic per-calendar-day sequence, race-safe via MySQL's upsert+LAST_INSERT_ID() idiom. */
public static function nextDailyOrderNumber(): int
{
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare(
        "INSERT INTO daily_order_counters (order_date, next_number)
         VALUES (CURDATE(), 1)
         ON DUPLICATE KEY UPDATE next_number = LAST_INSERT_ID(next_number + 1)"
    );
    $stmt->execute();
    return (int)$pdo->lastInsertId();
}
```

**Acceptance (EARS):**
- *Ubiquitous*: The system shall return a strictly-increasing integer per calendar day, starting at 1.
- *Event-driven*: When two callers invoke this simultaneously, the system shall return two distinct consecutive integers, never a duplicate.

**Verification:**
- [ ] Concurrency test (not a click-through, per `backend-commerce-concurrency`): fire 10 simultaneous calls via a small parallel PHP/curl script against a one-shot test endpoint wrapping this method; assert the 10 returned values are exactly `{1..10}` with no duplicates and no gaps.
- [ ] Call it twice on two different `CURDATE()` values (mock via a temporary override or just check the logic manually) — confirm the sequence restarts at 1 for a new date without manual intervention.

**parallel:** false

---

### Task 3: Wire the counter into the two existing paid-transition points

**Why this matters:** Without this, the counter exists but nothing populates it — website and POS orders would still show blank/null order numbers on the fulfillment board while only kiosk orders (built later) have one, which is the exact inconsistency Taemoor said to avoid ("Order 47 should mean the same thing everywhere").

**Implements:** Spec §"Q1 answered — all channels" · no screen (backend task)

**Files:**
- Modify: `public/api/webhooks/stripe.php` — around line 101-103, immediately after `TransactionRepo::transitionFromPending((int)$txn['id'], 'paid')` succeeds, call `TransactionRepo::assignDailyOrderNumber((int)$txn['id'])` (new method, see below) before the inventory-claiming loop.
- Modify: `public/api/pos-checkout.php` — around line 248-265, compute `$dailyOrderNumber = TransactionRepo::nextDailyOrderNumber();` before the `INSERT INTO transactions` and add `daily_order_number` to the insert's column list and bound params.
- Modify: `public/includes/TransactionRepo.php` — add `assignDailyOrderNumber(int $id): int` (calls `nextDailyOrderNumber()` then `UPDATE transactions SET daily_order_number = :n WHERE id = :id`, returns the number) for the webhook path, which doesn't have `daily_order_number` at INSERT time (the transaction row already exists as `pending` before payment succeeds).

**Acceptance (EARS):**
- *Event-driven*: When a Stripe webhook transitions a transaction from `pending` to `paid`, the system shall assign it the next daily order number.
- *Event-driven*: When a POS checkout completes, the system shall assign the transaction a daily order number at creation.
- *Ubiquitous*: A `pending` or `voided` transaction shall never receive a daily order number (only ones that actually reach `paid` consume a slot — keeps numbers meaningful, matches how a real concession stand only numbers orders that are actually being made).

**Verification:**
- [ ] Purchase workflow: buy a ticket on the public site with a real test card → confirm `transactions.daily_order_number` is populated after the webhook fires (one-shot DB check, same pattern as prior sessions).
- [ ] POS workflow: ring up a walk-up sale on `/pos/` → confirm the new transaction has a `daily_order_number` immediately (no webhook lag).
- [ ] Two transactions completed back-to-back (one POS, one website) get consecutive numbers regardless of channel — proves the counter is shared, not per-channel.

**parallel:** false

---

## Phase 2: Kiosk checkout API

### Task 4: Extract `PosAuth::verifyPinOnly()` — session-free PIN check

**Why this matters:** The kiosk's cash-confirm step needs to verify a POS employee PIN, but the kiosk has no `ALEX_POS_SESS` session and must not get one (customer self-serve, no login). `PosAuth::login()` fuses PIN verification with session mutation (`session_regenerate_id`, `$_SESSION[...]` writes at lines 151-155) — this task pulls out just the verification+lockout half so it's callable from an unauthenticated page.

**Implements:** Spec §"PIN verification without a POS session" (Revision 2026-07-09)

**Files:**
- Modify: `public/includes/PosAuth.php` — add a new public method `verifyPinOnly(string $pin): array` that does exactly what `login()` does at lines 111-148 and 183-202 (`password_verify()` loop against active employees, `locked_until` check, `registerFailure()` on no-match) but returns `['ok' => true]` / `['ok' => false, 'error' => ..., 'locked' => bool]` **without** touching `session_regenerate_id()` or `$_SESSION`. On success, still call `resetAttempts()` (that's DB state, not session state, and should reset regardless of which flow verified the PIN). `login()` itself is not modified — this is purely additive.

**Acceptance (EARS):**
- *Ubiquitous*: `verifyPinOnly()` shall never call `session_regenerate_id()` or write to `$_SESSION`.
- *Event-driven*: When a correct, unlocked PIN is passed, the system shall return `['ok' => true]` and reset that employee's `failed_attempts`.
- *Event-driven*: When an incorrect PIN is passed, the system shall register a failure the same way `login()` does (shared lockout state — a kiosk PIN brute-force attempt locks the same account a POS login attempt would).
- *Event-driven*: When the matching employee is currently locked, the system shall return `['ok' => false, 'locked' => true]` without re-registering a failure.

**Verification:**
- [ ] Unit-level check: call `verifyPinOnly()` with a known-good PIN from a plain PHP script with no session started — confirm no warning/error from missing session and `['ok' => true]` returned.
- [ ] Call with a wrong PIN 5 times → 6th call (even correct PIN) returns `locked: true`, matching `login()`'s existing threshold.
- [ ] `php -l public/includes/PosAuth.php` passes.

**parallel:** false

---

### Task 5: Build `api/kiosk-checkout.php`

**Why this matters:** This is the actual money-moving logic behind the whole kiosk — customers can only self-serve if a purchase (tickets and/or concessions) can be recorded without an employee session. Reuses everything already proven safe in `pos-checkout.php`, just without the auth gate.

**Implements:** Spec §"Engineering decision: a separate kiosk-checkout.php", §"Revision 2026-07-09" · `SCREEN-kiosk-payment`, `SCREEN-kiosk-confirmation`

**Files:**
- Create: `public/api/kiosk-checkout.php` — fork of `public/api/pos-checkout.php` (read it first; don't re-derive the stock-claim or capacity-claim logic from scratch). Carries over **both** line-item branches from `pos-checkout.php` (concession stock decrement AND `kind==='ticket'` handling — `showtime_id`/`age` fields, `ksort()`-ordered `SELECT ... FOR UPDATE` per showtime, `available_tickets - tickets_sold` capacity check, server-side `ticketPrice()`). Differences from `pos-checkout.php`:
  - No `PosAuth` require/bootstrap — kiosk has no customer-facing login, by spec.
  - No CSRF check — no session exists to bind a token to (same as `api/checkin.php` and `api/fulfillment.php`).
  - Rate-limited per IP via `RateLimiter::allow('kiosk-checkout:' . RateLimiter::clientIp(), 20, 60)` (mirrors `pos-checkout.php`'s own rate limit).
  - `'channel' => 'kiosk'` hardcoded (not client-supplied) for `source_channel`; `type` computed as `'combo'` when the cart has both a ticket and a concession line, else `'ticket'`/`'concession'` (matches the existing `transactions.type` enum — no schema change).
  - Calls `TransactionRepo::nextDailyOrderNumber()` before the `INSERT`, same as the POS fork in Task 3.
  - Post-commit, best-effort `TicketTokenRepo::generateForTransaction()` call if the order had any ticket lines (mirrors `pos-checkout.php:338-344` exactly — failure logged, not fatal).
  - **Cash confirmation (Q2, decided 2026-07-09: PIN-gated):** request body includes a `pin` field when `method === 'cash'`. Before the DB transaction opens, call `PosAuth::verifyPinOnly($pin)` (Task 4). On failure, return 401 with the same generic "Incorrect PIN" message `login()` uses, and claim nothing. Card payments never require or accept a `pin` field.
  - Response includes `daily_order_number` and, if any ticket lines existed, the minted `ticket_tokens` (token string + showtime label) so the confirmation screen (Task 9) can render QR codes without a second round-trip.

**Acceptance (EARS):**
- *Event-driven*: When stock or showtime capacity is insufficient for any line at commit time, the system shall reject the whole order with a clear per-item message and claim nothing (matches `pos-checkout.php`'s existing all-or-nothing behavior for both concessions and tickets).
- *Event-driven*: When two kiosks (or a kiosk and the POS) claim the last unit of a concession or the last seat of a showtime simultaneously, the system shall grant exactly one and cleanly reject the other — verified by the same atomic patterns already in `pos-checkout.php`, not re-derived.
- *Event-driven*: When `method === 'cash'` and the supplied PIN fails `verifyPinOnly()`, the system shall reject with 401 before opening any DB transaction.
- *Ubiquitous*: The endpoint shall never require a session or CSRF token (PIN is a per-request field, not a session credential).

**Verification:**
- [ ] Kiosk workflow: fire a POST with a valid concession-only cart, `method: 'card'`, and no cookies at all → order commits, response includes `transaction_ref`, `daily_order_number`.
- [ ] Kiosk workflow: fire a POST with a ticket line + concession line (combo), `method: 'cash'`, correct PIN → order commits, `type = 'combo'`, response includes a minted ticket token.
- [ ] Wrong PIN on a cash order → 401, no transaction row created, no stock/capacity claimed.
- [ ] Concurrency test: two simultaneous requests both trying to buy the last unit of a low-stock concession → exactly one succeeds. Repeat for the last seat of a low-capacity showtime.
- [ ] Sold-out / sold-out-showtime mid-cart: manually zero stock or capacity between two requests → second request gets a clean 409 with a specific item name, not a generic 500.
- [ ] `php -l public/api/kiosk-checkout.php` passes.

**parallel:** false

---

## Phase 3: Kiosk front-end

### Task 5: Kiosk page shell + welcome/attract screen

**Why this matters:** This is the screen running 95% of the time on the mounted tablet — it has to look intentional and branded, not like a blank waiting room, and needs to pass the "stranger figures it out in 5 seconds" bar from the spec's one overriding rule.

**Implements:** Spec §"Screen 1 — Welcome/attract" · `SCREEN-kiosk-welcome` (mockup: welcome screen in the approved artifact)

**Files:**
- Create: `public/kiosk/index.php` — page shell, `X-Robots-Tag: noindex, nofollow` header (matches `checkin.php`/`fulfillment.php` convention), pulls `ConcessionRepo::getAvailable()` for the ticker content, boots the welcome screen.
- Create: `public/assets/css/kiosk.css` — dark admin-palette welcome screen (real `--bg-primary`/`--bg-card`/`--text-primary` tokens copied from `admin/css/admin.css`, not re-derived) with the actual `assets/images/logo.webp`; separate fixed light warm palette (matching `main.css`'s `--crimson`/`--cream` tokens) for the operational screens (menu/cart/pay/confirm) — per the mockup's resolved contrast bug, these do **not** respond to `prefers-color-scheme` since this represents fixed physical hardware.
- Create: `public/assets/js/kiosk.js` — screen router (`go(screenId)`), 60s inactivity timer that calls `go('welcome')` on any period with no tap.

**Acceptance (EARS):**
- *Ubiquitous*: The welcome screen shall display the real logo, brand name, and a scrolling ticker of available concessions with live prices.
- *Event-driven*: When 60 seconds pass with no tap anywhere on the kiosk, the system shall return to the welcome screen from any other screen.
- *Event-driven*: When the welcome screen is tapped anywhere, the system shall advance to the menu screen.

**Verification:**
- [ ] Kiosk workflow: load `/kiosk/index.php` on a tablet-width viewport → logo renders, ticker scrolls, tap anywhere advances to menu.
- [ ] Idle timeout: leave the menu screen untouched for 60s → returns to welcome automatically.
- [ ] `X-Robots-Tag: noindex` header present; page not linked from any public nav (grep the codebase for `kiosk/` references outside `pos-preview`/admin bookmarking docs).

**parallel:** false

---

### Task 7: Menu (concessions + tickets), option picker, and cart-bar screens

**Why this matters:** This is the core of the kiosk — if a first-time customer can't find and add an item in 5 seconds, the whole feature fails its stated design bar. Now covers two fundamentally different product types (concessions with options/stock, tickets with showtime/age-tier/capacity), so it's the largest single UI task in the build.

**Implements:** Spec §"Screen 2 — Menu", §"Revision 2026-07-09" · `SCREEN-kiosk-menu` (mockup: menu screen, revised per feedback — larger cards, less congested; **ticket tab not yet mocked — needs a mockup pass before building**)

**Files:**
- Modify: `public/kiosk/index.php` — add the menu screen markup: category tabs now include a **Tickets** tab alongside concession categories, boot data (`window.KIOSK_BOOT`) with `ConcessionRepo::getAvailable()` output + options **and** active/upcoming `showtimes` (same query shape `pos.js`'s ticket picker already uses — read it first, mirror it, don't re-derive).
- Modify: `public/assets/js/kiosk.js` — grid render, category filter, tap-to-add for concessions (no-option items add instantly with a pulse; option items open the bottom-sheet picker) mirroring `pos.js`'s `buildConcessionCard`/`openOptions`/`addConcession`. **New:** ticket tab renders a showtime list; tapping a showtime opens Adult/Child qty steppers (mirrors `pos.js`'s existing ticket-line UI — find and reuse its function names/data shape rather than inventing a parallel one), adds ticket line(s) to the same cart array concessions use (cart items are tagged `kind: 'concession'|'ticket'` so cart review/payment can render both uniformly).
- Modify: `public/assets/css/kiosk.css` — large-card grid per the revised mockup (min-height ~230px cards, 130px photo area, 1.2rem+ name text, 1.4rem price); showtime-list and age-tier-stepper styles (new — no existing mockup, size touch targets to the same 80px minimum as everything else on this screen).

**Acceptance (EARS):**
- *Ubiquitous*: Every tap target (cards, tabs, cart bar button, stepper buttons, showtime rows) shall be at least 80px in at least one dimension, matching the spec's stated minimum.
- *Event-driven*: When a customer taps a no-option concession, the system shall add it to the cart instantly with a visible pulse and update the cart bar without a page reload.
- *Event-driven*: When a customer taps a product with options, the system shall open a full-screen picker; selecting one option adds the item and closes the picker automatically.
- *Event-driven*: When a customer taps a showtime, the system shall open Adult/Child qty steppers; confirming adds the corresponding ticket line(s) to the cart with the server-authoritative price shown (client never computes/sends its own ticket price).
- *State-driven*: While a concession's stock is zero, its card shall render greyed-out, labeled "Sold Out," and be untappable.
- *State-driven*: While a showtime has zero remaining capacity (`available_tickets - tickets_sold <= 0`), it shall render greyed-out, labeled "Sold Out," and be untappable — same treatment as a sold-out concession.

**Verification:**
- [ ] Kiosk workflow: tap through all concession category tabs plus the Tickets tab, confirm each filters/renders correctly.
- [ ] Add a no-option concession (e.g. popcorn) → instant add, pulse animation, cart bar updates.
- [ ] Add an item with options (e.g. fountain drink) → bottom sheet opens, tapping a flavor adds it and closes the sheet.
- [ ] Add tickets: tap a showtime, set 2 Adult + 1 Child, confirm → three ticket lines (or one line qty-grouped, matching cart data shape) appear in the cart bar total.
- [ ] A concession with `stock_quantity = 0` renders as Sold Out and does not respond to taps.
- [ ] A showtime with `available_tickets - tickets_sold <= 0` renders as Sold Out and does not respond to taps.

**parallel:** false

---

### Task 8: Cart review and payment screens (with PIN-gated cash confirm)

**Why this matters:** This is where the transaction actually commits — has to handle the sold-out-at-payment recovery path cleanly (spec's explicit unhappy-path requirement) or a customer is stuck with a half-built order and no clear next step. Now also the task that closes the PIN-gate decision, since the PIN pad lives on this screen.

**Implements:** Spec §"Screen 3 — Cart review", §"Screen 4 — Payment", §"Cash-confirmation trust model — RESOLVED 2026-07-09" · `SCREEN-kiosk-cart`, `SCREEN-kiosk-payment` (mockup: cart + payment screens, approved for the concessions-only version — **PIN pad and mixed ticket+concession line rendering are not yet mocked, need a mockup pass before building**)

**Files:**
- Modify: `public/kiosk/index.php` — cart review markup (line items — concession and ticket lines rendered with distinct icons/labels but the same steppers/remove pattern, three-button row per `frontend-interaction-patterns`: Add More / Start Over on the left, Pay Now on the right), payment markup (Cash/Card side-by-side cards; selecting Cash reveals a numeric PIN pad instead of an unguarded confirm button; Card keeps the existing mock-confirm panel).
- Modify: `public/assets/js/kiosk.js` — cart line qty +/-/remove for both line kinds, "Start Over" clears cart + returns to welcome, payment method selection reveals the right panel, `doCheckout()` posts to `api/kiosk-checkout.php` (Task 5) including `pin` in the body when `method === 'cash'`, handles the 409 sold-out/sold-out-showtime response by showing an inline error toast and returning to the cart screen, handles a 401 PIN failure by clearing the PIN pad and showing "Incorrect PIN, ask a staff member" without touching the cart.
- Modify: `public/assets/css/kiosk.css` — cart line (both kinds), stepper, payment card, PIN-pad, and error-toast styles.

**Acceptance (EARS):**
- *Event-driven*: When "Start Over" is tapped, the system shall clear the cart entirely and return to the welcome screen.
- *Event-driven*: When Cash is selected, the system shall show a PIN pad; the checkout request shall not be sent until a PIN has been entered.
- *Event-driven*: When Card is selected, the system shall show a mock "Confirm Card Payment" button (placeholder for a future real reader integration) — no PIN involved.
- *Event-driven*: When `kiosk-checkout.php` returns a stock/capacity-conflict error, the system shall show a specific, readable error and return the customer to the cart screen with their cart intact (they adjust manually).
- *Event-driven*: When `kiosk-checkout.php` returns a 401 (bad PIN), the system shall clear the PIN pad and show an incorrect-PIN message without discarding the cart.

**Verification:**
- [ ] Kiosk workflow: build a mixed cart (one ticket, one concession), adjust quantities, remove a line, confirm the running total updates correctly at each step.
- [ ] "Start Over" from the cart screen returns to welcome with an empty cart.
- [ ] Cash path: select Cash, enter the correct employee PIN → order commits, advances to confirmation.
- [ ] Cash path, wrong PIN: enter an incorrect PIN → 401 shown, PIN pad clears, cart untouched; correct PIN on retry succeeds.
- [ ] Card path: select Card, tap the mock Confirm button → order commits, advances to confirmation.
- [ ] Sold-out recovery: manually zero a cart item's stock (or a showtime's capacity) mid-flow, attempt to pay → clear error shown, lands back on cart screen.

**parallel:** false

---

### Task 9: Confirmation screen (with ticket QR codes)

**Why this matters:** The order number shown here is literally what staff will call out — has to be large, correct, and match what shows up on the fulfillment board a moment later. Also the customer's only way to receive their ticket QR at the kiosk (no email/SMS delivery for in-person orders), so a missing or broken QR here means a customer can't get into the theater.

**Implements:** Spec §"Screen 5 — Confirmation", §"Superseded 2026-07-09: ticket QR is back in scope" · `SCREEN-kiosk-confirmation` (mockup approved for the no-QR version — **QR layout not yet mocked, needs a mockup pass before building**)

**Files:**
- Modify: `public/kiosk/index.php` — confirmation screen markup: large `daily_order_number` (from the `kiosk-checkout.php` response), item list, **one QR image per ticket line** (rendered via `QrCode::pngDataUri()` server-side into the page the same way `confirmation.php:134` does — inline `<img>` per ticket, not a client-side JS QR generator), "being prepared" message (only shown if the order has a concession line — a tickets-only order has nothing to prepare), Done button.
- Modify: `public/assets/js/kiosk.js` — on successful checkout, populate confirmation screen from the API response (order number, item list, ticket-token QR data URIs already included in the `kiosk-checkout.php` response from Task 5 — no second round-trip needed), auto-return to welcome after 15s, Done button returns immediately and cancels the 15s timer.

**Acceptance (EARS):**
- *Ubiquitous*: The confirmation screen shall display the transaction's `daily_order_number` prominently as the primary heading.
- *Ubiquitous*: For every ticket line in the completed order, the confirmation screen shall display a scannable QR code generated from that ticket's `ticket_token`, using the same `QrCode` class `confirmation.php` uses (not a re-derived implementation).
- *Ubiquitous*: A tickets-only order shall not display a "being prepared" message (nothing to prepare); a concessions-only or combo order shall display it.
- *Event-driven*: When 15 seconds elapse on the confirmation screen with no interaction, the system shall return to welcome.
- *Event-driven*: When "Done" is tapped, the system shall return to welcome immediately.

**Verification:**
- [ ] Kiosk workflow, concessions-only order: confirmation shows order number, item list, "being prepared" message, no QR codes.
- [ ] Kiosk workflow, ticket order: confirmation shows order number and one scannable QR per ticket purchased (scan-test with a phone or the same manual check used for `confirmation.php`).
- [ ] Kiosk workflow, combo order: confirmation shows both the QR(s) and the "being prepared" message together.
- [ ] Scan a kiosk-issued QR at `/checkin.php` → check-in succeeds exactly like a website-purchased ticket's QR would.
- [ ] Wait 15s without touching anything → returns to welcome automatically.
- [ ] Tap Done immediately after an order → returns to welcome without waiting.

**parallel:** false

---

## Phase 4: Fulfillment board upgrade

### Task 10: Order-number display + time-elapsed color escalation

**Why this matters:** Directly closes the gap found in spec review — staff can finally shout a real order number, and can tell urgency at a glance without reading a timestamp.

**Implements:** Spec §"Build 2 — actual delta" · `SCREEN-fulfillment-card` (mockup, approved)

**Files:**
- Modify: `public/api/fulfillment.php` — around line 38-44, add `t.daily_order_number` to the `SELECT`, and use it (falling back to `t.transaction_ref` for any pre-existing row that predates this column, so historical/in-flight orders don't break) in the `$orders[$id]` array as `'orderNumber'`.
- Modify: `public/fulfillment.php` — around line 61-62, render `order.orderNumber` instead of `order.ref`; around line 45-50 (`timeAgo`), add a second function `timeUrgencyClass(iso)` returning `'ok'` / `'warn'` (≥5min) / `'late'` (≥10min), applied as a class on the `.order-time` element; re-run `timeUrgencyClass` in the existing `refreshTimestamps()` interval (line 113-117) so a card's color escalates live without a full re-render.
- Modify: `public/assets/css/fulfillment.css` — around line 61-62, bump `.order-id` font-size from `2rem` to `~3.2rem` per the approved mockup; add `.order-time.ok/.warn/.late` color rules (grey/amber/red, matching the mockup's exact values: `ok` `#64748b`/`#f1f5f9` bg, `warn` `#92400e`/`#fef3c7` bg, `late` `#991b1b`/`#fee2e2` bg — internal-tool semantic-color convention, not new colors invented here).

**Acceptance (EARS):**
- *Ubiquitous*: Every order card shall display its `daily_order_number` (or the transaction ref for legacy rows without one) as the largest element on the card.
- *State-driven*: While an order has been pending 0-4:59, its time badge shall render in the neutral/grey style.
- *State-driven*: While an order has been pending 5:00-9:59, its time badge shall render in the amber/warning style.
- *State-driven*: While an order has been pending ≥10:00, its time badge shall render in the red/danger style.

**Verification:**
- [ ] Fulfillment workflow: place a kiosk test order → card shows the real order number, not a transaction ref.
- [ ] Manually backdate a test transaction's `created_at` (one-shot DB script, same pattern as prior sessions) to 6 minutes ago → its badge turns amber on the next 15s refresh tick without a page reload; backdate to 11 minutes → turns red.
- [ ] A pre-existing paid transaction with `daily_order_number IS NULL` (from before this migration) still renders a usable card (falls back to ref) instead of showing "null" or breaking layout.

**parallel:** false

---

### Task 11: New-arrival flash + source filter tabs

**Why this matters:** Closes the last two gaps from the spec review: staff notice a new order the instant it lands (not just eventually scan and see it), and can filter to just kiosk or just online orders during a rush.

**Implements:** Spec §"Build 2 — actual delta" · `SCREEN-fulfillment-card` (mockup, approved — flash + filter tabs)

**Files:**
- Modify: `public/fulfillment.php` — around line 104-109 (`render()`), the existing `known[order.id]` guard already distinguishes "never seen" from "seen"; add a `.just-arrived` class (2-cycle flash animation, then auto-removed via `setTimeout`) on cards added *after* the first successful poll (track a `firstPollDone` boolean so the very first page load doesn't flash every card at once — only genuinely new arrivals during an open session flash). Add filter-tab markup above `#orderGrid` (All/Online/Walk-Up/Kiosk) and a `curFilter` variable; `render()` filters the incoming `orders` array by `channelClass` before diffing/building cards.
- Modify: `public/assets/css/fulfillment.css` — around line 55-58, add `.order-card.just-arrived` flash keyframes (blue pulse, matching the approved mockup, 2 cycles ~1.6s each); add `.filter-tabs`/`.filter-tab`/`.filter-tab.active` styles (pill buttons, matches the mockup and the badge color language already in this file).

**Acceptance (EARS):**
- *Event-driven*: When a new order appears on an already-open board (not on initial page load), the system shall flash it briefly (2 pulse cycles) so staff notice without needing to scan the whole grid.
- *Event-driven*: When a filter tab is tapped, the system shall show only orders matching that channel, without a page reload.
- *Ubiquitous*: The "All" filter shall be selected by default on page load.

**Verification:**
- [ ] Fulfillment workflow: with the board open, place a new order from another device/tab → the new card flashes visibly within one poll cycle (≤10s).
- [ ] Tap "Kiosk" filter with a mix of online/walk-up/kiosk orders pending → only kiosk-sourced cards remain visible; tap "All" → all return.
- [ ] Reload the page fresh with several pending orders already existing → none of them flash (only genuinely new arrivals during an open session do).

**parallel:** false

---

## Phase 5: End-to-end verification

### Task 12: Full loop walkthrough + concurrency spot-check

**Why this matters:** This is the actual acceptance bar from the brief: "kiosk order appears on fulfillment board, Mark Complete removes it, transaction shows in admin reports with source_channel = kiosk" — a task-by-task pass doesn't guarantee the seams between tasks work. With tickets in scope, this is also the first point a kiosk-issued QR gets scanned for real, and the first point a kiosk cash sale gets attempted with a wrong PIN outside a unit-level check.

**Implements:** Spec §"The complete operational loop" · no new screen (verification task)

**Files:** None (verification only — may produce small fix commits if gaps are found).

**Acceptance (EARS):**
- *Ubiquitous*: A completed kiosk order (concessions, tickets, or combo) shall appear on the fulfillment board within one poll cycle (≤10s) tagged `source_channel = kiosk` with the correct daily order number and correct `type` (`ticket`/`concession`/`combo`).
- *Ubiquitous*: Marking a kiosk order complete on the fulfillment board shall remove it from the board and be reflected in `admin/reports.php`'s channel breakdown.
- *Ubiquitous*: A kiosk-issued ticket QR shall check in successfully at `/checkin.php` exactly like a website-purchased ticket's QR.
- *Ubiquitous*: A kiosk cash sale shall be rejected end-to-end (no transaction, no stock/capacity claimed) when the wrong PIN is entered, and shall succeed when the correct PIN is entered.

**Verification:**
- [ ] Full loop, concessions: complete a real concessions-only order on `/kiosk/` (both cash-with-PIN and card paths) → confirm it appears on `/fulfillment.php` with the purple Kiosk badge and correct order number → tap Mark Complete → card slides out → check `admin/transactions.php` shows `source_channel = kiosk`, `type = concession`, matching `daily_order_number`.
- [ ] Full loop, tickets: complete a real ticket-only order on `/kiosk/` → confirmation shows a scannable QR → scan it at `/checkin.php` → check-in succeeds → confirm the transaction does **not** appear on the fulfillment board as a pending-prep item (nothing to prepare for a tickets-only order — verify Task 9/Task 10's fallback logic doesn't put a phantom card on the board, or if it intentionally does for admin visibility, confirm that's the intended behavior, not a bug).
- [ ] Full loop, combo: complete a mixed ticket+concession order → confirm both the QR delivery and the fulfillment-board prep card appear correctly, `type = combo`.
- [ ] PIN enforcement: attempt a kiosk cash sale with a deliberately wrong PIN → rejected, no transaction row, no stock/capacity claimed; retry with the correct PIN → succeeds.
- [ ] `admin/reports.php` — confirm kiosk sales appear in the existing channel/revenue breakdown without any reports code change (it already groups by `source_channel`, which now has a real `'kiosk'` value flowing into it).
- [ ] `php -l` on every file touched across all 11 prior tasks, run as a batch before final commit.
- [ ] Deploy via the existing GitHub Actions pipeline; confirm `db-migrate.php` ran clean in the Action log (per Task 1's idempotency requirement) before verifying anything live.

**parallel:** false

---

## Self-review (updated 2026-07-09)

- [x] Decomposition rationale states the load-bearing decision (the atomic counter) and why it's sequenced first
- [x] Every task has `**Why this matters:**`
- [x] Every task has an `**Implements:**` line citing spec section + mockup screen (or "no screen: backend/infra")
- [ ] Every mockup screen has at least one task building it — **gap:** the Tickets tab (Task 7), PIN pad (Task 8), and QR layout (Task 9) have no approved mockup yet; the 2026-07-07 mockups only cover the concessions-only version. **A mockup pass for these three surfaces should happen before Phase 3 starts**, not mid-build.
- [x] Every task has a file-touch list with paths
- [x] Every task has EARS acceptance criteria + user-workflow verification steps
- [x] No TODO/TBD left in the plan
- [x] Phases are sequential (`parallel: false` throughout) — schema → PIN primitive → checkout API → UI → board → E2E, real ordering dependencies throughout

## Open item carried into this revision

Task 12's verification flags a real open question, not just a test step: **does a tickets-only kiosk order belong on the fulfillment board at all?** The board exists to tell kitchen staff what to prepare — a tickets-only order has nothing to prepare. Recommend: tickets-only orders skip the fulfillment board entirely (no card ever created for them), while combo orders still show a card listing only the concession lines. This needs a decision before Task 10/11 (fulfillment board queries) are built, since it changes the `WHERE` clause in `api/fulfillment.php`.

## Next step

Awaiting your "go" to execute Phase 1, Task 1 — and a decision on the open item above before Phase 3/4 UI work starts.
