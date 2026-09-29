# Per-portal session isolation

## Problem

Every portal folder under `femi9/billing/` except `company/` runs its
session on the plain default cookie (`PHPSESSID`) — `company/.htaccess`
is the only one that sets `php_value session.name`. Login state is a
flat `$_SESSION['LOGIN_USER']` / `LOGIN_USER_TYPE` / `LOGIN_USER_ID` /
`LINKED_ACCOUNTS`, not namespaced per portal.

Because cookies are scoped by name (not by URL path, in this app's
setup), any two of these 11 portals logged into in the same browser —
even in separate tabs — share one session file and silently overwrite
each other:

- c-and-f, channel-partner, distributor, marketing, stockist,
  super-stockist, super_distributor, territory-partner, warehouse
  (the 9 types routed through central `login/`)
- salesbdm, track (standalone logins, each with their own
  `CheckLogin.php`, not routed through central `login/`, but still on
  `PHPSESSID`)

Symptom: log into distributor in one tab, then territory-partner in
another tab (or via the company "Login as TP" bridge) — the second
login's `activateAccountSession()` / bridge write overwrites
`LOGIN_USER_TYPE`, so the first tab's next action fails its
`LOGIN_USER_TYPE !== '<portal>'` check in `checksession.php` and bounces
to login.

`company/` doesn't have this problem — it already has its own cookie
name (`femi9_company_sess`) and its own standalone login
(`company/CheckLogin.php`), confirmed working today (that's why the
company ↔ territory-partner "Login as TP" bridge already coexists
safely with a company session).

## Goals

1. Any combination of portals can be logged in simultaneously in the
   same browser, in separate tabs, without logging each other out.
2. The existing "Switch Account" dropdown (for a mobile+password that
   matches more than one portal type) keeps working, but switching to
   another account no longer ends the session you switched away from —
   both stay valid if you still have that tab open.
3. No change to session **timing** semantics (the 5-hour inactivity
   timeout and `gc_maxlifetime` fix already shipped stay as they are).
4. No behavior change to the company↔territory-partner "Login as TP"
   admin bridge — it already does the right thing and becomes the
   template other portals copy.

## Non-goals

- Not touching `company/`'s own login (`CheckLogin.php`) or its
  session name — already correct.
- Not changing the account tables, password hashing, or
  `findMatchingAccounts()` matching logic.
- Not adding "stay logged into N accounts of the same portal type" —
  a mobile number still maps to at most one account per portal type.

## Design

### 1. Cookie isolation — 11 `.htaccess` edits

Add to each of c-and-f, channel-partner, distributor, marketing,
stockist, super-stockist, super_distributor, territory-partner,
warehouse, salesbdm, track:

```apache
<IfModule php_module>
  php_value session.name "femi9_<portal>_sess"
</IfModule>
```

using the portal's folder name with `-` replaced by `_` for the cookie
name (e.g. `channel-partner` → `femi9_channel_partner_sess`,
`super_distributor` → `femi9_super_distributor_sess`). Matches
`company/.htaccess`'s existing comment and structure exactly — same
file, prepended before any existing rewrite rules, since PHP reads
`php_value` directives regardless of order but keeping the comment at
the top matches the existing convention in `company/.htaccess`.

