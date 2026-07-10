-- Add permission columns for menu sections (Partner Location, Channel Partner,
-- Territory Partner, Stock Transfers) that were added to femi_menu.php without
-- matching admin_log columns — previously always visible to every "users"
-- sub-user regardless of what was granted at creation. Default 0 (deny),
-- consistent with every other permission column; existing sub-users must be
-- explicitly re-granted access via users_edit.php if they need it.
-- Applied: 2026-07-10

ALTER TABLE admin_log
  ADD COLUMN partner_location TINYINT(1) NOT NULL DEFAULT 0 AFTER remap,
  ADD COLUMN channel_partner TINYINT(1) NOT NULL DEFAULT 0 AFTER partner_location,
  ADD COLUMN territory_partner TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_partner,
  ADD COLUMN stock_transfers TINYINT(1) NOT NULL DEFAULT 0 AFTER territory_partner;
