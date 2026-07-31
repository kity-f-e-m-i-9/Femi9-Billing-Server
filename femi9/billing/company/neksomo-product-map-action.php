<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/NeksomoProductMapping.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: neksomo-manage-products.php?error");
    exit;
}

$neksomoProductId = (int)($_POST['neksomo_product_id'] ?? 0);

// Only ever touch products this login created — never the shared/admin catalog.
$own = $db_conn->prepare("SELECT id FROM products WHERE id = ? AND temp_id LIKE 'NKS-%'");
$own->bind_param('i', $neksomoProductId);
$own->execute();
$isOwn = $own->get_result()->num_rows > 0;
$own->close();

if (!$neksomoProductId || !$isOwn) {
    header("Location: neksomo-manage-products.php?error");
    exit;
}

// Selected company_product_ids, filtered to real (non-deleted, non-Neksomo) products only.
$submittedIds = array_map('intval', $_POST['company_product_ids'] ?? []);
$validIds = [];
if (!empty($submittedIds)) {
    $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
    $types = str_repeat('i', count($submittedIds));
    $stmt = $db_conn->prepare(
        "SELECT id FROM products WHERE id IN ($placeholders) AND temp_id NOT LIKE 'NKS-%' AND deleted_at IS NULL"
    );
    $stmt->bind_param($types, ...$submittedIds);
    $stmt->execute();
    $validIds = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
    $stmt->close();
}

$createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    // Full replace — the checkbox list is the source of truth for this neksomo product.
    $del = $db_conn->prepare("DELETE FROM neksomo_product_mapping WHERE neksomo_product_id = ?");
    $del->bind_param('i', $neksomoProductId);
    $del->execute();
    $del->close();

    if (!empty($validIds)) {
        $ins = $db_conn->prepare(
            "INSERT INTO neksomo_product_mapping (neksomo_product_id, company_product_id, created_by) VALUES (?, ?, ?)"
        );
        foreach ($validIds as $companyProductId) {
            $ins->bind_param('iis', $neksomoProductId, $companyProductId, $createdBy);
            $ins->execute();
        }
        $ins->close();
    }

    $db_conn->commit();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[neksomo-product-map-action] ' . $e->getMessage());
    header("Location: neksomo-product-map.php?id={$neksomoProductId}&error");
    exit;
}

header("Location: neksomo-product-map.php?id={$neksomoProductId}&updatedSuccess");
exit;
