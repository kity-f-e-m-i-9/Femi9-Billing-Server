<?php
include("checksession.php");

$state_id = isset($_GET["q"]) ? intval($_GET["q"]) : 0;
$invuser = isset($_GET["invuser"]) ? $_GET["invuser"] : "";
$current_stockist_id = isset($_GET["current_stockist_id"]) ? intval($_GET["current_stockist_id"]) : 0;
$current_dist_id = isset($_GET["current_dist_id"]) ? intval($_GET["current_dist_id"]) : 0;

if ($state_id <= 0) {
    echo '<select required name="dist_id" class="form-control" onchange="showTalukStockistEdit(' . $state_id . ', this.value)">
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

echo '<select required name="dist_id" class="form-control" id="districtSelect" onchange="showTalukStockistEdit(' . $state_id . ', this.value)">';
echo '<option value="" hidden>Select District</option>';

$available_count = 0;
$has_selected = false;

if (mysqli_num_rows($fetch_district) > 0) {
    while ($result_district = mysqli_fetch_assoc($fetch_district)) {
        $dist_id = $result_district['id'];
        $dist_name = htmlspecialchars($result_district['dist_name']);
        
        // Check if this district has any available taluks
        // First, get all taluks in this district
        $check_taluks = "SELECT id FROM taluk WHERE state_id = ? AND dist_id = ?";
        $stmt_taluks = mysqli_prepare($db_conn, $check_taluks);
        mysqli_stmt_bind_param($stmt_taluks, "ii", $state_id, $dist_id);
        mysqli_stmt_execute($stmt_taluks);
        $result_taluks = mysqli_stmt_get_result($stmt_taluks);
        
        $has_available_taluks = false;
        
        // Check each taluk to see if it's available
        while ($taluk_row = mysqli_fetch_assoc($result_taluks)) {
            $taluk_id = $taluk_row['id'];
            
            // Check if this taluk is occupied by another stockist
            $check_occupied = "SELECT id FROM stockiest WHERE taluk_id = ? AND id != ? LIMIT 1";
            $stmt_occupied = mysqli_prepare($db_conn, $check_occupied);
            mysqli_stmt_bind_param($stmt_occupied, "ii", $taluk_id, $current_stockist_id);
            mysqli_stmt_execute($stmt_occupied);
            $result_occupied = mysqli_stmt_get_result($stmt_occupied);
            
            if (mysqli_num_rows($result_occupied) == 0) {
                // This taluk is available
                $has_available_taluks = true;
                mysqli_stmt_close($stmt_occupied);
                break;
            }
            mysqli_stmt_close($stmt_occupied);
        }
        mysqli_stmt_close($stmt_taluks);
        
        // Only show districts that have available taluks OR is the current district
        if ($has_available_taluks || $dist_id == $current_dist_id) {
            $selected = ($dist_id == $current_dist_id) ? 'selected' : '';
            if ($selected) {
                $has_selected = true;
            }
            echo '<option value="' . $dist_id . '" ' . $selected . '>' . $dist_name . '</option>';
            $available_count++;
        }
    }
}

// If no available districts found
if ($available_count == 0) {
    echo '<option value="" disabled>No Districts with Available Taluks</option>';
}

echo '</select>';

// Add script to trigger taluk loading AFTER the select is rendered
if ($has_selected && $current_dist_id > 0) {
    echo '<script type="text/javascript">
        if (typeof showTalukStockistEdit === "function") {
            showTalukStockistEdit(' . $state_id . ', ' . $current_dist_id . ');
        }
    </script>';
}

mysqli_stmt_close($stmt_district);
?>