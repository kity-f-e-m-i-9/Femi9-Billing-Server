# Femi9 Billing Server — Session Handoff, Part 2 (2026-11-12)

Repo: `D:\xampp\htdocs\Femi9-Billing-Server`, branch `jd`. User communicates in Tanglish.
This continues `SESSION-HANDOFF-2026-11-12.md` (same repo root) — read that one first for the
earlier part of this session (Channel Partner Login-As, OT Sales export, CP Advance/Balance
columns, TP Invoice Report category filter — all already pushed to `origin/jd`, confirmed).
This file covers everything that happened *after* that push-confirmation, in the same session.

## 1. Push-status re-confirmation (recap, no new work)
User asked "already push panni irukanu check pannu pathuttu sollu" — confirmed via `git log`/
`git status -sb` that local `jd` and `origin/jd` are byte-identical (no ahead/behind), and that
commit `2bef6883` already contains the OT-sales-export changes, CP advance/balance columns, and
TP invoice-report category filter; commit `e70e6913` already contains the Channel-Partner
Login-As bridge. Nothing left uncommitted. Full detail in Part 1.

User then asked for a full written summary to hand to a different Claude session — produced
`SESSION-HANDOFF-2026-11-12.md` (Part 1) and pasted its full text back to the user on request.

## 2. Sales BDM TP Coverage Excel — generated fresh from DB
**What happened:** user showed a screenshot of an existing file `salesbdm_tp_coverage_local_v3 (1).xlsx`
(tabs per BDM: Arun, Asiya.A, Dhivya, Indumathi, Sabeena, Santhini, Swetha — columns S.No,
District, Firka, Target Amount, TP Name, TP Phone Number, TP Address) and asked for "intha
mathiri salesbdm wise" (salesbdm-wise, in this format). AskUserQuestion clarified: generate fresh
from DB (not just reuse the old file), and user supplied the file path from Downloads.

**Discovery:** this exact feature already exists in the codebase —
`femi9/billing/company/salesbdm-tp-coverage-export.php`, linked from the company sidebar menu as
**"TP Coverage Export (per BDM)"** (`femi_menu.php` lines 764 and 1282). The downloaded file the
user showed was literally this export's own output (filename pattern `salesbdm_tp_coverage_<date>.xlsx`
matches exactly). I initially just pointed the user to this menu item instead of running it for
them — **user pushed back**: "ipo enakku iruka database ahh vechu ready panni kudunu ketta nee
etho sidebar nu la vechutttu iruka" (I asked you to prepare it using my database, you just keep
pointing at some sidebar). **Lesson for next time: when a feature already exists and the user
asks for the output, generate it for them directly — don't just point at the UI path**, unless
they're asking how to do it themselves going forward.

**What I actually did:** wrote a scratchpad PHP script
(`gen_salesbdm_coverage.php`) that connects straight to the **local XAMPP DB** (via
`shared/env-loader.php`, `getenv('DB_HOST'/'DB_USERNAME'/'DB_PASSWORD'/'DB_NAME')` — note: this
`.env` uses `DB_USERNAME`/`DB_PASSWORD`, not `DB_USER`/`DB_PASS`) and replays the *exact* same
query/build logic as `salesbdm-tp-coverage-export.php` (not a reimplementation — copied
verbatim), then saves the `.xlsx` straight to disk instead of streaming it over HTTP. Ran it
successfully:
```
Active BDMs found: 7
BDMs with assigned districts: 7
Total firka rows loaded: 1054
Saved to: C:/Users/User/Downloads/salesbdm_tp_coverage_2026-11-12.xlsx
```
**Output file**: `C:\Users\User\Downloads\salesbdm_tp_coverage_2026-11-12.xlsx` — 7 sheets (one
per active Sales BDM), same exact layout/styling as the original export (merged title row per
sheet, blue header row, zebra-striped data rows, "Vacant Area" firkas in italic amber pushed to
the bottom of each sheet, frozen header pane, autofilter).

**Underlying data model** (for whoever picks this up next):
- `sales_bdm_staff` (`id`, `bdm_name`, `bdm_mobile`, `account_status`) — the BDM roster.
- `salesbdm_locations` (`bdm_id`, `location_id`) — which location-tree node(s) each BDM is
  assigned to (could be State/District/Division/Taluk depth — collapsed down to District via
  `districtsForAssignment()`, same convention as `marketing/include/AssignedLocations.php`'s
  `getMsAssignedDistricts()`).
- `partner_location_nodes` (`id`, `parent_id`, `depth`, `name`, `is_active`) — the shared
  location hierarchy; depth 3 = District, 4 = Division, 5 = Taluk, 6 = Firka.
- `partner_location_nodes` depth-6 (Firka) rows carry `target_amount` + `diaper_target_amount`
  (summed together as "Target Amount" in the export).
- `territory_partner_locations` (`location_id` → Firka, `territory_partner_id`) maps each Firka
  to the TP currently covering it (a Firka with no row here shows as "Vacant Area").
- **Not yet reconciled**: this was run against the **local DB only** (per this session's standing
  "No live DB access" constraint) — local vs. live may have drifted on BDM roster, location-tree
  assignments, or targets. If the user needs this to exactly match live, it should be regenerated
  by running the real `company/salesbdm-tp-coverage-export.php` directly on the live server
  instead (the sidebar "TP Coverage Export (per BDM)" link does exactly that).

