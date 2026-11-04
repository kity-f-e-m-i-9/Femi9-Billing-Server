# Femi9 Billing Server — Session Handoff Notes

Repo root: `D:\xampp\htdocs\Femi9-Billing-Server`, working branch `jd`.

## Done and pushed (commits 57afe3bd, 68e28ce8)
- Channel-wise sales report, dashboard collation-crash fix, ms-team-shop-view perf fix.
- Marketing dashboard: DM/ASM gets an alert modal on login if a new area was assigned to them today.
- Reward Points restricted to Stockist only (menu links, `reward_points.php`, `reward_points_export.php`, `LegacyRolePoints.php`).

## Uncommitted / not yet pushed (pending user confirmation to commit+push)
1. **`delete-territory-partner.php`** — soft-deleting a TP now also hard-deletes its rows from `territory_partner_locations` (that table has no historical/reporting value, unlike `territory_partners` itself). Fixes a bug where a deleted TP's assigned locations stayed permanently "locked."
2. **`salesbdm_add.php` / `salesbdm_edit.php`** + new **`get-zone-districts.php`** — selecting a Zone auto-fills "Assign Location" with that zone's districts (admin can still add/remove after); never mutates the zone's own underlying district definition.
3. **New product flag `visible_to_tp_cp`** (self-migrating column, `products` table) — gates ONLY the TP/CP "place a fresh PO to Company/Super Stockist" screens. Does NOT affect Get Order (DM) and does NOT affect any flow where a partner already holds physical stock (those stay gated purely by `closing_qty > 0`). Touches: `edit-product.php`, `product-action.php`, `channel-partner/add-purchase-order.php`, `channel-partner/purchase-order-action.php`, `territory-partner/add-purchase-order.php`, migration doc `db_migrations/2026_11_03_products_visible_to_tp_cp.sql`.
4. **TP "Submit To: Super Stockist"** now shows the SS's actual live stock instead of the static catalog — new `territory-partner/get-ss-products.php`, generalized the existing CP-switch-warning JS mechanism in `add-purchase-order.php` to also cover `ss`.

Only one DB schema change across all of the above: `products.visible_to_tp_cp` (self-migrating — every consuming file runs `SHOW COLUMNS`/`ALTER TABLE` itself on first use).

## Still open / needs user input
- Whether to commit+push the 11 real-work files above (8 modified + 3 new) — not yet confirmed.
- TP-0052 (P PRIYA), soft-deleted 2026-08-28, still stuck holding location "Thirunindravur" via the old pre-fix delete path — one-time manual cleanup, not yet confirmed.
- No self-service "SS places PO to Company" screen was found anywhere in the codebase (SS menu only has "TP Purchase Orders" for approving TP's own orders) — unclear if this needs the same `visible_to_tp_cp` gating; never resolved.
- Live site 500 error on `femi9billing.com/femi9/billing/login/authenticate.php` — confirmed via code reading that no file in this session's changes (committed or not) touches the login/session/auth chain. Root cause itself was never found (Apache error log only showed bot-scan noise; cPanel "Errors" search for "authenticate.php" returned 0/0). No confirmation retry succeeded.
- "Singaperumalkoil" location shows vacant — this is correct/expected (genuinely unassigned after a cascade-delete), user needs to manually reassign a TP via Edit Territory Partner.

## Resolved via live DB fix (SQL handed to user, not yet confirmed run)
Internal Stock Transfer invoice `tempid=AUTO20260923105221085-N1`, line "330mm XL (DW-9 PCS)" showed Rate=0.00/Amount=0.00. Root cause: `internal_transfer.price` was 0 for that one line (row `id=2920`, product_id=51, qty=7) at original data-entry time (23 Sep 2026) — not a code bug (`internal_transfer_print.php` derives Rate/Amount purely from stored per-row `price`/`taxable_value`, never a live product-price lookup; no edit UI exists for these line items, only `internal_transfer_delete.php`).

Fix SQL (scoped strictly by unique primary key `id = 2920`, so only this one row/invoice is affected):
```sql
UPDATE internal_transfer SET price = 76.00, taxable_value = 532.00 WHERE id = 2920;
```
(76.00 matches the sibling "330mm XL (SW-9 PCS)" line's rate; 532.00 = 7 × 76, discount=0, gst_type=exclusive gst=0%.) Invoice Total is computed live at print time by summing line items — no other table/row needs updating.

## Conventions learned this session
- **Self-migrating schema pattern**: any new column must be added by each consuming file itself (`SHOW COLUMNS ... LIKE` then `ALTER TABLE ... ADD COLUMN`), not a one-time migration script.
- **Soft-delete convention**: `deleted_at` TIMESTAMP NULL = active. Historical tables are never hard-deleted/cascaded — except `territory_partner_locations`, which is a pure current-assignment map with no historical value.
- **Three independent product visibility flags**, never conflate: `deleted_at` (Status — gates OT sales/stock transfer/general pickers), `visible_in_get_order` (gates only Marketing Staff's Get Order), `visible_to_tp_cp` (gates only TP/CP's own fresh-PO picker). None of these ever gate flows where a partner moves stock they already physically hold.
- **Credential handling**: never `grep`/`cat` DB credentials out of `config.php` directly (blocked by tool policy anyway). Instead write a standalone scratchpad PHP script that `require_once`s `femi9/billing/shared/env-loader.php` (loads `.env` into `$_ENV`) and connects via `mysqli_connect($_ENV['DB_HOST'], ...)`. Never print credential values — only query results.
- **No live DB access** — only local XAMPP DB (separate, out-of-sync dataset). Any live-data fix must be handed to the user as exact SQL to run themselves via phpMyAdmin.
- Local XAMPP MySQL can be unreachable at times (`mysqli_connect` "actively refused" / exit 255) — may need a MySQL service restart if local DB work is needed again.

## Prior memory notes (from persistent memory, still relevant)
- Confirm which folder is actually live before editing (has flipped before — don't assume `-jd` suffix).
- After any DB reimport, check indexes on `invoice`/`ms_orders`/`user_invoice` before assuming a code bug.
- The "Fix District" feature (`add-district-note.php` district-pinning toggle) is complete — don't suggest changes unasked.
- Never `git push` without asking first, even after committing locally.
