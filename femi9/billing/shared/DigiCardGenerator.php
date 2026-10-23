<?php
// Generates the "Digi Card" — a shareable PNG summary of a signed Territory
// Partner or Channel Partner agreement (name, ID, district, firka/division,
// both signatures) — purely from data already captured by
// AgreementService.php. Rendered on demand with GD (no disk cache): cheap
// enough per-view and always reflects the latest agreement/company-settings
// values. render_tp_digi_card() and render_cp_digi_card() each gather their
// own partner-type-specific data, then both hand off to the shared
// _dcRenderPartnerCard() for the actual drawing — the visual design (layout,
// fonts, photo frame, rounded corners, etc.) only needs to be built/tuned
// once and applies to both partner types identically.

require_once __DIR__ . '/AgreementService.php';

// Returns a GD image resource/object, or null if the TP hasn't signed yet
// (nothing meaningful to show — the card only makes sense post-signature).
function render_tp_digi_card(mysqli $db_conn, int $tpId)
{
    $stmt = $db_conn->prepare("SELECT * FROM territory_partners WHERE id = ?");
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $tp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$tp) { return null; }

    $agreement = get_or_create_tp_agreement($db_conn, $tpId);
    if ((int) ($agreement['is_locked'] ?? 0) !== 1 || empty($agreement['tp_signature'])) {
        return null;
    }
    $settings = get_agreement_settings($db_conn);

    // Per-Firka district, not one flat TP-level district: a TP's assigned
    // Firkas can span more than one district, so each Firka is paired with
    // its OWN district (walked up the partner_location_nodes parent chain
    // to depth=3, same "ancestor at depth N" recursive CTE
    // district-shop-invoice-export.php etc. already use), not the single
    // territory_partners.assigned_district value.
    $stmtLoc = $db_conn->prepare("
        WITH RECURSIVE danc AS (
            SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth = 3
            UNION ALL
            SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN danc a ON c.parent_id = a.node_id
        )
        SELECT pln.name AS item_name, danc.anc_name AS district_name
        FROM territory_partner_locations tpl
        JOIN partner_location_nodes pln ON pln.id = tpl.location_id
        LEFT JOIN danc ON danc.node_id = pln.id
        WHERE tpl.territory_partner_id = ?
        ORDER BY pln.name ASC
    ");
    $stmtLoc->bind_param('i', $tpId);
    $stmtLoc->execute();
    $locRows = $stmtLoc->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtLoc->close();

    // Item/District pairs — from the structured location assignment when
    // there is one; otherwise fall back to the agreement's own free-text
    // territory_firka (no per-item district available there) or the TP's
    // single assigned_district, so the card still shows something rather
    // than going blank for an older/manually-typed agreement with no
    // "Partner Location" rows at all.
    $itemPairs = [];
    if ($locRows) {
        foreach ($locRows as $row) {
            $itemPairs[] = [$row['item_name'], $row['district_name'] ?: '-'];
        }
    } else {
        $agreementFirka = trim((string) ($agreement['territory_firka'] ?? ''));
        $fallbackDistrict = trim((string) ($tp['assigned_district'] ?? '')) ?: '-';
        if ($agreementFirka !== '') {
            foreach (explode(',', $agreementFirka) as $f) {
                $f = trim($f);
                if ($f !== '') { $itemPairs[] = [$f, $fallbackDistrict]; }
            }
        }
    }

    $photoPath = null;
    if (!empty($tp['photo'])) {
        $candidate = __DIR__ . '/../company/tp_photo/' . $tp['photo'];
        if (is_file($candidate)) { $photoPath = $candidate; }
    }

    return _dcRenderPartnerCard([
        'titleText'            => 'AUTHORIZED TERRITORY PARTNER',
        'partnerName'          => $tp['name'] ?? '',
        'photoPath'            => $photoPath,
        'idLabel'              => 'Territory Partner ID',
        'idValue'              => $tp['tp_id'] ?? '-',
        'itemsLabel'           => 'Frikas name',
        'itemPairs'            => $itemPairs,
        'fallbackDistrict'     => trim((string) ($tp['assigned_district'] ?? '')) ?: '-',
        'companySigPath'       => !empty($settings['authorized_signatory_signature']) ? (__DIR__ . '/../company/company_signature/' . $settings['authorized_signatory_signature']) : null,
        'companySigName'       => $settings['authorized_signatory_name'] ?? '',
        'companySigDesignation'=> $settings['authorized_signatory_designation'] ?? '',
        'partnerSigSource'     => $agreement['tp_signature'],
        'partnerRoleLabel'     => 'Authorized Territory Partner',
        'partnerDesignation'   => $agreement['tp_designation'] ?? '',
    ]);
}

