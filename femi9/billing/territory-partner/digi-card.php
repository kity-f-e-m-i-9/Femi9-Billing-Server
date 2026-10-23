<?php
// Serves the signed-in TP's own Digi Card as a PNG — see
// shared/DigiCardGenerator.php. Rendered fresh on every request (no disk
// cache); ?download=1 sends it as an attachment instead of inline.
include("checksession.php");
require_once __DIR__ . '/../shared/DigiCardGenerator.php';

$img = render_tp_digi_card($db_conn, (int) $Login_user_IDvl);
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
