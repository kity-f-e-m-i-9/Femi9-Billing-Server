-- Backfills the daily login/first-invoice reward for Territory Partners from
-- 2026-07-01 up to today, covering the gap before the TP reward hook
-- (checkAndAwardDailyReward in territory-partner/shop-invoice-submit.php and
-- customer-invoice-submit.php) went live. 1 point per (TP, calendar day) —
-- matching the live code's rate for territory_partner — for every day a TP
-- issued at least one shop or customer invoice.
--
-- Safe to re-run: NOT EXISTS guards against a day that already has a
-- daily_login_rewards row for that TP (whether from this script running
-- before, or from the live feature having since covered that date), so it
-- never double-awards. Adjust the '2026-07-01' start date if you need a
-- different backfill window.
-- Applied: 2026-07-14

INSERT INTO daily_login_rewards
    (user_type, user_id, reward_date, points_awarded, invoice_id, invoice_number, created_at, notes)
SELECT 'territory_partner', ranked.tp_id, ranked.activity_date, 1, ranked.inv_id, ranked.inv_number, NOW(), 'backfill_2026_07_14'
FROM (
    SELECT tp_id, activity_date, inv_id, inv_number,
           ROW_NUMBER() OVER (PARTITION BY tp_id, activity_date ORDER BY inv_id) AS rn
    FROM (
        SELECT from_user_id AS tp_id, `date` AS activity_date, inv_id, inv_number
        FROM user_invoice
        WHERE from_user_type = 'territory_partner'
          AND `date` BETWEEN '2026-07-01' AND CURDATE()
        UNION ALL
        SELECT user_id AS tp_id, `date` AS activity_date, inv_id, inv_number
        FROM invoice
        WHERE user_type = 'territory_partner'
          AND `date` BETWEEN '2026-07-01' AND CURDATE()
    ) combined
) ranked
WHERE ranked.rn = 1
  AND NOT EXISTS (
      SELECT 1 FROM daily_login_rewards dlr
      WHERE dlr.user_type = 'territory_partner'
        AND dlr.user_id = ranked.tp_id
        AND dlr.reward_date = ranked.activity_date
  );

-- Verify
SELECT COUNT(*) AS rows_backfilled FROM daily_login_rewards WHERE notes = 'backfill_2026_07_14';
SELECT user_id, reward_date, points_awarded FROM daily_login_rewards WHERE notes = 'backfill_2026_07_14' ORDER BY reward_date, user_id;
