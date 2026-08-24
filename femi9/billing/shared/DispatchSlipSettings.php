<?php
/**
 * DispatchSlipSettings — the two global numbers driving the dispatch
 * slip's shipment-level Total Boxes estimate (see
 * db_migrations/2026_08_21_dispatch_slip_settings.sql). Separate from
 * products.packs_per_cover, which is a per-product, per-line threshold.
 *
 * Applied in two stages to whatever packs are left after per-line BOX
 * flags are already accounted for (see company/dispatch-slip-print.php):
 *   1. overall_packs_per_box  — group that pool into full boxes (floor
 *      division) — e.g. 80 packs at 50/box = 1 box, 30 packs left over.
 *   2. overall_packs_per_cover — check what's STILL left over after step 1
 *      against this second, smaller threshold — if it exceeds this, that
 *      remainder becomes one more box outright (same "exceed it -> box"
 *      rule as the per-line flag); otherwise it just stays shown as
 *      loose packs.
 */

function dispatchSlipEnsureSettingsTable(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS dispatch_slip_settings (
          id INT UNSIGNED NOT NULL PRIMARY KEY,
          overall_packs_per_box INT UNSIGNED NOT NULL DEFAULT 50,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $db->query("INSERT IGNORE INTO dispatch_slip_settings (id, overall_packs_per_box) VALUES (1, 50)");

    // Self-migrating: see db_migrations/2026_08_21_dispatch_slip_settings_cover.sql
    $col = $db->query("SHOW COLUMNS FROM dispatch_slip_settings LIKE 'overall_packs_per_cover'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE dispatch_slip_settings ADD COLUMN overall_packs_per_cover INT UNSIGNED NOT NULL DEFAULT 21 AFTER overall_packs_per_box");
    }
}

/** @return array{box:int,cover:int} Both always >= 1 (never lets a bad/zero value cause division by zero). */
function dispatchSlipGetOverallSettings(mysqli $db): array
{
    dispatchSlipEnsureSettingsTable($db);
    $row = $db->query("SELECT overall_packs_per_box, overall_packs_per_cover FROM dispatch_slip_settings WHERE id = 1")->fetch_assoc();
    $box   = (int)($row['overall_packs_per_box'] ?? 50);
    $cover = (int)($row['overall_packs_per_cover'] ?? 21);
    return [
        'box'   => $box > 0 ? $box : 50,
        'cover' => $cover > 0 ? $cover : 21,
    ];
}
