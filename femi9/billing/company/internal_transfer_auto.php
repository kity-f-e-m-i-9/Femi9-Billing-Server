<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    die("Auto Transfer is unavailable: one or more required company profiles "
        . "(Neksomo / FEMI HEALTH CARE / FEMI NAYAN LLP) could not be found "
        . "in company_godown.");
}

$stockService = new StockService($db_conn);
$requirements = get_auto_transfer_requirements($db_conn, $llpId);

$rows = [];
if (!empty($requirements)) {
    $productIds = array_keys($requirements);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $stmt = $db_conn->prepare("SELECT id, product_name FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $productResult = $stmt->get_result();
    $productNames = [];
    while ($p = $productResult->fetch_assoc()) {
        $productNames[(int) $p['id']] = $p['product_name'];
    }
    $stmt->close();

    foreach ($requirements as $pid => $required) {
        $neksomoAvail    = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $neksomoId) ?? 0);
        $healthcareAvail = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $healthcareId) ?? 0);
        $cappedQty       = cap_auto_transfer_qty($required, $neksomoAvail, $healthcareAvail);
        if ($cappedQty <= 0) continue;

        $rows[] = [
            'product_id'      => $pid,
            'product_name'    => $productNames[$pid] ?? "Product #$pid",
            'required'        => $required,
            'capped'          => $cappedQty,
            'neksomo_avail'   => $neksomoAvail,
            'healthcare_avail'=> $healthcareAvail,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Transfer for Orders : <?php echo $business_name; ?></title>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<div class="container mt-4">
    <h4>Auto Transfer for Orders</h4>
    <p class="text-muted">
        Quantities required today for waiting Territory Partner purchase
        orders and drafted OT channel orders (LLP), auto-capped to
        available Neksomo + Healthcare stock. Adjust any row before
        transferring.
    </p>

    <?php if (empty($rows)): ?>
        <div class="alert alert-info">Nothing to transfer today.</div>
    <?php else: ?>
        <form method="post" action="internal_transfer_auto_action.php">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Required Qty</th>
                        <th>Available (Neksomo)</th>
                        <th>Available (Healthcare)</th>
                        <th>Qty to Transfer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>
                            <input type="hidden" name="product_id[]" value="<?php echo (int) $row['product_id']; ?>">
                        </td>
                        <td><?php echo (int) $row['required']; ?></td>
                        <td><?php echo (int) $row['neksomo_avail']; ?></td>
                        <td><?php echo (int) $row['healthcare_avail']; ?></td>
                        <td>
                            <input type="number" min="0" name="qty[]"
                                   value="<?php echo (int) $row['capped']; ?>" class="form-control">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Transfer Now</button>
        </form>
    <?php endif; ?>
</div>
<script src="../../assets/plugins/jquery/jquery.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
