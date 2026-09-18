<?php
/**
 * Restricts company_godown rows flagged finance_only=1 (e.g. Femi Health Care,
 * Neksomo Hygiene Industries) to sessions logged in as admin_log.usertype='finance'.
 *
 * A 'neksomo' login is scoped even further: it only ever sees the single
 * 'NEKSOMO HYGIENE INDUSTRIES' godown, never 'FEMI HEALTH CARE' or any
 * regular (non finance-only) godown.
 */

// Self-migrating: ensure finance_only column exists (schema may not have been
// migrated on every environment yet). Cheap SHOW COLUMNS check, safe to repeat.
if (isset($db_conn) && $db_conn instanceof mysqli) {
    $_godownAccessCol = $db_conn->query("SHOW COLUMNS FROM company_godown LIKE 'finance_only'");
    if ($_godownAccessCol && $_godownAccessCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE company_godown ADD COLUMN finance_only TINYINT(1) NOT NULL DEFAULT 0 AFTER gname");
        $db_conn->query("UPDATE company_godown SET finance_only = 1 WHERE gname IN ('FEMI HEALTH CARE', 'NEKSOMO HYGIENE INDUSTRIES')");
    }
}

// function_exists guards below: this file is require_once'd from 140+ call
// sites using a mix of relative ("include/GodownAccess.php") and __DIR__-
// based absolute paths. On a case-insensitive filesystem (macOS default),
// a request URL whose directory casing differs from the real on-disk
// casing (e.g. "femi9 billing server" vs "Femi9 Billing Server") makes
// PHP's require_once dedupe-by-literal-path fail to recognize the two
// spellings as the same file, so it can load and execute this file twice
// in one request — the second run threw "Cannot redeclare
// get_login_usertype()" without this guard. See
// docs/superpowers/plans/2026-09-15-transfer-tax-invoice.md's Task 1,
// which added a second __DIR__-based require_once of this file from
// shared/TransferInvoiceData.php, exposing the gap.
if (!function_exists('get_login_usertype')) {
function get_login_usertype($db_conn) {
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $db_conn->prepare("SELECT usertype FROM admin_log WHERE username = ?");
    $stmt->bind_param("s", $_SESSION['LOGIN_USER']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cached = $row['usertype'] ?? '';
    return $cached;
}

function is_finance_login($db_conn) {
    return get_login_usertype($db_conn) === 'finance';
}

function is_neksomo_login($db_conn) {
    return get_login_usertype($db_conn) === 'neksomo';
}

// Read-only, company-wide stock insights login (see docs/superpowers/specs/
// 2026-09-18-stock-viewer-login-design.md) — sees every company profile and
// godown, same breadth as finance, but never writes stock.
function is_stockviewer_login($db_conn) {
    return get_login_usertype($db_conn) === 'stockviewer';
}

// SQL fragment to AND into a company_godown WHERE clause. $alias is the table
// alias/prefix used in the query (e.g. 'cg' -> 'cg.finance_only = 0'), blank if none.
function godown_finance_filter_sql($db_conn, $alias = '') {
    $prefix = $alias ? "{$alias}." : '';
    if (is_neksomo_login($db_conn)) return "{$prefix}gname = 'NEKSOMO HYGIENE INDUSTRIES'";
    if (is_finance_login($db_conn) || is_stockviewer_login($db_conn)) return '1=1';
    return "{$prefix}finance_only = 0";
}

// Guard for pages that load a single godown by id from user input (e.g. $_REQUEST['gid']).
function is_godown_allowed($db_conn, $godownId) {
    if (is_finance_login($db_conn) || is_stockviewer_login($db_conn)) return true;
    $stmt = $db_conn->prepare("SELECT finance_only, gname FROM company_godown WHERE id = ?");
    $stmt->bind_param("i", $godownId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    if (is_neksomo_login($db_conn)) return $row['gname'] === 'NEKSOMO HYGIENE INDUSTRIES';
    return (int)$row['finance_only'] === 0;
}

// Subquery of allowed godown ids, for filtering rows in other tables keyed by a
// godown id column, e.g.: "... WHERE godownid IN (" . godown_ids_subquery($db_conn) . ")"
function godown_ids_subquery($db_conn) {
    return "SELECT id FROM company_godown WHERE " . godown_finance_filter_sql($db_conn);
}
} // end function_exists('get_login_usertype') guard
