-- tp_purchase_orders.preferred_cp_id was only ever created lazily by
-- territory-partner/purchase-order-action.php's self-migrating ALTER
-- (added on the first PO submission after that feature shipped). Any
-- environment where that code path hadn't run yet was missing the column
-- entirely, causing a fatal error ("Unknown column 'preferred_cp_id'") on
-- company/add-tp-invoice.php and company/tp-invoice-action.php, both of
-- which SELECT it unconditionally with no such guard.
ALTER TABLE tp_purchase_orders
  ADD COLUMN preferred_cp_id INT NULL AFTER approver_ss_id;
