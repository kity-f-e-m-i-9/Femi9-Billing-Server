<button class="open-modal">Open Modal</button>


<div class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div id="php-value-container"></div>
    </div>
</div>


<script>
$(document).ready(function(){
    // Open modal on button click
    $(".open-modal").click(function(){
        // Make an AJAX request to fetch PHP value
        $.ajax({
            url: "get_php_value.php", // PHP script to fetch value
            type: "POST",
            data: {
                // Any data you want to send to the server
            },
            success: function(response){
                // Display the PHP value in the modal
                $("#php-value-container").html(response);
                $(".modal").show(); // Show the modal
            }
        });
    });

    // Close modal on close button click
    $(".modal-close").click(function(){
        $(".modal").hide(); // Hide the modal
    });
});

</script>