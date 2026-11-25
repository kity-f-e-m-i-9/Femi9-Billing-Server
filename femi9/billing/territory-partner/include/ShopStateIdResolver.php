<?php
// shop.state_id is a `state` TABLE id (e.g. 7=Tamilnadu, 18=Karnataka), NOT
// a partner_location_nodes id (where Tamilnadu=2, Karnataka=1319) — see
// shared/ShopInvoiceData.php's own comment; every GST-calculation file that
// reads shop.state_id for invoice printing depends on this. But the State
// <select> rendered by geo_layers.php (loc_2) submits a
// partner_location_nodes id, and shop-action.php/shop-edit-action.php were
// both saving that raw submitted value straight into shop.state_id —
// corrupting it into the WRONG id space on every single Add/Edit Shop save,
// which is how ~30,000 shop rows ended up with garbage state_id in the
// first place (confirmed 2026-12-01, same investigation as geo_layers.php's
// depthToField fix). resolveRealStateId() converts a submitted
// partner_location_nodes State id into the real `state` table id those
// action scripts should actually store; returns 0 (same as "not provided")
// if no match is found.
function resolveRealStateId(mysqli $db_conn, int $plnStateId): int {
    if ($plnStateId <= 0) { return 0; }
    $stmt = $db_conn->prepare("
        SELECT rs.id
        FROM partner_location_nodes pln
        JOIN state rs ON LOWER(REPLACE(rs.st_name, ' ', '')) = LOWER(REPLACE(pln.name, ' ', ''))
        WHERE pln.id = ? AND pln.depth = 2
        LIMIT 1
    ");
    $stmt->bind_param('i', $plnStateId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : 0;
}
