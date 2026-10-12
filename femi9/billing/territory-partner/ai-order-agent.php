<?php
// Backend for the TP "order assistant" chat widget (assets/ai-agent-widget.js),
// embedded on add-purchase-order.php (flow=po) and shop-invoice-add.php
// (flow=invoice). Takes the TP's typed/voice-transcribed message, asks
// TpOrderAgentService (Claude, tool-use) to match it against this TP's own
// product catalog for that flow, and returns matched {product_id, quantity}
// lines for the widget to fill into the page's existing product/qty fields —
// all price/stock/carton-multiple validation stays in each page's own code.
declare(strict_types=1);

require_once __DIR__ . "/checksession.php";
require_once __DIR__ . "/../shared/TpProductType.php";
require_once __DIR__ . "/../shared/TpOrderAgentService.php";
require_once __DIR__ . "/../shared/TpEmergencyStockRequest.php";
include __DIR__ . "/config.php";

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) $input = [];

$message = trim((string)($input['message'] ?? ''));
$flow = in_array($input['flow'] ?? '', ['po', 'invoice'], true) ? $input['flow'] : 'po';
$productType = in_array($input['product_type'] ?? '', ['napkin', 'diaper'], true) ? $input['product_type'] : 'napkin';
$history = is_array($input['history'] ?? null) ? array_slice($input['history'], -10) : [];
$cpId = (int)($input['cp_id'] ?? 0);

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Empty message']);
    exit;
}

$tpId = (int) $Login_user_IDvl;
if ($tpId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($flow === 'po' && $cpId > 0) {
    // CP-sourced order — every product is annotated with THIS CP's actual
    // closing_qty (0 when the CP simply doesn't stock it), same source
    // get-cp-products.php uses to build the real product picker, MINUS
    // whatever's already reserved by other TPs' own pending (status=
    // 'waiting', not yet invoiced) purchase orders against this same CP —
    // otherwise two TPs could both be told the same stock is free and
    // both order it. This is a display/decision figure only: the real
    // channel_partner_stock.closing_qty is never touched here, only what
    // the assistant is told is "available" to offer/promise.
    $sql = "SELECT p.id, p.productName, p.packs_per_carton,
                   GREATEST(0, COALESCE(cps.closing_qty, 0) - COALESCE(reserved.qty, 0)) AS available_qty
            FROM products p
            LEFT JOIN channel_partner_stock cps
                   ON cps.product_id = p.id AND cps.channel_partner_id = $cpId
            LEFT JOIN (
                SELECT poi.product_id, SUM(poi.qty) AS qty
                FROM tp_purchase_order_items poi
                JOIN tp_purchase_orders po ON po.id = poi.po_id
                WHERE po.preferred_cp_id = $cpId AND po.status = 'waiting'
                GROUP BY poi.product_id
            ) reserved ON reserved.product_id = p.id
            WHERE p.deleted_at IS NULL AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
              AND " . tpProductTypeSqlFilter($productType, 'p') . "
            ORDER BY p.productName ASC";
} elseif ($flow === 'po') {
    // packs_per_carton lets the agent convert "3 boxes/cartons" into the
    // actual qty the Qty field needs (e.g. 3 cartons x 50/carton = 150) —
    // without it, "3 box" and "3 pieces" look identical to the model.
    $sql = "SELECT id, productName, packs_per_carton FROM products
            WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)
              AND " . tpProductTypeSqlFilter($productType, 'products') . "
            ORDER BY productName ASC";
} else {
    $sql = "SELECT p.id, p.productName
            FROM products p
            INNER JOIN territory_partner_stock tps
                    ON tps.product_id = p.id
                   AND tps.territory_partner_id = $tpId
                   AND tps.closing_qty > 0
            WHERE (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
            ORDER BY p.id ASC";
}

$catalog = [];
$res = mysqli_query($db_conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $catalog[] = $row;
    }
}

if (empty($catalog)) {
    echo json_encode(['success' => true, 'reply' => 'No products are currently available for this.', 'lines' => []]);
    exit;
}

try {
    $service = new TpOrderAgentService($db_conn);
    $result = $service->interpret($message, $history, $catalog, $flow);
} catch (\Throwable $e) {
    $result = ['success' => false, 'reply' => "Assistant isn't available right now.", 'lines' => [], 'message' => $e->getMessage()];
}

// Each emergency-flagged line becomes its own request row for Company to
// manually review — never auto-approved/auto-ordered here. Only possible
// when a CP is actually selected (that's the only place "emergency_lines"
// can come from — see TpOrderAgentService::resolveLines()).
if ($cpId > 0 && !empty($result['emergency_lines'])) {
    foreach ($result['emergency_lines'] as $el) {
        createTpEmergencyStockRequest(
            $db_conn,
            $tpId,
            $cpId,
            (int) $el['product_id'],
            (int) $el['quantity'],
            (int) $el['cp_available_qty'],
            mb_substr($message, 0, 255)
        );
    }
}

echo json_encode($result);
