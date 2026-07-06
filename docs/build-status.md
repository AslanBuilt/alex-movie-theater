# Alex Theater — Build Status vs. Agentic Task Plan

> Reconciliation of the agentic task plan against what is **actually built and live** as of **2026-06-27**.
> Verified against the live repo + production site, not memory. Items carried from memory or not directly
> re-checked are marked **(to verify)** — do not treat those as confirmed.
>
> ⚠️ This is a *plan-vs-current-build* audit. It is **NOT** Task 0.2 (the *old Square site vs. new site*
> parity audit), which is still open and needs the old site URL (`alexmovietheater.com`).

## Legend
- ✅ **Done** — built, verified live
- 🟢 **Done (variant)** — built, but differs from the plan's wording (see note)
- 🟡 **Partial / to verify**
- 🔴 **Open** — not built
- ⛔ **Blocked** — needs a user/client input

---

## Phase 0 — Orient
| Task | Status | Note |
|---|---|---|
| 0.1 CRM context pull | 🔴 Open | Needs Aslan CRM access (`aslan-crm-api`); not done by code agent |
| 0.2 Gap audit (OLD Square site vs NEW) | 🔴 Open | ⛔ needs old site URL. This doc is a *different* audit (plan vs build) |
| 0.3 Hosting + deploy + `.claude/project.json` | 🟡 Partial | Host **resolved & proven**: `parityrfp.com/cs/alex-movie-theater/`, FTP deploy via GitHub Actions works (multiple deploys this session). `.claude/project.json`/`project-stage` not written (needs `aslan-project-config` convention) |

## Phase 1 — Website Redesign
| Task | Status | Note |
|---|---|---|
| 1.1 Hero (Chevelle, header, mobile) | ✅ Done | `index.php`: `hero-4.webp` Chevelle is first slide; "Real Movies. $5 Tickets." kept verbatim |
| 1.2 Now Showing carousel + detail pages | ✅ Done | `.poster-carousel` w/ prev/next arrows; `movie.php` detail pages live (DB-driven) |
| 1.3 Remove duplicate/cluttered content | ✅ Done | Google Reviews between Now Showing & senior teaser; senior page simplified |
| 1.4 Homepage senior teaser | ✅ Done | `.senior-teaser-section` present on homepage |
| 1.5 Merge Location + Contact | ✅ Done | `location.php` = "Location & Contact" w/ map; `contact.php` 301→`location.php#contact` |
| 1.6 Fix AI artifacts (contrast/stray text) | 🟡 To verify | Not re-audited this pass; map root-cause ADR (`docs/decisions/`) not written |

## Phase 2 — Database + Admin
| Task | Status | Note |
|---|---|---|
| 2.1 Data schema design | 🟢 Done (variant) | Schema live (`database/schema.sql` + `migrate-task2.sql`); not structured as `db/migrations/` artifacts. Table names differ from plan (`concessions`, `transactions`, `transaction_items` — see `project_task4_pos` memory) |
| 2.2 Admin movies + showtimes | ✅ Done | `admin/movies.php`, `showtime-edit.php`; public carousel/`movie.php` read DB |
| 2.3 Admin concessions | ✅ Done | `concession-edit.php` w/ file upload + options; public `concessions.php` reads DB |
| 2.4 **Mock payment API** | 🟢 **Superseded** | **Real test-mode Stripe is built + card-verified** (deliberate "Option A" decision — Stripe test mode IS the demo path; no mock fallback). Do NOT build a throwaway mock. `StripeService.php` + `api/webhooks/stripe.php` |
| 2.5 Online ticket + concession purchase | ✅ Done | `checkout.php` → Stripe → `confirmation.php`. Known gap: no capacity *hold* during the payment window (small oversell risk) |

## Phase 3 — Inventory + Reporting
| Task | Status | Note |
|---|---|---|
| 3.1 Inventory management | ✅ Done | Order completion decrements stock + writes `inventory_log`; manual adjust in `admin/concession-stock.php`; reorder warnings |
| 3.2 Daily sales report + reorder alerts | ✅ Done | `admin/reports.php` (210 lines): tonight/today/yesterday/week/month buckets, top sellers, reorder flags. Arbitrary date-range = intentionally skipped |

