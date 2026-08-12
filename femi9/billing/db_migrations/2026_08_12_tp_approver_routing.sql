-- TPs already assigned to a Super Stockist (territory_partners.onboard_ss_id)
-- can now choose, per purchase order / advance-payment submission, whether
-- Company or their assigned SS reviews and approves it. Whichever is chosen
-- becomes the sole owner of that request's balance pool — a TP's advance
-- balance is scoped per-approver, not global — so every table that used to
-- assume "Company" implicitly needs to record who it was actually routed to.
--
-- approver_ss_id stores super_stockiest.id (the numeric PK), mirroring how
-- onboard_ss_id itself stores the SS's temp_id (varchar) — deliberately not
-- an FK, matching this codebase's existing convention for that relationship.
--
-- All existing rows default to approver_type='company', approver_ss_id=NULL
-- — zero behavior change for data that predates this feature, and TPs with
-- onboard_ss_id IS NULL never produce anything else going forward either.

ALTER TABLE tp_purchase_orders
  ADD COLUMN approver_type ENUM('company','ss') NOT NULL DEFAULT 'company' AFTER territory_partner_id,
  ADD COLUMN approver_ss_id INT UNSIGNED NULL AFTER approver_type,
  ADD KEY idx_tppo_approver (approver_type, approver_ss_id);

ALTER TABLE tp_advance_payments
  ADD COLUMN approver_type ENUM('company','ss') NOT NULL DEFAULT 'company' AFTER territory_partner_id,
  ADD COLUMN approver_ss_id INT UNSIGNED NULL AFTER approver_type,
  ADD KEY idx_tpap_approver (approver_type, approver_ss_id);

ALTER TABLE tp_advance_payment_submissions
  ADD COLUMN approver_type ENUM('company','ss') NOT NULL DEFAULT 'company' AFTER territory_partner_id,
  ADD COLUMN approver_ss_id INT UNSIGNED NULL AFTER approver_type,
  ADD KEY idx_tpaps_approver (approver_type, approver_ss_id);