// Same as render_tp_digi_card(), for a Channel Partner. "Divisions" are the
// CP equivalent of a TP's Firkas (channel_partner_locations instead of
// territory_partner_locations), sitting at depth=4 in partner_location_nodes
// rather than depth=6 — the same depth=3 "ancestor district" recursive CTE
// still applies unchanged, since it just climbs parent_id regardless of the
// starting depth.
function render_cp_digi_card(mysqli $db_conn, int $cpId)
{
    $stmt = $db_conn->prepare("SELECT * FROM channel_partners WHERE id = ?");
    $stmt->bind_param('i', $cpId);
    $stmt->execute();
    $cp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cp) { return null; }

    $agreement = get_or_create_cp_agreement($db_conn, $cpId);
    if ((int) ($agreement['is_locked'] ?? 0) !== 1 || empty($agreement['cp_signature'])) {
        return null;
    }
    $settings = get_agreement_settings($db_conn);

    $stmtLoc = $db_conn->prepare("
        WITH RECURSIVE danc AS (
            SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth = 3
            UNION ALL
            SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN danc a ON c.parent_id = a.node_id
        )
        SELECT pln.name AS item_name, danc.anc_name AS district_name
        FROM channel_partner_locations cpl
        JOIN partner_location_nodes pln ON pln.id = cpl.location_id
        LEFT JOIN danc ON danc.node_id = pln.id
        WHERE cpl.channel_partner_id = ?
        ORDER BY pln.name ASC
    ");
    $stmtLoc->bind_param('i', $cpId);
    $stmtLoc->execute();
    $locRows = $stmtLoc->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtLoc->close();

    $itemPairs = [];
    if ($locRows) {
        foreach ($locRows as $row) {
            $itemPairs[] = [$row['item_name'], $row['district_name'] ?: '-'];
        }
    } else {
        $agreementDivisions = trim((string) ($agreement['approved_divisions'] ?? ''));
        $fallbackDistrict = trim((string) ($cp['branch_district'] ?? '')) ?: '-';
        if ($agreementDivisions !== '') {
            foreach (preg_split('/[,\n]/', $agreementDivisions) as $d) {
                $d = trim($d);
                if ($d !== '') { $itemPairs[] = [$d, $fallbackDistrict]; }
            }
        }
    }

    $photoPath = null;
    if (!empty($cp['photo'])) {
        $candidate = __DIR__ . '/../company/cp_photo/' . $cp['photo'];
        if (is_file($candidate)) { $photoPath = $candidate; }
    }

    return _dcRenderPartnerCard([
        'titleText'            => 'AUTHORIZED CHANNEL PARTNER',
        'partnerName'          => $cp['name'] ?? '',
        'photoPath'            => $photoPath,
        'idLabel'              => 'Channel Partner ID',
        'idValue'              => $cp['cp_id'] ?? '-',
        'itemsLabel'           => 'Divisions name',
        'itemPairs'            => $itemPairs,
        'fallbackDistrict'     => trim((string) ($cp['branch_district'] ?? '')) ?: '-',
        'companySigPath'       => !empty($settings['authorized_signatory_signature']) ? (__DIR__ . '/../company/company_signature/' . $settings['authorized_signatory_signature']) : null,
        'companySigName'       => $settings['authorized_signatory_name'] ?? '',
        'companySigDesignation'=> $settings['authorized_signatory_designation'] ?? '',
        'partnerSigSource'     => $agreement['cp_signature'],
        'partnerRoleLabel'     => 'Authorized Channel Partner',
        'partnerDesignation'   => $agreement['cp_designation'] ?? '',
    ]);
}

