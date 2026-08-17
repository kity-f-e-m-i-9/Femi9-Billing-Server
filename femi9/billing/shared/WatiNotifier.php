<?php
/**
 * Outbound Laravel(-equivalent plain PHP)->Wati notifications (spec §2).
 * Payment approval on the billing site usually isn't instant, so instead
 * of the WhatsApp agent polling /payment/verify-status forever, this
 * class pushes the update proactively once an admin approves/rejects a
 * wa_po_advance_payment_submissions row.
 *
 * Uses the same Wati Send Template API endpoint/credential
 * (WATI_API_TOKEN / WATI_TENANT_ID) as send-otp.php.
 */

class WatiNotifier {
    private $apiToken;
    private $tenantId;

    public function __construct() {
        $this->apiToken = $_ENV['WATI_API_TOKEN'] ?? '';
        $this->tenantId = $_ENV['WATI_TENANT_ID'] ?? '';
    }

    public function isConfigured(): bool {
        return $this->apiToken !== '' && $this->tenantId !== '';
    }

    /**
     * Sends the "po_payment_approved" approved WhatsApp template with
     * {{name}}, {{amount}}, {{po_number}} variables (spec §2).
     *
     * @return array{success:bool, message:string}
     */
    public function sendPoPaymentApprovedNotification(string $waNumber, string $name, float $amount, string $poNumber): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Wati credentials not configured'];
        }
        return $this->sendTemplate($waNumber, 'po_payment_approved', [
            ['name' => 'name', 'value' => $name],
            ['name' => 'amount', 'value' => number_format($amount, 2)],
            ['name' => 'po_number', 'value' => $poNumber],
        ]);
    }

    /**
     * Sends the payment-rejected counterpart, same template mechanism.
     */
    public function sendPoPaymentRejectedNotification(string $waNumber, string $name, float $amount, string $reason): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Wati credentials not configured'];
        }
        return $this->sendTemplate($waNumber, 'po_payment_rejected', [
            ['name' => 'name', 'value' => $name],
            ['name' => 'amount', 'value' => number_format($amount, 2)],
            ['name' => 'reason', 'value' => $reason],
        ]);
    }

    private function sendTemplate(string $waNumber, string $templateName, array $parameters): array {
        $payload = json_encode([
            'template_name' => $templateName,
            'broadcast_name' => $templateName . '_' . time(),
            'parameters' => $parameters,
        ]);

        $url = "https://live-mt-server.wati.io/{$this->tenantId}/api/v1/sendTemplateMessage?whatsappNumber=" . urlencode($waNumber);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiToken, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => "cURL error: $curlError"];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['success' => false, 'message' => "Wati returned HTTP $httpCode: $response"];
        }
        return ['success' => true, 'message' => 'OK'];
    }
}

/**
 * Looks up the wa_po_advance_payment_submissions row + the account's
 * registered WhatsApp number (checking wa_po_linked_numbers first for an
 * explicitly-linked WA number, then falling back to the category table's
 * own mobile_field) and fires the approval notification. Called from the
 * wa-po payment approve action after a submission's status flips to
 * 'accepted'.
 *
 * Silently no-ops (returns a result array, doesn't throw) if Wati isn't
 * configured yet or the submission/account can't be found — this must
 * never block the actual approval action from completing.
 *
 * @return array{success:bool, message:string}
 */
function triggerWaPoPaymentApprovalNotification($db_conn, int $submissionId): array {
    $stmt = mysqli_prepare($db_conn,
        "SELECT user_category, user_id, amount FROM wa_po_advance_payment_submissions WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $submissionId);
    mysqli_stmt_execute($stmt);
    $submission = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$submission) {
        return ['success' => false, 'message' => 'Submission not found'];
    }

    $category = $submission['user_category'];
    $userId = (int)$submission['user_id'];

    $categoryTables = [
        'distributor' => ['table' => 'distributor', 'id_field' => 'id', 'mobile_field' => 'mobile_number', 'name_field' => 'name'],
        'super_distributor' => ['table' => 'super_distributor', 'id_field' => 'id', 'mobile_field' => 'mobile_number', 'name_field' => 'name'],
        'stockiest' => ['table' => 'stockiest', 'id_field' => 'id', 'mobile_field' => 'mobile_number', 'name_field' => 'name'],
        'super_stockiest' => ['table' => 'super_stockiest', 'id_field' => 'id', 'mobile_field' => 'mobile_number', 'name_field' => 'name'],
        'channel_partner' => ['table' => 'channel_partners', 'id_field' => 'id', 'mobile_field' => 'mobile', 'name_field' => 'name'],
        'candf' => ['table' => 'c_and_f', 'id_field' => 'id', 'mobile_field' => 'username', 'name_field' => 'name'],
        'marketing' => ['table' => 'marketing_staff', 'id_field' => 'id', 'mobile_field' => 'ms_mobile', 'name_field' => 'ms_name'],
        'territory_partner' => ['table' => 'territory_partners', 'id_field' => 'id', 'mobile_field' => 'mobile', 'name_field' => 'name'],
    ];

    $cfg = $categoryTables[$category] ?? null;
    if (!$cfg) {
        return ['success' => false, 'message' => 'Unknown user_category'];
    }

    $stmt2 = mysqli_prepare($db_conn,
        "SELECT `{$cfg['mobile_field']}` AS mobile, `{$cfg['name_field']}` AS name FROM `{$cfg['table']}` WHERE `{$cfg['id_field']}` = ?"
    );
    mysqli_stmt_bind_param($stmt2, 'i', $userId);
    mysqli_stmt_execute($stmt2);
    $account = mysqli_stmt_get_result($stmt2)->fetch_assoc();
    mysqli_stmt_close($stmt2);

    if (!$account) {
        return ['success' => false, 'message' => 'Account not found'];
    }

    // Prefer an explicitly linked WhatsApp number (the number the person
    // is actually chatting from) over the category table's registered
    // mobile, since that's more likely to be the live WA conversation.
    $waNumber = $account['mobile'];
    $linkStmt = mysqli_prepare($db_conn,
        "SELECT wa_number FROM wa_po_linked_numbers WHERE user_category = ? AND user_id = ? ORDER BY linked_at DESC LIMIT 1"
    );
    mysqli_stmt_bind_param($linkStmt, 'si', $category, $userId);
    mysqli_stmt_execute($linkStmt);
    $linkRow = mysqli_stmt_get_result($linkStmt)->fetch_assoc();
    mysqli_stmt_close($linkStmt);
    if ($linkRow && !empty($linkRow['wa_number'])) {
        $waNumber = $linkRow['wa_number'];
    }

    $notifier = new WatiNotifier();
    if (!$notifier->isConfigured()) {
        return ['success' => false, 'message' => 'Wati credentials not configured — notification skipped'];
    }

    // No dedicated PO number exists yet at approval time (the PO is
    // created later via /po/finalize using this proof_id) — pass the
    // proof id itself as a stand-in reference in the template.
    return $notifier->sendPoPaymentApprovedNotification(
        $waNumber,
        (string)$account['name'],
        (float)$submission['amount'],
        'PRF-' . $submissionId
    );
}
