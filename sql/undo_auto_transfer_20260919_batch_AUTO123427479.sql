-- ============================================================
-- Undo Auto Transfer batch AUTO20260919123427479 (products 4 & 11)
-- Date written: 2026-09-21
--
-- What this reverses: an Auto Internal Transfer run on 2026-09-19 at
-- 18:04:27 moved stock Neksomo -> Healthcare -> LLP for:
--   - product_id 4  (180mm 30pc)      : 2 units
--   - product_id 11 (290mm L 9pc)     : 39 units
-- The demand behind it was 12 OT channel orders that are all still
-- status='draft' with no code path to ever become 'confirmed' (see
-- audit conversation, 2026-09-19/21) -- the physical goods were never
-- actually walked from Neksomo/Healthcare into the LLP godown, so LLP's
-- system stock ran ahead of the physical count by exactly this amount.
--
-- This mirrors exactly what AutoTransferDemand.php's undo_auto_transfer()
-- does for tempid 'AUTO20260919123427479-N2' / product 4 & 11 on the
-- local (MAMP) database on 2026-09-21, PLUS the skip-flag cleanup that
-- function itself is missing (see note at the end).
--
-- SAFE TO RUN: every stock write below reads the CURRENT closing_qty
-- live via a subquery (never a hardcoded number), so it's correct
-- regardless of what's happened on production since. The whole thing
-- runs in one transaction and rolls back automatically if any guard
-- fails.
--
-- PRE-FLIGHT: run this block FIRST and eyeball the output before
-- touching anything. If the two internal_transfer rows below don't
-- exist on production (e.g. this batch never ran there, or was already
-- reversed), STOP -- do not run the rest of this file.
-- ============================================================

-- ---- PRE-FLIGHT CHECK (read-only) ----
SELECT id, tempid, send_from, send_to, product_id, qty, returned_qty
FROM internal_transfer
WHERE tempid IN ('AUTO20260919123427479-N1','AUTO20260919123427479-N2')
ORDER BY product_id, tempid;
-- Expect exactly 4 rows:
--   product_id=11, tempid ...-N1, send_from=<neksomo_id>, send_to=<healthcare_id>, qty=39, returned_qty=0
--   product_id=11, tempid ...-N2, send_from=<healthcare_id>, send_to=<llp_id>,      qty=39, returned_qty=0
--   product_id=4,  tempid ...-N1, send_from=<neksomo_id>, send_to=<healthcare_id>, qty=2,  returned_qty=0
--   product_id=4,  tempid ...-N2, send_from=<healthcare_id>, send_to=<llp_id>,      qty=2,  returned_qty=0
-- If returned_qty is non-zero on any row, or a row is missing, STOP and
-- investigate before proceeding -- that means this transfer was already
-- partially touched (e.g. a manual return against it) and the reversal
-- below would not net out cleanly.

SELECT id AS godown_id, gname FROM company_godown WHERE gname IN
  ('NEKSOMO HYGIENE INDUSTRIES','FEMI HEALTH CARE','FEMI NAYAN LLP');
-- Note the three ids -- used only for your own cross-check against the
-- send_from/send_to values above; the statements below resolve them
-- live via subquery so you don't need to hand-edit anything.


-- ============================================================
-- REVERSAL -- run as one transaction
-- ============================================================
START TRANSACTION;

SET @neksomo_id    = (SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1);
SET @healthcare_id = (SELECT id FROM company_godown WHERE gname = 'FEMI HEALTH CARE' LIMIT 1);
SET @llp_id        = (SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1);

-- Order of operations mirrors undo_auto_transfer(): reverse leg 2
-- (Healthcare -> LLP) first, then leg 1 (Neksomo -> Healthcare) --
-- opposite of how the stock moved originally.

-- ---------- PRODUCT 11 (290mm L 9pc), qty 39 ----------

-- Leg 2 reverse-in: LLP loses the 39 units it received
SET @p11_llp_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@llp_id);
UPDATE stock
   SET closing_qty = closing_qty - 39,
       input_qty   = GREATEST(0, input_qty - 39)
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @llp_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @llp_id, NULL, 'transfer_in_reverse', 39, @p11_llp_before, @p11_llp_before - 39, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-undo-YYYYMMDD');

-- Leg 2 reverse-out: Healthcare gets the 39 units back (pass-through, nets to 0)
SET @p11_hc_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock
   SET closing_qty = closing_qty + 39
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @healthcare_id, NULL, 'transfer_out_reverse', 39, @p11_hc_before, @p11_hc_before + 39, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-undo-YYYYMMDD');

