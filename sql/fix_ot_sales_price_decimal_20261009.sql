-- ot_sales.price was INT, so decimal rates (e.g. 35.10, 307.80) were truncated
-- to whole rupees (35, 307) while sub_total / gst_amount / total / round_off
-- were saved from the real rate. The print page recomputes from price, so it
-- showed wrong rate, taxable value, GST and round-off (e.g. WEBD/26-27/27:
-- 119.80 instead of 120.00).
-- Stored sub_total, gst_amount, total and ot_sales_invoice.round_off are already
-- correct, so only the rate has to be restored.

-- 1) Preview the rows that will change
SELECT id, tempid, qty, price AS old_price, discount, sub_total,
       ROUND((CAST(sub_total AS DECIMAL(12,2)) + discount) / qty, 2) AS new_price
FROM ot_sales
WHERE qty > 0
  AND ABS(CAST(sub_total AS DECIMAL(12,2)) - (qty * price - discount)) > 0.01;

-- 1b) Backup of the affected rows (for rollback)
CREATE TABLE ot_sales_price_backup_20261009 AS
SELECT id, tempid, price FROM ot_sales
WHERE qty > 0
  AND ABS(CAST(sub_total AS DECIMAL(12,2)) - (qty * price - discount)) > 0.01;

-- 2) Allow decimal rates
ALTER TABLE ot_sales MODIFY price DECIMAL(12,2) NOT NULL;

-- 3) Restore the real rate from the saved line amount
UPDATE ot_sales
SET price = ROUND((CAST(sub_total AS DECIMAL(12,2)) + discount) / qty, 2)
WHERE qty > 0
  AND ABS(CAST(sub_total AS DECIMAL(12,2)) - (qty * price - discount)) > 0.01;
