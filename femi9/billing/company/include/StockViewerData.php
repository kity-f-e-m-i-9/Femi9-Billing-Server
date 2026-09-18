<?php
/**
 * Read-only query layer for the Stock Viewer login (see docs/superpowers/
 * specs/2026-09-18-stock-viewer-login-design.md). Every function here is a
 * pure SELECT — nothing in this file ever writes to `stock` or any other
 * table.
 */

const STOCK_VIEWER_LOW_STOCK_THRESHOLD = 10;

// One row per (product, company profile, warehouse) that currently has a
// stock row — joined with product/profile/warehouse names. $filters may
// contain 'product_id', 'company_godown_id', 'warehouse_id', each a scalar
// or array of ints; omitted/empty filters are not applied. Neksomo's raw
// NKS-% placeholder products are always excluded — never shown to any
// company-facing viewer.
function get_stock_viewer_rows(mysqli $db_conn, array $filters = []): array
{
    $where = ["(p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)", "s.user_type = 'company'"];
    $params = [];
    $types = '';

    foreach (['product_id' => 's.product_id', 'company_godown_id' => 's.user_id', 'warehouse_id' => 's.warehouse_id'] as $key => $column) {
        if (empty($filters[$key])) continue;
        $values = is_array($filters[$key]) ? $filters[$key] : [$filters[$key]];
        $values = array_map('intval', $values);
        if (empty($values)) continue;
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $where[] = "$column IN ($placeholders)";
        foreach ($values as $v) { $params[] = $v; $types .= 'i'; }
    }

    $sql = "SELECT
                s.product_id, p.productName, p.unit_type, p.pieces_per_pack,
                s.user_id AS company_godown_id, cg.gname AS company_godown_name,
                s.warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                s.closing_qty, s.extra_pieces
            FROM stock s
            JOIN products p ON p.id = s.product_id
            JOIN company_godown cg ON cg.id = s.user_id
            LEFT JOIN warehouses w ON w.id = s.warehouse_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY cg.gname ASC, w.code ASC, p.productName ASC";

    $stmt = $db_conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Top-level summary numbers for the dashboard's cards.
function get_stock_viewer_summary(mysqli $db_conn): array
{
    $rows = get_stock_viewer_rows($db_conn);

    $totalClosingQty = 0;
    $skuIds = [];
    $profileIds = [];
    $warehouseIds = [];
    $closingByProduct = [];

    foreach ($rows as $row) {
        $totalClosingQty += (int) $row['closing_qty'];
        $skuIds[(int) $row['product_id']] = true;
        $profileIds[(int) $row['company_godown_id']] = true;
        if ($row['warehouse_id'] !== null) $warehouseIds[(int) $row['warehouse_id']] = true;
        $pid = (int) $row['product_id'];
        $closingByProduct[$pid] = ($closingByProduct[$pid] ?? 0) + (int) $row['closing_qty'];
    }

    $lowStockCount = 0;
    foreach ($closingByProduct as $qty) {
        if ($qty < STOCK_VIEWER_LOW_STOCK_THRESHOLD) $lowStockCount++;
    }

    return [
        'total_closing_qty'   => $totalClosingQty,
        'sku_count'           => count($skuIds),
        'company_profile_count' => count($profileIds),
        'warehouse_count'     => count($warehouseIds),
        'low_stock_count'     => $lowStockCount,
    ];
}

// [company_godown_name => total closing_qty], ordered by name.
function get_stock_viewer_totals_by_profile(mysqli $db_conn): array
{
    $totals = [];
    foreach (get_stock_viewer_rows($db_conn) as $row) {
        $name = $row['company_godown_name'];
        $totals[$name] = ($totals[$name] ?? 0) + (int) $row['closing_qty'];
    }
    ksort($totals);
    return $totals;
}

// [warehouse label => total closing_qty], "Unassigned" bucket for rows with
// no warehouse_id, ordered by warehouse code.
function get_stock_viewer_totals_by_warehouse(mysqli $db_conn): array
{
    $totals = [];
    foreach (get_stock_viewer_rows($db_conn) as $row) {
        $label = $row['warehouse_id'] !== null
            ? $row['warehouse_code'] . ($row['warehouse_name'] ? ' - ' . $row['warehouse_name'] : '')
            : 'Unassigned';
        $totals[$label] = ($totals[$label] ?? 0) + (int) $row['closing_qty'];
    }
    return $totals;
}

// Top $limit products by total closing_qty across every profile/warehouse,
// as [['productName' => ..., 'total_qty' => ...], ...], descending.
function get_stock_viewer_top_products(mysqli $db_conn, int $limit = 10): array
{
    $totals = [];
    foreach (get_stock_viewer_rows($db_conn) as $row) {
        $name = $row['productName'];
        $totals[$name] = ($totals[$name] ?? 0) + (int) $row['closing_qty'];
    }
    arsort($totals);
    $top = array_slice($totals, 0, $limit, true);

    $result = [];
    foreach ($top as $name => $qty) {
        $result[] = ['productName' => $name, 'total_qty' => $qty];
    }
    return $result;
}

// Every product that currently has at least one stock row — for the
// "Stock by Product" page's picker.
function get_stock_viewer_product_options(mysqli $db_conn): array
{
    $sql = "SELECT DISTINCT p.id, p.productName
            FROM stock s
            JOIN products p ON p.id = s.product_id
            WHERE (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL) AND s.user_type = 'company'
            ORDER BY p.productName ASC";
    return $db_conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Every company profile and every active warehouse — for filter bars /
// pickers across all 4 pages.
function get_stock_viewer_company_profiles(mysqli $db_conn): array
{
    return $db_conn->query("SELECT id, gname FROM company_godown ORDER BY gname ASC")->fetch_all(MYSQLI_ASSOC);
}

function get_stock_viewer_warehouses(mysqli $db_conn): array
{
    return $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);
}
