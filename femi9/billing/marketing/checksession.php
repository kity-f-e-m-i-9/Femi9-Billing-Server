<?php require_once __DIR__ . '/../shared/csrf-guard.php'; femi_csrf_guard();
// Start session if not already started
ini_set('session.gc_maxlifetime', 18000); // match 5-hour app timeout; php.ini default (24min) was killing idle sessions
if (session_status() === PHP_SESSION_NONE) {
    if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
        session_save_path(sys_get_temp_dir());
    }
    session_start();
}

// Include database connection
require_once __DIR__ . '/include/db-connect.php';

// Company "view a Marketing Staff's dashboard" bridge — read-only,
// allowlisted pages only. Mirrors salesbdm/checksession.php's own
// company_bdm_view_bridge mechanism exactly (see its comment for the full
// reasoning). Never touches $_SESSION — it just resolves $result_LoGuserDtails
// for THIS request, and config.php (included right after) picks it up
// instead of re-querying by $_SESSION['LOGIN_USER'].
$_msCompanyBridgeAllowedScripts = ['dashboard.php', 'kpi.php'];
if (in_array(basename($_SERVER['SCRIPT_NAME']), $_msCompanyBridgeAllowedScripts, true) && !empty($_COOKIE['femi9_company_ms_view'])) {
    $db_conn->query("CREATE TABLE IF NOT EXISTS company_ms_view_bridge (
        token VARCHAR(64) PRIMARY KEY,
        ms_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $stmt_cmv = $db_conn->prepare("
        SELECT s.* FROM company_ms_view_bridge b
        JOIN marketing_staff s ON s.id = b.ms_id
        WHERE b.token = ? AND b.expires_at > NOW() LIMIT 1
    ");
    $stmt_cmv->bind_param('s', $_COOKIE['femi9_company_ms_view']);
    $stmt_cmv->execute();
    $bridgeMsRow = $stmt_cmv->get_result()->fetch_assoc();
    $stmt_cmv->close();
    if ($bridgeMsRow && ($bridgeMsRow['account_status'] ?? '') === 'active') {
        $result_LoGuserDtails = $bridgeMsRow;
        $log_username = $bridgeMsRow['ms_mobile'];
        $Login_user_IDvl = (int)$bridgeMsRow['id'];
        $Login_user_TYPEvl = 'marketing';
        $_companyBridgeView = true;
        return;
    }
}

// Check if user is logged in - Use isset() instead of empty()
if (!isset($_SESSION['LOGIN_USER']) || $_SESSION['LOGIN_USER'] === '') {
    $_SESSION['errorMessage'] = 'Session Expired. Please login again.';
    header('Location: ../login/index.php');
    exit;
}

// The session may belong to a different account type (e.g. after switching
// accounts, or a session shared across panels) — reject if it doesn't match.
if (isset($_SESSION['LOGIN_USER_TYPE']) && $_SESSION['LOGIN_USER_TYPE'] !== 'marketing') {
    session_unset();
    session_destroy();
    header('Location: ../login/index.php');
    exit;
}

// Session timeout check (5 hours of inactivity)
$timeout_duration = 18000; // 5 hours

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    
    if ($elapsed_time > $timeout_duration) {
        // Session expired due to inactivity
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['errorMessage'] = 'Session expired due to inactivity. Please login again.';
        header('Location: ../login/index.php');
        exit;
    }
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Get user details from database
$log_username = $_SESSION['LOGIN_USER'];
$Login_user_IDvl = $_SESSION['LOGIN_USER_ID'] ?? '';
$Login_user_TYPEvl = $_SESSION['LOGIN_USER_TYPE'] ?? 'marketing';

// Fetch user details
$select_LoGuserDtails = "SELECT * FROM marketing_staff WHERE ms_mobile = ? LIMIT 1";
$stmt_LoGuserDtails = mysqli_prepare($db_conn, $select_LoGuserDtails);
mysqli_stmt_bind_param($stmt_LoGuserDtails, "s", $log_username);
mysqli_stmt_execute($stmt_LoGuserDtails);
$fetch_LoGuserDtails = mysqli_stmt_get_result($stmt_LoGuserDtails);
$result_LoGuserDtails = mysqli_fetch_assoc($fetch_LoGuserDtails);
mysqli_stmt_close($stmt_LoGuserDtails);

// Check if user exists
if (!$result_LoGuserDtails) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'User account not found. Please contact support.';
    header('Location: ../login/index.php');
    exit;
}

// Check if account is still active
if (isset($result_LoGuserDtails['account_status']) && $result_LoGuserDtails['account_status'] != 'active') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'Your account has been deactivated. Please contact support.';
    header('Location: ../login/index.php');
    exit;
}

// Set additional session variables if not set
if (!isset($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['id'];
}
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['ms_name'];
}

