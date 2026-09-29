# Per-Portal Session Isolation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give each of the 11 non-company portals its own session cookie so logging into two portals in the same browser (different tabs) no longer logs either out, while keeping the "Switch Account" dropdown working without ending the source session.

**Architecture:** Each portal gets `php_value session.name "femi9_<portal>_sess"` in its `.htaccess` (matching `company/.htaccess`, which already has this). All places that currently write `$_SESSION['LOGIN_USER']` etc. directly and redirect cross-folder (central `login/authenticate.php`, `login/select-account.php`, `login/switch-account.php`, `salesbdm/CheckLogin.php`, `track/CheckLogin.php`) instead mint a short-lived, single-use DB-backed bridge token carrying the account payload, and redirect to the target portal's own `switch-login.php`, which consumes the token and populates that portal's own session. This is the exact pattern `territory-partner/switch-login.php` already uses for the company "Login as TP" admin feature — that file becomes the template, and its existing TP-admin-bridge code path is left untouched alongside the new one.

**Tech Stack:** PHP 8.2, MySQLi, Apache `.htaccess` `php_value`, no test framework in this codebase — verification is manual (`php -l` for syntax, curl/browser walkthroughs for behavior), matching how the rest of this codebase is verified.

**Spec:** `docs/superpowers/specs/2026-09-28-per-portal-session-isolation-design.md`

## Global Constraints

