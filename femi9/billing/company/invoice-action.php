<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['addInvoice'])) {

    $user_type_Loginvl = $Login_user_TYPEvl;
    $user_id_Loginvl   = $Login_user_IDvl;

    $randum_number = mysqli_real_escape_string($db_conn, $_REQUEST['randum_number']);
    $inv_id        = mysqli_real_escape_string($db_conn, $_REQUEST['inv_id']);
    $customer_id   = (int) $_REQUEST['customer_id'];
    $date          = date("Y-m-d", strtotime($_REQUEST['date']));
    $inv_year      = date("Y",     strtotime($_REQUEST['date']));
    $pr_id         = (int) $_REQUEST['pr_id'];
    $amount        = (int) $_REQUEST['amount'];
    $qty           = (int) $_REQUEST['qty'];
    $total         = $amount * $qty;

    // Create invoice header if this is the first item
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM invoice WHERE inv_id = ? AND user_type = ? AND user_id = ?"
    );
    $stmt->bind_param('sss', $inv_id, $user_type_Loginvl, $user_id_Loginvl);
    $stmt->execute();
    $exists = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($exists === 0) {
        $stmt = $db_conn->prepare(
            "SELECT MAX(id_only) AS max_id FROM invoice WHERE user_type = ? AND user_id = ?"
        );
        $stmt->bind_param('ss', $user_type_Loginvl, $user_id_Loginvl);
        $stmt->execute();
        $maxId      = (int)($stmt->get_result()->fetch_assoc()['max_id'] ?? 0);
        $stmt->close();
        $id_only    = $maxId + 1;
        $format_num = str_pad($id_only, 3, '0', STR_PAD_LEFT);
        $INVDATE    = date("Ymd", strtotime($_REQUEST['date']));
        $inv_number = "{$INVDATE}/{$randum_number}/{$format_num}";

        $stmt = $db_conn->prepare(
            "INSERT INTO invoice
                (inv_id, id_only, inv_number, customer_id, date, inv_year,
                 sub_total, discount, total, user_type, user_id)
             VALUES (?, ?, ?, ?, ?, ?, '0', '0', '0', ?, ?)"
        );
        $stmt->bind_param('siisiss', $inv_id, $id_only, $inv_number, $customer_id,
                          $date, $inv_year, $user_type_Loginvl, $user_id_Loginvl);
        $stmt->execute();
        $stmt->close();
    }

    // Stock availability check — read-only, no deduction here (deferred to submit)
    $stockService = new StockService($db_conn);
    $available    = $stockService->getClosingQty($pr_id, $user_type_Loginvl, $user_id_Loginvl);

    if ($available === null || $available < $qty) {
        echo "<script>window.location='invoice?InvoiceID=" . base64_encode($inv_id) . "&&InvalidStock&&AlertStockError';</script>";
        exit;
    }

    // Duplicate product guard
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM invoice_items
          WHERE inv_id = ? AND pr_id = ? AND user_type = ? AND user_id = ?"
    );
    $stmt->bind_param('siss', $inv_id, $pr_id, $user_type_Loginvl, $user_id_Loginvl);
    $stmt->execute();
    $itemExists = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($itemExists > 0) {
        echo "<script>window.location='invoice?InvoiceID=" . base64_encode($inv_id) . "&&ItemAlreadyExists&&AlertMessage';</script>";
        exit;
    }

    // Insert line item only — stock deduction happens atomically in invoice-submit.php
    $stmt = $db_conn->prepare(
        "INSERT INTO invoice_items (inv_id, pr_id, amount, qty, total, user_type, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('siiiiss', $inv_id, $pr_id, $amount, $qty, $total,
                      $user_type_Loginvl, $user_id_Loginvl);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location='invoice?InvoiceID=" . base64_encode($inv_id) . "&&AddedSuccess&&FemiAdded';</script>";
}
?>
