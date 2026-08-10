# Femi9 Billing – WhatsApp PO Automation: API & Webhook Spec (Plain PHP)

This is the contract between the **Wati WhatsApp agent** and your **plain PHP/MySQL billing app**
(femi9billing.com – no Laravel/Composer framework assumed).
Two directions of traffic:

- **Inbound to Laravel** – the agent calls these APIs during the conversation.
- **Outbound from Laravel** – Laravel calls Wati to push async updates (e.g. payment approved) back to the user.

Base URL suggestion: `https://femi9.in/api/wa-po/v1`

---

## 1. Inbound APIs (Wati agent → Laravel)

All requests must carry:
```
Authorization: Bearer <API_KEY>
X-Signature: <HMAC-SHA256 of raw body, using WEBHOOK_SECRET>
Content-Type: application/json
```

### 1.1 Verify user / genuineness + category

This endpoint now has to handle **three outcomes**, not one: a clean single match, a number tied
to more than one account/category, or a number that isn't registered at all.

```
POST /auth/verify-user
{
  "wa_number": "+9198XXXXXXXX"
}
```

**Case A – exactly one account (the simple case)**
```json
{
  "match_type": "exact",
  "accounts": [
    {
      "user_id": 4521,
      "tp_login_id": "TP-4521",
      "name": "Ramesh Traders",
      "category": "distributor",
      "status": "active"
    }
  ]
}
```

**Case B – one person/business, multiple TP logins or categories on the same number**
(e.g. Ramesh Traders operates both a distributor account and a retailer sub-account, both
registered against the same mobile number)
```json
{
  "match_type": "multiple_accounts",
  "accounts": [
    {"user_id": 4521, "tp_login_id": "TP-4521", "name": "Ramesh Traders (Distributor)", "category": "distributor", "status": "active"},
    {"user_id": 4522, "tp_login_id": "TP-4522", "name": "Ramesh Traders (Retail Unit 2)", "category": "retailer", "status": "active"}
  ]
}
```
Agent must show a selection menu ("Which account is this PO for?") before continuing – never
guess or default to the first one.

**Case C – number not registered against any account**
```json
{
  "match_type": "not_found",
  "accounts": []
}
```
Do **not** say "number not registered" outright (avoids confirming/denying account existence to
an unknown caller). Instead route to the fallback verification flow below (1.1b).

Use `status != active` on the *selected* account to short-circuit with a rejection message
(blocked / KYC pending) even after a match.

### 1.1a Select account (when match_type = multiple_accounts)
```
POST /auth/select-account
{
  "wa_number": "+9198XXXXXXXX",
  "user_id": 4522
}
```
```json
{
  "session_token": "sess_8f2a...",
  "user_id": 4522,
  "tp_login_id": "TP-4522",
  "category": "retailer"
}
```
Returns a short-lived `session_token` (suggest 30–60 min TTL) that every subsequent call in this
conversation should include, so the agent doesn't need to re-resolve identity on every step and
can't accidentally mix up two accounts mid-conversation. Store it against the Wati
`conversation_id` in your own KV/cache (Redis if available, else a MySQL table with an indexed
expiry column) rather than trusting the model to hold it in the prompt state reliably across a
long chat.

**This call also upserts `wa_number_last_account(wa_number, user_id, updated_at)`** – that's what
powers the "remember last used" behaviour below, so no separate endpoint is needed for it.

### 1.1c Remembering the last-used account (fast path for repeat multi-category users)

For a user with multiple accounts, showing the full picker on every single PO is friction they
don't need once they've told you their preference once. Change the `/auth/verify-user` response
for `match_type: multiple_accounts` to also surface the remembered choice:

```json
{
  "match_type": "multiple_accounts",
  "accounts": [
    {"user_id": 4521, "tp_login_id": "TP-4521", "name": "Ramesh Traders (Distributor)", "category": "distributor", "status": "active"},
    {"user_id": 4522, "tp_login_id": "TP-4522", "name": "Ramesh Traders (Retail Unit 2)", "category": "retailer", "status": "active"}
  ],
  "last_used_account_id": 4522
}
```
`last_used_account_id` comes from a simple lookup against `wa_number_last_account` keyed by
`wa_number`. Null if this number has never selected before (first-time multi-account user still
sees the full picker).