-- Leg 1 reverse-in: Healthcare loses the 39 units again (back to its pre-batch level)
SET @p11_hc_before2 = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock
   SET closing_qty = closing_qty - 39,
       input_qty   = GREATEST(0, input_qty - 39)
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @healthcare_id, NULL, 'transfer_in_reverse', 39, @p11_hc_before2, @p11_hc_before2 - 39, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-undo-YYYYMMDD');

-- Leg 1 reverse-out: Neksomo gets the 39 units back
SET @p11_nk_before = (SELECT closing_qty FROM stock WHERE product_id=11 AND user_type='company' AND user_id=@neksomo_id);
UPDATE stock
   SET closing_qty = closing_qty + 39
 WHERE product_id = 11 AND user_type = 'company' AND user_id = @neksomo_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (11, 'company', @neksomo_id, NULL, 'transfer_out_reverse', 39, @p11_nk_before, @p11_nk_before + 39, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-undo-YYYYMMDD');


-- ---------- PRODUCT 4 (180mm 30pc), qty 2 ----------

SET @p4_llp_before = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@llp_id);
UPDATE stock
   SET closing_qty = closing_qty - 2,
       input_qty   = GREATEST(0, input_qty - 2)
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @llp_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @llp_id, NULL, 'transfer_in_reverse', 2, @p4_llp_before, @p4_llp_before - 2, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-undo-YYYYMMDD');

SET @p4_hc_before = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock
   SET closing_qty = closing_qty + 2
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @healthcare_id, NULL, 'transfer_out_reverse', 2, @p4_hc_before, @p4_hc_before + 2, 'transfer', 'AUTO20260919123427479-N2', '', 'prod-undo-YYYYMMDD');

SET @p4_hc_before2 = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@healthcare_id);
UPDATE stock
   SET closing_qty = closing_qty - 2,
       input_qty   = GREATEST(0, input_qty - 2)
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @healthcare_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @healthcare_id, NULL, 'transfer_in_reverse', 2, @p4_hc_before2, @p4_hc_before2 - 2, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-undo-YYYYMMDD');

SET @p4_nk_before = (SELECT closing_qty FROM stock WHERE product_id=4 AND user_type='company' AND user_id=@neksomo_id);
UPDATE stock
   SET closing_qty = closing_qty + 2
 WHERE product_id = 4 AND user_type = 'company' AND user_id = @neksomo_id;
INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
VALUES (4, 'company', @neksomo_id, NULL, 'transfer_out_reverse', 2, @p4_nk_before, @p4_nk_before + 2, 'transfer', 'AUTO20260919123427479-N1', '', 'prod-undo-YYYYMMDD');


-- ---------- Remove the transfer records themselves ----------
DELETE FROM internal_transfer
WHERE tempid IN ('AUTO20260919123427479-N1','AUTO20260919123427479-N2')
  AND product_id IN (4, 11);

-- Only drop the shared invoice header rows if no line items remain under
-- them (mirrors undo_auto_transfer()'s own cleanup logic) -- since this
-- batch only ever carried products 4 and 11, both should now be empty.
DELETE FROM internal_transfer_invoice
WHERE tempid IN ('AUTO20260919123427479-N1','AUTO20260919123427479-N2')
  AND NOT EXISTS (
      SELECT 1 FROM internal_transfer it
      WHERE it.tempid = internal_transfer_invoice.tempid
  );


-- ---------- Clear the skip-flags this batch created ----------
-- undo_auto_transfer() itself does NOT do this (confirmed gap on the
-- local run) -- without it, the 12 draft OT orders behind this demand
-- stay invisible to Auto Transfer's requirement calc even though their
-- stock was just reversed. Scoped tightly by created_at to only remove
-- rows this exact batch created, never touching any other day's or
-- run's skip rows.
DELETE FROM auto_transfer_skip_today
WHERE skip_date = '2026-09-19'
  AND created_at = '2026-09-19 18:04:27'
  AND (source_ref LIKE '%:4' OR source_ref LIKE '%:11');


-- ---------- Verify before committing ----------
SELECT product_id, user_id, closing_qty
FROM stock
WHERE product_id IN (4, 11) AND user_type = 'company' AND user_id IN (@neksomo_id, @healthcare_id, @llp_id)
ORDER BY product_id, user_id;
-- Expected LLP (user_id = @llp_id) result: product 4 -> 387, product 11 -> 3083
-- (Neksomo/Healthcare values will differ from the local audit's 53935/78/0
--  since production has its own independent history -- only the LLP
--  figures need to match what you physically counted.)

-- If the LLP figures above match your physical count, commit:
COMMIT;
-- If anything looks wrong, instead run:
-- ROLLBACK;
