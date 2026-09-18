-- Adds physical-warehouse tagging to FIFO cost lots, so a sale drawn from
-- a specific godown only consumes that godown's own lots. Nullable,
-- additive — existing lots become "unassigned" (NULL), same convention
-- as stock.warehouse_id / stock_ledger.warehouse_id from Phase 1. See
-- docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md,
-- Phase 4.
ALTER TABLE stock_lots
  ADD COLUMN warehouse_id INT NULL AFTER user_id;
