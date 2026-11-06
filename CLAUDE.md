# Femi9 Billing Server — Session Handoff Notes

Repo root: `D:\xampp\htdocs\Femi9-Billing-Server`, working branch `jd`.

## Pushed to origin/jd (commit `3d7c330d`, 23 files) — NOT yet deployed to live server
User explicitly asked which files need manual upload to the live server for this commit. Full list:

**New files (14):**
- `femi9/billing/company/district-tp-reward-points-report.php`
- `femi9/billing/company/district-tp-stock-report.php`
- `femi9/billing/company/export-ot-sale-return-xlsx.php`
- `femi9/billing/company/export-ot-sale-view-xlsx.php`
- `femi9/billing/company/export-product-tp-sales-xlsx.php`
- `femi9/billing/company/get-tp-stock-breakdown.php`
- `femi9/billing/company/product-tp-sales-report.php`
- `femi9/billing/marketing/get-tp-stock-breakdown.php`
- `femi9/billing/marketing/my-assigned-tp-reward-points.php`
- `femi9/billing/marketing/my-assigned-tps.php`

**Modified files (9, full-file replace on live — these had unrelated prior content, check nobody else edited them live since):**
- `femi9/billing/company/femi_menu.php`, `femi9/billing/marketing/femi_menu.php`
- `femi9/billing/company/get-internal-transfer-breakdown.php`
- `femi9/billing/company/include/DatewiseStockReport.php`
- `femi9/billing/company/ot-sale-action.php`, `ot-sale-add.php`, `ot-sale-edit.php`, `ot-sale-import-action.php`, `ot-sale-import-template.php`, `ot-sale-manage-return.php`, `ot-sale-view.php`
- `femi9/billing/company/overall-stock.php`, `overstock_datewise.php`

**No DB schema change in this commit** — every table used (`territory_partner_stock`, `reward_points`, etc.) already existed.

## Uncommitted / not yet pushed — ASM "Daily Assign District TPs" (7 new files + 1 modified)
Marketing-side twin of the existing company-only `manage-daily-assign-district-tps.php`, scoped to the logged-in ASM/DM's own subordinates:
- `femi9/billing/marketing/manage-daily-assign-district-tps.php` (page)
- `femi9/billing/marketing/get-tp-assign-staff.php` (lists subtree minus self — `getMsSubtreeIds()`)
- `femi9/billing/marketing/get-tp-assign-districts.php` (validates `ms_id` ∈ subtree before returning districts)
- `femi9/billing/marketing/get-tp-assign-divisions.php`, `get-tp-assign-firkas.php`, `get-tp-assign-tp-for-firka.php` (read-only location-tree lookups, straight ports of the company versions)
- `femi9/billing/marketing/tp-assign-firka-action.php` (the save/write endpoint — re-derives the ASM's own subtree's allowed District ids server-side and rejects any `firka_id` outside it, even if the client bypassed the dropdowns; tested and confirmed blocking)
- `femi9/billing/marketing/femi_menu.php` modified — link gated behind `$hasTeamMN` (same flag "My Team" already uses), so it only shows for staff who actually have subordinates.

Writes to the same `tp_daily_assignments` table the company page already uses, so both pages' history shows up together on `daily-assign-report.php`. Not yet asked whether to push — ask before committing.

## Key bugs found and fixed this session
1. **Division-scoped DM bug** in `my-assigned-tps.php` / `my-assigned-tp-reward-points.php` (now fixed, in the pushed commit): both originally resolved a DM's TP roster by matching the scope node's **name** against the District table (`getMsDistrictTpIds`). A DM assigned at **Division** depth (e.g. Prabhu B → "Chennai Central B") isn't a District-depth row, so this silently returned **zero TPs**. Fixed by switching to **node-id** lookup (`getMsNodeTpIds`), matching the pattern `marketing/dashboard.php` already uses for a solo DM's own roster (`$allTpIds = getMsNodeTpIds($db_conn, $statsScopeIds, true)`).
2. **Pagination self-shrinking bug** in `district-tp-stock-report.php`: the paginator read `row.style.display` to decide what was "in scope" for the next page — but it had just set that same property itself, so each click shrank the visible pool. Fixed by tracking search-matches via a separate `data-search-match` attribute instead of reusing `style.display` for two different purposes. Also added windowed pagination (max 5 page-number buttons + …).
3. **Missing sidebar JS** on all new pages (`district-tp-stock-report.php`, `product-tp-sales-report.php`, `my-assigned-tps.php` etc.) — forgot the standard footer `<script>` block (jQuery, popper, bootstrap.min.js, perfect-scroll, pace, `main.min.js`) that actually drives the sidebar accordion menu. Every new page now includes it.
4. **Wrong column name** `mobile_number` used instead of the real `territory_partners.mobile` column in two new reports — caught via a local-DB dry run before ever hitting a browser.
5. **Local DB missing `tp_daily_assignments` table** — `tp-assign-firka-action.php`'s "Failed to save" turned out to be this one-time-schema table (`company/tp-daily-assignment-schema.sql`) never having been run on the local XAMPP DB (unrelated to any of this session's code changes). Created it locally via `CREATE TABLE IF NOT EXISTS`. **If live ever shows the same "Failed to save" error, check the same thing there** — but this table should already exist live since the company-side feature was presumably working before.

