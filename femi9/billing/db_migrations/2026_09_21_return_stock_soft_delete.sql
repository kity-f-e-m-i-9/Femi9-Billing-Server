-- Adds soft-delete support to the credit-note return tables. Deleting a
-- credit note (whole note via cnote_del.php, or a single item via
-- cnote_delete.php) previously hard-DELETEd rows and reversed stock with a
-- raw, un-logged UPDATE — losing all audit trail and, for items that had
-- never actually been finished (no stock ever applied), silently
-- subtracting stock that was never credited. See femi9/billing/company/
-- cnote_delete.php and cnote_del.php for the corresponding code change:
-- both now set deleted_at instead of deleting, and any stock reversal goes
-- through StockService so it's ledger-logged like everywhere else.
--
-- Nullable, additive — no backfill, no existing row is touched.

ALTER TABLE user_return_stock
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER gst_type,
  ADD COLUMN deleted_by VARCHAR(255) NULL DEFAULT NULL AFTER deleted_at;

ALTER TABLE user_return_stock_items
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER rwpoints_sls,
  ADD COLUMN deleted_by VARCHAR(255) NULL DEFAULT NULL AFTER deleted_at;
