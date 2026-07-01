-- Add updated_by to territory_partners and channel_partners
-- Applied: 2026-06-29

ALTER TABLE territory_partners ADD COLUMN updated_by varchar(100) NOT NULL DEFAULT '' AFTER created_by;
ALTER TABLE channel_partners   ADD COLUMN updated_by varchar(100) NOT NULL DEFAULT '' AFTER created_by;
