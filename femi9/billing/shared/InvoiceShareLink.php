<?php
/**
 * Signs and verifies the invoice-PDF share links sent out via WhatsApp
 * (see shop-invoice-pdf.php, customer-invoice-pdf.php, etc.). These links
 * are opened by the shop/customer's own phone — no login session exists at
 * that point — so a plain guessable invoice id would let anyone enumerate
 * other people's invoices. Instead each link carries an HMAC signature over
 * (kind, id) using a server-only secret; only links this app generated
 * itself verify successfully. No expiry — a shared invoice link is meant to
 * keep working whenever the recipient gets around to opening it.
 */

require_once __DIR__ . '/env-loader.php';

function invoice_share_signing_key(): string {
    $key = $_ENV['INVOICE_SHARE_SIGNING_KEY'] ?? '';
    if (empty($key)) {
        throw new Exception('INVOICE_SHARE_SIGNING_KEY not configured');
    }
    return $key;
}

/**
 * @param string $kind Short tag identifying which invoice-pdf endpoint the
 *                      id belongs to (e.g. 'shop', 'customer', 'tp') so a
 *                      signature minted for one can't be replayed against
 *                      another endpoint's id space.
 * @param string $id    The (already base64-encoded, as used elsewhere in
 *                      this codebase) invoice id.
 */
function invoice_share_sign(string $kind, string $id): string {
    $payload = $kind . ':' . $id;
    $sig     = hash_hmac('sha256', $payload, invoice_share_signing_key());
    // Short, URL-safe: 16 hex chars (64 bits) is already infeasible to
    // brute-force per-link and keeps the wa.me message text compact.
    return substr($sig, 0, 16);
}

function invoice_share_verify(string $kind, string $id, string $sig): bool {
    if ($sig === '') {
        return false;
    }
    $expected = invoice_share_sign($kind, $id);
    return hash_equals($expected, $sig);
}

/**
 * Builds the full https:// URL for a share-PDF endpoint, using APP_URL from
 * .env so the link always points at the real production domain regardless
 * of what host the TP happens to be browsing the admin app from.
 */
function invoice_share_url(string $endpointPath, string $kind, string $id): string {
    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
    $sig  = invoice_share_sign($kind, $id);
    return $base . $endpointPath . '?id=' . urlencode($id) . '&sig=' . $sig;
}
