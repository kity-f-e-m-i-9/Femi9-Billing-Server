-- Deleting an OT Channel return (ot-return-delete.php) was undoing it by
-- writing a plain 'ot_deduct' ledger entry — indistinguishable from a real
-- new sale. That corrupted Datewise/Current-Stock reporting two ways: the
-- deleted return's qty kept showing in "Return Qty" forever (never netted
-- out), AND the undo got counted a second time as a phantom "Sales Qty"
-- entry that never actually happened. Adding a distinct action lets the
-- report net it out of Return Qty without inflating Sales Qty.

ALTER TABLE stock_ledger
  MODIFY COLUMN action ENUM(
    'deduct','credit','reverse_deduct','reverse_credit',
    'transfer_out','transfer_in','transfer_out_reverse','transfer_in_reverse',
    'return_accept','return_reject','ot_deduct','ot_reverse','ot_reverse_undo'
  ) NOT NULL;
