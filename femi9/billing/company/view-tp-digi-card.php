<?php
// Company-side view of a Territory Partner's signed Digi Card — same PNG
// shared/DigiCardGenerator.php produces on the TP's own side, so
// finance/admin can view or download it without logging in as the TP.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once __DIR__ . '/../shared/DigiCardGenerator.php';

$tpId = (int) ($_GET['tp_id'] ?? 0);
$img = $tpId > 0 ? render_tp_digi_card($db_conn, $tpId) : null;
if (!$img) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="femi9-tp-digi-card.png"');
}
imagepng($img);
imagedestroy($img);
