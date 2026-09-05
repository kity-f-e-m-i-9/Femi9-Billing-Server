<?php
/**
 * Courier amount CHANGE REQUESTS — a separate, earlier checkpoint than the
 * company-side override in TpCourierPayment.php (tpEnsureCourierOverrideColumn
 * et al., which only applies to an ALREADY-SUBMITTED order). This one fires
 * BEFORE the TP has even paid: if the auto box/cover-calculated courier fee
 * looks wrong on the pre-submission pay-courier-payment.php page, the TP can
 * request a correction instead of paying the shown figure — the request
 * routes to whichever Sales BDM's district assignment covers that TP (see
 * salesbdm/include/BdmTpScope.php for the district-matching this reverses),
 * who sets the actual amount to charge. Company gets a read-only oversight
 * view of every request (who approved, original vs corrected amount).
 * Confirmed with the business owner 2026-09-04.
 */
function tpEnsureCourierAmountRequestTable(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS tp_courier_amount_requests (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          territory_partner_id INT UNSIGNED NOT NULL,
          product_type ENUM('napkin','diaper') NOT NULL,
          total_boxes INT UNSIGNED NOT NULL,
          total_covers INT UNSIGNED NOT NULL DEFAULT 0,
          calculated_amount DECIMAL(10,2) NOT NULL,
          approved_amount DECIMAL(10,2) NULL,
          cart_snapshot TEXT NULL,
          note VARCHAR(500) NULL,
          status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          reviewed_by_bdm_id INT UNSIGNED NULL,
          reviewed_by_name VARCHAR(255) NULL,
          reviewed_at TIMESTAMP NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_tcar_tp (territory_partner_id, status),
          KEY idx_tcar_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/**
 * Reverse of salesbdm/include/BdmTpScope.php's getBdmAssignedTpIds(): given a
 * TP id, find every Sales BDM whose personal district assignment
 * (getBdmAssignedDistrictNames()) covers that TP's own district. Reuses that
 * exact function per-BDM rather than re-walking the location tree here, so
 * the two directions can never drift out of sync with each other.
 * Normally returns exactly one BDM id; returns more only if districts were
 * mistakenly double-assigned, and none at all if nobody covers that TP's
 * district yet — callers must handle the empty case (surface to Company as
 * "unassigned" rather than silently losing the request).
 * @return int[] sales_bdm_staff.id values
 */
function tpFindBdmIdsForTp($db_conn, int $tpId): array
{
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';

    $stmt = $db_conn->prepare("SELECT LOWER(TRIM(COALESCE(NULLIF(assigned_district,''), branch_district))) AS district FROM territory_partners WHERE id = ?");
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $tpDistrict = $row['district'] ?? '';
    if ($tpDistrict === '' || $tpDistrict === null) return [];

    $bdmRows = $db_conn->query("SELECT id FROM sales_bdm_staff WHERE account_status = 'active'")->fetch_all(MYSQLI_ASSOC);
    $matched = [];
    foreach ($bdmRows as $b) {
        $bdmId = (int)$b['id'];
        $districtNames = array_map(fn($n) => mb_strtolower(trim($n)), getBdmAssignedDistrictNames($db_conn, $bdmId));
        if (in_array($tpDistrict, $districtNames, true)) {
            $matched[] = $bdmId;
        }
    }
    return $matched;
}

/**
 * The one request this TP/product_type pair should currently act on — the
 * latest non-rejected request. A rejected request is dead history (the TP
 * pays the normal calculated amount, same as if nothing was ever requested)
 * so it's deliberately excluded here; it still shows up in the BDM/Company
 * list views by querying the table directly.
 */
function tpCourierAmountRequestGetActive(mysqli $db, int $tpId, string $productType): ?array
{
    $stmt = $db->prepare("
        SELECT * FROM tp_courier_amount_requests
        WHERE territory_partner_id = ? AND product_type = ? AND status IN ('pending','approved')
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param('is', $tpId, $productType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function tpCourierAmountRequestCreate(mysqli $db, int $tpId, string $productType, int $totalBoxes, int $totalCovers, float $calculatedAmount, array $cartItems, string $note): int
{
    $snapshot = json_encode($cartItems);
    $stmt = $db->prepare("
        INSERT INTO tp_courier_amount_requests
            (territory_partner_id, product_type, total_boxes, total_covers, calculated_amount, cart_snapshot, note, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param('isiidss', $tpId, $productType, $totalBoxes, $totalCovers, $calculatedAmount, $snapshot, $note);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * A BDM approving/rejecting sets the same reviewer fields regardless of
 * outcome — Company's audit view needs to know who acted even on a
 * rejection, not just on an approval. Company can also review directly
 * (bypassing the assigned Sales BDM) — pass $bdmId null in that case;
 * reviewed_by_bdm_id stays NULL while reviewed_by_name still records who
 * acted, so the audit trail is never ambiguous about which login did it.
 */
function tpCourierAmountRequestReview(mysqli $db, int $requestId, string $status, ?float $approvedAmount, ?int $bdmId, string $reviewerName): bool
{
    $stmt = $db->prepare("
        UPDATE tp_courier_amount_requests
        SET status = ?, approved_amount = ?, reviewed_by_bdm_id = ?, reviewed_by_name = ?, reviewed_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param('sdisi', $status, $approvedAmount, $bdmId, $reviewerName, $requestId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}
