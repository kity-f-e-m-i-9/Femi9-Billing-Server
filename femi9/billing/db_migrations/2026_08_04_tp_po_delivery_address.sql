-- Lets a TP choose, per purchase order, whether to ship to their existing
-- registered delivery address (default) or type a one-off new delivery
-- address instead. The choice is snapshotted onto the PO at submit time
-- (not just a pointer back to territory_partners) so it stays correct even
-- if the TP's master address changes later, and is copied onto the invoice
-- when the company converts the PO so tp-invoice-print.php can render it.
-- Safe to re-run.
ALTER TABLE tp_purchase_orders
  ADD COLUMN use_default_delivery_address TINYINT(1) NOT NULL DEFAULT 1 AFTER excess_amount,
  ADD COLUMN custom_delivery_line1 VARCHAR(255) NULL AFTER use_default_delivery_address,
  ADD COLUMN custom_delivery_line2 VARCHAR(255) NULL AFTER custom_delivery_line1,
  ADD COLUMN custom_delivery_city VARCHAR(100) NULL AFTER custom_delivery_line2,
  ADD COLUMN custom_delivery_district VARCHAR(100) NULL AFTER custom_delivery_city,
  ADD COLUMN custom_delivery_state VARCHAR(100) NULL AFTER custom_delivery_district,
  ADD COLUMN custom_delivery_country VARCHAR(100) NULL AFTER custom_delivery_state,
  ADD COLUMN custom_delivery_pincode VARCHAR(20) NULL AFTER custom_delivery_country;

ALTER TABLE tp_invoices
  ADD COLUMN use_default_delivery_address TINYINT(1) NOT NULL DEFAULT 1 AFTER total_amount,
  ADD COLUMN custom_delivery_line1 VARCHAR(255) NULL AFTER use_default_delivery_address,
  ADD COLUMN custom_delivery_line2 VARCHAR(255) NULL AFTER custom_delivery_line1,
  ADD COLUMN custom_delivery_city VARCHAR(100) NULL AFTER custom_delivery_line2,
  ADD COLUMN custom_delivery_district VARCHAR(100) NULL AFTER custom_delivery_city,
  ADD COLUMN custom_delivery_state VARCHAR(100) NULL AFTER custom_delivery_district,
  ADD COLUMN custom_delivery_country VARCHAR(100) NULL AFTER custom_delivery_state,
  ADD COLUMN custom_delivery_pincode VARCHAR(20) NULL AFTER custom_delivery_country;