// If this DM has any of today's own Firka assignments still unresolved
// (nothing chosen yet, or chosen but not yet Accepted by the TP / Resolved
// by ASM/Company), lock the whole portal to daily-work-start.php until it
// clears -- same gate pattern as territory-partner/checksession.php's own
// Accept/Deny block, mirrored here so the DM-side half of the chain is
// enforced just as hard (2026-11-20, user request). Wrapped in try/catch and
// behind a table-exists check for the same reason that one is: this must
// never be able to lock everyone out just because the self-migrating
// tp_daily_assignments table/columns are missing somewhere.
// work-start-requests.php / work-start-resolve-action.php — the ONLY pages
// that can resolve a subordinate DM's escalated "Ask ASM/Company" request —
// were missing from this list. dmHasBlockingWorkStartToday() walks the
// recursive subtree under this person's own scope nodes, so an ASM/SM gets
// gated the moment ANY subordinate DM has an unresolved firka assignment,
// not just their own. Without these two here, that ASM/SM had no way to
// ever reach the one page that could clear the block — a deadlock, since
// daily-work-start.php itself only lets a DM review their own direct
// roster, with no UI for a subordinate's escalation. 2026-11-21,
// code-review finding.
//
// manage-daily-assign-district-tps.php and its own AJAX endpoints — the
// SAME deadlock class, one step earlier in the chain: the moment an ASM
// uses this page to assign a TP to a Firka for today, that very row is
// "pending"/"none" (tp_approval_status/company_request_status) until the
// TP accepts or someone resolves it — which immediately satisfies
// dmHasBlockingWorkStartToday() for the ASM's OWN session (the new row sits
// inside their own subtree), gating every subsequent AJAX call this same
// page makes. The "get-*" endpoints below already got the JSON error
// response instead of a raw HTML redirect (see the AJAX detection further
// down), but still silently lost their real assigned_tp_id/assigned_tp_name
// payload — the frontend then reads that as "nothing assigned" and renders
// "Vacant Area", even though the assignment it just saved is sitting right
// there in the DB. Confirmed live: saving an assignment for Firka "Erode A"
// (location_id 972, TP Thilagavathy.D) immediately re-showed "Vacant Area"
// on the very next lookup. 2026-11-23, user-reported.
$__dwsExemptPages = [
    'daily-work-start.php', 'daily-work-start-action.php', 'work-start-requests.php', 'work-start-resolve-action.php',
    'change-password.php', 'logout.php',
    'manage-daily-assign-district-tps.php', 'get-tp-assign-staff.php', 'get-tp-assign-districts.php',
    'get-tp-assign-divisions.php', 'get-tp-assign-firkas.php', 'get-tp-assign-tp-for-firka.php', 'tp-assign-firka-action.php',
    // Read-only report — no reason to ever block it. 2026-11-25, user-reported
    // (clicking this link from the sidebar hit the same lock screen).
    'daily-assign-report.php', 'get-daily-assign-report.php', 'export-daily-assign-report-xlsx.php',
];
$__dwsCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
if (!in_array($__dwsCurrentPage, $__dwsExemptPages, true)) {
    try {
        $__dwsChk = $db_conn->query("SHOW TABLES LIKE 'tp_daily_assignments'");
        if ($__dwsChk && $__dwsChk->num_rows > 0) {
            $__dwsColChk = $db_conn->query("SHOW COLUMNS FROM tp_daily_assignments LIKE 'tp_approval_status'");
            if ($__dwsColChk && $__dwsColChk->num_rows > 0) {
                // This hard self-lock is only meant for a plain DM, who
                // physically works a Firka themselves and so must personally
                // confirm Daily Work Start for it. An ASM/SM (anyone with
                // subordinates) can ALSO hold their own direct location
                // assignment at District/Division depth for oversight
                // purposes — dmHasBlockingWorkStartToday()'s recursive match
                // then catches a SUBORDINATE DM's own Firka assignment too
                // (it's a descendant of that broader district/division node),
                // locking the ASM's entire portal the moment they use "Daily
                // Assign District TPs" to help a subordinate, even though
                // it's that DM's confirmation to give, not theirs. An ASM/SM
                // still reaches work-start-requests.php (exempted above) to
                // resolve an escalated request — this only removes the
                // blanket self-lock for managers. 2026-11-25, user-reported.
                $__dwsHasSubordinates = false;
                if ((int) $Login_user_IDvl > 0) {
                    $__dwsTeamChk = $db_conn->prepare("SELECT COUNT(*) AS n FROM marketing_staff WHERE manager_id=?");
                    $__dwsTeamChk->bind_param('i', $Login_user_IDvl);
                    $__dwsTeamChk->execute();
                    $__dwsHasSubordinates = (int) ($__dwsTeamChk->get_result()->fetch_assoc()['n'] ?? 0) > 0;
                    $__dwsTeamChk->close();
                }
                require_once __DIR__ . '/include/AssignedLocations.php';
                require_once __DIR__ . '/../shared/TpWorkStart.php';
                $__dwsScopeNodes = $__dwsHasSubordinates ? [] : getMsAssignedTargetScopeNodes($db_conn, (int) $Login_user_IDvl);
                $__dwsScopeIds = array_column($__dwsScopeNodes, 'id');
                if (!$__dwsHasSubordinates && dmHasBlockingWorkStartToday($db_conn, $__dwsScopeIds)) {
                    // An AJAX/action/export endpoint hit from an already-open tab
                    // while blocked otherwise got this HTML redirect instead of
                    // the JSON/binary payload its caller expects — JSON.parse on
                    // the redirect's HTML body throws, so the save/lookup just
                    // silently does nothing with no visible error. Detected by
                    // filename convention (same "get-*.php" / "*-action.php"
                    // naming this codebase already uses for its JSON endpoints)
                    // or an explicit XMLHttpRequest header, whichever fires
                    // first. 2026-11-21, code-review finding.
                    $__dwsIsAjax = (strpos($__dwsCurrentPage, 'get-') === 0)
                        || (substr($__dwsCurrentPage, -11) === '-action.php')
                        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
                    if ($__dwsIsAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error'   => 'blocked_daily_work_start',
                            'message' => "Today's Firka assignment needs to be resolved first — go to daily-work-start.php.",
                        ]);
                        exit;
                    }
                    header('Location: daily-work-start.php');
                    exit;
                }
            }
        }
    } catch (\Throwable $e) {
        // Fail open — never block the whole portal over this check.
    }
}

// All checks passed - user is authenticated
?>