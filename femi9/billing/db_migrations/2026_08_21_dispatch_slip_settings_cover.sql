-- Second stage of the shipment-level box estimate — see
-- shared/DispatchSlipSettings.php for the full two-stage rationale.

ALTER TABLE dispatch_slip_settings
  ADD COLUMN overall_packs_per_cover INT UNSIGNED NOT NULL DEFAULT 21 AFTER overall_packs_per_box;
