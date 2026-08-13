-- super-stockist/tp-advance-payment-action.php never set approver_type/
-- approver_ss_id on tp_advance_payments at insert time (only the columns
-- themselves existed, added by 2026_08_12_tp_approver_routing.sql, all
-- rows defaulting to 'company'). This left super-stockist/manage-tp-advance-payments.php
-- (once tightened to filter on approver_type='ss') showing zero historical
-- payments even for the ones an SS genuinely recorded themselves, while
-- add-tp-invoice.php's balance widget (already correctly scoped) never saw
-- them either — a TP could have an "active" balance visible on the SS's
-- payment list yet ₹0 spendable on invoicing, with no visible reason why.
--
-- Resolves historical SS-recorded payments the same way the tp_invoices
-- backfill did: created_by stores the phone number used to log in, which
-- reliably resolves to exactly one super_stockiest row via mobile_number.
-- Scoped to company_id IS NULL rows for TPs actually assigned to that SS —
-- company_id IS NULL is necessary (Company's own action always sets it)
-- but not sufficient on its own (a TP's own screenshot-to-payment
-- conversion also leaves it NULL and is not SS-created), so the
-- created_by-to-mobile_number match is what actually confirms SS
-- authorship, not the NULL check.
--
-- Idempotent — safe to re-run: only ever claims rows still at the
-- untouched 'company' default, never flips an already-decided row.

UPDATE tp_advance_payments tap
JOIN territory_partners tp ON tp.id = tap.territory_partner_id
JOIN super_stockiest ss
  ON ss.mobile_number COLLATE utf8mb4_general_ci = tap.created_by COLLATE utf8mb4_general_ci
 AND ss.temp_id COLLATE utf8mb4_general_ci = tp.onboard_ss_id COLLATE utf8mb4_general_ci
SET tap.approver_type = 'ss', tap.approver_ss_id = ss.id
WHERE tap.approver_type = 'company'
  AND tap.company_id IS NULL;
