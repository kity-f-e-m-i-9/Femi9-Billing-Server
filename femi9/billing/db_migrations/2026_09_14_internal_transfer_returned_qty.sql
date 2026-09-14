-- Track cumulative partial-return quantity per internal_transfer line, so a
-- new "Return Stock" action (distinct from the existing full-line delete) can
-- return less than the whole transferred qty, possibly across multiple
-- returns, without ever returning more than was originally sent.
-- Applied: 2026-09-14

ALTER TABLE internal_transfer
  ADD COLUMN returned_qty INT NOT NULL DEFAULT 0 AFTER qty;
