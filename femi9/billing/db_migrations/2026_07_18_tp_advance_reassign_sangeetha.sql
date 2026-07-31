-- Fixes a real bug: when TP Invoice TP/26-27/156 (id 210, ₹10,290.00, TP-0208
-- E. Sangeetha) was created, TpAdvanceService's FIFO advance-deduction logic
-- incorrectly selected a SOFT-DELETED advance payment (id 184, deleted
-- 2026-07-07, 9 days before this invoice existed) as the funding source,
-- deducting the full ₹10,290 from it — instead of the two real, active,
-- unadjusted payments that actually total ₹10,290 (id 309 = ₹10,000,
-- id 361 = ₹290).
--
-- This reverses the wrong deduction on payment 184 (back to its clean
-- pre-deduction state) and applies the correct deduction to payments 309
-- and 361 against the same invoice. Tested against a local recreation of
-- production's exact current state before being handed off — see chat.
--
-- IDs are specific to this one incident (E. Sangeetha / TP-0208 / invoice
-- TP/26-27/156) — do not reuse this file for any other TP/invoice without
-- re-deriving the ids from that case's own diagnostic queries.
-- Applied: 2026-07-18

START TRANSACTION;

-- 1) Reverse the wrong deduction on deleted payment 184
DELETE FROM tp_invoice_advance_log WHERE id = 287 AND tp_advance_id = 184 AND tp_invoice_id = 210;

UPDATE tp_advance_payments
SET adjusted_amount = 0.00,
    balance_amount   = 16000.00,
    status            = 'active'
WHERE id = 184;

-- 2) Apply the correct deduction to the two real payments
INSERT INTO tp_invoice_advance_log (tp_invoice_id, tp_invoice_number, tp_advance_id, deducted_amount, created_at)
VALUES
  (210, 'TP/26-27/156', 309, 10000.00, NOW()),
  (210, 'TP/26-27/156', 361, 290.00, NOW());

UPDATE tp_advance_payments SET adjusted_amount = 10000.00, balance_amount = 0.00, status = 'fully_adjusted' WHERE id = 309;
UPDATE tp_advance_payments SET adjusted_amount = 290.00,   balance_amount = 0.00, status = 'fully_adjusted' WHERE id = 361;

COMMIT;

-- Verify
SELECT id, amount, adjusted_amount, balance_amount, status, deleted_at FROM tp_advance_payments WHERE id IN (184, 309, 361);
SELECT * FROM tp_invoice_advance_log WHERE tp_invoice_id = 210;
