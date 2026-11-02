<?php
// Shared query logic for the "Daily Assign Report" page — used by both the
// AJAX JSON endpoint (get-daily-assign-report.php) and the Excel export
// (export-daily-assign-report-xlsx.php) so the two never drift apart.
require_once __DIR__ . "/../../marketing/include/AssignedLocations.php";

function fetchDailyAssignRows($db_conn, int $ms_id, string $from, string $to): array {
    $sql = "
        SELECT da.assign_date, d.name AS district_name, dv.name AS division_name,
               f.name AS firka_name, COALESCE(tp.name, 'Vacant Area') AS tp_name,
               da.assigned_by
        FROM tp_daily_assignments da
        JOIN partner_location_nodes f  ON f.id  = da.location_id
        JOIN partner_location_nodes t  ON t.id  = f.parent_id
        JOIN partner_location_nodes dv ON dv.id = t.parent_id
        JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        LEFT JOIN territory_partners tp ON tp.id = da.territory_partner_id
        WHERE da.assign_date BETWEEN ? AND ?
    ";

    $types  = 'ss';
    $params = [$from, $to];

    if ($ms_id) {
        $districts = getMsAssignedDistricts($db_conn, $ms_id);
        $distIds = array_column($districts, 'id');
        if (empty($distIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($distIds), '?'));
        $sql .= " AND d.id IN ($placeholders)";
        $types .= str_repeat('i', count($distIds));
        foreach ($distIds as $id) { $params[] = $id; }
    }

    $sql .= " ORDER BY da.assign_date DESC, d.name ASC, f.name ASC";

    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}
