<?php
// AJAX backend for internal_transfer_auto.php's "Excluded Today" tab —
// everything currently excluded from today's auto-transfer, so a "Not
// Today" click is never a dead end with no way to find/undo it.
include("checksession.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

echo json_encode(get_auto_transfer_skipped_today($db_conn));
