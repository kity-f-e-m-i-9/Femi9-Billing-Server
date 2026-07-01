# Stock System Audit & Fix Log — Company Portal
**Date:** 2026-06-05  
**Scope:** `/femi9/billing/company/` — all stock-related PHP files  
**Outcome:** 12 issues found and fixed across 3 severity levels

---

## Architecture Overview

### StockService (`include/StockService.php`)
Centralized stock management class. **All stock mutations must go through this class.**

- Uses `SELECT ... FOR UPDATE` to prevent race conditions (TOCTOU)
- Writes every operation to `stock_ledger` for full audit trail
- Methods: `deduct`, `credit`, `reverseDeduct`, `reverseCredit`, `deductAndCredit`, `acceptReturn`, `otReverse`, `hasLedgerEntry`, `reverseAll`, `getClosingQty`, `getInvoiceItems`
- Added in this audit: `transferOut`, `transferIn`, `reverseTransferOut`, `reverseTransferIn`

### `STOCK_MAINTAINING_TYPES`
```php
['company', 'super_stockiest', 'stockiest', 'super_distributor', 'distributor', 'candf']
```
Customers do NOT maintain stock. Always check membership before crediting a buyer.

### `stock_ledger` actions
| Action | Triggered by |
|---|---|
| `deduct` | Sale (seller side) |
| `credit` | Sale (buyer side, if stock-maintaining) |
| `reverse_deduct` | Sale reversal (seller) |
| `reverse_credit` | Sale reversal (buyer) |
| `transfer_out` | Internal transfer / demo-free (from godown) |
| `transfer_in` | Internal transfer (to godown) |
| `transfer_out_reverse` | Reversal of transfer_out |
| `transfer_in_reverse` | Reversal of transfer_in |
| `opening_stock` | Initial stock setup |
| `return_stock_delete` | Deletion of a return record |

---

## New StockService Methods Added

### `transferOut(int $productId, string $userType, string $userId, int $qty, string $refType, string $refId, string $createdBy, bool $externalTransaction = false)`
- Increments `sent_qty`, decrements `closing_qty`
- Writes `transfer_out` ledger entry

### `transferIn(int $productId, string $userType, string $userId, int $qty, string $refType, string $refId, string $createdBy, bool $externalTransaction = false)`
- Increments `input_qty` and `closing_qty` (creates stock row if absent)
- Writes `transfer_in` ledger entry

### `reverseTransferOut(int $productId, string $userType, string $userId, int $qty, string $refType, string $refId, string $createdBy, bool $externalTransaction = false)`
- Decrements `sent_qty` (floor 0), restores `closing_qty`
- Writes `transfer_out_reverse` ledger entry

### `reverseTransferIn(int $productId, string $userType, string $userId, int $qty, string $refType, string $refId, string $createdBy, bool $externalTransaction = false)`
- Decrements `input_qty` and `closing_qty` (floor 0)
- Writes `transfer_in_reverse` ledger entry

All four use `lockStockRow()` (SELECT FOR UPDATE) and support `$externalTransaction` flag.

---

## Issues Found and Fixed

### CRITICAL

#### C-1 — `user-del-inv-product.php` — Missing buyer stock reversal on line item delete
**Problem:** When a B2B invoice line item was deleted, `reverseDeduct` was called for the seller but `reverseCredit` was never called for the buyer — leaving the buyer's stock permanently inflated.

**Fix:**
- Extended SELECT to also fetch `to_user_type` and `to_user_id`
- Wrapped both calls in a shared transaction
- Added `reverseCredit()` for buyer when `in_array($to_type, StockService::STOCK_MAINTAINING_TYPES)`

---

#### C-2 — `internal_transfer_action.php` / `internal_transfer_delete.php` — No transaction, no ledger, SQL injection
**Problem:** Three simultaneous issues:
1. No database transaction — a crash between the two godown updates left stock inconsistent
2. No `stock_ledger` entries — transfers were invisible to audit and reversal tools
3. `$_REQUEST` values embedded directly in SQL strings (SQL injection)

**Fix (action):** Complete rewrite:
- All queries use prepared statements
- Pre-validates stock for all products via `getClosingQty()` before opening transaction
- Atomic transaction wraps INSERT (header + items) + `transferOut` + `transferIn`
- Tempid duplicate guard prevents double-submission

**Fix (delete):** Complete rewrite:
- Prepared statement fetches record before deletion
- Single transaction wraps DELETE + `reverseTransferOut` + `reverseTransferIn`

---

#### C-3 — `del-inv-product2.php` — SQL injection via base64-decoded input
**Problem:** Output of `base64_decode()` on user-controlled input was embedded directly into SQL queries. Also bypassed StockService entirely.

**Fix:** Complete rewrite:
- All queries use prepared statements
- Dual-path logic: StockService path (`reverseDeduct` + `reverseCredit`) when ledger entries exist; legacy direct SQL path with `GREATEST(0, ...)` floor guards for pre-migration records