`territory-partner/.htaccess` already lacks one today (that's the
open half of the existing company↔TP bridge, called out in
`territory-partner/switch-login.php`'s own comments as a known gap) —
this closes it.

### 2. Shared bridge-token helper

New file `femi9/billing/shared/session-bridge.php`, following the
existing per-feature bridge pattern (`company_tp_login_bridge`,
`salesbdm_company_bridge`, `salesbdm_login_switch_bridge`) but as one
reusable table instead of one-off tables per feature, since this now
has many callers:

```sql
CREATE TABLE IF NOT EXISTS portal_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    payload TEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

`payload` is a JSON-encoded object with the same shape
`activateAccountSession()` already consumes, plus the linked-accounts
list:

```json
{
  "type": "distributor",
  "id": 123,
  "name": "...",
  "mobile": "9xxxxxxxxx",
  "linked_accounts": [ {..same shape findMatchingAccounts() returns..} ]
}
```

Two functions:

- `mintBridgeToken(array $account, array $linkedAccounts): string` —
  creates the table if missing, inserts a row with a
  `bin2hex(random_bytes(32))` token and a short expiry (2 minutes —
  matches `salesbdm_login_switch_bridge`'s existing convention for a
  same-request redirect chain, not the 30-minute admin "login as"
  bridges which cross a human decision point), returns the token.
- `consumeBridgeToken(string $token): ?array` — looks up and
  **deletes** the row in one go (matches the existing single-use
  delete-on-read pattern in `territory-partner/switch-login.php`),
  returns the decoded payload or `null` if missing/expired.

### 3. Every login write becomes a mint + redirect

Replace direct `$_SESSION[...] = ...` writes with
`mintBridgeToken()` + redirect to `<folder>/switch-login.php?token=...`
in:

- `login/authenticate.php` — single-match branch (currently calls
  `activateAccountSession($matches[0])` directly).
- `login/select-account.php` — the multi-match picker's "choose this
  one" action.
- `login/switch-account.php` — rewritten to mint a token for the
  target portal instead of overwriting `$_SESSION` in place, and to
  **not** unset anything on the source session (goal 2).
- `salesbdm/CheckLogin.php` — standalone login, currently writes
  `LOGIN_USER` directly (via `salesbdm/include/LoginHelpers.php`).
- `track/CheckLogin.php` — same.

`login/account-lib.php`'s `activateAccountSession()` stops being
called from these entry points; it's replaced by the mint step. (Left
in place only if something else still calls it — check at
implementation time; remove if dead.)

### 4. Each portal gets its own `switch-login.php` bridge consumer

`territory-partner/switch-login.php` already does exactly this shape
for the admin "Login as TP" feature — becomes the template. Each of
the 11 portals gets one, adjusted for the shared bridge helper instead
of the portal-specific bridge tables:

```php
<?php
// Consumes a portal_login_bridge token minted by login/authenticate.php,
// login/select-account.php, login/switch-account.php, or the portal's own
// standalone CheckLogin.php (salesbdm/track), and starts this portal's own
// session (femi9_<portal>_sess — see .htaccess) from it.
$_hadExistingSession = isset($_COOKIE[session_name()]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
$payload = $token ? consumeBridgeToken($token) : null;
if (!$payload || $payload['type'] !== '<this portal's central-login type key>') {
    header('Location: ../login/index.php?sessionexpiry');
    exit;
}

if ($_hadExistingSession) {
    session_regenerate_id(true);
}
$_SESSION['LOGIN_USER']       = $payload['mobile'];
$_SESSION['LOGIN_USER_ID']    = $payload['id'];
$_SESSION['LOGIN_USER_NAME']  = $payload['name'];
$_SESSION['LOGIN_USER_TYPE']  = $payload['type'];
$_SESSION['LINKED_ACCOUNTS']  = $payload['linked_accounts'];
$_SESSION['last_activity']    = time();

header('Location: dashboard.php');
exit;
```

The `$_hadExistingSession` / conditional `session_regenerate_id()`
guard is carried over verbatim — it's there specifically to avoid the
documented double-`Set-Cookie` bug already fixed once in both
`company/switch-login.php` and `territory-partner/switch-login.php`.

`territory-partner/switch-login.php` itself keeps its existing
`company_tp_login_bridge`-token branch (the admin "Login as TP"
feature, unrelated to this change) as a separate code path alongside
the new `portal_login_bridge` branch — both can coexist since they're
different token tables and TP checks `type` on the payload before
trusting it.

### 5. `LINKED_ACCOUNTS` stays in session, refreshed on bridge

No live re-query needed (per earlier decision) — the bridge payload
carries whatever `findMatchingAccounts()` produced at the moment of
the original login/switch, copied into the target portal's own
session by its `switch-login.php`. This matches today's behavior
exactly (the list is already a point-in-time snapshot re-validated
only when actually switching, via `switch-account.php`'s existing
`findAccountByMobile()` freshness check) — just relocated to flow
through the bridge instead of being read from the same `$_SESSION`.

### 6. Switch Account dropdown — unchanged UI, new backend

`app-header.php` in every portal already links to
`../login/switch-account.php?type=<type>` — no markup change needed.
Only `login/switch-account.php`'s internals change (mint + redirect
instead of overwrite), per section 3.

## Error handling

- Expired/missing/reused bridge token → same fallback every existing
  bridge consumer already uses: redirect to
  `login/index.php?sessionexpiry`.
- Payload `type` mismatch (defensive — shouldn't happen since each
  portal's `switch-login.php` is only ever linked to with its own
  type) → same fallback.
- Account deactivated between mint and consume → not re-checked at
  consume time for the primary login path (mirrors today: `authenticate.php`
  already checked `active` via `findMatchingAccounts()` moments
  earlier). `switch-account.php`'s existing re-check
  (`findAccountByMobile` + `active`) stays, since that gap is larger
  (user-driven navigation, not an immediate redirect).

## Testing

- Manual, per user's standing preference (no automated test runs
  without asking). Verify after implementation:
  - Log into distributor in tab A, then territory-partner in tab B —
    tab A stays logged in, action in tab A succeeds.
  - Company "Login as TP" still works unaffected (separate bridge
    table, untouched).
  - A mobile+password linked to two portal types: log in, use "Switch
    Account", confirm both tabs (source portal reopened, target
    portal) stay logged in simultaneously.
  - salesbdm and track logins still work standalone and don't collide
    with each other or with any central-login portal.
