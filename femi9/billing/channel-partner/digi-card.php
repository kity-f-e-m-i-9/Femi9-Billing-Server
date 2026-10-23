<?php
// Serves the signed-in CP's own Digi Card as a PNG — see
// shared/DigiCardGenerator.php. Rendered fresh on every request (no disk
// cache); ?download=1 sends it as an attachment instead of inline.
include("checksession.php");
require_once __DIR__ . '/../shared/DigiCardGenerator.php';

// Unlike territory-partner/checksession.php, channel-partner/checksession.php
// doesn't set a $Login_user_IDvl PHP variable directly (only
// $_SESSION['LOGIN_USER_ID'] — $Login_user_IDvl only exists after also
// including config.php, which this standalone image endpoint has no other
// reason to load), so read the session value directly instead.
$img = render_cp_digi_card($db_conn, (int) ($_SESSION['LOGIN_USER_ID'] ?? 0));
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