**Agent behaviour:**
- `last_used_account_id` present → show a quick-confirm instead of the full list:
  *"Continue as Ramesh Traders – Retail Unit 2 (TP-4522)? [Yes, continue] [Switch account]"*
  - "Yes, continue" → straight to `/auth/select-account` with that `user_id`, no extra step.
  - "Switch account" → falls through to the full picker over `accounts[]`.
- `last_used_account_id` is null → show the full picker directly (nothing to confirm yet).
- Every `/auth/select-account` call – whether reached via quick-confirm or full picker – updates
  `last_used_account_id` for next time, so switching sticks as the new default going forward.

### 1.1b Fallback verification – WhatsApp number doesn't match any registered account

This covers: staff texting from a personal phone, a new employee, a number change that wasn't
updated in the billing system, etc. Never let an unmatched number straight into the catalog/PO
flow – verify identity through the number that **is** on file.

```
POST /auth/lookup-by-alt
{
  "identifier": "TP-4521"      // TP login ID, or the registered mobile number, or GSTIN – user supplies one
}
```
```json
{
  "found": true,
  "user_id": 4521,
  "tp_login_id": "TP-4521",
  "name": "Ramesh Traders",
  "registered_number_masked": "+9198XXXX21",
  "category": "distributor",
  "status": "active"
}
```

```
POST /auth/send-otp
{ "user_id": 4521 }
```
Sends a 6-digit OTP via **WhatsApp**, using Wati's outbound Send Template API, to the number on
file in `users.registered_wa_number` – not necessarily the number the person is currently
chatting from. This means `send-otp.php` is one of the few *outbound* calls PHP makes to Wati
(alongside the payment-approval push in §2), so it needs `WATI_API_TOKEN` too.

```php
// send-otp.php (excerpt, after generating $otp and storing it with expiry in DB)
$payload = json_encode([
    'template_name' => 'wa_po_login_otp',   // pre-approved WhatsApp AUTHENTICATION-category template
    'broadcast_name' => 'wa_po_otp_' . time(),
    'parameters' => [ ['name' => 'otp', 'value' => $otp] ],
]);
$ch = curl_init("https://live-mt-server.wati.io/{$tenantId}/api/v1/sendTemplateMessage?whatsappNumber=" . urlencode($registeredWaNumber));
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . WATI_API_TOKEN, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
```
`{ "otp_id": "OTP-771", "expires_in": 300 }`

**Template note:** WhatsApp's **Authentication** template category is purpose-built for OTPs –
it renders with a native "Copy Code" / autofill button and has tighter delivery SLAs than a
generic marketing template, but it must be submitted and approved via Wati/Meta before use, and
by policy can only carry the code (no extra marketing copy). Get `wa_po_login_otp` approved
before wiring this up.

**Known limitation:** if the registered number doesn't have WhatsApp (rare for a B2B contact but
possible – landline-linked account, etc.), this send will fail. Decide now whether you want a
silent SMS fallback for that edge case or just a "contact support" message – worth a quick check
of how many registered numbers in the DB currently lack WhatsApp before deciding it's worth building.

```
POST /auth/verify-otp
{ "otp_id": "OTP-771", "code": "482913", "wa_number": "+9198XXXXXXXX" }
```
```json
{ "verified": true, "session_token": "sess_a91c...", "user_id": 4521, "category": "distributor" }
```

**Guardrails for this path:**
- Max 3 OTP attempts, then lock for 15 min and tell the user to contact support directly – don't loop indefinitely.
- Max 3 OTP *sends* per hour per account to prevent SMS-bombing abuse.
- OTP expires in 5 minutes.
- On success, optionally ask consent: *"Save this WhatsApp number to your account for faster access next time?"* – if yes, call:

```
POST /auth/link-number
{ "user_id": 4521, "wa_number": "+9198XXXXXXXX", "session_token": "sess_a91c..." }
```
This appends the number to a `linked_numbers` table (many-to-one with `users`) so next time this
same WhatsApp number hits `/auth/verify-user`, it resolves as Case A/B directly – no repeat OTP.
Never auto-link without explicit consent, and never overwrite the primary registered number.

### 1.2 Get advance payment balance
```
GET /balance/advance?user_id=4521
```
```json
{
  "user_id": 4521,
  "advance_balance": 18500.00,
  "currency": "INR",
  "as_of": "2026-08-10T10:15:00+05:30"
}
```

