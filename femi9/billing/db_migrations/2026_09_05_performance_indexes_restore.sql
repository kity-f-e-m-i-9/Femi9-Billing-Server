-- Performance indexes that speed up company login / mis-report / district
-- revenue queries. These live outside the normal migration flow because a
-- fresh DB export+import (via phpMyAdmin/cPanel) drops custom indexes that
-- weren't captured in the original schema dump — this file lets you restore
-- them in one shot after any such reimport.
--
-- Run after every DB reimport:
--   mysql -u root billing0femi9_billingapp < 2026_09_05_performance_indexes_restore.sql
--
-- Safe to re-run any time — every statement is IF NOT EXISTS.

CREATE INDEX IF NOT EXISTS idx_invoice_inv_id ON invoice (inv_id);
CREATE INDEX IF NOT EXISTS idx_invoice_user_type_date ON invoice (user_type, date, sub_total);
CREATE INDEX IF NOT EXISTS idx_invoice_user_id ON invoice (user_type, user_id, date);

CREATE INDEX IF NOT EXISTS idx_ui_to_user_id ON user_invoice (to_user_type(50), to_user_id(50), from_user_type(50), date);

CREATE INDEX IF NOT EXISTS idx_shop_date ON ms_orders (shop_id, order_date);
CREATE INDEX IF NOT EXISTS idx_ms_id ON ms_orders (ms_id);
CREATE INDEX IF NOT EXISTS idx_tp_id ON ms_orders (tp_id);
CREATE INDEX IF NOT EXISTS idx_order_date ON ms_orders (order_date);

CREATE INDEX IF NOT EXISTS idx_district_node_id ON ms_shop (district_node_id);
CREATE INDEX IF NOT EXISTS idx_taluk_node_id ON ms_shop (taluk_node_id);

-- company/ot-sale-view.php was doing ~46 full-table-scan queries PER ROW
-- (one per product per invoice, with no index on tempid/date/prid at all) —
-- a 1-month filter (~1187 invoices) meant ~55,000 unindexed queries and a
-- 2+ minute load. Added 2026-09-10 alongside the query-batching rewrite of
-- that page.
CREATE INDEX IF NOT EXISTS idx_ot_sales_tempid ON ot_sales (tempid);
CREATE INDEX IF NOT EXISTS idx_ot_sales_date ON ot_sales (date);
CREATE INDEX IF NOT EXISTS idx_ot_sales_tempid_prid ON ot_sales (tempid, prid);
CREATE INDEX IF NOT EXISTS idx_ot_sales_invoice_tempid ON ot_sales_invoice (tempid);
