-- tp_invoices never got approver_type/approver_ss_id when the rest of the
-- approver-routing feature added them to tp_purchase_orders,
-- tp_advance_payments, and tp_advance_payment_submissions. This migration
-- only adds the columns and backfills existing data — it deliberately does
-- NOT change any query/filter/enforcement logic yet (that's a separate,
-- later step once the GST/non-GST split between Company and SS billing is
-- decided).
--
-- Any existing invoice containing at least one GST product (products.gst >
-- 0) is backfilled to approver_type='company' here, matching
-- tp_purchase_orders' column shape (approver_ss_id stores
-- super_stockiest.id, not a FK, same convention as the sibling tables).
--
-- Historical invoices actually created by an SS (created_by_user_type=
-- 'super_stockiest') never had created_by_user_id populated (a pre-existing
-- bug in the old INSERT — bound but silently left blank), so the SS can't
-- be identified by that column. created_by stores the phone number used to
-- log in, which does reliably resolve to exactly one super_stockiest row
-- via mobile_number — used here only for this one-time historical backfill.
-- Going forward, super-stockist/tp-invoice-action.php sets approver_type/
-- approver_ss_id directly at insert time, so this resolution path is never
-- needed for new invoices.
--
-- Order matters: GST-forces-company runs first, then SS-ownership only
-- claims rows still at the default 'company' — so a GST invoice an SS
-- happened to create stays under Company either way.
--
-- MySQL has no ADD COLUMN IF NOT EXISTS, so this is a guarded procedure
-- instead of plain DDL — safe to re-run any number of times. Both backfill
-- UPDATEs are safe to re-run: the GST one always re-asserts 'company' for
-- GST invoices, and the SS-ownership one only touches rows still at the
-- untouched default, so re-running never flips an already-decided row.

DELIMITER $$

DROP PROCEDURE IF EXISTS _tp_invoices_approver_backfill $$
CREATE PROCEDURE _tp_invoices_approver_backfill()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tp_invoices' AND COLUMN_NAME = 'approver_type'
    ) THEN
        ALTER TABLE tp_invoices
          ADD COLUMN approver_type ENUM('company','ss') NOT NULL DEFAULT 'company' AFTER territory_partner_id,
          ADD COLUMN approver_ss_id INT UNSIGNED NULL AFTER approver_type,
          ADD KEY idx_tpi_approver (approver_type, approver_ss_id);
    END IF;
END $$

DELIMITER ;

CALL _tp_invoices_approver_backfill();
DROP PROCEDURE IF EXISTS _tp_invoices_approver_backfill;

UPDATE tp_invoices ti
SET ti.approver_type = 'company', ti.approver_ss_id = NULL
WHERE EXISTS (
    SELECT 1 FROM tp_invoice_items tii
    JOIN products p ON p.id = tii.product_id
    WHERE tii.tp_invoice_id = ti.id AND p.gst > 0
);

UPDATE tp_invoices ti
JOIN super_stockiest ss
  ON ss.mobile_number COLLATE utf8mb4_general_ci = ti.created_by COLLATE utf8mb4_general_ci
SET ti.approver_type = 'ss', ti.approver_ss_id = ss.id
WHERE ti.created_by_user_type = 'super_stockiest'
  AND ti.approver_type = 'company'
  AND NOT EXISTS (
      SELECT 1 FROM tp_invoice_items tii
      JOIN products p ON p.id = tii.product_id
      WHERE tii.tp_invoice_id = ti.id AND p.gst > 0
  );
