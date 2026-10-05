-- Extends the View/Edit/Delete/All granular permission model to the
-- Channel Partner and Territory Partner RECORD itself — i.e. the Edit/
-- Delete buttons on manage-channel-partner.php / manage-territory-partner.php.
-- The existing `channel_partner` / `territory_partner` columns stay the
-- View/base permission (and keep gating every other CP/TP-related page,
-- e.g. invoices, wallet, stock, agreements, zones — unchanged, still a
-- single on/off switch for that whole section).
-- Applied: 2026-10-03

ALTER TABLE admin_log
  ADD COLUMN channel_partner_edit     TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_partner,
  ADD COLUMN channel_partner_delete   TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_partner_edit,
  ADD COLUMN territory_partner_edit   TINYINT(1) NOT NULL DEFAULT 0 AFTER territory_partner,
  ADD COLUMN territory_partner_delete TINYINT(1) NOT NULL DEFAULT 0 AFTER territory_partner_edit;