// Shared drawing routine both render_tp_digi_card() and render_cp_digi_card()
// delegate to — see their own callers for what each key in $p means.
function _dcRenderPartnerCard(array $p)
{
    $W = 1000; $H = 800;
    $img = imagecreatetruecolor($W, $H);
    imagesavealpha($img, true);
    imagealphablending($img, true);

    // ── Violet/purple gradient background ──────────────────────────────
    $topColor    = ['r' => 0x4c, 'g' => 0x1d, 'b' => 0x95]; // #4c1d95
    $bottomColor = ['r' => 0x8b, 'g' => 0x5c, 'b' => 0xf6]; // #8b5cf6
    for ($y = 0; $y < $H; $y++) {
        $ratio = $y / $H;
        $r = (int) ($topColor['r'] + ($bottomColor['r'] - $topColor['r']) * $ratio);
        $g = (int) ($topColor['g'] + ($bottomColor['g'] - $topColor['g']) * $ratio);
        $b = (int) ($topColor['b'] + ($bottomColor['b'] - $topColor['b']) * $ratio);
        $rowColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $W, $y, $rowColor);
    }

    $white     = imagecolorallocate($img, 255, 255, 255);
    $offWhite  = imagecolorallocate($img, 230, 220, 250);
    $gold      = imagecolorallocate($img, 251, 191, 36); // #fbbf24 accent

    $font      = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';
    $fontBold  = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
    $hasFont   = is_file($font) && is_file($fontBold);

    // ── Large, faint logo watermark centered behind everything ─────────
    // Client-supplied wordmark (2026-10-21), pre-processed once into a
    // transparent-background asset by scratchpad/make_watermark_logo.php —
    // the original file is a flat white background with no alpha channel.
    $logoPath = __DIR__ . '/../../assets/images/femi-logo-watermark-purple.png';
    if (is_file($logoPath)) {
        $logo = @imagecreatefrompng($logoPath);
        if ($logo) {
            imagesavealpha($logo, true);
            imagealphablending($logo, true);
            $lw = imagesx($logo); $lh = imagesy($logo);
            $targetW = 560; $targetH = (int) ($lh * ($targetW / $lw));
            $resized = imagecreatetruecolor($targetW, $targetH);
            imagesavealpha($resized, true);
            imagealphablending($resized, false);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            imagealphablending($resized, true);
            imagecopyresampled($resized, $logo, 0, 0, 0, 0, $targetW, $targetH, $lw, $lh);
            _dcAlphaBlendOnto($img, $resized, (int) (($W - $targetW) / 2), (int) (($H - $targetH) / 2) - 10, $targetW, $targetH, 18);
            imagedestroy($logo);
            imagedestroy($resized);
        }
    }

    // ── Partner profile photo (or initial-letter placeholder), left side ─
    // This left box is ALWAYS drawn — a real square photo when the partner
    // has one, otherwise a same-size badge with their name's first letter
    // (the same initial-avatar pattern the app's own header already uses
    // for an account with no photo) — so the layout is identical whether or
    // not a photo happens to exist. Top of the photo/placeholder square
    // (and the divider beside it) lines up with the first data row's text,
    // not the title/name above it. $formStartY is where that first row's
    // text BASELINE sits — its visual top (where the glyphs actually
    // start) is well above that baseline, so the photo/divider's own top
    // (a plain box, no baseline offset) shifts up ~24px to visually line
    // up with the text's top edge, not its baseline.
    $formStartY = 240;
    $photoX = 80; $photoY = $formStartY - 24; $photoW = 250; $photoH = 250;
    $dividerX = $photoX + $photoW + 34;
    // Title + name centered on the whole card width (not the right-hand
    // content column) — the off-center version (centered only between the
    // photo divider and the right edge) looked wrong once the photo/
    // placeholder square itself got bigger.
    $centerX = (int) ($W / 2);

    // Signature strip position — computed up front (not just before it's
    // drawn, further down) because the form-rows block below needs to know
    // where it starts to decide whether its own content still leaves clear
    // space above it.
    $sigY = 560; $sigW = 340; $sigH = 190;
    $leftX  = 70;
    $rightX = $W - 70 - $sigW;

    if ($hasFont) {
        $titleBbox = imagettfbbox(16, 0, $fontBold, $p['titleText']);
        $titleHalfW = (int) (($titleBbox[2] - $titleBbox[0]) / 2);
        _dcText($img, $fontBold, 16, $centerX, 90, $gold, $p['titleText'], true);
        imageline($img, $centerX - $titleHalfW - 60, 85, $centerX - $titleHalfW - 15, 85, $gold);
        imageline($img, $centerX + $titleHalfW + 15, 85, $centerX + $titleHalfW + 60, 85, $gold);
        _dcText($img, $fontBold, 36, $centerX, 144, $white, mb_strtoupper($p['partnerName']), true);
        imageline($img, $centerX - 150, 162, $centerX + 150, 162, $gold);

        $photoImg = $p['photoPath'] ? @imagecreatefromstring((string) file_get_contents($p['photoPath'])) : null;
        if ($photoImg) {
            _dcDrawFramedPhoto($img, $photoImg, $photoX, $photoY, $photoW, $photoH, 18, $white);
            imagedestroy($photoImg);
        } else {
            _dcDrawInitialPlaceholder($img, $photoX, $photoY, $photoW, $photoH, 18, $white, $fontBold, _dcFirstLetter($p['partnerName']));
        }
        imageline($img, $dividerX, $photoY, $dividerX, $photoY + $photoH, imagecolorallocatealpha($img, 255, 255, 255, 90));

        // District Name(s) and Firka/Division(s) listed as their own
        // labeled blocks. District names are de-duplicated — several
        // Firkas/Divisions commonly share the same district.
        $districtNames = [];
        $itemNames = [];
        foreach ($p['itemPairs'] as [$iName, $dName]) {
            $itemNames[] = $iName;
            if ($dName !== '' && $dName !== '-' && !in_array($dName, $districtNames, true)) {
                $districtNames[] = $dName;
            }
        }
        $districtDisplay = $districtNames ? implode(', ', $districtNames) : $p['fallbackDistrict'];
        $itemsDisplay = $itemNames ? implode(', ', $itemNames) : '-';

        // Form-style rows ("Label : value"), left-aligned with every value
        // starting at the same X — not centered stacked blocks. The colon
        // is drawn as its own fixed-position glyph (not appended to each
        // label string) so all rows line up in one straight column
        // regardless of label length. The left column reserved for the
        // photo/initial badge leaves less width for values than a
        // full-width layout would, so a partner with many Firkas/Divisions
        // can wrap onto several lines — shrink the font just enough to
        // keep the whole block clear of the signature strip rather than
        // letting it run on and overlap.
        $labelX = $dividerX + 30;
        $budgetBottom = $sigY - 55;
        $rowsData = [
            [$p['idLabel'], $p['idValue']],
            ['District name', $districtDisplay],
            [$p['itemsLabel'], $itemsDisplay],
        ];

        // The value (e.g. "RANIPET") is drawn a few points larger than its
        // own label — it's the actual answer the partner cares about, so
        // it should read as the heavier/bigger of the two, not match it.
        $formSize = 29;
        do {
            $valueSize = $formSize + 4;
            $maxLabelTextW = 0;
            foreach ($rowsData as [$label, ]) {
                $bbox = imagettfbbox($formSize, 0, $font, $label);
                $maxLabelTextW = max($maxLabelTextW, $bbox[2] - $bbox[0]);
            }
            $colonX = $labelX + $maxLabelTextW + 8;
            $valueX = $colonX + 22;
            $maxValueWidth = $W - $valueX - 60;

            $yEnd = $formStartY;
            foreach ($rowsData as [, $value]) {
                $lineCount = count(_dcWrapTextLines($fontBold, $valueSize, $value, $maxValueWidth));
                $yEnd += (int) (($lineCount - 1) * $valueSize * 1.4) + (int) ($valueSize * 2.3);
            }
            if ($yEnd <= $budgetBottom || $formSize <= 12) { break; }
            $formSize--;
        } while (true);

        $y = $formStartY;
        foreach ($rowsData as [$label, $value]) {
            _dcText($img, $font, $formSize, $labelX, $y, $offWhite, $label, false);
            _dcText($img, $font, $formSize, $colonX, $y, $offWhite, ':', false);
            $lines = _dcWrapTextLines($fontBold, $valueSize, $value, $maxValueWidth);
            foreach ($lines as $i => $line) {
                _dcText($img, $fontBold, $valueSize, $valueX, $y, $white, $line, false);
                if ($i < count($lines) - 1) { $y += (int) ($valueSize * 1.4); }
            }
            $y += (int) ($valueSize * 2.3);
        }
    }

    // ── Signature strip: company (left) + partner (right) ───────────────
    _dcSignatureBox($img, $leftX, $sigY, $sigW, $sigH, $white, $hasFont ? $font : null, $hasFont ? $fontBold : null,
        $p['companySigPath'], $p['companySigName'], 'Authorized Signatory — FEMI9', $p['companySigDesignation']);

    _dcSignatureBox($img, $rightX, $sigY, $sigW, $sigH, $white, $hasFont ? $font : null, $hasFont ? $fontBold : null,
        $p['partnerSigSource'], $p['partnerName'], $p['partnerRoleLabel'], $p['partnerDesignation']);

    // Scaled down to the client's requested smaller card size as a final
    // resample (simplest way to shrink everything in proportion at once,
    // rather than re-deriving every one of the coordinates/font sizes above
    // by hand), then given true rounded corners — a card shape, not just a
    // rectangle — by cutting the four corners to transparent.
    $finalScale = 0.82;
    $finalW = (int) ($W * $finalScale);
    $finalH = (int) ($H * $finalScale);
    $final = imagecreatetruecolor($finalW, $finalH);
    imagesavealpha($final, true);
    imagealphablending($final, false);
    imagefill($final, 0, 0, imagecolorallocatealpha($final, 0, 0, 0, 127));
    imagealphablending($final, true);
    imagecopyresampled($final, $img, 0, 0, 0, 0, $finalW, $finalH, $W, $H);
    imagedestroy($img);
    $img = $final;
    _dcRoundCorners($img, 28);

    return $img;
}

