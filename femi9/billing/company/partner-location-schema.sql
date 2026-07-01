-- Partner Location Nodes Table
-- Mirrors the distribution_location_nodes concept from the Femi9 Billing Site.
-- Self-referencing tree: root nodes have parent_id = NULL (depth = 1),
-- children inherit depth = parent.depth + 1.

CREATE TABLE IF NOT EXISTS `partner_location_nodes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`  INT UNSIGNED NULL DEFAULT NULL,
  `depth`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `name`       VARCHAR(150) NOT NULL,
  `code`       VARCHAR(50) NULL DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pln_parent` (`parent_id`),
  KEY `idx_pln_depth`  (`depth`),
  CONSTRAINT `fk_pln_parent`
    FOREIGN KEY (`parent_id`)
    REFERENCES `partner_location_nodes` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