---

#### C-4 — `stock-reversal-tool.php` — Broken rollback (referenced non-existent key)
**Problem:** Execute log stored `['inv_id' => ..., 'reversed' => ...]`. Rollback tried `buildRollbackQuery($entry['sql'])` but `'sql'` key was never set — rollback silently failed every time.

**Fix:**
- Execute log now stores full invoice identity: `inv_id`, `ref_type`, `seller_type`, `seller_id`, `buyer_type`, `buyer_id`, `reversed`
- Rollback re-fetches invoice items via `getInvoiceItems()` and calls `deductAndCredit()` for each — correct because after `reverseAll()`, `hasLedgerEntry()` returns false so re-apply proceeds cleanly

---

### MODERATE

#### M-1 — `return-action.php` — Accept path never reduced buyer stock
**Problem:** When a return was accepted, `acceptReturn()` credited the company's stock back. But the buyer who returned the goods still showed the originally-credited stock in their balance.

**Fix:** Added `reverseCredit()` call for `from_user` inside the same accept transaction:
```php
$stockService->acceptReturn(...); // company gets stock back
if (in_array($fromusertype, StockService::STOCK_MAINTAINING_TYPES, true)) {
    $stockService->reverseCredit($prid, $fromusertype, $fromuserid, $qty, 'return', $returnid, $createdBy, true);
}
```

---

#### M-2 — `delete-return.php` — Raw SQL, no transaction, no lock, potential negative stock
**Problem:** Direct SQL mutation of `stock` table with `base64_decode()` output in query string, no `FOR UPDATE`, no floor check, no ledger.

**Fix:** Complete rewrite:
- Prepared statements throughout
- `SELECT ... FOR UPDATE` before stock update
- Writes `return_stock_delete` ledger entry
- `max(0, ...)` floor on `returnqty` decrement
- All inside a transaction

---

#### M-3 — Five files bypassing StockService entirely
Files: `delete-input.php`, `delete-return.php`, `demofree_action.php`, `demofree_delete.php`, `op-stock.php`

All five directly mutated the `stock` table without `FOR UPDATE` lock, floor checks, or ledger entries. Fixed individually:

| File | Fix Applied |
|---|---|
| `delete-input.php` | `reverseCredit('adjustment', $tempid)` via StockService |
| `delete-return.php` | Manual `FOR UPDATE` + `return_stock_delete` ledger + transaction |
| `demofree_action.php` | `transferOut('demofree', $tempid)` via StockService, pre-validation |
| `demofree_delete.php` | `reverseTransferOut('demofree', $tempid)` via StockService |
| `op-stock.php` | `opening_stock` ledger INSERT added (see also L-2) |

---

### LOW

#### L-1 — `delete-input.php` — No floor check on stock decrement
Covered by M-3 fix: `StockService::reverseCredit` floors at 0.

#### L-2 — `op-stock.php` — No CSRF, SQL injection on godown query, no ledger
**Problem:** Opening stock form had no CSRF protection, godown ID was embedded raw in SQL, and no ledger entry was written so stock had no audit trail from day one.

**Fix:**
- CSRF token (`$_SESSION['csrf_token_opstock']`) generated on page load, validated on POST, rotated after use
- Godown details query uses prepared statement
- Each new stock row also writes an `opening_stock` ledger entry
- Form includes: `<input type="hidden" name="csrf_token" value="...">`

#### L-3 — `demofree_action.php` — Race condition on stock check
Covered by M-3 fix: `transferOut` uses `FOR UPDATE` lock; pre-validation uses `getClosingQty()`.

---

## Key Design Rules (for future development)

1. **Never write directly to the `stock` table.** Always go through `StockService`.
2. **Always use `SELECT ... FOR UPDATE`** when reading stock before an update (StockService handles this automatically).
3. **Every stock change must have a `stock_ledger` entry.** No silent mutations.
4. **Check `in_array($type, StockService::STOCK_MAINTAINING_TYPES)`** before crediting a buyer.
5. **Use `hasLedgerEntry()`** as an idempotency guard before reversals.
6. **Wrap multi-step stock operations in a transaction.** Pass `$externalTransaction = true` to StockService methods when you've already called `begin_transaction()`.
7. **Floors at zero** — no stock column should ever go negative. StockService enforces this with `MAX(0, ...)`.
8. **Validate stock before opening a transaction.** Pre-check with `getClosingQty()` (no lock needed), then let `transferOut`/`deduct` do the locked re-check inside the transaction.
9. **Always use prepared statements.** Never interpolate `$_REQUEST`, `base64_decode()`, or any user input into SQL strings.
10. **CSRF tokens** on all forms that mutate data: generate with `bin2hex(random_bytes(32))`, validate with `hash_equals()`, rotate after each successful POST.
