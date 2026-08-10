-- WhatsApp Purchase Order automation (Wati agent <-> plain-PHP API layer).
-- Parallel, standalone subsystem serving ALL 8 central-login user
-- categories (distributor, super_distributor, stockiest, super_stockiest,
-- channel_partner, candf, marketing, territory_partner) — NOT the same
-- tables as the existing territory-partner-only tp_purchase_orders /
-- tp_advance_payments / tp_advance_payment_* system, and does not touch
-- those tables. Only territory_partner currently has that schema; building
-- a fresh generic wa_po_* schema keyed on (user_category, user_id) avoids
-- forking the tp_* schema per category. See femi9-whatsapp-po-api-spec.md.
--
-- Idempotent — safe to run more than once.

-- ---------------------------------------------------------------------
-- Session / auth support tables
-- ---------------------------------------------------------------------

-- Short-lived identity binding for one WhatsApp conversation, created by
-- /auth/select-account or /auth/verify-otp. Every downstream call in the
-- conversation must present session_token and have it resolved back to
-- this row's user_category+user_id — never trust a user_id passed directly
-- in a request body — so one WA conversation can't act as a different
-- account mid-chat. Sliding 30-60 min TTL: expires_at is refreshed on
-- every valid use.
CREATE TABLE IF NOT EXISTS `wa_po_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `session_token` CHAR(64) NOT NULL,
  `wa_number` VARCHAR(20) NOT NULL,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `conversation_id` VARCHAR(100) NULL DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_wps_token` (`session_token`),
  KEY `idx_wps_expires` (`expires_at`),
  KEY `idx_wps_user` (`user_category`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Powers the "remember last used account" fast path (spec 1.1c) for a
-- number with multiple accounts — upserted by /auth/select-account.
CREATE TABLE IF NOT EXISTS `wa_number_last_account` (
  `wa_number` VARCHAR(20) NOT NULL PRIMARY KEY,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Numbers explicitly linked to an account via /auth/link-number after OTP
-- verification (spec 1.1b) — many-to-one with each category's own user
-- table (a distributor could link several personal/staff WhatsApp numbers
-- over time). Never auto-linked and never overwrites the registered number
-- on the category table itself.
CREATE TABLE IF NOT EXISTS `wa_po_linked_numbers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `wa_number` VARCHAR(20) NOT NULL,
  `linked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_wpln_cat_user_number` (`user_category`, `user_id`, `wa_number`),
  KEY `idx_wpln_number` (`wa_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per OTP send for the fallback verification path (spec 1.1b).
-- otp_code_hash stores SHA-256 of the 6-digit code — never plaintext.
CREATE TABLE IF NOT EXISTS `wa_po_otp_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `otp_id` VARCHAR(40) NOT NULL,
  `otp_code_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts_used` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_wpoa_otp_id` (`otp_id`),
  KEY `idx_wpoa_user` (`user_category`, `user_id`),
  KEY `idx_wpoa_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backs the "max 3 OTP sends per hour per account" guardrail — one row per
-- (account, hourly window), send_count incremented on each /auth/send-otp.
CREATE TABLE IF NOT EXISTS `wa_po_otp_rate_limit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `window_start` DATETIME NOT NULL,
  `send_count` INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_wporl_user_window` (`user_category`, `user_id`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Generic request rate-limit bucket used by wa_po_rate_limit_check() in
-- _bootstrap.php for any endpoint/key combination (per spec 4's
-- api_rate_limits sketch) — e.g. keyed as "verify-user:<wa_number>" or
-- "po-create:<user_category>:<user_id>".
CREATE TABLE IF NOT EXISTS `wa_po_rate_limits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `rate_key` VARCHAR(191) NOT NULL,
  `window_start` DATETIME NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_wprl_key_window` (`rate_key`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Purchase order tables (generic across all 8 categories)
-- ---------------------------------------------------------------------

-- Mirrors tp_purchase_orders' shape/columns, but keyed on
-- (user_category, user_id) instead of territory_partner_id, and adds
-- idempotency_key + source since these are created by an automated WhatsApp
-- agent that can retry a send (spec 1.5) rather than a human clicking
-- "submit" once.
CREATE TABLE IF NOT EXISTS `wa_po_purchase_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `order_date` DATE NOT NULL,
  `status` ENUM('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting',
  `excess_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `use_default_delivery_address` TINYINT(1) NOT NULL DEFAULT 1,
  `custom_delivery_line1` VARCHAR(255) NULL,
  `custom_delivery_line2` VARCHAR(255) NULL,
  `custom_delivery_city` VARCHAR(100) NULL,
  `custom_delivery_district` VARCHAR(100) NULL,
  `custom_delivery_state` VARCHAR(100) NULL,
  `custom_delivery_country` VARCHAR(100) NULL,
  `custom_delivery_pincode` VARCHAR(20) NULL,
  `idempotency_key` VARCHAR(100) NOT NULL,
  `source` VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
  `notes` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_wppo_idempotency` (`idempotency_key`),
  KEY `idx_wppo_user_date` (`user_category`, `user_id`, `order_date`),
  KEY `idx_wppo_status_date` (`status`, `order_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wa_po_purchase_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT UNSIGNED NOT NULL,
  `product_id` INT NOT NULL,
  `qty` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  KEY `idx_wppoi_po` (`po_id`),
  KEY `idx_wppoi_product` (`product_id`),
  CONSTRAINT `fk_wppoi_po` FOREIGN KEY (`po_id`) REFERENCES `wa_po_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Advance-payment tables (generic across all 8 categories)
-- ---------------------------------------------------------------------

-- Mirrors tp_advance_payments' shape — a confirmed advance balance entry,
-- created once a wa_po_advance_payment_submissions row is approved.
CREATE TABLE IF NOT EXISTS `wa_po_advance_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `balance_amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('active','fully_adjusted') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_wpap_user` (`user_category`, `user_id`),
  KEY `idx_wpap_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WhatsApp-submitted advance-payment proof. Unlike the TP web UI's
-- multi-step draft flow (upload screenshot first, edit fields, THEN submit),
-- the WhatsApp flow always arrives with amount + screenshot together in one
-- /payment/submit-proof call, so there's no 'draft' status here — it goes
-- straight to pending_review (or accepted/rejected once Claude Vision has
-- classified the attached screenshot).
CREATE TABLE IF NOT EXISTS `wa_po_advance_payment_submissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_category` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_mode` VARCHAR(50) NOT NULL DEFAULT 'UPI',
  `reference_number` VARCHAR(255) NULL,
  `note` VARCHAR(500) NULL,
  `status` ENUM('draft','pending_review','accepted','rejected') NOT NULL DEFAULT 'pending_review',
  `used_for_po_id` INT UNSIGNED NULL DEFAULT NULL,
  `rejection_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `reviewed_by` VARCHAR(100) NULL,
  KEY `idx_wpaps_user` (`user_category`, `user_id`),
  KEY `idx_wpaps_status` (`status`),
  KEY `idx_wpaps_used_for_po` (`used_for_po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mirrors tp_advance_payment_screenshots' shape, one row per uploaded proof
-- image attached to a wa_po_advance_payment_submissions row.
CREATE TABLE IF NOT EXISTS `wa_po_advance_payment_screenshots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `submission_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `detected_amount` DECIMAL(12,2) NULL,
  `reference_number` VARCHAR(255) NULL,
  `ocr_raw_text` MEDIUMTEXT NULL,
  `status` ENUM('accepted','pending_review','rejected') NOT NULL DEFAULT 'pending_review',
  `rejection_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_wpaps2_submission` (`submission_id`),
  KEY `idx_wpaps2_status` (`status`),
  KEY `idx_wpaps2_refnum` (`reference_number`),
  CONSTRAINT `fk_wpaps2_submission` FOREIGN KEY (`submission_id`) REFERENCES `wa_po_advance_payment_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mirrors tp_advance_payment_screenshot_ocr_corrections' shape exactly —
-- separate table (own corrections pool, own few-shot history) scoped to
-- this WhatsApp subsystem rather than sharing the TP corrections table.
CREATE TABLE IF NOT EXISTS `wa_po_payment_screenshot_ocr_corrections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `screenshot_id` INT UNSIGNED NOT NULL,
  `field` VARCHAR(20) NOT NULL,
  `engine` VARCHAR(20) NOT NULL DEFAULT 'claude_vision',
  `wrong_value` VARCHAR(255) NULL,
  `correct_value` VARCHAR(255) NOT NULL,
  `raw_text_hash` CHAR(64) NOT NULL,
  `ocr_raw_text` MEDIUMTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_wppsoc_screenshot` (`screenshot_id`),
  KEY `idx_wppsoc_field` (`field`),
  KEY `idx_wppsoc_engine` (`engine`),
  KEY `idx_wppsoc_hash` (`raw_text_hash`),
  CONSTRAINT `fk_wppsoc_screenshot` FOREIGN KEY (`screenshot_id`) REFERENCES `wa_po_advance_payment_screenshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
