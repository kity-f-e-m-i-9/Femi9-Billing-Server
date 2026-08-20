-- Phase 3 of the Napkin/Diaper separation project — every TP advance
-- payment now belongs to a wallet (Napkin or Diaper), drawn down
-- independently by shared/TpAdvanceService.php's deduct/restore functions,
-- keyed off the invoice's own product_type (Phase 2).
--
-- Column default is 'napkin' — existing rows must be reclassified by the
-- companion PHP backfill script (company/db_migrations_scripts/backfill_tp_advance_product_type.php),
-- NOT by a blanket UPDATE here, since the correct value is data-driven: it's
-- inferred per-row from what that specific payment actually funded via
-- tp_invoice_advance_log -> tp_invoices.product_type, with 'napkin' only as
-- the fallback for rows with no purchase history yet or genuinely mixed
-- history (see the backfill script's own comments for the exact rule).

ALTER TABLE tp_advance_payments
  ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id,
  ADD KEY idx_tpap_ptype (territory_partner_id, product_type);

ALTER TABLE tp_advance_payment_submissions
  ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id;

-- tp_advance_payment_screenshots has no territory_partner_id of its own —
-- it's supporting evidence tied to a submission via submission_id, so its
-- type is always whatever tp_advance_payment_submissions.product_type says
-- for that submission_id. No column needed here.
