# Customer Kiosk + Fulfillment Board Upgrade — Design Spec

**Date:** 2026-07-07 (revised 2026-07-09)
**Status:** Draft — awaiting mockup approval
**Project type:** hybrid (public pages + operated surfaces) — this spec covers two *operated* surfaces (kiosk is customer-facing but templated/single-purpose like `/checkin.php`; fulfillment board is back-of-house)

## Revision 2026-07-09 — scope now includes ticket sales

The 2026-07-07 draft explicitly scoped the kiosk to concessions-only and removed the ticket-QR confirmation branch as dead code. Taemoor has since confirmed the kiosk **must sell both tickets and concessions**, and that "staff PIN same as POS" means **PIN-gate only the cash-confirm button** (not full staff login — the kiosk stays customer self-serve, no session). This revision folds tickets back in. Everything below that isn't explicitly called out as changed (daily order number, fulfillment board upgrade, Phase 1) is unaffected.

**What changes, concretely:**
- Screen 2 (Menu) gains a **Tickets** tab alongside concession categories — showtime list, Adult/Child qty steppers, mirroring the selection UI `pos.js` already uses for its own ticket line items (mirror it, don't re-derive — read it first).
- `api/kiosk-checkout.php` must handle `kind:"ticket"` lines identically to `pos-checkout.php`'s ticket branch: same `SELECT ... FOR UPDATE` atomic capacity claim on `showtimes` (locked in ascending id order to match `pos-checkout.php`'s deadlock-avoidance convention), same `ticketPrice()` server-side pricing, same post-commit best-effort `TicketTokenRepo::generateForTransaction()` call.
- Screen 5 (Confirmation) **un-removes** the QR branch: loop over any `ticket_tokens` rows for the transaction and render `QrCode::pngDataUri()` per ticket, same as `confirmation.php:134`.
- Cash-confirm on Screen 4 is now **PIN-gated** (Q2, resolved: small addition as anticipated). This requires extracting a session-free PIN-check primitive from `PosAuth::login()` — see new "PIN verification without a POS session" section below — since the kiosk has no `ALEX_POS_SESS` session to mutate and shouldn't get one.
- `transactions.type` becomes `'combo'` (already a valid enum value: `ENUM('ticket','concession','combo')`, `schema.sql:213`) whenever a kiosk order contains both an item of each kind — no schema change needed, just correct value selection at insert time.

**New engineering task this adds:** extracting `PosAuth::verifyPinOnly(string $pin): array` — the same `password_verify()` + `employees.failed_attempts`/`locked_until` DB-backed lockout check `login()` already does (lines 116-148, 183-202 of `PosAuth.php`), minus the session mutation (`session_regenerate_id`, `$_SESSION[...]` writes, lines 151-155). This is additive — `login()` itself is untouched, so nothing about POS auth changes. The new method is DB-driven, not session-driven, so it's already portable to an unauthenticated page as-is; it just needs to be pulled out as its own callable.

## Business meaning

## Business meaning

Tim wants the concession counter to run like a fast-food pit crew: a customer can serve themselves at a kiosk without waiting on staff, and the person making food sees one clean queue of "what's owed" regardless of whether the order came from the website, a staff-rung sale, or the kiosk. Right now every concession sale requires an employee to personally ring it up on `/pos/`, which caps throughput at intermission and during rushes. The kiosk removes that bottleneck for customers willing to serve themselves; the upgraded fulfillment board makes sure whoever's making popcorn can read a wall-mounted screen from 8 feet away instead of squinting at a laptop.

## What already exists — don't rebuild

Before designing, I checked the current state. Two things Tim's requirements assume are missing already exist:

- **`transactions.fulfillment_status` and `source_channel`** (including the `'kiosk'` enum value) are **already in `database/schema.sql`** — no migration needed.
- **The fulfillment board is already substantially built** (`/fulfillment.php` + `/api/fulfillment.php`): 10s polling, atomic idempotent "mark complete" (`WHERE fulfillment_status = 'pending'` guard), slide-in/slide-out animation, channel badges already color-coded exactly as requested (online=blue, walk-up=green, kiosk=purple), empty state. **Build 2 is an upgrade, not a rebuild** — see the delta list below.

## Build 2 — Fulfillment board: actual delta (smaller than the brief implies)

