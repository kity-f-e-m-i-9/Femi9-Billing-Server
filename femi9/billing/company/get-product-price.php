<?php
/**
 * Get Product Price via AJAX
 * Returns product price and other details
 */

include("checksession.php");
include("config.php");

header('Content-Type: application/json');

$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Get product details
$stmt = $db_conn->prepare("SELECT id, productName, price, gst, hsn, rwpoints FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if ($product) {
    echo json_encode([
        'success' => true,
        'price' => floatval($product['price']),
        'product_name' => $product['productName'],
        'gst' => floatval($product['gst']),
        'hsn' => $product['hsn'],
        'rwpoints' => floatval($product['rwpoints'])
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
}
?>
