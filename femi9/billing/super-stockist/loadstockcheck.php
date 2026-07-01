<?php include("checksession.php");
$userid  = $_GET['q'];
$invuser = $_GET['invuser'];

if ($invuser == "territory_partner") {
    $tp_id = (int) $userid;
    $stmt  = $db_conn->prepare("SELECT COUNT(*) AS numstockcheck FROM territory_partners WHERE id = ? AND is_active = 1 AND stock_initialized = 1");
    $stmt->bind_param("i", $tp_id);
    $stmt->execute();
    $numstockcheck = (int) $stmt->get_result()->fetch_assoc()['numstockcheck'];
    $stmt->close();
} else {
    if ($invuser == "stockiest")          { $displaytitle = "Stockist"; }
    elseif ($invuser == "super_stockiest"){ $displaytitle = "Super Stockist"; }
    elseif ($invuser == "super_distributor"){ $displaytitle = "Super Distributor"; }
    elseif ($invuser == "distributor")    { $displaytitle = "Distributor"; }
    else                                  { $displaytitle = ""; }

    $userid_esc  = mysqli_real_escape_string($db_conn, $userid);
    $invuser_esc = mysqli_real_escape_string($db_conn, $invuser);
    $r = mysqli_query($db_conn, "SELECT COUNT(*) AS numstockcheck FROM stock WHERE user_type='$invuser_esc' AND user_id='$userid_esc'");
    $numstockcheck = (int) mysqli_fetch_assoc($r)['numstockcheck'];
}

if ($numstockcheck > 0) { ?>
    <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
<?php } else { ?>
    <span style="color:red;">Please update opening stock! (<?= isset($displaytitle) ? $displaytitle : 'Territory Partner' ?>)</span>
<?php } ?>

 