// Cuts the four corners of the whole card image to transparent outside a
// quarter-circle of the given radius, so the exported PNG itself is a
// rounded card shape (not just a rectangle that happens to get border-radius
// applied by CSS when shown on a web page — this is what makes the
// downloaded/WhatsApp-shared file and the PDF embed look rounded too).
// Fades alpha smoothly over a ~1.5px band at the boundary instead of a hard
// in/out cutoff — a hard cutoff left a visibly jagged, light-fringed edge
// once viewed against a page background.
function _dcRoundCorners($img, int $radius): void
{
    $w = imagesx($img); $h = imagesy($img);
    imagealphablending($img, false);
    $feather = 1.5;
    $corners = [
        [0, 0, $radius, $radius, $radius, $radius],                         // top-left
        [$w - $radius - 2, 0, $w - 1, $radius, $w - $radius - 1, $radius],          // top-right
        [0, $h - $radius - 2, $radius, $h - 1, $radius, $h - $radius - 1],          // bottom-left
        [$w - $radius - 2, $h - $radius - 2, $w - 1, $h - 1, $w - $radius - 1, $h - $radius - 1], // bottom-right
    ];
    foreach ($corners as [$x0, $y0, $x1, $y1, $cx, $cy]) {
        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $dist = sqrt(($x - $cx) ** 2 + ($y - $cy) ** 2);
                if ($dist <= $radius - $feather) { continue; }
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF; $g = ($rgba >> 8) & 0xFF; $b = $rgba & 0xFF;
                if ($dist >= $radius + $feather) {
                    $newA = 127;
                } else {
                    $frac = ($dist - ($radius - $feather)) / (2 * $feather);
                    $newA = (int) round($a + $frac * (127 - $a));
                }
                imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $r, $g, $b, $newA));
            }
        }
    }
}