### 1.3 Product catalog (for building the cart in chat)
```
GET /catalog/products?category=distributor&q=optional-search
```
```json
{
  "products": [
    {"sku": "FEM-PAD-XL-40", "name": "Femi9 XL Pads (40pk)", "price": 320.00, "moq": 5, "in_stock": true}
  ]
}
```

### 1.4 Validate cart against balance
```
POST /po/validate-cart
{
  "user_id": 4521,
  "items": [{"sku": "FEM-PAD-XL-40", "qty": 10, "price": 320.00}]
}
```
```json
{
  "total_amount": 3200.00,
  "within_balance": true,
  "balance_after": 15300.00
}
```

### 1.5 Create PO directly (balance sufficient – this is the "webhook" for direct submission)
```
POST /po/create
{
  "user_id": 4521,
  "items": [{"sku": "FEM-PAD-XL-40", "qty": 10, "price": 320.00}],
  "total_amount": 3200.00,
  "source": "whatsapp",
  "idempotency_key": "wa-4521-20260810-1"
}
```
```json
{
  "po_number": "PO-2026-08-0142",
  "status": "created",
  "created_at": "2026-08-10T10:20:11+05:30"
}
```
**`idempotency_key` is mandatory** – WhatsApp/Wati can retry a message send, and you don't want duplicate POs. Generate it as `wa-{user_id}-{yyyyMMdd}-{sequence}` and make the column unique in Laravel.

### 1.6 Submit payment proof (balance insufficient path)
```
POST /payment/submit-proof
{
  "user_id": 4521,
  "cart_ref": "cart-4521-20260810-1",
  "screenshot_url": "https://wati-media.../img.jpg",
  "utr_reference": "optional if user typed it"
}
```
```json
{ "proof_id": "PRF-9931", "status": "pending_review" }
```
Wati stores inbound media on its own CDN – pass that media URL straight through; don't re-host it.

### 1.7 Poll payment verification status
```
GET /payment/verify-status?proof_id=PRF-9931
```
```json
{ "proof_id": "PRF-9931", "status": "pending", "verified_amount": null, "remarks": null }
```
Values: `pending | approved | rejected`.

### 1.8 Finalize PO after approval
```
POST /po/finalize
{
  "user_id": 4521,
  "proof_id": "PRF-9931",
  "items": [...],
  "total_amount": 3200.00,
  "idempotency_key": "wa-4521-20260810-2"
}
```
```json
{ "po_number": "PO-2026-08-0143", "status": "confirmed" }
```

---

## 1c. Agent conversation flow (state machine)

```
[START] user sends any message
   │
   ▼
Show MENU (Place Purchase Order / Track PO / Support / etc.)
   │  user picks "Purchase Order"
   ▼
Call /auth/verify-user(wa_number)
   │
   ├─ match_type = exact ─────────────────────────────────► go to CATEGORY-BOUND, account = accounts[0]
   │
   ├─ match_type = multiple_accounts
   │      │ last_used_account_id present?
   │            ├─ yes → quick-confirm: "Continue as <name> (<category>)?" [Continue / Switch]
   │            │           ├─ Continue → /auth/select-account(that user_id) → session_token
   │            │           └─ Switch   → show full account picker → user selects → /auth/select-account
   │            └─ no  → show full account picker directly → user selects → /auth/select-account
   │      ────────────────────────────────────────────────► go to CATEGORY-BOUND
   │
   └─ match_type = not_found
          │ "To continue, please share your TP Login ID or registered mobile number"
          │ call /auth/lookup-by-alt
                ├─ found = false → polite rejection + human support handoff, END
                └─ found = true
                       │ call /auth/send-otp
                       │ "We've sent a 6-digit code to your registered number ending XX. Please share it."
                       │ call /auth/verify-otp
                             ├─ verified = false (retry, max 3) → re-prompt / lock after 3
                             └─ verified = true
                                    │ optional consent to link number → /auth/link-number
                                    ────────────────────────────────────► go to CATEGORY-BOUND

[CATEGORY-BOUND] account status check
   │
   ├─ status != active → "Your account is <blocked/KYC pending> – please contact support." END
   │
   └─ status = active
         │ GET /balance/advance(user_id)
         │ GET /catalog/products(category) → build cart conversationally
         │ POST /po/validate-cart
               ├─ within_balance = true
               │     │ POST /po/create (idempotency_key!)
               │     │ send PO confirmation (PO number)
               │     │ "Anything else?" → yes: back to MENU | no: END
               │
               └─ within_balance = false
                     │ "Balance insufficient. Please share a payment screenshot / UTR."
                     │ user sends screenshot → POST /payment/submit-proof
                     │ "Submitted for verification, we'll confirm shortly."
                     │ [poll or wait for outbound webhook – see §2]
                           ├─ status = approved → POST /po/finalize → confirmation → "Anything else?"
                           ├─ status = rejected → explain + offer retry / support handoff
                           └─ status = pending  → after N minutes, agent can proactively re-check
                                                    once via GET /payment/verify-status, but the
                                                    primary notification should be the outbound
                                                    webhook in §2, not an infinite poll loop
```

