-- Tracks whether a verified payment-proof screenshot has been converted into
-- an actual tp_advance_payments ledger entry (via the "Add to TP Payment
-- Entry" button on company/tp-today-orders.php's Payment Proof modal).
-- Without this, a screenshot's verified amount stayed invisible to the
-- advance-balance ledger, meaning tp-invoice-action.php's
-- getTpAdvanceBalance()/deductTpAdvance() gate could block invoicing the very
-- order the excess payment was proof for.
ALTER TABLE tp_purchase_order_screenshots
  ADD COLUMN advance_payment_id INT UNSIGNED NULL AFTER reviewed_at,
  ADD INDEX idx_tppos_advpay (advance_payment_id);
