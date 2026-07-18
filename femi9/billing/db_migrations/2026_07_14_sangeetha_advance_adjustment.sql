-- Manually adjusts SANGEETHA's (stockiest, from_user_id=22182FST17092481056)
-- unadjusted advance payment (id 2477, ₹15,965, dated 2026-06-11) against her
-- oldest unpaid bill, invoice S016 (4569891773CMPST20092454834, dated
-- 2024-09-20, receivable ₹17,985 before this runs).
--
-- Partial adjustment: ₹15,965 < ₹17,985, so S016 will still carry ₹2,020
-- outstanding after this runs. advance_payments.id=2477 will move to
-- status='fully_adjusted' (its own balance fully used up).
--
-- Mirrors exactly what processSingleInvoice() in
-- advance-payment-reconciliation.php does live: insert into the ledger
-- table (advance_payment_adjustments), let the after_adjustment_insert
-- trigger update advance_payments, then apply the same amount to the
-- invoice's receipt row. Does NOT touch advance_payments directly —
-- avoids the exact double-write mistake found and fixed earlier.
--
-- Verify the numbers below (ADVANCE PAYMENT ID, INVOICE ID, AMOUNT) against
-- production before running — they were computed from local data and must
-- match production's real state for this specific advance payment.
-- Applied: 2026-07-14

START TRANSACTION;

INSERT INTO advance_payment_adjustments
    (advance_payment_id, invoice_id, invoice_number, adjusted_amount, adjustment_date,
     adjustment_type, balance_before, balance_after, adjusted_by_user_id, adjusted_by_user_type, remarks)
VALUES
    (2477, '4569891773CMPST20092454834', 'S016', 15965.00, CURDATE(),
     'invoice', 15965.00, 0.00, 'company', 'company',
     'Auto-adjusted against oldest unpaid bill (S016) for Sangeetha (stockiest)');

UPDATE receipt
SET received   = received   + 15965.00,
    receivable = receivable - 15965.00
WHERE inv_id = '4569891773CMPST20092454834';

COMMIT;

-- Verify
SELECT id, amount, adjusted_amount, balance_amount, status FROM advance_payments WHERE id = 2477;
SELECT inv_id, received, receivable FROM receipt WHERE inv_id = '4569891773CMPST20092454834';