| Requirement | Status | Action |
|---|---|---|
| Color-coded source badges | ✅ already exists | none |
| Auto-refresh polling | ✅ already exists (10s) | none |
| Mark Complete → slide out | ✅ already exists | none |
| Empty state | ✅ already exists | none |
| Idempotent complete (double-tap safe) | ✅ already exists | none |
| Order number "very large, shout-able" | ⚠️ exists but wrong shape — see below | fix |
| Time elapsed color escalation (5min yellow, 10min red) | ❌ missing | add |
| New-order arrival highlight/flash | ⚠️ partial (slides in, no flash) | add flash pulse |
| Source filter tabs (All/Online/Walk-Up/Kiosk) | ❌ missing | add |

### The order-number gap (found during spec review, needs your sign-off)

Tim's own words: *"They push a button, yep that one's done... ORDER 47 READY."* The board currently shows `#TXN-D128E055` (the internal `transaction_ref` hex string) as the "order number." That's not shout-able — nobody calls out "Order T-X-N-D-1-2-8." This is a **pre-existing gap**, not something the kiosk introduces, but the kiosk build is what surfaces it since kiosk orders need the same treatment.

**Proposed fix:** add a `daily_order_number` column (`SMALLINT UNSIGNED`) to `transactions`, assigned sequentially per calendar day (resets to 1 each morning, like a real concession stand's order counter) at the moment a transaction is created `paid`/committed — for website orders that's the Stripe webhook; for POS and kiosk that's checkout time. The fulfillment board displays `daily_order_number` (e.g. "47") instead of `transaction_ref`; `transaction_ref` stays as the internal admin/reports identifier.

This is a schema change touching three checkout paths (website webhook, POS, new kiosk), so I'm flagging it rather than silently adding it — see **Open question 1** below.

## Build 1 — Customer kiosk

### Screens (per your spec, 1:1)

1. **Welcome/attract** — full-screen brand, "Tap anywhere to order," scrolling featured items pulled from `concessions` (same `ConcessionRepo::getAvailable()` the POS already uses). Auto-return here after 60s idle or order completion.
2. **Menu** — category tabs, product grid (photo/name/price only), tap-to-add with green pulse, full-screen option picker for items with options, sold-out cards greyed/untappable, persistent cart bar.
3. **Cart review** — photo/name/option/qty/line price, 80px+/− steppers, remove, running total, Add More / Start Over / Pay Now.
4. **Payment** — Cash (staff-confirmed) / Card (mock placeholder for a real reader later).
5. **Confirmation** — large order number, item list, "being prepared" message, auto-return after 15s or immediate "Done."

### Superseded 2026-07-09: ticket QR is back in scope

The 2026-07-07 draft removed the ticket-QR branch as dead code because the kiosk was concessions-only at the time. That's no longer true (see Revision above) — the kiosk now sells tickets, so Screen 5 **must** render QR codes for any ticket lines, using the exact mechanism `confirmation.php:134` already uses (`QrCode::pngDataUri()` per `ticket_tokens` row).

### Engineering decision: a separate `/api/kiosk-checkout.php`, not literally reusing `/api/pos-checkout.php`

The brief says "same checkout API endpoint (add `source_channel = kiosk`)." I'm implementing that in *spirit* (same tables, same SQL shape, same atomic stock-decrement pattern) but as a **separate file**, because `pos-checkout.php` is gated behind `PosAuth::isLoggedIn()` (employee PIN or admin session) — that's a real security boundary, not incidental, since POS sales are attributed to a logged-in employee. The kiosk has explicitly **no login**. Bolting an unauthenticated branch onto an authenticated endpoint is the wrong shape; two competent engineers would converge on a separate endpoint here (same as `/api/checkin.php` and `/api/fulfillment.php` are already separate, rate-limited, unauthenticated-but-unlinked endpoints, distinct from admin-authenticated ones). `kiosk-checkout.php` will share the identical stock-check/insert logic, just without the auth gate, and will be rate-limited per IP the same way `checkin.php`/`fulfillment.php` already are.

### Cash-confirmation trust model — RESOLVED 2026-07-09: PIN-gated

Q2 is answered: the cash-confirm button is **PIN-gated**. Flow: staff selects Cash → screen shows a numeric PIN pad instead of an unguarded confirm button → PIN is submitted as part of the same `kiosk-checkout.php` POST that commits the sale (never trust a client-side "PIN was entered" flag with no server check) → the endpoint calls `PosAuth::verifyPinOnly()` (new method, see Revision above) before committing; on failure it returns 401 and the kiosk shows "Incorrect PIN, ask a staff member" without touching the cart. Card payments are unaffected — no PIN, same mock-confirm flow as before.

### Concurrency (`backend-commerce-concurrency` applied)

Concessions are not a scarce/holdable resource the way tickets are — same reasoning already applied to `pos-checkout.php`. Stock is claimed atomically at the moment payment is confirmed (`UPDATE concessions SET stock_quantity = stock_quantity - :qty WHERE id = :id AND stock_quantity >= :qty`), never read-then-written. If two kiosks (or a kiosk and the POS) try to claim the last unit simultaneously, exactly one wins; the other gets a clear "sold out, please adjust your order" and returns to cart review. No hold/reservation step is needed because nothing is claimed until the staff/customer actually commits to pay — unlike tickets, an abandoned kiosk cart never touches inventory.

### Data model — what's genuinely new

```sql
-- Only new thing kiosk needs beyond what already exists:
ALTER TABLE transactions
  ADD COLUMN daily_order_number SMALLINT UNSIGNED NULL AFTER transaction_ref;
```

`source_channel = 'kiosk'` and `fulfillment_status` already exist — no other schema changes.

## Flow review (actor × goal)

| # | Actor | Goal | Spec prose | Mockup | Verdict |
|---|---|---|---|---|---|
| 1 | Customer (kiosk) | Order concessions unassisted | ✅ Screens 1-5 | ✅ | MATCH |
| 2 | Customer (kiosk) | Recover from a sold-out item at payment time | ✅ "clear error, let customer adjust" | ✅ (error state in mockup) | MATCH |
| 3 | Customer (kiosk) | Abandon mid-order | ✅ "Start Over" + 60s idle return | ✅ | MATCH |
| 4 | Staff (concession stand) | Confirm a kiosk cash payment | ✅ | ✅ | MATCH |
| 5 | Staff (back of house) | See all pending orders regardless of channel | ✅ existing board | ✅ | MATCH |
| 6 | Staff (back of house) | Filter to just kiosk/online/walk-up orders | ✅ new filter tabs | ✅ | MATCH |
| 7 | Staff (back of house) | Notice a new order the instant it arrives | ✅ "animate + highlight" | ✅ | MATCH |
| 8 | Staff (back of house) | Gauge urgency without reading a timestamp | ✅ yellow/red thresholds | ✅ | MATCH |
| 9 | Owner (admin) | See kiosk sales in reports alongside other channels | Implied, not detailed | Not mocked (no new screen — `admin/reports.php` already groups by channel via existing `source_channel`) | PARTIAL — no code change needed, `source_channel='kiosk'` flows into existing reports automatically once transactions are tagged |

No gaps found that block building. Flow 9 needs no new code — `admin/reports.php` already reports by `source_channel`, and `'kiosk'` becomes a real value the moment kiosk orders exist.

## Open questions — RESOLVED 2026-07-09

**Q1 (daily order number, all channels):** Answered — yes, all channels (website, POS, kiosk). Counter resets at midnight server time (`CURDATE()`), as originally planned — confirmed, not store-open time.

**Q2 (cash-confirm PIN gate):** Answered — PIN-gated, using the POS employee PIN (same `employees.pin_hash` table, via the new `PosAuth::verifyPinOnly()` primitive). See "Cash-confirmation trust model" above.

**Q3 (new, 2026-07-09 — ticket scope):** Answered — kiosk sells both tickets and concessions. See Revision section at the top of this doc.

## Skills applied

`workflow-brainstorm` (this doc's structure), `workflow-spec-review` (the order-number finding, the kiosk-checkout auth boundary finding, the ticket-QR dead-code finding — all caught here rather than at code review), `workflow-flow-review` (the 9-flow table above), `backend-commerce-concurrency` (atomic stock claim, no-hold reasoning), `frontend-interaction-patterns` (button placement in mockups — Add More/Start Over left, Pay Now right; no native `confirm()`), `frontend-feedback-system` (toast/error patterns, no native alerts), `internal-tool-conventions` (fulfillment board is an operator screen — tabular figures, semantic color, Tabler-style icons, 8-foot contrast). Not re-read in full this pass (already reflected in this codebase's existing conventions, which I've built against directly this session): `backend-api-endpoints`, `backend-php-standards`, `quality-testing-validation`, `quality-production-readiness`, `public-website-conventions`.

## Next step

Mockups below (Artifact). After you approve or request changes, I'll write `workflow-plan`'s implementation plan and then build.