// Alpha-aware "watermark" blend: imagecopymerge() ignores the source's own
// alpha channel (transparent pixels get merged in as if opaque black),
// leaving a visible rectangular patch around a logo's real silhouette. This
// pre-composites the source onto a snapshot of the destination region first
// (respecting the source's alpha), then merges that result back at $pct —
// so only the logo's actual visible pixels fade in, not its bounding box.
function _dcAlphaBlendOnto($dstImg, $srcImg, int $dstX, int $dstY, int $srcW, int $srcH, int $pct): void
{
    $cut = imagecreatetruecolor($srcW, $srcH);
    imagecopy($cut, $dstImg, 0, 0, $dstX, $dstY, $srcW, $srcH);
    imagealphablending($cut, true);
    imagecopy($cut, $srcImg, 0, 0, 0, 0, $srcW, $srcH);
    imagecopymerge($dstImg, $cut, $dstX, $dstY, 0, 0, $srcW, $srcH, $pct);
    imagedestroy($cut);
}

// First letter of a name, uppercased — same initial-avatar convention the
// app's own header already uses for an account with no photo on file.
function _dcFirstLetter(string $name): string
{
    $name = trim($name);
    return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
}

// Same rounded-square frame as _dcDrawFramedPhoto, but filled with a solid
// tint and the partner's first initial instead of a cropped photo — drawn
// for any partner with no photo uploaded, so the card's layout never has to
// shift depending on whether one exists.
function _dcDrawInitialPlaceholder($img, int $x, int $y, int $w, int $h, int $radius, int $borderColor, string $fontBold, string $letter): void
{
    // Solid gold fill (not a pale tint) so the white initial drawn on top
    // actually has contrast — a near-white tint here made the letter all
    // but invisible against it.
    $fillColor = imagecolorallocate($img, 217, 119, 6); // #d97706, darker than the card's gold accent
    imagealphablending($img, false);
    for ($py = 0; $py < $h; $py++) {
        for ($px = 0; $px < $w; $px++) {
            if (_dcInsideRoundedRect($px, $py, $w, $h, $radius)) {
                imagesetpixel($img, $x + $px, $y + $py, $fillColor);
            }
        }
    }
    imagealphablending($img, true);

    for ($t = 0; $t < 4; $t++) {
        _dcStrokeRoundedRect($img, $x - $t - 2, $y - $t - 2, $w + 2 * ($t + 2), $h + 2 * ($t + 2), $radius + $t + 2, $borderColor);
    }

    $white = imagecolorallocate($img, 255, 255, 255);
    _dcText($img, $fontBold, 90, $x + (int) ($w / 2), $y + (int) ($h / 2) + 32, $white, $letter, true);
}

