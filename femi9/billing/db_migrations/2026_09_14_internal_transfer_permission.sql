-- Add a permission column for the "Internal Stock Transfer" menu section so it
-- can be granted to custom "users" sub-accounts, matching the pattern used for
-- stock_transfers etc. Default 0 (deny); existing sub-users must be explicitly
-- granted access via users_edit.php if they need it.
-- Applied: 2026-09-14

ALTER TABLE admin_log
  ADD COLUMN internal_transfer TINYINT(1) NOT NULL DEFAULT 0 AFTER stock_transfers;
