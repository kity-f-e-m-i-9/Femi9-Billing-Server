<?php
include("checksession.php");

$state_id = isset($_GET["q"]) ? intval($_GET["q"]) : 0;
$invuser = isset($_GET["invuser"]) ? $_GET["invuser"] : "";
$current_ss_id = isset($_GET["current_ss_id"]) ? intval($_GET["current_ss_id"]) : 0;
$current_dist_id = isset($_GET["current_dist_id"]) ? intval($_GET["current_dist_id"]) : 0;

if ($state_id <= 0) {
    echo '<select required name="dist_id" class="form-control">
            <option value="" hidden>Select State First</option>
          </select>';
    exit;
}

// Get districts for selected state
$select_district = "SELECT id, dist_name FROM district WHERE state_id = ? ORDER BY dist_name ASC";
$stmt_district = mysqli_prepare($db_conn, $select_district);

if (!$stmt_district) {
    echo '<select required name="dist_id" class="form-control">
            <option value="" hidden>Error loading districts</option>
          </select>';
    exit;
}

mysqli_stmt_bind_param($stmt_district, "i", $state_id);
mysqli_stmt_execute($stmt_district);
$fetch_district = mysqli_stmt_get_result($stmt_district);

echo '<select required name="dist_id" class="form-control" id="districtSelect">';
echo '<option value="" hidden>Select District</option>';

$available_count = 0;

if (mysqli_num_rows($fetch_district) > 0) {
    while ($result_district = mysqli_fetch_assoc($fetch_district)) {
        $dist_id = $result_district['id'];
        $dist_name = htmlspecialchars($result_district['dist_name']);
        
        // Check if this district already has a super stockist (excluding current one)
        $check_existing = "SELECT id FROM super_stockiest WHERE district_id = ? AND id != ? LIMIT 1";
        $stmt_check = mysqli_prepare($db_conn, $check_existing);
        mysqli_stmt_bind_param($stmt_check, "ii", $dist_id, $current_ss_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $is_occupied = mysqli_num_rows($result_check) > 0;
        mysqli_stmt_close($stmt_check);
        
        // ONLY show available districts (not occupied) OR current district
        if (!$is_occupied || $dist_id == $current_dist_id) {
            $selected = ($dist_id == $current_dist_id) ? 'selected' : '';
            echo '<option value="' . $dist_id . '" ' . $selected . '>' . $dist_name . '</option>';
            $available_count++;
        }
    }
}

// If no available districts found
if ($available_count == 0) {
    echo '<option value="" disabled>No Available Districts for This State</option>';
}

echo '</select>';

mysqli_stmt_close($stmt_district);
?>