## 3. Channel Partner TP "ASMA" (TP-0312) — "Add Invoice" missing from menu — ROOT CAUSE FOUND, FIX NOT YET APPLIED
**Symptom:** on live, `customer-invoice-add.php` for TP-0312 (ASMA) showed empty Product and
Customer dropdowns. Initial wrong hypothesis (based on stale local DB, which had **zero** rows
in `territory_partner_stock` and `customers` for this TP — local is a separate, sometimes
out-of-sync dataset, confirmed again here) was "no stock, no customer". **User corrected this
twice** with live screenshots: `overall-stock.php` live shows real stock (7 products, 92 total
units: 20/40/10/6/6/5/5), and `customer-manage.php` live shows 1 real customer ("Shakila").

**Real finding** (confirmed by reading code, not guessed): the "Add Invoice" link itself is
**missing from the sidebar menu entirely** for this TP — user's screenshot of the Customer
submenu shows only Add Customer / Manage Customer / Manage Invoice, no Add Invoice. Traced to
`femi9/billing/territory-partner/femi_menu.php` line 4:
```php
$_tp_can_invoice = $_menu_tp_row && (int)$_menu_tp_row['stock_initialized'] === 1;
```
...gating both the Shop and Customer "Add Invoice" links (lines 101, 112). `stock_initialized` on
`territory_partners` is set to `1` **in exactly one place in the whole codebase**:
`femi9/billing/company/add-tp-input-stock-action.php` (lines 45 and 95), i.e. only when the
company runs this specific TP through the "Add TP Input Stock" (opening-stock) screen. Any TP
whose stock arrived through some *other* route (internal transfer, super-stockist shipment, etc.
— there are other paths that insert rows into `territory_partner_stock` directly) ends up with
real stock on `overall-stock.php` but `stock_initialized` still `0`/`NULL`, hiding "Add Invoice"
from their menu even though they have genuine stock to invoice against. **This is confirmed to be
TP-specific** — user explicitly said "intha tp ku mattum tthan ipadi irukku" (this only happens
for this TP), consistent with the theory (most TPs' stock does go through the opening-stock
screen; ASMA's apparently didn't).

**Proposed fix, NOT yet applied — needs the user's go-ahead:**
1. **Immediate unblock for ASMA only** (live DB, one-off):
   ```sql
   UPDATE territory_partners SET stock_initialized = 1 WHERE tp_id = 'TP-0312';
   ```
2. **Structural fix (prevents recurrence for any other TP)**: change `$_tp_can_invoice` in
   `territory-partner/femi_menu.php` to check actual stock existence instead of the one-time flag,
   e.g. `EXISTS (SELECT 1 FROM territory_partner_stock WHERE territory_partner_id = ? AND
   closing_qty > 0)` — or OR the existing flag with that check, whichever the user prefers.

**User has not yet confirmed which of these (or both) to do.** Picking this up: ask first, this
is exactly the kind of "confirm before changing shared behavior" item this codebase's established
conventions call for (see Part 1's "Conventions to keep following").

## 4. TP password resets (plaintext default, same pattern as earlier in the session — see Part 1)
Two more TPs got the standard default-password-reset SQL handed to the user, same pattern as the
TP-0301 case already logged in Part 1 (no in-app reset flow exists; this is the only way):
```sql
UPDATE territory_partners SET password = '$2y$10$sjeRY9Bec5UnOkj.t9pi/OahXkQJ9yw3T5l8Egs3ooCiouoo19Enm'
WHERE tp_id = 'TP-xxxx';
```
resets to plaintext default `Partner@123`. Given this session for:
- **TP-0058 (P.ANANTHAVALLI, mobile 9841844585)** — SQL handed over, **not confirmed as run**.
(TP-0301 from Part 1 also still not confirmed as run.)
**If this keeps coming up, worth revisiting the earlier suggestion to build a real in-app reset
flow** — it's now come up 3 times across this session.

## Still open — needs user input, do not assume (adds to Part 1's list)
- Whether to apply the ASMA `stock_initialized` SQL fix, the structural `femi_menu.php` fix, both,
  or neither — **not yet decided**.
- Whether the local-DB-generated `salesbdm_tp_coverage_2026-11-12.xlsx` is good enough, or whether
  the user wants it regenerated against live (via the existing sidebar export) for an exact match.
- TP-0058 and TP-0301 password resets — SQL handed over for both, **neither confirmed as run**.
- Everything still open from Part 1 (live deployment of the 23-file commit, TP-0150 duplicate shop
  delete, mobile responsiveness) remains open and unchanged.

## Reusable finding for future sessions
- **Local XAMPP DB and live DB drift on *data* (stock, customers, assignments) far more than on
  *schema/code*.** Every time this session relied on local DB to answer "does X exist/how much
  stock does TP Y have," it was wrong at least once and had to be corrected by a live screenshot.
  **Treat local-DB-derived facts about any individual TP/customer/stock-row as provisional until
  confirmed against a live screenshot or live query** — this has now happened enough times in one
  session that it's a real pattern, not a one-off.
- `shared/env-loader.php`'s `.env` keys are `DB_USERNAME`/`DB_PASSWORD` (not `DB_USER`/`DB_PASS`) —
  useful for any future scratchpad DB script, saves a failed-connection round-trip.