## Testing pattern established this session (reuse this)
No PHP built-in server, no direct HTTP access to this Apache/XAMPP install from the agent — so real end-to-end testing is done by **simulating an authenticated session and `include`-ing the target file directly via CLI**, not just `php -l`:
```php
// run_page.php / run_page_raw.php / run_page_post.php (scratchpad) — e.g.:
chdir($cwd); session_start();
foreach ($sessionData as $k=>$v) $_SESSION[$k]=$v;   // LOGIN_USER, LOGIN_USER_TYPE, LOGIN_USER_ID
$_GET = $getData;  // or $_POST + REQUEST_METHOD='POST' for action endpoints
include $file;
```
Real company admin session: `admin_log.username='9585711510'` (usertype `admin`, bypasses all `requirePermission()` checks). Real marketing sessions used for testing: ms id `68` (Prabhu B, Division-scoped DM — Chennai Central B), ms id `37` (Sivarama Krishnan, ASM with 8 subordinate DMs), ms id `1` (LOGESH A, DM — Erode district, used in the Daily-Assign screenshot). This caught 2 of the 5 bugs above that `php -l` alone would never have found. User explicitly asked for every page to be run **twice** per test to confirm determinism — kept doing that throughout.

## Still open / needs user input
- Whether to commit+push the ASM Daily Assign District TPs feature (7 files + femi_menu.php) — not yet asked.
- Whether/how to deploy the already-pushed 23-file commit (`3d7c330d`) to the live server — user asked for the file list (given above) but hasn't confirmed doing the actual upload.
- Mobile responsiveness of the new report pages was asked about — current answer given: viewport meta + `table-responsive` wrapper present (same convention as existing dashboard tables), but tables scroll horizontally on narrow screens rather than reflowing into cards. No redesign done; only flagged as an option if wanted.
- **TP-0150 (Chandra C.P) duplicate shop "Marutham mart" (`shop.id = 32505`)** — user explicitly wanted this deleted via direct SQL on the live DB, not a code change. I had initially added a Delete button to `territory-partner/shop-manage.php` + `shop-delete.php`; user said they didn't want that, so it was fully reverted via `git checkout --` (confirmed back to original — no Delete button, no ownership check). Handed user `DELETE FROM shop WHERE id = 32505;` after they confirmed via a SELECT that it's the one duplicate row. Not confirmed as run.
- **TP-0301 (Radhika Santhoshkumar, mobile 9894756870) forgot their password** — there is no working "reset password" UI for Territory Partners (`manage-territory-partner.php`'s `decryptPassword()` always returns `null`; its "Copy password" button is dead since passwords are one-way bcrypt hashes). Handed the user a SELECT to confirm identity by `tp_id`, then:
  ```sql
  UPDATE territory_partners SET password = '$2y$10$sjeRY9Bec5UnOkj.t9pi/OahXkQJ9yw3T5l8Egs3ooCiouoo19Enm' WHERE tp_id = 'TP-0301';
  ```
  resets to plaintext default `Partner@123` (same default as new-TP creation in `territory-partner-action.php`). Not confirmed as run. **If this comes up again for other TPs, same approach applies** — there's no in-app reset flow yet; consider offering to build one if it recurs often.
- **Unexplained/untracked files, never resolved**: none currently outstanding — `export-product-tp-sales-xlsx.php` and `product-tp-sales-report.php` (previously flagged as mysteriously untracked) are now accounted for, committed in `3d7c330d`.

## Prior memory notes (from persistent memory, still relevant)
- Confirm which folder is actually live before editing (has flipped before — don't assume `-jd` suffix).
- After any DB reimport, check indexes on `invoice`/`ms_orders`/`user_invoice` before assuming a code bug.
- The "Fix District" feature (`add-district-note.php` district-pinning toggle) is complete — don't suggest changes unasked.
- Never `git push` without asking first, even after committing locally.

## Conventions carried over from earlier sessions (still relevant)
- **Self-migrating schema pattern**: any new column is added by each consuming file itself (`SHOW COLUMNS ... LIKE` then `ALTER TABLE ... ADD COLUMN`), not a one-time migration script — except genuinely new tables (like `tp_daily_assignments`), which do get a one-time `.sql` file that must be run manually once.
- **Credential handling**: never `grep`/`cat` DB credentials out of `config.php` directly. Instead write a standalone scratchpad PHP script that `require_once`s `femi9/billing/shared/env-loader.php` and connects via `mysqli_connect($_ENV['DB_HOST'], ...)`. Never print credential values — only query results.
- **No live DB access** — only local XAMPP DB (separate, sometimes out-of-sync dataset — confirmed this session to be live/current enough for October 2026 data, used for several of the above tests).
