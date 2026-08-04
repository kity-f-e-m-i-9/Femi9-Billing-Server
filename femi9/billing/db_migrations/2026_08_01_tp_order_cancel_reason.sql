-- TP's "Cancel" action on a not-yet-invoiced Get Order now requires a
-- reason, shown back to the DM. Safe to re-run.
ALTER TABLE tp_orders ADD COLUMN void_reason VARCHAR(255) NULL DEFAULT NULL AFTER voided_at;
