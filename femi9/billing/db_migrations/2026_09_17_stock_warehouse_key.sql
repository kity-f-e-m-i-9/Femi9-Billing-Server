-- Extends stock's identity key to include warehouse_id, so the same
-- product/entity can have independent stock rows in different godowns.
-- NULL warehouse_id remains its own distinct identity in a MySQL unique
-- key (multiple NULLs don't collide), so every pre-existing stock row
-- keeps behaving exactly as it does today — this is purely additive.
--
-- Phase 1 of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md
-- Applied: 2026-09-17

ALTER TABLE stock
  DROP INDEX uq_stock_entity,
  ADD UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id);

-- Records which godown (if any) a stock_ledger movement affected, so the
-- audit trail stays warehouse-aware once StockService starts passing a
-- real warehouse_id.
ALTER TABLE stock_ledger
  ADD COLUMN warehouse_id INT NULL AFTER user_id,
  ADD INDEX idx_stock_ledger_warehouse (warehouse_id);