## Phase 4 — POS Interface
| Task | Status | Note |
|---|---|---|
| 4.1 Walk-up kiosk (customer self-serve) | 🔴 **Open** | **The one genuinely-unbuilt feature.** Schema has `kiosk` enum value but no kiosk view exists. Would reuse public checkout + POS patterns + existing capacity model |
| 4.2 Staff cash register | ✅ Done | `pos/index.php` (PIN 7090, `staff_register`). **Full sale→void→restock E2E verified 2026-06-27** (TXN-D128E055). Void *logic* verified; admin void button's own click-path untested by a human |

## Phase 5 — SEO + Launch
| Task | Status | Note |
|---|---|---|
| 5.1 SEO baseline | 🟡 Partial | ✅ JSON-LD `MovieTheater` + canonical site-wide; per-page meta titles/descriptions present. **To do:** per-showing `Movie` JSON-LD; sitemap.xml/robots.txt **deferred** (site is a subdir of parityrfp.com, root files not served until launch domain) |
| 5.2 Production readiness checklist | 🔴 Open | `docs/launch-checklist.md` not written |

---

## Plan "Open Questions" — already answered by the build
- **Deploy host?** → **parityrfp.com/cs/alex-movie-theater/** (proven by every deploy this session).
- **Numbered seating?** → Build already uses a **capacity counter, not seat selection** (matches "0 = unlimited / capacity" model). No numbered seats assumed.
- **Exact theater name?** → **"The Alex"** is used site-wide already.

## Still genuinely blocked on user/client
- Logo (use brother's existing vs placeholder) — Task 1.1 polish.
- Google Maps API key domain restriction (Task 1.5 map diagnosis + ADR).
- CRM/SOW status (Task 0.1).
- **SendGrid secrets + DNS** (order-confirmation emails are built but inert).
- **Stripe webhook registration + real `whsec_`** (test mode).
- **GA4 / Meta Pixel IDs**, **~22 concession photos**.

## Realistic open *build* items (unblocked, code work)
1. **4.1 Walk-up kiosk** — the standout feature; reuses existing flows. *Confirm scope before building.*
2. **5.2** launch-checklist doc; **1.6** AI-artifact/contrast re-audit + map ADR; per-showing **Movie JSON-LD** (5.1).
3. **0.2** old-vs-new Square parity audit — needs old site URL.

---

## Closing session — human-gated items (2026-07-06)

| Item | Status | Note |
|---|---|---|
| Task 0 — stray pending transactions | ✅ Done | 19 stale test transactions (all `source_channel=website`, empty `customer_email`, round test amounts, dates 06-25→07-02) voided via `payment_status='pending'` → `'voided'`. Safety-checked first: zero `inventory_log` sale entries and zero `ticket_tokens` tied to any of the 19 refs — nothing to restock/restore. Confirmed via direct DB query (one-shot script uploaded/executed/deleted via FTP, not phpMyAdmin — no cPanel access this session). |
| Task 5 — `gateway_ref` ghost column | ✅ Done | `SHOW COLUMNS FROM transactions LIKE 'gateway_ref'` → empty result, confirming it was never created in the live DB. Zero code references repo-wide (grep-confirmed). Removed from `database/schema.sql`. |
| Task 1 — Stripe webhook registration | ⛔ Blocked | Needs Stripe Dashboard access (browser + login) — not yet done this session. |
| Task 2 — QR purchase test | ⛔ Blocked | Needs browser + phone camera + email inbox — not yet done this session. |
| Task 3 — oversell race | ⛔ Pending decision | User to confirm: two-tab phpMyAdmin race test, or close as code-reviewed. |
| Task 4 — admin session timeout | ⛔ Pending | Awaiting user signal ("ready for Task 4") to deploy temp `ADMIN_SESSION_TTL=10`. |
| Task 6 — FTP credentials in skills repo | ⛔ Flagged for Tim | Live FTP password confirmed still present in `infra-deploy-manual` skill file; used this session to run the one-shot diagnostic scripts. Action items: Tim rotates the password, updates the GitHub Actions secret, and decides whether the skill file should reference the secret's location instead of its value. |
