<?php
/**
 * active-users.php
 * Sequential userid based ONLY on last ACTIVE account
 * Concurrency-safe using transaction + FOR UPDATE
 */

include("checksession.php");
include("config.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ─────────────────────────────────────────────
   1. Allowed User Types
───────────────────────────────────────────── */

$allowed_types = [
    'super_stockiest' => ['table'=>'super_stockiest','prefix'=>'SS','label'=>'Super Stockist'],
    'stockist' => ['table'=>'stockiest','prefix'=>'S','label'=>'Stockist'],
    'super_distributor' => ['table'=>'super_distributor','prefix'=>'SD','label'=>'Super Distributor'],
    'distributor' => ['table'=>'distributor','prefix'=>'D','label'=>'Distributor'],
];

$usertype = $_REQUEST['usertype'] ?? '';

if (!isset($allowed_types[$usertype])) {
    $_SESSION['ErrorMessage'] = "Invalid user type.";
    echo "<script>opener.location.reload(true); self.close();</script>";
    exit;
}

$config = $allowed_types[$usertype];
$table  = $config['table'];
$prefix = $config['prefix'];
$label  = $config['label'];

$userrowid = intval($_REQUEST['userrowid'] ?? 0);

if ($userrowid <= 0) {
    $_SESSION['ErrorMessage'] = "Invalid user ID.";
    echo "<script>opener.location.reload(true); self.close();</script>";
    exit;
}

/* ─────────────────────────────────────────────
   2. Start Transaction
───────────────────────────────────────────── */

mysqli_begin_transaction($db_conn);

try {

    // Lock active rows only
    $lockQuery = mysqli_query(
        $db_conn,
        "SELECT MAX(userid) as last_id 
         FROM `$table` 
         WHERE account_status = 'active'
         FOR UPDATE"
    );

    if (!$lockQuery) {
        throw new Exception("Lock failed.");
    }

    $row = mysqli_fetch_assoc($lockQuery);
    $next_userid = ($row['last_id'] ?? 0) + 1;

    $useridtext = "FEMI9-$prefix-" . $next_userid;

    /* ─────────────────────────────────────────────
       3. Update & Activate
    ───────────────────────────────────────────── */

    $stmt = mysqli_prepare($db_conn,
        "UPDATE `$table`
         SET userid = ?,
             useridtext = ?,
             account_status = 'active',
             updated_at = NOW()
         WHERE id = ? AND account_status != 'active'"
    );

    if (!$stmt) {
        throw new Exception("Prepare failed.");
    }

    mysqli_stmt_bind_param($stmt, "isi",
        $next_userid,
        $useridtext,
        $userrowid
    );

    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) == 0) {
        throw new Exception("Already active or invalid user.");
    }

    mysqli_stmt_close($stmt);

    mysqli_commit($db_conn);

} catch (Exception $e) {

    mysqli_rollback($db_conn);

    $_SESSION['ErrorMessage'] = "Activation failed: " . $e->getMessage();
    echo "<script>opener.location.reload(true); self.close();</script>";
    exit;
}

/* ─────────────────────────────────────────────
   4. Webhook Call
───────────────────────────────────────────── */

$data = ['coupon_code' => $useridtext];
$jsonData = json_encode($data);

$ch = curl_init('https://maintain.femi9.in/api/webhooks/coupon');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $jsonData,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Api-Key: YOUR_REAL_API_KEY_HERE'
    ],
    CURLOPT_SSL_VERIFYPEER => true
]);

$response   = curl_exec($ch);
$curlError  = curl_error($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError || $httpStatus >= 400) {
    error_log("Webhook failed | $table | ID $userrowid | HTTP $httpStatus | $curlError | $response");
}

/* ─────────────────────────────────────────────
   5. Success
───────────────────────────────────────────── */

$_SESSION['SuccessMessage'] = "$label Activated Successfully.";
echo "<script>opener.location.reload(true); self.close();</script>";
exit;

?>