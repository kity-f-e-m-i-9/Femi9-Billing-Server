-- Records GST inclusive/exclusive handling on Internal Stock Transfer lines,
-- computed from each product's own gst_type at the time of transfer
-- (inclusive products back out the taxable value from the entered rate;
-- exclusive products add GST on top) — same convention already used by
-- Neksomo purchases (see 2026_07_28_neksomo_purchase_gst.sql). Previously
-- internal_transfer_action.php always treated the entered rate as
-- GST-exclusive regardless of the product's actual gst_type, silently
-- overcharging GST on any product marked 'inclusive'. Snapshotting
-- gst_type/taxable_value per line keeps historical transfers accurate even
-- if a product's GST% or gst_type changes later.
ALTER TABLE internal_transfer
    ADD COLUMN gst_type ENUM('inclusive','exclusive') NOT NULL DEFAULT 'exclusive' AFTER gst,
    ADD COLUMN taxable_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gst_type;
