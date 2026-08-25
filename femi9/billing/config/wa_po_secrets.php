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

define('WA_PO_API_KEY', 'e34991059a058d6485da971dac2564cc190ff58605e941836d3c100a030c1677');
define('WA_PO_WEBHOOK_SECRET', '0d05278c8bbca7978a7595b62254318ed6c9f75724b1d8383d78eb715e1e1234');
