-- Physical/actual stock counts a DM or ASM records against a Territory
-- Partner after visiting them — distinct from territory_partner_stock
-- (the TP's own input/sale ledger). One row per (TP, product); re-saving
-- the same product overwrites it, which is how an existing entry gets
-- "edited" from the Actual Stock modal on my-assigned-tps.php.
--
-- Self-created too: every endpoint that reads/writes this table also runs
-- this same CREATE TABLE IF NOT EXISTS itself, so a forgotten manual run of
-- this file on any environment can never surface as a "Failed to save"
-- error (see tp_daily_assignments for the prior incident this avoids).

CREATE TABLE IF NOT EXISTS `tp_actual_stock_entries` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `territory_partner_id`  INT UNSIGNED NOT NULL,
  `product_id`            INT UNSIGNED NOT NULL,
  `qty`                   INT NOT NULL DEFAULT 0,
  `updated_by`            VARCHAR(100) NULL DEFAULT NULL,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tase_tp_product` (`territory_partner_id`, `product_id`),
  KEY `idx_tase_tp` (`territory_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
