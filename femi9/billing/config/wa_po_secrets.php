<?php
/**
 * WhatsApp Purchase Order automation — server-side secrets.
 *
 * WA_PO_API_KEY       — Bearer token the Wati agent must send on every
 *                        inbound call (Authorization: Bearer <key>).
 * WA_PO_WEBHOOK_SECRET — HMAC-SHA256 key used to verify the X-Signature
 *                        header on every inbound call's raw body.
 *
 * Both were generated with bin2hex(random_bytes(32)) at file-creation time.
 * There is no user-facing UI for this subsystem yet, so shipping real
 * random values here (rather than a placeholder string) is safe — these
 * never leave the server and only the Wati agent config needs a copy.
 *
 * This file must stay outside direct web access — see config/.htaccess.
 */

define('WA_PO_API_KEY', '828704fb0a6d4f7e60498bf7da99e50d3f032c3a40648e3a3865c1f1070f5080');
define('WA_PO_WEBHOOK_SECRET', 'c07f39d469b283812fab46586459f226a7b0d00292ea26d2b0bf1022310edfaa');
