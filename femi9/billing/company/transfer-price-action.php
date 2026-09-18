<?php
include("checksession.php");
require_once("include/AutoTransferDemand.php");
include("config.php");

error_reporting(0);

$productIds  = $_POST['product_id']     ?? [];
$ratesHc     = $_POST['rate_healthcare'] ?? [];
$ratesLlp    = $_POST['rate_llp']        ?? [];
$updatedBy   = $_SESSION['LOGIN_USER'] ?? 'system';

foreach ($productIds as $i => $rawPid) {
    $pid = (int) $rawPid;
    if ($pid <= 0) continue;
    $rawHc  = $ratesHc[$i]  ?? '';
    $rawLlp = $ratesLlp[$i] ?? '';
    // Skip products the user left both boxes blank on — otherwise every
    // never-rated product in the list would get an explicit 0 row just
    // for being submitted along with the rest of the page's form.
    if ($rawHc === '' && $rawLlp === '') continue;
    $rateHc  = $rawHc  === '' ? 0.0 : (float) $rawHc;
    $rateLlp = $rawLlp === '' ? 0.0 : (float) $rawLlp;
    set_auto_transfer_default_rate($db_conn, $pid, $rateHc, $rateLlp, $updatedBy);
}

$_SESSION['sucMessage'] = "Transfer prices saved.";
echo "<script>window.location='transfer-price';</script>";
