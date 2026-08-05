-- Get Order form now takes a per-product discount % — shows the actual
-- (pre-discount) amount and the discounted amount live as the DM types.
ALTER TABLE ms_orders ADD COLUMN discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER qty;