**Design notes:**
- Bind every downstream call in a conversation to the `session_token` from `/auth/select-account`
  or `/auth/verify-otp` – this is what prevents a user with two categories from accidentally
  having their PO attributed to the wrong account mid-conversation.
- Re-verification: don't trust identity for the whole calendar day – expire `session_token` after
  30–60 min of inactivity so a shared/office phone can't stay "logged in" as one TP account indefinitely.
- The account-picker and OTP steps should use Wati's interactive list/button messages, not free text, to avoid parsing ambiguity.

---

## 2. Outbound webhook (Laravel → Wati)

Payment approval on the website usually isn't instant, and your flow explicitly needs to notify
the user once admin approves it – so instead of the agent polling forever, have Laravel **push**
the update back to WhatsApp when the admin approves/rejects in the billing site:

```
POST https://live-mt-server.wati.io/{tenant_id}/api/v1/sendTemplateMessage
Authorization: Bearer <WATI_API_TOKEN>
```
Trigger this from a Laravel model observer / job when `payment_proofs.status` changes.
Use an **approved WhatsApp template** (Wati requires templates for business-initiated messages
outside the 24-hour session window), e.g. `po_payment_approved` with variables `{{name}}`,
`{{amount}}`, `{{po_number}}`.

This is the one place Laravel needs a Wati credential (the WATI_API_TOKEN), separate from the
API key Wati uses to call *you*.

---

## 3. Secrets – yes, keep and use them. Here's how.

**Short answer: yes, absolutely keep API and webhook secrets – this endpoint set can create real
purchase orders and touch payment data, so it must be authenticated and signed.** Two secrets, two purposes:

| Secret | Direction | Purpose |
|---|---|---|
| `API_KEY` (Bearer token) | Wati → PHP | Authenticates that the caller is your Wati agent, not a random request |
| `WEBHOOK_SECRET` (HMAC key) | Wati → PHP | Signs the request body so PHP can verify it wasn't tampered with in transit |
| `WATI_API_TOKEN` | PHP → Wati | Lets PHP push proactive template messages back to WhatsApp – used for **both** OTP delivery (§1.1b) and payment-approval notifications (§2) |

**Where they live:**
- **Never** paste secrets into the agent's `instructions` text – that's prompt content, visible/editable in the Wati console and not meant for credentials.
- On the Wati side, store the API key via the platform's integration/credential store (`create_integration_auth` / `create_connection` in the integrations toolset) so the agent's bound action references a `connection_id`, not a raw key in the prompt.
- On the billing-site side, put `WA_PO_API_KEY`, `WA_PO_WEBHOOK_SECRET`, and `WATI_API_TOKEN` in a `config/wa_po_secrets.php` file outside the webroot (or `.htaccess`-protected if it must stay inside) – see §4 for the exact pattern. No Composer/`.env` dependency needed.
- Verify `X-Signature` on every inbound request in a middleware before it touches PO/balance logic; reject on mismatch with 401.
- Rotate both secrets periodically (e.g. every 90 days) and on any suspected leak – plan for a `key_version` or dual-key overlap so rotation doesn't break in-flight conversations.
- Rate-limit the endpoints (Laravel throttle middleware) since `/po/create` and `/po/finalize` are money-moving.
- Log every call with the `idempotency_key` and `user_id` for audit/reconciliation, but never log full screenshot URLs or secrets in plaintext logs.

---

## 4. Suggested PHP endpoint structure (plain PHP, no framework)