- Cookie name per portal: `femi9_<portal>_sess`, portal folder name with `-` replaced by `_` (e.g. `channel-partner` → `femi9_channel_partner_sess`, `super_distributor` → `femi9_super_distributor_sess`).
- Bridge table name: `portal_login_bridge` (token VARCHAR(64) PK, payload TEXT, expires_at TIMESTAMP, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP).
- Bridge token expiry: 2 minutes (matches `salesbdm_login_switch_bridge`'s existing convention for same-request redirect chains).
- Bridge token is single-use: read-and-delete in one operation, matching `territory-partner/switch-login.php`'s existing pattern.
- `company/` is out of scope — already isolated and correct. Do not touch `company/.htaccess`, `company/CheckLogin.php`, `company/switch-login.php`, or the `company_tp_login_bridge` table.
- `territory-partner/switch-login.php`'s existing `company_tp_login_bridge` branch (admin "Login as TP" feature) must keep working unmodified — the new `portal_login_bridge` branch is added alongside it, not instead of it.
- No automated test run without asking the user first (standing project rule) — every task's verification step is manual (`php -l`, curl, or a described browser action), never an automated suite invocation.
- Never commit until a task's manual verification step has been described and, where the plan says to actually run something (`php -l`, curl), that command has been run and shown to pass.

---

## Task 1: Shared bridge-token helper

**Files:**
- Create: `femi9/billing/shared/session-bridge.php`

**Interfaces:**
- Consumes: nothing from other tasks (this is the foundation).
- Produces:
  - `mintBridgeToken(mysqli $db_conn, array $account, array $linkedAccounts): string` — returns a 64-char hex token.
  - `consumeBridgeToken(mysqli $db_conn, string $token): ?array` — returns the decoded payload array (`type`, `id`, `name`, `mobile`, `linked_accounts`) or `null`.

`$account` shape (matches `findMatchingAccounts()`'s per-match entries, already used by `activateAccountSession()` today):
```php
['type' => string, 'id' => int|string, 'name' => string, 'mobile' => string]
```
`$linkedAccounts` is the full array `findMatchingAccounts()` returns (list of the same shape, one per linked type).

- [ ] **Step 1: Write the file**

```php
<?php
// Shared cross-portal login handoff. Every portal except company/ has its
// own session cookie (see each portal's .htaccess — femi9_<portal>_sess),
// so a login resolved on one URL (central login/, or salesbdm/track's own
// standalone CheckLogin.php) can't write directly into $_SESSION and expect
// the target portal to see it. This bridge carries the resolved account
// (and, for the "Switch Account" dropdown, the full linked-accounts list)
// across that cookie boundary via a single-use, short-lived DB token —
// same shape as the existing company_tp_login_bridge /
// salesbdm_login_switch_bridge tables, generalized into one reusable table
// since this now has many callers instead of one admin feature.

function ensurePortalLoginBridgeTable(mysqli $db_conn): void
{
    $db_conn->query("CREATE TABLE IF NOT EXISTS portal_login_bridge (
        token VARCHAR(64) PRIMARY KEY,
        payload TEXT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function mintBridgeToken(mysqli $db_conn, array $account, array $linkedAccounts): string
{
    ensurePortalLoginBridgeTable($db_conn);

    $payload = json_encode([
        'type'            => $account['type'],
        'id'              => $account['id'],
        'name'            => $account['name'],
        'mobile'          => $account['mobile'],
        'linked_accounts' => $linkedAccounts,
    ]);

    $token = bin2hex(random_bytes(32));
    $ins = $db_conn->prepare(
        "INSERT INTO portal_login_bridge (token, payload, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))"
    );
    $ins->bind_param('ss', $token, $payload);
    $ins->execute();
    $ins->close();

    return $token;
}

function consumeBridgeToken(mysqli $db_conn, string $token): ?array
{
    ensurePortalLoginBridgeTable($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT payload FROM portal_login_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Single-use — delete on read regardless of outcome, so the same link
    // can never be replayed even if it was still within its 2-minute window.
    $del = $db_conn->prepare("DELETE FROM portal_login_bridge WHERE token = ?");
    $del->bind_param('s', $token);
    $del->execute();
    $del->close();

    if (!$row) {
        return null;
    }

    $payload = json_decode($row['payload'], true);
    return is_array($payload) ? $payload : null;
}
?>
```

- [ ] **Step 2: Syntax check**

Run: `php -l "femi9/billing/shared/session-bridge.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add femi9/billing/shared/session-bridge.php
git commit -m "Add shared bridge-token helper for cross-portal login handoff"
```

---

## Task 2: Cookie isolation — 11 `.htaccess` edits

**Files:**
- Modify: `femi9/billing/c-and-f/.htaccess`
- Modify: `femi9/billing/channel-partner/.htaccess`
- Modify: `femi9/billing/distributor/.htaccess`
- Modify: `femi9/billing/marketing/.htaccess`
- Modify: `femi9/billing/stockist/.htaccess`
- Modify: `femi9/billing/super-stockist/.htaccess`
- Modify: `femi9/billing/super_distributor/.htaccess`
- Modify: `femi9/billing/territory-partner/.htaccess`
- Modify: `femi9/billing/warehouse/.htaccess`
- Modify: `femi9/billing/salesbdm/.htaccess`
- Modify: `femi9/billing/track/.htaccess`

**Interfaces:**
- Consumes: nothing.
- Produces: each portal now runs on cookie name `femi9_<portal>_sess` — every later task (2's `switch-login.php` files, `checksession.php`, `logout.php`) relies on `session_name()` picking this up automatically once `session_start()` runs in that folder, no code change needed in those files for the cookie name itself.

Cookie name mapping (folder → cookie name):
- `c-and-f` → `femi9_c_and_f_sess`
- `channel-partner` → `femi9_channel_partner_sess`
- `distributor` → `femi9_distributor_sess`
- `marketing` → `femi9_marketing_sess`
- `stockist` → `femi9_stockist_sess`
- `super-stockist` → `femi9_super_stockist_sess`
- `super_distributor` → `femi9_super_distributor_sess`
- `territory-partner` → `femi9_territory_partner_sess`
- `warehouse` → `femi9_warehouse_sess`
- `salesbdm` → `femi9_salesbdm_sess`
- `track` → `femi9_track_sess`

- [ ] **Step 1: Read each portal's current `.htaccess`**

Run: `for f in c-and-f channel-partner distributor marketing stockist super-stockist super_distributor territory-partner warehouse salesbdm track; do echo "=== $f ==="; cat "femi9/billing/$f/.htaccess"; done`

Confirm none already has a `session.name` line (expected — only `company/` has one today).

- [ ] **Step 2: Prepend the session-name block to each file**

For each portal, add this block at the very top of its `.htaccess` (before any existing `RewriteEngine`/other directives), using that portal's cookie name from the mapping above:

```apache
# Give this module its own session cookie so it doesn't share a session
# file (and its file lock) with other billing modules open in other tabs
# on the same domain.
<IfModule php_module>
  php_value session.name "femi9_<portal>_sess"
</IfModule>

```

Example for `distributor/.htaccess` (apply the same shape to all 11, substituting the cookie name):

```apache
# Give this module its own session cookie so it doesn't share a session
# file (and its file lock) with other billing modules open in other tabs
# on the same domain.
<IfModule php_module>
  php_value session.name "femi9_distributor_sess"
</IfModule>

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^([^\.]+)$ $1.php [NC,L]
```

Use the Edit tool on each of the 11 files, inserting the block before the first existing line (`RewriteEngine On` in most of them — check each file's actual first line from Step 1 first, since some may differ).

- [ ] **Step 3: Verify each file**

Run: `for f in c-and-f channel-partner distributor marketing stockist super-stockist super_distributor territory-partner warehouse salesbdm track; do echo "=== $f ==="; grep -A2 "session.name" "femi9/billing/$f/.htaccess"; done`

Expected: each portal prints its own correct `php_value session.name "femi9_<portal>_sess"` line, matching the mapping table above exactly (no copy-paste mismatches).

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/c-and-f/.htaccess femi9/billing/channel-partner/.htaccess femi9/billing/distributor/.htaccess femi9/billing/marketing/.htaccess femi9/billing/stockist/.htaccess femi9/billing/super-stockist/.htaccess femi9/billing/super_distributor/.htaccess femi9/billing/territory-partner/.htaccess femi9/billing/warehouse/.htaccess femi9/billing/salesbdm/.htaccess femi9/billing/track/.htaccess
git commit -m "Give each portal its own session cookie name, isolating sessions across portals"
```

---

## Task 3: Per-portal `switch-login.php` bridge consumers (9 central-login portals)

**Files:**
- Create: `femi9/billing/c-and-f/switch-login.php`
- Create: `femi9/billing/channel-partner/switch-login.php`
- Create: `femi9/billing/distributor/switch-login.php`
- Create: `femi9/billing/marketing/switch-login.php`
- Create: `femi9/billing/stockist/switch-login.php`
- Create: `femi9/billing/super-stockist/switch-login.php`
- Create: `femi9/billing/super_distributor/switch-login.php`
- Create: `femi9/billing/warehouse/switch-login.php`
- Modify: `femi9/billing/territory-partner/switch-login.php` (already exists for the admin "Login as TP" bridge — add the new branch alongside it, don't replace it)

**Interfaces:**
- Consumes: `consumeBridgeToken(mysqli $db_conn, string $token): ?array` from Task 1 (`shared/session-bridge.php`).
- Produces: each file redirects to `dashboard.php` inside its own folder with a populated `$_SESSION['LOGIN_USER']`, `LOGIN_USER_ID`, `LOGIN_USER_NAME`, `LOGIN_USER_TYPE`, `LINKED_ACCOUNTS`, `last_activity` — the exact shape `checksession.php` in that same folder already expects (no changes needed to any `checksession.php` for this).

Portal type keys (must match the payload's `type` field exactly, per `shared/user-config.php`'s `getUserConfig()` array keys):
- `c-and-f` → `candf`
- `channel-partner` → `channel_partner`
- `distributor` → `distributor`
- `marketing` → `marketing`
- `stockist` → `stockiest`
- `super-stockist` → `super_stockiest`
- `super_distributor` → `super_distributor`
- `territory-partner` → `territory_partner`
- `warehouse` → `warehouse`

- [ ] **Step 1: Create the 8 new `switch-login.php` files**

For each of c-and-f, channel-partner, distributor, marketing, stockist, super-stockist, super_distributor, warehouse — create `femi9/billing/<folder>/switch-login.php` with this content, substituting `<TYPE_KEY>` from the mapping above:

```php
<?php
// Landing point for the central login handoff (login/authenticate.php,
// login/select-account.php, login/switch-account.php) — consumes a
// single-use portal_login_bridge token and starts this portal's own
// session (femi9_<portal>_sess — see .htaccess) from it. Needed because
// this portal has its own session cookie name, separate from central
// login/'s PHPSESSID, so login/ can't write $_SESSION here directly.
//
// Whether this portal's own cookie already existed BEFORE this request's
// session_start() determines whether session_regenerate_id() below is
// needed — calling it unconditionally sends a second Set-Cookie for the
// same name in the same response, and which one the browser keeps isn't
// guaranteed. Same fix already applied in company/switch-login.php and
// territory-partner/switch-login.php.
$_hadExistingSession = isset($_COOKIE[session_name() ?: 'PHPSESSID']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
$payload = $token ? consumeBridgeToken($db_conn, $token) : null;

if (!$payload || ($payload['type'] ?? '') !== '<TYPE_KEY>') {
    header('Location: ../login/index.php?sessionexpiry');
    exit;
}

if ($_hadExistingSession) {
    session_regenerate_id(true);
}
$_SESSION['LOGIN_USER']      = $payload['mobile'];
$_SESSION['LOGIN_USER_ID']   = $payload['id'];
$_SESSION['LOGIN_USER_NAME'] = $payload['name'];
$_SESSION['LOGIN_USER_TYPE'] = $payload['type'];
$_SESSION['LINKED_ACCOUNTS'] = $payload['linked_accounts'] ?? [];
$_SESSION['last_activity']   = time();

header('Location: dashboard.php');
exit;
```

- [ ] **Step 2: Add the new branch to `territory-partner/switch-login.php`**

Read the existing file first (`femi9/billing/territory-partner/switch-login.php`) to confirm its current shape before editing — it must keep its existing `company_tp_login_bridge` token branch (the admin "Login as TP" feature) fully intact. Add a new, separate branch: if `$_GET['token']` doesn't resolve against `company_tp_login_bridge`, try `portal_login_bridge` via `consumeBridgeToken()` before giving up. Structure:

```php
<?php
// Landing point for BOTH:
// 1. company/login-as-tp.php's admin "Login as TP" bridge
//    (company_tp_login_bridge table) — existing, unchanged.
// 2. The central login handoff (login/authenticate.php,
//    login/select-account.php, login/switch-account.php) via the shared
//    portal_login_bridge table — new, added here.
//
// Whether a PHPSESSID cookie already existed BEFORE this request's
// session_start() — determines whether session_regenerate_id() below is
// needed at all. Calling it unconditionally is a real bug (confirmed
// 2026-09-23): on the common case (no prior TP session in this browser),
// session_start() already issues a fresh, unguessable id and its own
// Set-Cookie; calling session_regenerate_id() right after then sends a
// SECOND, different Set-Cookie for the SAME cookie name in the same
// response. Whichever one a browser keeps is not guaranteed — if it keeps
// the first (pre-regenerate) one, this session's own data (written below,
// after the regenerate) lives under the id the browser never kept, so the
// very next request finds no session at all and gets bounced straight to
// "session failed" — a login that looks like it logs you right back out.
// Same root cause and fix as company/switch-login.php's own documented
// history of this exact bug.
$_hadExistingTpSession = isset($_COOKIE[session_name() ?: 'PHPSESSID']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: ../login/index.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_tp_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    tp_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $db_conn->prepare("SELECT tp_id FROM company_tp_login_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Single-use — delete on read regardless of outcome, so the same link can
// never be replayed even if it was still within its 2-minute window.
$del = $db_conn->prepare("DELETE FROM company_tp_login_bridge WHERE token = ?");
$del->bind_param('s', $token);
$del->execute();
$del->close();

if ($row) {
    $stmt = $db_conn->prepare("SELECT id, name, mobile, is_active FROM territory_partners WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param('i', $row['tp_id']);
    $stmt->execute();
    $tp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tp || !$tp['is_active']) {
        header('Location: ../login/index.php?sessionexpiry');
        exit;
    }

    if ($_hadExistingTpSession) {
        session_regenerate_id(true);
    }
    $_SESSION['LOGIN_USER']      = $tp['mobile'];
    $_SESSION['LOGIN_USER_ID']   = $tp['id'];
    $_SESSION['LOGIN_USER_NAME'] = $tp['name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'territory_partner';
    $_SESSION['last_activity']   = time();
    // Marks this session as company-initiated so the header can offer a way
    // back — the company session itself was never touched (different cookie
    // name, see company/login-as-tp.php), this just points the header link.
    $_SESSION['LOGGED_IN_VIA_COMPANY'] = true;

    header('Location: dashboard.php');
    exit;
}

// Not a company-bridge token — try the shared central-login bridge instead.
$payload = consumeBridgeToken($db_conn, $token);
if (!$payload || ($payload['type'] ?? '') !== 'territory_partner') {
    header('Location: ../login/index.php?sessionexpiry');
    exit;
}

if ($_hadExistingTpSession) {
    session_regenerate_id(true);
}
$_SESSION['LOGIN_USER']      = $payload['mobile'];
$_SESSION['LOGIN_USER_ID']   = $payload['id'];
$_SESSION['LOGIN_USER_NAME'] = $payload['name'];
$_SESSION['LOGIN_USER_TYPE'] = $payload['type'];
$_SESSION['LINKED_ACCOUNTS'] = $payload['linked_accounts'] ?? [];
$_SESSION['last_activity']   = time();

header('Location: dashboard.php');
exit;
```

- [ ] **Step 3: Syntax check all 9 files**

Run: `for f in c-and-f channel-partner distributor marketing stockist super-stockist super_distributor territory-partner warehouse; do php -l "femi9/billing/$f/switch-login.php"; done`
Expected: `No syntax errors detected` for all 9.

- [ ] **Step 4: Verify each file's TYPE_KEY substitution is correct**

Run: `grep -H "!== '" femi9/billing/{c-and-f,channel-partner,distributor,marketing,stockist,super-stockist,super_distributor,warehouse}/switch-login.php femi9/billing/territory-partner/switch-login.php`
Expected output must match the mapping table exactly:
```
femi9/billing/c-and-f/switch-login.php: !== 'candf'
femi9/billing/channel-partner/switch-login.php: !== 'channel_partner'
femi9/billing/distributor/switch-login.php: !== 'distributor'
femi9/billing/marketing/switch-login.php: !== 'marketing'
femi9/billing/stockist/switch-login.php: !== 'stockiest'
femi9/billing/super-stockist/switch-login.php: !== 'super_stockiest'
femi9/billing/super_distributor/switch-login.php: !== 'super_distributor'
femi9/billing/warehouse/switch-login.php: !== 'warehouse'
femi9/billing/territory-partner/switch-login.php: !== 'territory_partner'
```
If any line doesn't match, fix that file before continuing.

- [ ] **Step 5: Commit**

```bash
git add femi9/billing/c-and-f/switch-login.php femi9/billing/channel-partner/switch-login.php femi9/billing/distributor/switch-login.php femi9/billing/marketing/switch-login.php femi9/billing/stockist/switch-login.php femi9/billing/super-stockist/switch-login.php femi9/billing/super_distributor/switch-login.php femi9/billing/warehouse/switch-login.php femi9/billing/territory-partner/switch-login.php
git commit -m "Add per-portal bridge-token login consumers for the 9 central-login portals"
```

---

## Task 4: `switch-login.php` for salesbdm and track (standalone logins)

**Files:**
- Create: `femi9/billing/salesbdm/switch-login-central.php`
- Create: `femi9/billing/track/switch-login.php`

**Interfaces:**
- Consumes: `consumeBridgeToken(mysqli $db_conn, string $token): ?array` from Task 1.
- Produces: same session shape as Task 3, for `salesbdm` and `track` folders.

Note: `salesbdm/switch-login.php` **already exists** — it's the existing company↔salesbdm bridge consumer (`salesbdm_login_switch_bridge` table, unrelated feature, must not be touched). This task's file is named `switch-login-central.php` to avoid colliding with it; Task 5 will point `salesbdm/CheckLogin.php` at this new file when it needs to self-bridge (see Task 5's note on why salesbdm's own login also needs a bridge hop even though it's not cross-folder in the visible URL).

Type keys: `salesbdm` (not in `getCentralLoginTypes()`, but the payload just needs a consistent internal key — use the literal string `salesbdm`) and `track` (payload type `track`).

- [ ] **Step 1: Create `salesbdm/switch-login-central.php`**

```php
<?php
// Consumes a portal_login_bridge token minted by this portal's own
// CheckLogin.php after a successful salesbdm login. Needed even though
// CheckLogin.php lives in this same folder: salesbdm/ now has its own
// session cookie (femi9_salesbdm_sess — see .htaccess), and CheckLogin.php
// runs before that cookie exists in the browser on a first-ever visit, so
// routing through one consistent mint+consume bridge (same as every other
// portal) avoids a separate direct-write code path that would silently
// diverge from how every other portal now logs in.
$_hadExistingSession = isset($_COOKIE[session_name() ?: 'PHPSESSID']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
$payload = $token ? consumeBridgeToken($db_conn, $token) : null;

if (!$payload || ($payload['type'] ?? '') !== 'salesbdm') {
    header('Location: index.php?sessionexpiry');
    exit;
}

if ($_hadExistingSession) {
    session_regenerate_id(true);
}
$_SESSION['LOGIN_USER']      = $payload['mobile'];
$_SESSION['LOGIN_USER_ID']   = $payload['id'];
$_SESSION['LOGIN_USER_NAME'] = $payload['name'];
$_SESSION['LOGIN_USER_TYPE'] = $payload['type'];
$_SESSION['LINKED_ACCOUNTS'] = $payload['linked_accounts'] ?? [];
$_SESSION['last_activity']   = time();

header('Location: dashboard.php');
exit;
```

- [ ] **Step 2: Create `track/switch-login.php`**

```php
<?php
// Consumes a portal_login_bridge token minted by track/CheckLogin.php
// after a successful track login. track/ has no cross-portal bridge or
// dual-account logic otherwise (see track/checksession.php's own
// "track ku salesbdm link aagave aagathu" note) — this bridge exists only
// to hand the resolved login off to track's own session cookie
// (femi9_track_sess — see .htaccess), same reason as salesbdm's
// switch-login-central.php above.
$_hadExistingSession = isset($_COOKIE[session_name() ?: 'PHPSESSID']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
$payload = $token ? consumeBridgeToken($db_conn, $token) : null;

if (!$payload || ($payload['type'] ?? '') !== 'track') {
    header('Location: index.php?sessionexpiry');
    exit;
}

if ($_hadExistingSession) {
    session_regenerate_id(true);
}
$_SESSION['LOGIN_USER']      = $payload['mobile'];
$_SESSION['LOGIN_USER_ID']   = $payload['id'];
$_SESSION['LOGIN_USER_NAME'] = $payload['name'];
$_SESSION['LOGIN_USER_TYPE'] = $payload['type'];
$_SESSION['last_activity']   = time();

header('Location: dashboard.php');
exit;
```

- [ ] **Step 3: Syntax check**

Run: `php -l "femi9/billing/salesbdm/switch-login-central.php" && php -l "femi9/billing/track/switch-login.php"`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/salesbdm/switch-login-central.php femi9/billing/track/switch-login.php
git commit -m "Add bridge-token login consumers for salesbdm and track standalone logins"
```

---

## Task 5: Rewire the login-write call sites to mint instead of writing directly

**Files:**
- Modify: `femi9/billing/login/authenticate.php`
- Modify: `femi9/billing/login/select-account.php`
- Modify: `femi9/billing/login/switch-account.php`
- Modify: `femi9/billing/salesbdm/include/LoginHelpers.php`
- Modify: `femi9/billing/salesbdm/CheckLogin.php`
- Modify: `femi9/billing/salesbdm/choose-login.php`
- Modify: `femi9/billing/track/CheckLogin.php`
- Modify (maybe): `femi9/billing/login/account-lib.php` (remove `activateAccountSession()` if it becomes dead code — see Step 9)

**Interfaces:**
- Consumes: `mintBridgeToken(mysqli $db_conn, array $account, array $linkedAccounts): string` from Task 1; each of the 11 `switch-login.php` files from Tasks 3–4 (as redirect targets); `getUserConfig($type)['folder']` from `shared/user-config.php` (already used today).
- Produces: `finalizeSalesBdmSession($db_conn, array $user): string` — signature change from `void` to `string` (now returns a bridge token instead of writing `$_SESSION` directly); no other new interface. This task is the last piece — after this, the full login flow works end to end.

- [ ] **Step 1: Read the current `login/authenticate.php` single-match branch**

Confirm the current code around the `activateAccountSession($matches[0])` call (already read earlier in this session — it's):

```php
    if (count($matches) === 1) {
        activateAccountSession($matches[0]);
        unset($_SESSION['PENDING_LOGIN']);
        $_SESSION['LINKED_ACCOUNTS'] = $matches;
        session_regenerate_id(true);
        header('Location: ../' . $matches[0]['folder'] . '/dashboard.php');
        exit;
    }
```

- [ ] **Step 2: Replace it with a mint + redirect to the target portal's `switch-login.php`**

```php
    if (count($matches) === 1) {
        require_once __DIR__ . '/../shared/session-bridge.php';
        $bridgeToken = mintBridgeToken($db_conn, $matches[0], $matches);
        unset($_SESSION['PENDING_LOGIN']);
        header('Location: ../' . $matches[0]['folder'] . '/switch-login.php?token=' . urlencode($bridgeToken));
        exit;
    }
```

Note: `session_regenerate_id(true)` is dropped here — it was regenerating the `login/`-scoped `PHPSESSID` session, which no longer matters once the target portal's own `switch-login.php` does its own `session_regenerate_id()` on its own cookie (Task 3/4's `$_hadExistingSession` guard already handles that correctly for the target session).

- [ ] **Step 3: Update `login/select-account.php`**

Confirmed current code (the user-picked-an-account branch):

```php
        activateAccountSession($chosen);
        unset($_SESSION['PENDING_LOGIN']);
        session_regenerate_id(true);
        header('Location: ../' . $chosen['folder'] . '/dashboard.php');
        exit;
```

Replace with:

```php
        require_once __DIR__ . '/../shared/session-bridge.php';
        $bridgeToken = mintBridgeToken($db_conn, $chosen, $accounts);
        unset($_SESSION['PENDING_LOGIN']);
        header('Location: ../' . $chosen['folder'] . '/switch-login.php?token=' . urlencode($bridgeToken));
        exit;
```

(`$accounts` is `$_SESSION['LINKED_ACCOUNTS']`, already assigned earlier in the file at `$accounts = $_SESSION['LINKED_ACCOUNTS'];` — confirmed present.)

- [ ] **Step 4: Rewrite `login/switch-account.php`**

Replace the file's tail (from the `activateAccountSession(...)` call onward) — current code:

```php
$cfg = getUserConfig($target['type']);
activateAccountSession([
    'type'   => $target['type'],
    'id'     => $fresh['id'],
    'name'   => $fresh['name'],
    'mobile' => $fresh['mobile'],
]);
session_regenerate_id(true);
header('Location: ../' . $cfg['folder'] . '/dashboard.php');
exit;
```

with:

```php
require_once __DIR__ . '/../shared/session-bridge.php';

$cfg = getUserConfig($target['type']);
$bridgeToken = mintBridgeToken($db_conn, [
    'type'   => $target['type'],
    'id'     => $fresh['id'],
    'name'   => $fresh['name'],
    'mobile' => $fresh['mobile'],
], $_SESSION['LINKED_ACCOUNTS']);
header('Location: ../' . $cfg['folder'] . '/switch-login.php?token=' . urlencode($bridgeToken));
exit;
```

This drops the direct `$_SESSION` overwrite entirely (goal: switching no longer ends the source portal's own session, since `login/switch-account.php` never touches any portal's session now — only the target portal's own `switch-login.php` does, on its own cookie).

- [ ] **Step 5: Update `salesbdm/include/LoginHelpers.php`'s `finalizeSalesBdmSession()`**

This single function is the choke point for both salesbdm login call sites (`CheckLogin.php`'s normal login branch, and `choose-login.php`'s `'salesbdm'` branch), so only this function needs the session-write change — the two callers only need their trailing redirect changed (Step 6).

Current function (confirmed by reading the file):

```php
function finalizeSalesBdmSession($db_conn, array $user): void {
    $_SESSION['LOGIN_USER'] = $user['bdm_mobile'];
    $_SESSION['LOGIN_USER_ID'] = $user['id'];
    $_SESSION['LOGIN_USER_NAME'] = $user['bdm_name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'salesbdm';
    $_SESSION['last_activity'] = time();

    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_company_bridge (
        token VARCHAR(64) PRIMARY KEY,
        bdm_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $bridgeToken = bin2hex(random_bytes(32));
    $bridgeStmt = $db_conn->prepare("INSERT INTO salesbdm_company_bridge (token, bdm_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
    $bridgeStmt->bind_param('si', $bridgeToken, $user['id']);
    $bridgeStmt->execute();
    $bridgeStmt->close();
    setcookie('femi9_bdm_bridge', $bridgeToken, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

    $updateStmt = mysqli_prepare($db_conn, "UPDATE sales_bdm_staff SET last_login = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($updateStmt, "i", $user['id']);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);
}
```

The `femi9_bdm_bridge` cookie + `salesbdm_company_bridge` table here is a **separate, unrelated** existing feature (lets a salesbdm view a narrow allowlist of TP pages inside `company/`'s own session, per `company/checksession.php`'s existing bridge logic) — do not remove or change that part. Only replace the `$_SESSION[...] = ...` block at the top with a mint call, and change the return type from `void` to `string` (the bridge token, which the caller now needs for its redirect):

```php
function finalizeSalesBdmSession($db_conn, array $user): string {
    require_once __DIR__ . '/../../shared/session-bridge.php';
    $ownBridgeToken = mintBridgeToken($db_conn, [
        'type'   => 'salesbdm',
        'id'     => $user['id'],
        'name'   => $user['bdm_name'],
        'mobile' => $user['bdm_mobile'],
    ], []);

    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_company_bridge (
        token VARCHAR(64) PRIMARY KEY,
        bdm_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $companyBridgeToken = bin2hex(random_bytes(32));
    $bridgeStmt = $db_conn->prepare("INSERT INTO salesbdm_company_bridge (token, bdm_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
    $bridgeStmt->bind_param('si', $companyBridgeToken, $user['id']);
    $bridgeStmt->execute();
    $bridgeStmt->close();
    setcookie('femi9_bdm_bridge', $companyBridgeToken, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

    $updateStmt = mysqli_prepare($db_conn, "UPDATE sales_bdm_staff SET last_login = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($updateStmt, "i", $user['id']);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    return $ownBridgeToken;
}
```

(`[]` for `linked_accounts` — salesbdm is a standalone login, not part of `getCentralLoginTypes()`, so there's no cross-type "Switch Account" list for it.)

- [ ] **Step 6: Update the two callers of `finalizeSalesBdmSession()` to use its return value**

In `femi9/billing/salesbdm/CheckLogin.php`, find:
```php
    // Login successful - Create session
    require_once __DIR__ . '/include/LoginHelpers.php';
    finalizeSalesBdmSession($db_conn, $user);
```
through:
```php
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;
```
Replace with:
```php
    // Login successful - Create session
    require_once __DIR__ . '/include/LoginHelpers.php';
    $bridgeToken = finalizeSalesBdmSession($db_conn, $user);

    // Redirect through the bridge so this portal's own session cookie
    // (femi9_salesbdm_sess) gets the login, not whatever cookie this
    // request happened to run under.
    header('Location: switch-login-central.php?token=' . urlencode($bridgeToken));
    exit;
```
(The `session_regenerate_id(true)` call is dropped here — it was regenerating whatever session this request ran under before the cookie-isolation change; the actual salesbdm session regeneration now happens inside `switch-login-central.php`, Task 4, via its own `$_hadExistingSession` guard.)

In `femi9/billing/salesbdm/choose-login.php`, find:
```php
    if ($user && $user['account_status'] === 'active') {
        finalizeSalesBdmSession($db_conn, $user);
        session_regenerate_id(true);
        header('Location: dashboard.php');
        exit;
    }
```
Replace with:
```php
    if ($user && $user['account_status'] === 'active') {
        $bridgeToken = finalizeSalesBdmSession($db_conn, $user);
        header('Location: switch-login-central.php?token=' . urlencode($bridgeToken));
        exit;
    }
```

- [ ] **Step 7: Update `track/CheckLogin.php`**

Find:
```php
    $_SESSION['LOGIN_USER'] = $user['mobile'];
    $_SESSION['LOGIN_USER_ID'] = $user['id'];
    $_SESSION['LOGIN_USER_NAME'] = $user['name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'track';
    $_SESSION['last_activity'] = time();

    $upd = $db_conn->prepare("UPDATE track_users SET last_login = NOW() WHERE id = ?");
    $upd->bind_param('i', $user['id']);
    $upd->execute();
    $upd->close();

    logLoginAttempt($mobileNumber, true, 'Login successful');

    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';
    if (file_exists($rateLimitFile)) {
        $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
        unset($rateLimits[$rateLimitKey]);
        file_put_contents($rateLimitFile, json_encode($rateLimits));
    }

    session_regenerate_id(true);
    header('Location: dashboard.php');
    exit;
```
Replace with:
```php
    $upd = $db_conn->prepare("UPDATE track_users SET last_login = NOW() WHERE id = ?");
    $upd->bind_param('i', $user['id']);
    $upd->execute();
    $upd->close();

    logLoginAttempt($mobileNumber, true, 'Login successful');

    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';
    if (file_exists($rateLimitFile)) {
        $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
        unset($rateLimits[$rateLimitKey]);
        file_put_contents($rateLimitFile, json_encode($rateLimits));
    }

    require_once __DIR__ . '/../shared/session-bridge.php';
    $bridgeToken = mintBridgeToken($db_conn, [
        'type'   => 'track',
        'id'     => $user['id'],
        'name'   => $user['name'],
        'mobile' => $user['mobile'],
    ], []);
    header('Location: switch-login.php?token=' . urlencode($bridgeToken));
    exit;
```
(`[]` for `linked_accounts` — track is standalone, per its own checksession.php's "track ku salesbdm link aagave aagathu" note, no cross-type switching.)

- [ ] **Step 8: Syntax check all modified files**

Run: `php -l femi9/billing/login/authenticate.php && php -l femi9/billing/login/select-account.php && php -l femi9/billing/login/switch-account.php && php -l femi9/billing/salesbdm/include/LoginHelpers.php && php -l femi9/billing/salesbdm/CheckLogin.php && php -l femi9/billing/salesbdm/choose-login.php && php -l femi9/billing/track/CheckLogin.php`
Expected: `No syntax errors detected` for all 7.

- [ ] **Step 9: Check whether `activateAccountSession()` in `login/account-lib.php` is now dead code**

Run: `grep -rn "activateAccountSession" femi9/billing --include="*.php"`
Expected: only the function definition itself in `account-lib.php` remains (Steps 2–4 replaced its only 3 call sites — `authenticate.php`, `select-account.php`, `switch-account.php` — with `mintBridgeToken()`). Note: `finalizeSalesBdmSession()` and the track login never called `activateAccountSession()` — they had their own separate direct `$_SESSION` writes, already handled in Steps 5–7.

If the grep shows zero remaining call sites outside the definition, remove the `activateAccountSession()` function body from `femi9/billing/login/account-lib.php` (delete the function declaration and its docblock comment). If any call site remains, leave it as-is and note which file still uses it.

- [ ] **Step 10: Syntax check `account-lib.php` if modified**

Run: `php -l femi9/billing/login/account-lib.php`
Expected: `No syntax errors detected`

- [ ] **Step 11: Commit**

```bash
git add femi9/billing/login/authenticate.php femi9/billing/login/select-account.php femi9/billing/login/switch-account.php femi9/billing/salesbdm/include/LoginHelpers.php femi9/billing/salesbdm/CheckLogin.php femi9/billing/salesbdm/choose-login.php femi9/billing/track/CheckLogin.php femi9/billing/login/account-lib.php
git commit -m "Route all portal logins through the bridge-token handoff instead of writing sessions directly"
```

---

## Task 6: Manual end-to-end verification

**Files:** none (verification only, no code changes)

**Interfaces:**
- Consumes: everything from Tasks 1–5, running against the live local MAMP server.

- [ ] **Step 1: Tell the user this task requires them to test in a real browser**

Since the user's standing rule is manual testing only (no automated test runs), and this change spans cookies/redirects that are easiest to verify visually, ask the user to walk through:

1. Open the app in a browser, log into any central-login portal (e.g. Distributor) in Tab A. Confirm dashboard loads.
2. Open Tab B, log into a different portal (e.g. Territory Partner) via the login page directly (not via company's "Login as TP").
3. Switch back to Tab A, click around (e.g. open a report page). Confirm Tab A is still logged in as Distributor — no bounce to the login page.
4. Switch to Tab B, confirm it's still logged in as Territory Partner too.
5. From either tab, if that mobile+password is linked to more than one portal type, open the "Switch Account" dropdown and switch to the other linked type. Confirm the tab you switched away from (if reopened) is still logged in.
6. From Company, use "Login as TP" (existing admin feature) in a new tab. Confirm it still works exactly as before, and that the Company tab stays logged in throughout.
7. Log into salesbdm and track (if credentials are available) and confirm each works standalone and doesn't collide with any other open portal tab.

- [ ] **Step 2: Report back any failures found**

If any step fails, do not proceed to further tasks — stop and diagnose the specific failing step before continuing (this plan has no further tasks after this one, so a failure here means returning to the relevant earlier task to fix it).

- [ ] **Step 3: No commit for this task** (verification only)
