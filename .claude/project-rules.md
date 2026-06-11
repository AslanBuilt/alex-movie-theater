<!-- aslan-skills:project-rules -->
# alex-movie-theater — Project Rules & Memory

**This file is the project's source of truth for facts Claude keeps re-deriving.**
It is auto-loaded every session (SessionStart hook). Keep it current: when a fact
changes (deploy path, a credential's location, a new convention), update it here
so no future session has to ask. Facts live here; *decisions* (with rationale)
live in `docs/client-context/decisions.md` or `docs/decisions/` (ADRs).

> Secrets rule: this file records **where** credentials live and their **names**,
> never their **values**. Real secrets live in GitHub Actions Secrets + cPanel.

## Stage & type

- **Stage:** `demo` (see `.claude/project-stage`; rules → `lifecycle-stage` skill)
- **Type:** `hybrid` (see `.claude/project-type`; gate strength → `aslan` / `workflow`)

## Deploy

- **Host:** GoDaddy cPanel, parityrfp.com (staging). cPanel admin: https://aslanadvisors.com:2083
- **URL:** https://parityrfp.com/cs/alex-movie-theater/  (admin at `/admin/`)
- **Pipeline:** GitHub Actions `.github/workflows/deploy.yml` — auto-deploy on push to `master` (also `workflow_dispatch`). NOTE: this is a **per-directory** FTP workflow, NOT the canonical whole-repo template — it matches this project's `public/`-is-docroot layout (see Conventions below).
- **FTP targets (per dir):** `public/` → `/cs/alex-movie-theater/` (web root, flat); `includes/`, `templates/` → same-named subdirs; `config/` → `config/` **excluding `database.php`**; `database/` → `database/` **excluding `seed.sql`**.
- **Docroot:** `public/` is deployed flat AS the web root — `public/index.php` is the homepage. No rewrite `.htaccess` needed (differs from the canonical template's rewrite model).
- **Credentials live in:** GitHub repo Secrets — `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD` (set on `AslanBuilt/alex-movie-theater`, 2026-06-11). Standard parityrfp FTP creds (host `72.167.208.71`, user `DW@parityrfp.com`). No `FTP_SERVER_DIR` variable — paths are hardcoded in the workflow.
- **GitHub repo:** `AslanBuilt/alex-movie-theater` (origin, where we push + deploy). Upstream: `2KTay/alex-movie-theater` (fetch only — original client fork; do not configure).

## Database

- **Naming convention:** confirm the cPanel account's actual prefix (parityrfp account prefix is `r5nok0izu6hd_`); don't assume `aslanadv_`.
- **DB config is server-side only:** `config/database.php` holds the live DB credentials. It is **gitignored** and **excluded from the deploy** (set up manually on the server / cPanel). The deploy does NOT inject DB secrets — there is no `config/.env` step and no `DB_*` GitHub secrets for this project.
- **Schema/seeds:** `database/` (deployed, minus `seed.sql`). `database/seed-with-passwords.sql` is gitignored.

## Aslan CRM tracking

This project is mirrored in the Aslan CRM (PRD, requirements, flows, screens, meetings, tasks — see `aslan-crm-prd`). When a CRM project id is set, each session auto-pulls a live digest (recent meetings, open questions, to-do tasks, unverified must-haves) via `.claude/crm-context.mjs`.

- **CRM project id:** `none`  ← also in `.claude/crm-project-id` (one line). Empty/`none` = not CRM-tracked; the auto-pull stays silent.
- **API key (location, never value):** `~/.claude/aslan-crm-api.key`, or the `ASLAN_CRM_API_KEY` env var. If neither exists the session is prompted to paste a key and saves it there (`aslan-crm-api` → "Getting access").
- **Refresh / query more:** `node .claude/crm-context.mjs none --full`, or read/write records directly per `aslan-crm-api` + `aslan-crm-prd`.

## Where things are

- **Specs:** `docs/superpowers/specs/` (canonical `.md` + rendered `.html`)
- **Mockups:** `docs/superpowers/specs/mockups/` (interactive HTML — the design target)
- **Plans:** `docs/superpowers/plans/`
- **Decisions:** `docs/client-context/decisions.md` (append-only) + `docs/decisions/` (ADRs)
- **Open client questions:** `docs/client-context/open-questions.md`
- **Gaps backlog (if used):** `docs/superpowers/specs/__GAPS_BACKLOG__`

## Conventions established on THIS project

<!-- Append project-specific conventions, overrides, and gotchas as they're set.
     Each entry = one line: what + why. Examples:
     - Item/BOM data is NetSuite-mastered, read-only in-app (see ADR-003).
     - Push to main is desired at this stage; no PR required (solo dev, mvp). -->

- Deploy uses a **per-directory** FTP workflow (not the canonical whole-repo template) because `public/` is deployed flat as the docroot — keep this; do not "upgrade" to the rewrite model on the live demo without cause.
- DB credentials are server-side only (`config/database.php`, gitignored + deploy-excluded); no `DB_*` GitHub secrets.

## Known gotchas

<!-- Things that wasted time once. Record so they never do again. -->

- parityrfp FTP server-dir is `cs/<project>/`, not a `public_html/...` path.
- `secrets` cannot be used in a GitHub Actions `if:` — hoist to job `env`, gate on `env`. (Canonical `deploy.yml` already does this.)
- cPanel DB account prefix is account-specific — verify before assuming `aslanadv_`.
