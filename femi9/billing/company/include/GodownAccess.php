<?php
/**
 * Restricts company_godown rows flagged finance_only=1 (e.g. Femi Health Care,
 * Neksomo Hygiene Industries) to sessions logged in as admin_log.usertype='finance'.
 */

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

// SQL fragment to AND into a company_godown WHERE clause. $alias is the table
// alias/prefix used in the query (e.g. 'cg' -> 'cg.finance_only = 0'), blank if none.
function godown_finance_filter_sql($db_conn, $alias = '') {
    if (is_finance_login($db_conn)) return '1=1';
    $prefix = $alias ? "{$alias}." : '';
    return "{$prefix}finance_only = 0";
}

// Guard for pages that load a single godown by id from user input (e.g. $_REQUEST['gid']).
function is_godown_allowed($db_conn, $godownId) {
    if (is_finance_login($db_conn)) return true;
    $stmt = $db_conn->prepare("SELECT finance_only FROM company_godown WHERE id = ?");
    $stmt->bind_param("i", $godownId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;
    return (int)$row['finance_only'] === 0;
}

// Subquery of allowed godown ids, for filtering rows in other tables keyed by a
// godown id column, e.g.: "... WHERE godownid IN (" . godown_ids_subquery($db_conn) . ")"
function godown_ids_subquery($db_conn) {
    return "SELECT id FROM company_godown WHERE " . godown_finance_filter_sql($db_conn);
}
