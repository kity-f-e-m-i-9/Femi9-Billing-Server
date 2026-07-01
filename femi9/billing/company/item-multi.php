<!DOCTYPE html>
<html>
<head>
    <title>Select Product and Show Price Dynamically</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<form id="orderForm" action="process_order.php" method="post">
    <div class="item">
        <select name="product[]" class="product">
            <option value="">Select Product</option>
            <?php
            // Fetch products from database or other source
            $products = array(
                array("id" => 1, "name" => "Product 1", "price" => 10),
                array("id" => 2, "name" => "Product 2", "price" => 20),
                array("id" => 3, "name" => "Product 3", "price" => 30)
            );
            foreach ($products as $product) {
                echo "<option value='".$product['id']."' data-price='".$product['price']."'>".$product['name']."</option>";
            }
            ?>
        </select>
        <input type="text" name="quantity[]" class="quantity" placeholder="Quantity">
        <span class="price">Price: $0</span>
        <button type="button" class="remove">Remove</button>
    </div>
    <button type="button" id="add">Add Item</button>
    <button type="submit">Submit Order</button>
</form>

<script>
$(document).ready(function(){
    // Add item
    $("#add").click(function(){
        $(".item:first").clone().appendTo("#orderForm").find('input[type="text"]').val('');
    });

    // Remove item
    $(document).on('click', '.remove', function(){
        $(this).parent().remove();
        updateTotal();
    });

    // Update price based on selected product
    $(document).on('change', '.product', function(){
        var price = $(this).find('option:selected').data('price');
        $(this).siblings('.price').text("Price: $" + price);
        updateTotal();
    });

    // Recalculate total price
    function updateTotal() {
        var total = 0;
        $('.item').each(function(){
            var price = $(this).find('.product option:selected').data('price');
            var quantity = $(this).find('.quantity').val();
            if(price && quantity) {
                total += parseInt(price) * parseInt(quantity);
            }
        });
        $('#total').text("Total: $" + total);
    }

    // Recalculate total on input change
    $(document).on('keyup', '.quantity', function(){
        updateTotal();
    });

});
</script>

</body>
</html>