// Crops $srcImg to fill a $w x $h rounded-rectangle frame at ($x, $y) on
// $img — the partner's profile photo (a plain rectangular upload), shown as
// a framed portrait rather than a hard-edged rectangle. A thin
// $borderColor border sits just outside the rounded photo itself.
function _dcDrawFramedPhoto($img, $srcImg, int $x, int $y, int $w, int $h, int $radius, int $borderColor): void
{
    $sw = imagesx($srcImg); $sh = imagesy($srcImg);
    $srcAspect = $sw / $sh; $dstAspect = $w / $h;
    if ($srcAspect > $dstAspect) {
        $cropH = $sh; $cropW = (int) ($sh * $dstAspect);
    } else {
        $cropW = $sw; $cropH = (int) ($sw / $dstAspect);
    }
    $srcX = (int) (($sw - $cropW) / 2);
    $srcY = (int) (($sh - $cropH) / 2);

    $resized = imagecreatetruecolor($w, $h);
    imagecopyresampled($resized, $srcImg, 0, 0, $srcX, $srcY, $w, $h, $cropW, $cropH);

    imagealphablending($img, false);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    for ($py = 0; $py < $h; $py++) {
        for ($px = 0; $px < $w; $px++) {
            if (_dcInsideRoundedRect($px, $py, $w, $h, $radius)) {
                imagesetpixel($img, $x + $px, $y + $py, imagecolorat($resized, $px, $py));
            }
        }
    }
    imagealphablending($img, true);
    imagedestroy($resized);

    // Border: a slightly larger rounded rect outline drawn just outside the
    // photo edge, a few pixels thick.
    for ($t = 0; $t < 4; $t++) {
        _dcStrokeRoundedRect($img, $x - $t - 2, $y - $t - 2, $w + 2 * ($t + 2), $h + 2 * ($t + 2), $radius + $t + 2, $borderColor);
    }
}

