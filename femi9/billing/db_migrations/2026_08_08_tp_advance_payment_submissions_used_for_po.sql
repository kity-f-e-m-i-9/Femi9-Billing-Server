-- A submission that already unlocked one purchase order's excess-balance
-- gate must not silently unlock a second, unrelated order too — there was
-- no way to tell "already spent" apart from "still available," so a TP with
-- one old pending/accepted submission could submit unlimited over-balance
-- orders. This column is stamped with the PO that consumed a submission,
-- and the gate query in add-purchase-order.php / purchase-order-action.php
-- only counts submissions where this is still NULL.
ALTER TABLE tp_advance_payment_submissions
  ADD COLUMN used_for_po_id INT UNSIGNED NULL AFTER advance_payment_id,
  ADD KEY idx_tpapsub_used_for_po (used_for_po_id),
  ADD CONSTRAINT fk_tpapsub_used_for_po FOREIGN KEY (used_for_po_id) REFERENCES tp_purchase_orders (id) ON DELETE SET NULL;
