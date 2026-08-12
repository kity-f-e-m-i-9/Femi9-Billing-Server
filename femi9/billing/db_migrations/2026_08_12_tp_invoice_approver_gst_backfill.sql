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
-- MySQL has no ADD COLUMN IF NOT EXISTS, so this is a guarded procedure
-- instead of plain DDL — safe to re-run any number of times. The backfill
-- UPDATE only ever narrows (sets 'company'), never re-widens an existing
-- 'company' row back to 'ss'.

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
