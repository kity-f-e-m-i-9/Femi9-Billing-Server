<?php
// Merged into manage-purchase-orders.php — that page now shows every
// purchase order alongside its resulting bill (subtotal, discount, payment
// status), plus invoices the company created directly, so there's no need
// for a separate "Purchased Bill Copy" page anymore.
header("Location: manage-purchase-orders.php");
exit;
