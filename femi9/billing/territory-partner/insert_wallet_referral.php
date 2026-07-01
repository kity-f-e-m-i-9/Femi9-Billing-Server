<?php
/*
 * Runs on every TP dashboard load.
 * Credits referral commission to the referrer's wallet IF the TP hit their
 * location target last month. One record per TP per month (dedup check).
 * Wrapped in try-catch: any DB/table missing error is silently skipped.
 */

try {

date_default_timezone_set("Asia/Kolkata");

$_wr_today         = date("Y-m-d");
$_wr_lastMonthFirst = date("Y-m-01", strtotime("-1 month", strtotime($_wr_today)));
$_wr_lastMonthDays  = (int)date("t", strtotime($_wr_lastMonthFirst));
$_wr_lastMonthLast  = date("Y-m-{$_wr_lastMonthDays}", strtotime($_wr_lastMonthFirst));
$_wr_monthName      = date("M", strtotime($_wr_lastMonthFirst));
$_wr_year           = date("Y", strtotime($_wr_lastMonthFirst));

$_wr_tpId = (int)$Login_user_IDvl;
$_wr_tpEsc = mysqli_real_escape_string($db_conn, (string)$_wr_tpId);

// 1. Load TP's referral setup
$_wr_tp = mysqli_fetch_assoc(mysqli_query($db_conn,
    "SELECT referral_id, referral_type, referral_percentage
     FROM territory_partners WHERE id='$_wr_tpEsc' LIMIT 1"
));
if (empty($_wr_tp['referral_id']) || empty($_wr_tp['referral_type']) || !$_wr_tp['referral_percentage']) return;

$_wr_refId  = $_wr_tp['referral_id'];
$_wr_refTyp = $_wr_tp['referral_type'];  // 'CP','TP','SS','Stockist','SD','D'
$_wr_refPct = (float)$_wr_tp['referral_percentage'];

// 2. Get TP's location target for last month
$_wr_from_esc = mysqli_real_escape_string($db_conn, $_wr_lastMonthFirst);
$_wr_to_esc   = mysqli_real_escape_string($db_conn, $_wr_lastMonthLast);

$_wr_tgt = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(n.target_amount),0)
     FROM territory_partner_locations tpl
     JOIN partner_location_nodes n ON n.id = tpl.location_id
     WHERE tpl.territory_partner_id='$_wr_tpEsc'"))[0] ?? 0);

if ($_wr_tgt <= 0) return;  // No target configured

// 3. TP's actual purchases last month from tp_invoices
$_wr_purchase = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(total_amount),0) FROM tp_invoices
     WHERE territory_partner_id='$_wr_tpEsc'
       AND invoice_date BETWEEN '$_wr_from_esc' AND '$_wr_to_esc'"))[0] ?? 0);

// Deduct returns
$_wr_returns = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(total),0) FROM user_return_stock
     WHERE from_usertype='territory_partner' AND from_userid='$_wr_tpEsc'
       AND date BETWEEN '$_wr_from_esc' AND '$_wr_to_esc'"))[0] ?? 0);

$_wr_net = $_wr_purchase - $_wr_returns;

// 4. Target check
if ($_wr_net < $_wr_tgt) return;

// 5. Dedup — already credited this TP+month?
$_wr_utype_esc = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
$_wr_dup = (int)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COUNT(*) FROM wallet_monthly_sls_report
     WHERE user_type='$_wr_utype_esc' AND user_id='$_wr_tpEsc'
       AND from_date='$_wr_from_esc' AND to_date='$_wr_to_esc'
       AND commission_type='Refferral'"))[0] ?? 0);
if ($_wr_dup > 0) return;

// 6. Resolve referrer's wallet ID and internal type
$_wr_typeMap = [
    'CP'       => ['internal' => 'channel_partner',   'table' => 'channel_partners',   'id_col' => 'cp_id',      'wid_col' => 'id'],
    'TP'       => ['internal' => 'territory_partner', 'table' => 'territory_partners', 'id_col' => 'tp_id',      'wid_col' => 'id'],
    'SS'       => ['internal' => 'super_stockiest',  'table' => 'super_stockiest',    'id_col' => 'useridtext', 'wid_col' => 'temp_id'],
    'Stockist' => ['internal' => 'stockiest',        'table' => 'stockiest',          'id_col' => 'useridtext', 'wid_col' => 'temp_id'],
    'SD'       => ['internal' => 'super_distributor','table' => 'super_distributor',  'id_col' => 'useridtext', 'wid_col' => 'temp_id'],
    'D'        => ['internal' => 'distributor',      'table' => 'distributor',        'id_col' => 'useridtext', 'wid_col' => 'temp_id'],
];

if (!isset($_wr_typeMap[$_wr_refTyp])) return;

$_wr_map     = $_wr_typeMap[$_wr_refTyp];
$_wr_rid_esc = mysqli_real_escape_string($db_conn, $_wr_refId);

$_wr_refRow = mysqli_fetch_assoc(mysqli_query($db_conn,
    "SELECT `{$_wr_map['wid_col']}` AS wid
     FROM `{$_wr_map['table']}`
     WHERE `{$_wr_map['id_col']}` = '$_wr_rid_esc' LIMIT 1"
));
if (!$_wr_refRow || empty($_wr_refRow['wid'])) return;

$_wr_refWid      = mysqli_real_escape_string($db_conn, (string)$_wr_refRow['wid']);
$_wr_refInternal = mysqli_real_escape_string($db_conn, $_wr_map['internal']);

// 7. Commission amount
$_wr_commission = round($_wr_net * $_wr_refPct / 100, 2);

// 8. Insert wallet record
mysqli_query($db_conn,
    "INSERT INTO wallet_monthly_sls_report
     (user_type, user_id, from_date, to_date, month, year,
      total_sls_amount, target_sls_amount, target_reached,
      refer_by_usertype, refer_by_userid, commission_percentage,
      commission_amount, commission_type, remarks)
     VALUES
     ('$_wr_utype_esc','$_wr_tpEsc',
      '$_wr_from_esc','$_wr_to_esc','$_wr_monthName','$_wr_year',
      '$_wr_net','$_wr_tgt','yes',
      '$_wr_refInternal','$_wr_refWid','$_wr_refPct',
      '$_wr_commission','Refferral','Nil')"
);

} catch (\Throwable $_wr_e) {
    // Silently skip — missing table/column should not crash the dashboard
}
