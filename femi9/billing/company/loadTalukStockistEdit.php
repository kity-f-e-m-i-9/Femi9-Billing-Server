<?php
include("checksession.php");

$state_id = isset($_GET["state_id"]) ? intval($_GET["state_id"]) : 0;
$dist_id = isset($_GET["dist_id"]) ? intval($_GET["dist_id"]) : 0;
$invuser = isset($_GET["invuser"]) ? $_GET["invuser"] : "";
$current_stockist_id = isset($_GET["current_stockist_id"]) ? intval($_GET["current_stockist_id"]) : 0;
$current_taluk_id = isset($_GET["current_taluk_id"]) ? intval($_GET["current_taluk_id"]) : 0;

if ($state_id <= 0 || $dist_id <= 0) {
    echo '<select required name="taluk_id" class="form-control">
            <option value="" hidden>Select District First</option>
          </select>';
    exit;
}

// Get taluks for selected state and district
$select_taluk = "SELECT id, taluk FROM taluk WHERE state_id = ? AND dist_id = ? ORDER BY taluk ASC";
$stmt_taluk = mysqli_prepare($db_conn, $select_taluk);

if (!$stmt_taluk) {
    echo '<select required name="taluk_id" class="form-control">
            <option value="" hidden>Error loading taluks</option>
          </select>';
    exit;
}

mysqli_stmt_bind_param($stmt_taluk, "ii", $state_id, $dist_id);
mysqli_stmt_execute($stmt_taluk);
$fetch_taluk = mysqli_stmt_get_result($stmt_taluk);

echo '<select required name="taluk_id" class="form-control" id="talukSelect">';
echo '<option value="" hidden>Select Taluk</option>';

$available_count = 0;

if (mysqli_num_rows($fetch_taluk) > 0) {
    while ($result_taluk = mysqli_fetch_assoc($fetch_taluk)) {
        $taluk_id = $result_taluk['id'];
        $taluk_name = htmlspecialchars($result_taluk['taluk']);
        
        // Check if this taluk already has a stockist (excluding current one)
        $check_existing = "SELECT id FROM stockiest WHERE taluk_id = ? AND id != ? LIMIT 1";
        $stmt_check = mysqli_prepare($db_conn, $check_existing);
        mysqli_stmt_bind_param($stmt_check, "ii", $taluk_id, $current_stockist_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $is_occupied = mysqli_num_rows($result_check) > 0;
        mysqli_stmt_close($stmt_check);
        
        // ONLY show available taluks (not occupied) OR current taluk
        if (!$is_occupied || $taluk_id == $current_taluk_id) {
            $selected = ($taluk_id == $current_taluk_id) ? 'selected' : '';
            echo '<option value="' . $taluk_id . '" ' . $selected . '>' . $taluk_name . '</option>';
            $available_count++;
        }
    }
}

// If no available taluks found
if ($available_count == 0) {
    echo '<option value="" disabled>No Available Taluks for This District</option>';
}

echo '</select>';

mysqli_stmt_close($stmt_taluk);
?>