Since this is plain PHP/MySQL (not Laravel), skip route-group/middleware conventions and instead
use a small shared bootstrap file that every endpoint includes first. Two layout options – pick
whichever matches how the rest of femi9billing.com is structured:

**Option A – one file per endpoint (simplest, matches typical legacy PHP layout)**
```
/api/wa-po/
  ├── _bootstrap.php        (auth check, signature check, DB connection – included by every file below)
  ├── verify-user.php
  ├── select-account.php
  ├── lookup-by-alt.php
  ├── send-otp.php
  ├── verify-otp.php
  ├── link-number.php
  ├── balance.php
  ├── catalog.php
  ├── validate-cart.php
  ├── po-create.php
  ├── payment-submit-proof.php
  ├── payment-verify-status.php
  └── po-finalize.php
```
Called as `POST /api/wa-po/verify-user.php`, etc.

**`_bootstrap.php`** – shared auth/signature check, included at the top of every endpoint file:
```php
<?php
// _bootstrap.php
require_once __DIR__ . '/../../config/wa_po_secrets.php'; // defines WA_PO_API_KEY, WA_PO_WEBHOOK_SECRET – kept OUTSIDE webroot if possible
require_once __DIR__ . '/../../db.php'; // existing DB connection used by the rest of the billing app

header('Content-Type: application/json');

function wa_po_fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

// 1. Bearer token check
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/', $authHeader, $m) || !hash_equals(WA_PO_API_KEY, $m[1])) {
    wa_po_fail(401, 'Invalid or missing API key');
}

// 2. HMAC signature check on raw body
$rawBody = file_get_contents('php://input');
$expectedSig = hash_hmac('sha256', $rawBody, WA_PO_WEBHOOK_SECRET);
$givenSig = $headers['X-Signature'] ?? '';
if (!hash_equals($expectedSig, $givenSig)) {
    wa_po_fail(401, 'Signature mismatch');
}

$input = json_decode($rawBody, true) ?? [];

// 3. Very basic rate limiting (swap for Redis/APCu-backed limiter in production)
// ... omitted here, see note below ...
```

**`verify-user.php`** – example of a single endpoint:
```php
<?php
require_once __DIR__ . '/_bootstrap.php';

$waNumber = $input['wa_number'] ?? null;
if (!$waNumber) wa_po_fail(400, 'wa_number is required');

// existing $mysqli / $pdo connection from db.php
$stmt = $pdo->prepare("
    SELECT u.id AS user_id, u.tp_login_id, u.name, u.category, u.status
    FROM users u
    JOIN user_phone_numbers p ON p.user_id = u.id
    WHERE p.phone = ? AND p.is_registered = 1
");
$stmt->execute([$waNumber]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($accounts) === 0) {
    echo json_encode(['match_type' => 'not_found', 'accounts' => []]);
} elseif (count($accounts) === 1) {
    echo json_encode(['match_type' => 'exact', 'accounts' => $accounts]);
} else {
    echo json_encode(['match_type' => 'multiple_accounts', 'accounts' => $accounts]);
}
```
The other endpoints follow the same pattern: include `_bootstrap.php`, read `$input`, hit the
existing billing DB with prepared statements, `echo json_encode(...)`.

**`config/wa_po_secrets.php`** (kept outside the public webroot, or `.htaccess`-denied if it must live inside):
```php
<?php
define('WA_PO_API_KEY', 'generate-a-long-random-value-here');
define('WA_PO_WEBHOOK_SECRET', 'generate-a-different-long-random-value-here');
define('WATI_API_TOKEN', 'your-wati-api-token');
```
If this file must sit inside the webroot (shared hosting / cPanel constraints, which matches your
setup), add to `.htaccess` in that folder:
```
<Files "wa_po_secrets.php">
    Require all denied
</Files>
```

**Option B – single router file** (`/api/wa-po/index.php` dispatching on a `?action=` or path-info
param) if you'd rather not have 13 separate files. Either works – Option A is usually easier to
reason about and matches how most of femi9billing.com's existing endpoints look already.

### Rate limiting without a framework
Since there's no Laravel throttle middleware here, the simplest option on shared hosting is a
MySQL-backed counter table (`api_rate_limits(key, window_start, count)`) checked at the top of
`_bootstrap.php`, or APCu if it's enabled on the server. Redis is cleaner if it's available but
not required just for this.
