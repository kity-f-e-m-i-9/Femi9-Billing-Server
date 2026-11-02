-- Daily history log for "Manage Daily Assign District TPs".
-- territory_partner_locations (tp_tables.sql) only ever holds the CURRENT
-- TP-per-firka mapping; this table additionally records one row per
-- (date, firka) every time that mapping is saved from the admin page, so
-- past assignments can be looked up by date range / month / DM later.

CREATE TABLE IF NOT EXISTS `tp_daily_assignments` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assign_date`          DATE NOT NULL,
  `location_id`          INT UNSIGNED NOT NULL,
  `territory_partner_id` INT UNSIGNED NULL DEFAULT NULL,
  `assigned_by`          VARCHAR(100) NULL DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tda_date_location` (`assign_date`, `location_id`),
  KEY `idx_tda_location` (`location_id`),
  KEY `idx_tda_tp` (`territory_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
