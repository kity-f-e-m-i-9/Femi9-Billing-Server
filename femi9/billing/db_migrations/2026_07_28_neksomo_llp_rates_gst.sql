-- Snapshot each product's GST rate/type onto its LLP sale and purchase rate
-- entries, so GST can be factored into reporting without retroactively
-- changing past figures if a product's GST% is edited later. The entered
-- rate_per_piece already means "pre-tax" for exclusive products and
-- "tax-inclusive" for inclusive ones, matching the same convention used by
-- neksomo_purchase_items (Purchase from Manufacturer).
-- Applied: 2026-07-28

ALTER TABLE neksomo_llp_piece_rates
    ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER rate_per_piece,
    ADD COLUMN gst_type ENUM('inclusive','exclusive') NOT NULL DEFAULT 'exclusive' AFTER gst_rate;

ALTER TABLE neksomo_llp_piece_purchase_rates
    ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER rate_per_piece,
    ADD COLUMN gst_type ENUM('inclusive','exclusive') NOT NULL DEFAULT 'exclusive' AFTER gst_rate;
