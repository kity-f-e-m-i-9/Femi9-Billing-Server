<?php
/**
 * WhatsApp Purchase Order automation - server-side secrets.
 *
 * WA_PO_API_KEY        - API key the n8n agent sends on every inbound call as
 *                        X-Api-Key: <key>.
 * WA_PO_WEBHOOK_SECRET - HMAC-SHA256 key behind the static X-Signature value,
 *                        which is HMAC-SHA256(WA_PO_API_KEY, WA_PO_WEBHOOK_SECRET).
 *
 * Both values come from shared/.env (WA_PO_API_KEY, WA_PO_WEBHOOK_SECRET) and
 * must never be committed. Generate with: bin2hex(random_bytes(32)).
 * If either is rotated, recompute X-Signature and update the n8n tool nodes.
 * api/wa-po/_bootstrap.php refuses all calls while either value is empty.
 *
 * This file must stay outside direct web access - see config/.htaccess.
 */

require_once __DIR__ . '/../shared/env-loader.php';

define('WA_PO_API_KEY', (string)($_ENV['WA_PO_API_KEY'] ?? ''));
define('WA_PO_WEBHOOK_SECRET', (string)($_ENV['WA_PO_WEBHOOK_SECRET'] ?? ''));
