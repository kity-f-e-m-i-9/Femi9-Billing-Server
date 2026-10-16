-- Deleting an OT sale entirely (ot-sale-delete.php) was undoing it by
-- writing a plain 'ot_reverse' ledger entry — indistinguishable from a
-- genuine customer return. Since Datewise/Current-Stock reporting shows
-- Sales Qty GROSS (deduct/ot_deduct, not netted by returns — see
-- DatewiseStockReport.php's computeStockMovement()), a deleted sale's
-- original 'ot_deduct' kept counting as a sale forever, while the delete
-- only inflated "Return Qty" alongside it — even though the sale never
-- really happened. Adding a distinct action lets the report actually
-- remove a deleted sale from Sales Qty, instead of just logging it as a
-- same-size return next to an unmoved Sales Qty figure.

ALTER TABLE stock_ledger
  MODIFY COLUMN action ENUM(
    'deduct','credit','reverse_deduct','reverse_credit',
    'transfer_out','transfer_in','transfer_out_reverse','transfer_in_reverse',
    'return_accept','return_reject','ot_deduct','ot_reverse','ot_reverse_undo',
    'ot_deduct_undo'
  ) NOT NULL;