// Point-in-rounded-rectangle test used by _dcDrawFramedPhoto's per-pixel crop.
function _dcInsideRoundedRect(int $px, int $py, int $w, int $h, int $r): bool
{
    $cx = max($r, min($px, $w - $r));
    $cy = max($r, min($py, $h - $r));
    if ($px >= $r && $px <= $w - $r) { return true; }
    if ($py >= $r && $py <= $h - $r) { return true; }
    return (($px - $cx) ** 2 + ($py - $cy) ** 2) <= $r ** 2;
}

// Outline of a rounded rectangle, built from arcs at the four corners plus
// the four straight edges between them (GD has no native rounded-rect
// primitive to stroke).
function _dcStrokeRoundedRect($img, int $x, int $y, int $w, int $h, int $r, int $color): void
{
    imageline($img, $x + $r, $y, $x + $w - $r, $y, $color);
    imageline($img, $x + $r, $y + $h, $x + $w - $r, $y + $h, $color);
    imageline($img, $x, $y + $r, $x, $y + $h - $r, $color);
    imageline($img, $x + $w, $y + $r, $x + $w, $y + $h - $r, $color);
    imagearc($img, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $color);
    imagearc($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, 270, 360, $color);
    imagearc($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, 90, 180, $color);
    imagearc($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, 0, 90, $color);
}

// Centered (or left-aligned) single line of text.
function _dcText($img, string $fontFile, float $size, int $x, int $y, int $color, string $text, bool $centered = false): void
{
    if ($centered) {
        $bbox = imagettfbbox($size, 0, $fontFile, $text);
        $textW = $bbox[2] - $bbox[0];
        $x = $x - (int) ($textW / 2);
    }
    imagettftext($img, $size, 0, $x, $y, $color, $fontFile, $text);
}

