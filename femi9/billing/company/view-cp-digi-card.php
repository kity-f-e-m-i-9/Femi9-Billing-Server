<?php
// Company-side view of a Channel Partner's signed Digi Card — same PNG
// shared/DigiCardGenerator.php produces on the CP's own side, so
// finance/admin can view or download it without logging in as the CP.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once __DIR__ . '/../shared/DigiCardGenerator.php';

$cpId = (int) ($_GET['cp_id'] ?? 0);
$img = $cpId > 0 ? render_cp_digi_card($db_conn, $cpId) : null;
if (!$img) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="femi9-cp-digi-card.png"');
}
imagepng($img);
imagedestroy($img);
