-- Pilot for granular View/Edit/Delete sub-permissions (starting with the
-- "Manage Input Stock" module). The existing `manage_input_stock` column
-- stays as the View/base permission; these two new columns let a company
-- grant a "users" sub-account the ability to see the list without also
-- being able to edit or delete entries.
-- Applied: 2026-10-03

ALTER TABLE admin_log
  ADD COLUMN manage_input_stock_edit   TINYINT(1) NOT NULL DEFAULT 0 AFTER manage_input_stock,
  ADD COLUMN manage_input_stock_delete TINYINT(1) NOT NULL DEFAULT 0 AFTER manage_input_stock_edit;
