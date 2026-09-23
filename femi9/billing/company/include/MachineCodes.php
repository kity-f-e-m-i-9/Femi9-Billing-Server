<?php
/**
 * MachineCodes — global, manageable list of packing-machine codes.
 * Used to optionally tag which physical machine performed a piece<->pack
 * conversion (see neksomo-piece-pack-convert.php). Not scoped per
 * company/godown — one shared list across the whole system.
 */

function ensure_machine_code_master_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS machine_code_master (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL,
            name VARCHAR(150) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_machine_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function get_active_machine_codes(mysqli $db_conn): array
{
    ensure_machine_code_master_table($db_conn);
    return $db_conn->query(
        "SELECT id, code, name FROM machine_code_master WHERE is_active = 1 ORDER BY code ASC"
    )->fetch_all(MYSQLI_ASSOC);
}
