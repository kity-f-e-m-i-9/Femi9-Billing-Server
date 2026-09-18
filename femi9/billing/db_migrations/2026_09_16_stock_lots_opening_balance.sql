-- Seed one opening lot per (product, holder) with existing stock, so
-- FIFO consumption has something to draw from immediately after this
-- migration instead of falling back to the blended rate for all
-- pre-cutover stock. Rate is today's "latest effective_date <= now" lookup
-- — the same fallback formula StockService::fallbackRate() uses, applied
-- once here for existing stock. Uses only company-side stock (StockLots
-- is only wired into the company StockService copy in this phase).
-- Applied: 2026-09-16

INSERT INTO stock_lots (product_id, user_type, user_id, rate, qty_purchased, qty_remaining, purchase_date, ref_type, created_by)
SELECT
    s.product_id, s.user_type, s.user_id,
    COALESCE(
        (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM neksomo_llp_piece_rates r
         WHERE r.product_id = p.id AND r.effective_date <= CURDATE()
         ORDER BY r.effective_date DESC LIMIT 1),
        (SELECT CASE WHEN fr.gst_type = 'inclusive' THEN fr.rate_per_piece / (1 + fr.gst_rate/100) ELSE fr.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM femi9_llp_sale_rates fr
         WHERE fr.product_id = p.id AND fr.effective_date <= CURDATE()
         ORDER BY fr.effective_date DESC LIMIT 1),
        0
    ) AS rate,
    s.closing_qty, s.closing_qty, CURDATE(), 'opening_balance', 'migration'
FROM stock s
JOIN products p ON p.id = s.product_id
WHERE s.closing_qty > 0
  AND s.user_type = 'company';
