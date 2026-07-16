-- mis-report.php (the page users land on right after company login) runs 15+
-- queries filtering `invoice` and `ot_sales` by date range (and joining
-- invoice_items -> invoice on inv_id). Neither table had any secondary index
-- (only PRIMARY on `id`), so every one of those queries was a full table
-- scan — 55k+ rows on invoice, 32k+ rows on ot_sales — causing the page to
-- hang for a long time after Sign In. user_invoice already has equivalent
-- indexes (idx_user_invoice_date etc.); invoice/ot_sales never got theirs
-- carried over, likely lost on a DB export/reimport.
--
-- NOT idempotent (MariaDB 10.4 has no ADD INDEX IF NOT EXISTS) — safe to run
-- once; re-running will error with "Duplicate key name" if already applied.

ALTER TABLE `invoice` ADD INDEX `idx_invoice_inv_id` (`inv_id`);
ALTER TABLE `invoice` ADD INDEX `idx_invoice_date_usertype` (`date`, `user_type`);
ALTER TABLE `ot_sales` ADD INDEX `idx_ot_sales_date_godown` (`date`, `godownid`);

-- mis-report.php's state/district breakdown (recursive CTE over
-- partner_location_nodes) joins shop.temp_id = user_invoice.to_user_id and
-- shop.district_id = <recursive node id>. shop.temp_id was only ever indexed
-- as the *second* column of idx_shop_state_temp (state_id, temp_id), so it
-- couldn't be used as a lookup on its own, and district_id had no index at
-- all — with 18k+ shop rows this turned the join into a full scan per
-- recursive row and hung for minutes. Observed live via SHOW FULL
-- PROCESSLIST: a single request stuck 1400+ seconds on this exact query.
ALTER TABLE `shop` ADD INDEX `idx_shop_temp_id` (`temp_id`);
ALTER TABLE `shop` ADD INDEX `idx_shop_district_id` (`district_id`);
