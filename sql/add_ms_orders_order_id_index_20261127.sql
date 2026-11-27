-- ms_orders had no index on order_id. Every "DM Get Order -> its invoice" join
-- (user_invoice.source_ms_order_id = ms_orders.order_id: TeamTargetRollup.php,
-- ms_prorders.php) scanned ms_orders x user_invoice: 19 s per call on live.
-- With the index: 0.05 s. Applied to production 2026-11-27. Safe to re-run;
-- needed again if the database is restored/re-imported without it.
ALTER TABLE ms_orders ADD INDEX IF NOT EXISTS idx_ms_orders_order_id (order_id), ALGORITHM=INPLACE, LOCK=NONE;
