<?php
/**
 * "Zone" = a company-defined group of districts (from the shared
 * partner_location_nodes tree — see partner_location_layers for the
 * COUNTRY > STATE > DISTRICT > ... depths), used to:
 *   - offer a fixed list of zones on Add/Edit Sales BDM's "Zone" field
 *     (was free text)
 *   - scope tp-zone-advance-payments.php to one zone's TPs
 *
 * A district can belong to at most one zone (uk_zone_district below) —
 * the Manage Zones picker shows an already-assigned district as disabled/
 * labeled rather than letting a second zone claim it.
 *
 * TP-to-zone matching reuses the same name-based approach as
 * salesbdm/include/BdmTpScope.php's getBdmAssignedTpIds() — territory_partners
 * has no FK to the location tree, only a free-text assigned_district
 * (falling back to branch_district), matched case-insensitively.
 */

function ensurePartnerZonesTables(mysqli $db_conn): void
{
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS partner_zones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_zone_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS partner_zone_districts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            zone_id INT NOT NULL,
            district_node_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_zone_district (district_node_id),
            KEY idx_zone (zone_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/** The depth of the DISTRICT layer in partner_location_layers. */
function getZoneDistrictDepth(mysqli $db_conn): int
{
    $row = $db_conn->query("SELECT depth FROM partner_location_layers WHERE UPPER(layer_name) LIKE 'DISTRICT%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
    return $row ? (int)$row['depth'] : 3;
}

/** All zones with their district + matching-TP counts, for the Manage Zones list. */
function getAllZonesWithCounts(mysqli $db_conn): array
{
    ensurePartnerZonesTables($db_conn);
    $zones = $db_conn->query("
        SELECT z.id, z.name, COUNT(zd.id) AS district_count
        FROM partner_zones z
        LEFT JOIN partner_zone_districts zd ON zd.zone_id = z.id
        GROUP BY z.id, z.name
        ORDER BY z.name ASC
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($zones as &$z) {
        $z['tp_count'] = count(getZoneTpIds($db_conn, (int)$z['id']));
    }
    unset($z);
    return $zones;
}

/** District node ids already assigned to a zone => zone name (for the picker). */
function getAssignedDistrictZoneNames(mysqli $db_conn): array
{
    ensurePartnerZonesTables($db_conn);
    $rows = $db_conn->query("
        SELECT zd.district_node_id, z.name AS zone_name
        FROM partner_zone_districts zd
        JOIN partner_zones z ON z.id = zd.zone_id
    ")->fetch_all(MYSQLI_ASSOC);
    $map = [];
    foreach ($rows as $r) { $map[(int)$r['district_node_id']] = $r['zone_name']; }
    return $map;
}

/** This zone's district node ids. */
function getZoneDistrictNodeIds(mysqli $db_conn, int $zoneId): array
{
    ensurePartnerZonesTables($db_conn);
    $stmt = $db_conn->prepare("SELECT district_node_id FROM partner_zone_districts WHERE zone_id = ?");
    $stmt->bind_param('i', $zoneId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($r) => (int)$r['district_node_id'], $rows);
}

/** This zone's district NAMES — what territory_partners.assigned_district is matched against. */
function getZoneDistrictNames(mysqli $db_conn, int $zoneId): array
{
    $ids = getZoneDistrictNodeIds($db_conn, $zoneId);
    if (empty($ids)) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $db_conn->prepare("SELECT name FROM partner_location_nodes WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($r) => $r['name'], $rows);
}

/**
 * TP ids whose assigned_district (falling back to branch_district) falls
 * inside this zone's districts — same case-insensitive name match as
 * salesbdm/include/BdmTpScope.php's getBdmAssignedTpIds().
 */
function getZoneTpIds(mysqli $db_conn, int $zoneId, bool $includeInactive = false): array
{
    $names = array_map(fn($n) => mb_strtolower(trim($n)), getZoneDistrictNames($db_conn, $zoneId));
    if (empty($names)) return [];

    $_col = $db_conn->query("SHOW COLUMNS FROM territory_partners LIKE 'assigned_district'");
    if ($_col && $_col->num_rows === 0) {
        $db_conn->query("ALTER TABLE territory_partners ADD COLUMN assigned_district VARCHAR(100) DEFAULT NULL AFTER branch_district");
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $types = str_repeat('s', count($names));
    $activeClause = $includeInactive ? '' : 'is_active = 1 AND ';
    $stmt = $db_conn->prepare("
        SELECT id FROM territory_partners
        WHERE {$activeClause}LOWER(TRIM(COALESCE(NULLIF(assigned_district,''), branch_district))) IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$names);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($r) => (int)$r['id'], $rows);
}
