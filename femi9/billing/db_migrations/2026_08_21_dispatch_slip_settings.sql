-- One global, editable number (not hardcoded) driving the dispatch slip's
-- shipment-level "Total Boxes" estimate — see company/dispatch-slip-print.php.
-- Single-row table (id=1 always) rather than a generic key-value settings
-- table, since this is the only global dispatch-slip setting so far.

CREATE TABLE IF NOT EXISTS dispatch_slip_settings (
  id INT UNSIGNED NOT NULL PRIMARY KEY,
  overall_packs_per_box INT UNSIGNED NOT NULL DEFAULT 50,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO dispatch_slip_settings (id, overall_packs_per_box) VALUES (1, 50);
