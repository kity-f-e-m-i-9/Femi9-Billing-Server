-- Record GST on Neksomo manufacturer purchases, computed from each product's
-- own gst/gst_type at the time of purchase (inclusive products back out the
-- taxable value from the entered cost; exclusive products add GST on top).
-- Snapshotting rate/type/breakdown per line item keeps historical purchases
-- accurate even if a product's GST% changes later.
-- Applied: 2026-07-28

ALTER TABLE neksomo_purchase_items
    ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER cost_per_piece,
    ADD COLUMN gst_type ENUM('inclusive','exclusive') NOT NULL DEFAULT 'exclusive' AFTER gst_rate,
    ADD COLUMN taxable_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_cost,
    ADD COLUMN gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER taxable_value;

ALTER TABLE neksomo_manufacturer_purchases
    ADD COLUMN total_taxable_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_amount,
    ADD COLUMN total_gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_taxable_value;
