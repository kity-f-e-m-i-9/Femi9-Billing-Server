<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Popup within Table</title>
<style>
/* CSS for the popup */
.popup {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: white;
    padding: 20px;
    border: 1px solid #ccc;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    z-index: 9999;
}
</style>
</head>
<body>

<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        // Example PHP code to generate table rows
        $users = array(
            array("John Doe", "john@example.com","9677646815"),
            array("Jane Smith", "jane@example.com","9363978814"),
            // Add more user data as needed
        );

        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user[0]}</td>";
            echo "<td>{$user[1]}</td>";
			echo "<td style='display:none;'>{$user[2]}</td>";
			echo "<td style='display:none;'><a href='delete-shop.php'><img src='../../assets/images/delete-32.png'/></a></td>";
            echo "<td><a href='#' class='popup-trigger'>View Details</a></td>";
            echo "</tr>";
        }
        ?>
    </tbody>
</table>

<!-- Popup container -->
<div id="popup" class="popup">
    <h2>User Details</h2>
    <div id="popup-content">
        <!-- Content will be loaded dynamically -->
    </div>
    <button id="close-popup">Close</button>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Show popup when button is clicked
    $('.popup-trigger').click(function(){
        var rowData = $(this).closest('tr').find('td').map(function(){
            return $(this).text();
        }).get();

        // Populate popup content with row data
        $('#popup-content').html("<p>Name: " + rowData[0] + "</p><p>Email: " + rowData[1] + "</p><p>Mobile: " + rowData[2] + "</p><p>" + rowData[3] + "</p>");

        // Show the popup
        $('#popup').fadeIn();
    });

    // Close popup when close button is clicked
    $('#close-popup').click(function(){
        $('#popup').fadeOut();
    });
});
</script>

</body>
</html>