// Word-wraps $text into lines that each fit within $maxWidth at $size.
function _dcWrapTextLines(string $fontFile, float $size, string $text, int $maxWidth): array
{
    $words = preg_split('/\s+/', trim($text));
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current === '' ? $word : ($current . ' ' . $word);
        $bbox = imagettfbbox($size, 0, $fontFile, $test);
        if (($bbox[2] - $bbox[0]) > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') { $lines[] = $current; }
    return $lines ?: [''];
}

// Shrinks the font until the text fits maxWidth, then draws it centered.
function _dcWrappedText($img, string $fontFile, float $size, int $cx, int $y, int $color, string $text, int $maxWidth, bool $centered = false): void
{
    $size = min($size, 26);
    while ($size > 10) {
        $bbox = imagettfbbox($size, 0, $fontFile, $text);
        if (($bbox[2] - $bbox[0]) <= $maxWidth) { break; }
        $size -= 1;
    }
    _dcText($img, $fontFile, $size, $cx, $y, $color, $text, $centered);
}

// Removes a near-white (or near-whatever-the-corner-pixel-is) flat backdrop
// by turning those pixels fully transparent — most uploaded signature photos
// (company's) are ink-on-white-paper with no real alpha channel, and this is
// the only way to drop that background short of asking for better source
// images. A canvas-drawn signature (the partner's own, via signature-pad.js)
// is already transparent, so this pass is a no-op there (no near-white
// pixels to begin with).
function _dcStripFlatBackground($sigImg): void
{
    $w = imagesx($sigImg); $h = imagesy($sigImg);
    imagealphablending($sigImg, false);
    imagesavealpha($sigImg, true);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($sigImg, $x, $y);
            $a = ($rgba >> 24) & 0x7F;
            if ($a >= 110) { continue; } // already transparent
            $r = ($rgba >> 16) & 0xFF; $g = ($rgba >> 8) & 0xFF; $b = $rgba & 0xFF;
            if ($r > 225 && $g > 225 && $b > 225) {
                imagesetpixel($sigImg, $x, $y, imagecolorallocatealpha($sigImg, $r, $g, $b, 127));
            }
        }
    }
}

// Just the signature image + name/role, directly on the card background —
// no panel/box behind it.
function _dcSignatureBox($img, int $x, int $y, int $w, int $h, int $bgColor, ?string $font, ?string $fontBold,
    ?string $signatureSource, string $name, string $roleLabel, string $designation = ''): void
{
    $sigAreaH = (int) ($h * 0.55);
    if ($signatureSource) {
        $sigImg = null;
        if (str_starts_with($signatureSource, 'data:image')) {
            $b64 = substr($signatureSource, strpos($signatureSource, ',') + 1);
            $raw = base64_decode($b64);
            $sigImg = $raw ? @imagecreatefromstring($raw) : null;
        } elseif (is_file($signatureSource)) {
            $sigImg = @imagecreatefromstring((string) file_get_contents($signatureSource));
        }
        if ($sigImg) {
            imagesavealpha($sigImg, true);
            _dcStripFlatBackground($sigImg);
            $sw = imagesx($sigImg); $sh = imagesy($sigImg);
            $maxW = $w - 40; $maxH = $sigAreaH - 20;
            $scale = min($maxW / $sw, $maxH / $sh, 1);
            $dw = (int) ($sw * $scale); $dh = (int) ($sh * $scale);
            $dx = $x + (int) (($w - $dw) / 2);
            $dy = $y + 15 + (int) (($sigAreaH - 20 - $dh) / 2);
            $resized = imagecreatetruecolor($dw, $dh);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            imagecopyresampled($resized, $sigImg, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
            imagealphablending($img, true);
            imagecopy($img, $resized, $dx, $dy, 0, 0, $dw, $dh);
            imagedestroy($sigImg);
            imagedestroy($resized);
        }
    }

    $lineY = $y + $sigAreaH;
    $lightLine = imagecolorallocatealpha($img, 255, 255, 255, 70);
    $white     = imagecolorallocate($img, 255, 255, 255);
    $offWhite  = imagecolorallocate($img, 230, 220, 250);
    imageline($img, $x + 30, $lineY, $x + $w - 30, $lineY, $lightLine);

    if ($font && $fontBold) {
        $cx = $x + (int) ($w / 2);
        _dcWrappedText($img, $fontBold, 18, $cx, $lineY + 26, $white, $name !== '' ? $name : '-', $w - 20, true);
        $roleText = $designation !== '' ? ($roleLabel . ' (' . $designation . ')') : $roleLabel;
        _dcWrappedText($img, $fontBold, 17, $cx, $lineY + 50, $offWhite, $roleText, $w - 20, true);
    }
}
