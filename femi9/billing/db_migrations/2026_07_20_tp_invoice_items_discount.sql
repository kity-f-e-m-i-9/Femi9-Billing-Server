-- Per-product discount for Super Stockist -> Territory Partner invoices,
-- matching the Disc(%)/Disc(Rs.) pattern already used on
-- territory-partner/shop-invoice-add.php. `amount` stays the gross line
-- total (qty * rate) for backward compatibility with existing
-- prints/reports; discount_amount is subtracted separately when computing
-- the invoice's net total.
ALTER TABLE tp_invoice_items
  ADD COLUMN discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER amount,
  ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_percentage;
