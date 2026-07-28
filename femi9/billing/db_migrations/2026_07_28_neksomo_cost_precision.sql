-- Widen cost/rate/value columns from 2 to 6 decimal places so entered cost
-- prices (Purchase from Manufacturer, Sale to Femi9 LLP, Purchase Rate) stop
-- getting silently rounded off to whole paise. GST rate/% columns are left
-- untouched — those are percentages, not currency, and don't need the extra
-- precision.
-- Applied: 2026-07-28

ALTER TABLE neksomo_llp_piece_rates
    MODIFY COLUMN rate_per_piece DECIMAL(14,6) NOT NULL;

ALTER TABLE neksomo_llp_piece_purchase_rates
    MODIFY COLUMN rate_per_piece DECIMAL(14,6) NOT NULL;

ALTER TABLE neksomo_purchase_items
    MODIFY COLUMN cost_per_piece DECIMAL(14,6) NOT NULL,
    MODIFY COLUMN total_cost DECIMAL(16,6) NOT NULL,
    MODIFY COLUMN taxable_value DECIMAL(16,6) NOT NULL,
    MODIFY COLUMN gst_amount DECIMAL(16,6) NOT NULL;

ALTER TABLE neksomo_manufacturer_purchases
    MODIFY COLUMN cost_per_piece DECIMAL(14,6) NOT NULL,
    MODIFY COLUMN total_cost DECIMAL(16,6) NOT NULL,
    MODIFY COLUMN total_amount DECIMAL(16,6) NOT NULL,
    MODIFY COLUMN total_taxable_value DECIMAL(16,6) NOT NULL,
    MODIFY COLUMN total_gst_amount DECIMAL(16,6) NOT NULL;
