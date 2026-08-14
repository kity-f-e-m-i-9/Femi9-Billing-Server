-- Restructures standalone TP advance-payment submissions to support
-- multiple independently-uploaded/verified screenshots per submission,
-- matching the PO page's upload UX (repeat "choose file, click Upload" up
-- to 5 times). Payment details (amount/date/mode/reference/note) are
-- entered once per submission, not duplicated per screenshot — a new
-- parent table holds those; tp_advance_payment_screenshots becomes a pure
-- child table (one row per uploaded image + its own detection result).
--
-- Submission-level status/review/advance_payment_id lives on the parent:
-- the submission only becomes reviewable once every child screenshot has
-- finished verifying (no longer pending upload/vision-call), and company
-- approves/rejects/converts the whole submission as one unit — matching
-- how a PO's excess-payment proof already works (many screenshots support
-- one PO's payment).
CREATE TABLE tp_advance_payment_submissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  territory_partner_id INT UNSIGNED NOT NULL,

  amount DECIMAL(12,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_mode VARCHAR(50) NOT NULL,
  reference_number VARCHAR(255) NOT NULL,
  note VARCHAR(500) NULL,

  -- 'draft' while the TP is still uploading screenshots (mirrors a PO's
  -- po_id IS NULL in-progress state) — flips to 'pending_review' once the
  -- TP explicitly submits, only allowed once every screenshot has resolved.
  status ENUM('draft','pending_review','accepted','rejected') NOT NULL DEFAULT 'draft',
  rejection_reason VARCHAR(255) NULL,
  reviewed_by VARCHAR(100) NULL,
  reviewed_at TIMESTAMP NULL,
  advance_payment_id INT UNSIGNED NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP NULL,
  KEY idx_tpapsub_tp (territory_partner_id),
  KEY idx_tpapsub_status (status),
  KEY idx_tpapsub_advpay (advance_payment_id),
  CONSTRAINT fk_tpapsub_advpay FOREIGN KEY (advance_payment_id) REFERENCES tp_advance_payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tp_advance_payment_screenshots
  ADD COLUMN submission_id INT UNSIGNED NULL AFTER territory_partner_id,
  ADD KEY idx_tpaps_submission (submission_id),
  ADD CONSTRAINT fk_tpaps_submission FOREIGN KEY (submission_id) REFERENCES tp_advance_payment_submissions (id) ON DELETE CASCADE;

-- Migrate the one existing row (predates this restructure) into a
-- submission of its own, preserving its already-verified state.
INSERT INTO tp_advance_payment_submissions
  (territory_partner_id, amount, payment_date, payment_mode, reference_number, note, status, rejection_reason, reviewed_by, reviewed_at, advance_payment_id, created_at, submitted_at)
SELECT territory_partner_id, submitted_amount, submitted_payment_date, submitted_payment_mode, submitted_reference_number, submitted_note,
       CASE status WHEN 'accepted' THEN 'accepted' WHEN 'rejected' THEN 'rejected' ELSE 'pending_review' END,
       rejection_reason, reviewed_by, reviewed_at, advance_payment_id, created_at, created_at
FROM tp_advance_payment_screenshots
WHERE submission_id IS NULL;

UPDATE tp_advance_payment_screenshots s
JOIN tp_advance_payment_submissions sub
  ON sub.territory_partner_id = s.territory_partner_id
 AND sub.reference_number = s.submitted_reference_number
 AND sub.created_at = s.created_at
SET s.submission_id = sub.id
WHERE s.submission_id IS NULL;

-- Now that payment details AND territory_partner_id (available via the
-- submission) live on the parent, drop the redundant columns from the
-- child table entirely.
ALTER TABLE tp_advance_payment_screenshots
  DROP FOREIGN KEY fk_tpaps_advpay,
  DROP KEY idx_tpaps_tp,
  DROP KEY idx_tpaps_advpay,
  DROP COLUMN submitted_amount,
  DROP COLUMN submitted_payment_date,
  DROP COLUMN submitted_payment_mode,
  DROP COLUMN submitted_reference_number,
  DROP COLUMN submitted_note,
  DROP COLUMN reviewed_by,
  DROP COLUMN reviewed_at,
  DROP COLUMN advance_payment_id,
  DROP COLUMN territory_partner_id,
  MODIFY COLUMN submission_id INT UNSIGNED NOT NULL;
