# Femi9 Billing Server — Session Handoff (2026-11-12, evening)

Repo: `D:\xampp\htdocs\Femi9-Billing-Server`, branch `jd`. User communicates in Tanglish —
reply in Tanglish. This file replaces any earlier same-named handoff file; it is a full
summary of this specific session's work, written to hand to a different Claude session so
it can continue without re-deriving context. Give this file to the other session as-is.

## Bottom line / current state
**NOTHING in this session has been committed or pushed yet.** Everything below is sitting
as uncommitted changes in the working tree. The user asked for this summary specifically
so the next session doesn't lose context before pushing — **the very next thing to do,
once the user confirms, is stage + commit + push all of it to `origin/jd`.** Do not push
without an explicit go-ahead even though the user has been asked multiple times already —
confirm once more at the start of the next session since time has passed.

Run `git status --porcelain=v1 -uall` first thing to see the exact file list before acting.

## Feature 1 — CP Invoices page: Excel export
`femi9/billing/company/manage-cp-invoices.php` — added an "Export Excel" button (green,
top-right of the "All CP Invoices" card) that passes the page's current filters
(`cp_id`, `date_from`, `date_to`, `type_filter`) through to a new export file.

**New file:** `femi9/billing/company/export-cp-invoices-xlsx.php` — same filters/sort as
the on-screen table, `requirePermission('channel_partner')`. Tested via CLI simulation,
confirmed 16 real rows exported with correct headers/amounts.

## Feature 2 — Company menu cleanup (Marketing Staff section)
`femi9/billing/company/femi_menu.php` (both role-branches — company admin + the
`$resultusertypeGET['ms']` branch):
- Removed **"Product Orders Report"** and **"No Orders Report"** links from the menu only.
  Files (`company/ms_prorders.php`, `company/ms_noorders.php`) were **NOT** hard-blocked —
  an earlier attempt added a `die()` to both files, but code review caught that they're
  also used by the Sales BDM bridge (`bdm-ms-shop-view.php`'s "Orders" link, explicitly
  allowlisted in `checksession.php`'s `$_bdmAllowedScripts`) and by
  `ms-order-invoice-add.php`'s post-save redirect — the `die()` was reverted. **If asked
  again to fully block these pages, a BDM-aware approach is needed, not a blanket die().**
- Moved **"MS Daily Visit Report"** to the bottom of that submenu list (after "Daily Assign
  Report"), per explicit user request, in both role-branches.

No code change was made for the "Target Amount" question the user asked about
(`marketing/ms-team-shop-view.php` / company's Marketing Team Shop View) — just explained:
target = every Firka's `target_amount` under the person's assigned District/Division
(filled + vacant), prorated to the date range. Source: `company/include/TeamTargetRollup.php`
doc comment, confirmed still accurate.

## Feature 3 — "Daily Work Start" confirmation chain (the big feature)
Replaces an earlier, now-scrapped "DM approves the Firka→TP assignment itself" design
(built, tested, then explicitly discarded when the user clarified what they actually
wanted — see the long back-and-forth in the transcript if curious; the final design below
is the only one that matters going forward).

### What it does, end to end
1. Company/ASM assigns a Firka→TP for a date via the existing "Daily Assign District TPs"
   (`company/tp-assign-firka-action.php` / `marketing/tp-assign-firka-action.php` —
   unchanged save logic, just now also resets the work-start chain, see below).
2. **DM reminders** (`marketing/dashboard.php`'s login popup):
   - Today's assigned Firka(s) — pre-existing, unchanged.
   - **New:** once it's past 4:30pm, a "tomorrow's assignment" reminder, shown **only once
     per fresh login** (a session flag `dm_tomorrow_reminder_date` is set the moment it's
     rendered; surviving page reloads in the same session suppress it; logging out —
     `session_destroy()` — and back in clears the flag and shows it again). Confirmed this
     works correctly only across **separate PHP processes** (matching a real
     logout→new-login HTTP request pair) — doing `session_destroy()` then `session_start()`
     again in the *same* request/process does NOT reliably reset it (a PHP session-API
     quirk, not a bug in the feature) — don't test it that way again, it gives false
     negatives.
   - **New:** a "Daily Work Start (N need action)" button, shown whenever ≥1 of today's
     Firkas still need the DM to act (status `none` or `denied` with no company escalation
     already pending).
3. **DM's "Daily Work Start" page** (`marketing/daily-work-start.php`, new) — lists today's
   Firka/TP rows in the DM's own assigned scope, each with a status-dependent action:
   - `none` + has a TP → **"TP Approve"** button (sends the TP a work-start request) and/or
     **"Ask ASM/Company"** (skip the TP, escalate straight to company — auto-note "TP not
     available").
   - `none` + Vacant (no TP) → only **"Ask ASM/Company"** (auto-note "Vacancy Area").
   - `requested` → "Waiting for TP to respond" (no action).
   - `denied` → shows the TP's reason + **"Ask ASM/Company"** button.
   - `accepted` / company-resolved → "Working today" / "Resolved by Company" badge.
   - Action endpoint: `marketing/daily-work-start-action.php`.
4. **TP side — the actual gate.** `territory-partner/checksession.php` (modified, every TP
   page includes this) — after the existing Agreement-sign gate, checks for any
   `tp_daily_assignments` row for *today*, *this TP*, with `tp_approval_status` IN
   (`requested`, `denied`) — if found, redirects to `work-start-confirm.php` and **nothing
   else on the whole portal is reachable** until resolved (allowlist: that page + its
   action endpoint + the pre-existing agreement/change-password/logout pages). Wrapped in
   try/catch + a `SHOW TABLES`/`SHOW COLUMNS` existence check so it **fails open** (never
   blocks anyone) if the table/columns are ever missing on some environment.
   - `territory-partner/work-start-confirm.php` (new) — a bare, no-chrome page (same style
     as the existing Agreement gate), lists every blocking Firka for that TP today:
     - `requested` rows → "I'm starting work" / "Can't work today" (deny requires a reason,
       modal has no backdrop-dismiss).
     - `denied` rows (already decided, still blocking pending company) → an informational
       "waiting for ASM/Company" message, **no buttons** — this distinction matters: I
       initially had this page only query `requested` rows, which meant a `denied` TP saw
       an *empty* confirm page and got redirected to `dashboard.php`, which the gate then
       immediately bounced back to `work-start-confirm.php` — an **infinite redirect loop**,
       caught in review before it shipped. Fixed by having
       `fetchTpPendingWorkStartRequests()` return both statuses and the page render them
       differently. A manual "Logout" link was added to this page too (it's otherwise a
       dead end with no nav).
   - `territory-partner/work-start-confirm-action.php` (new) — Accept/Deny, scope-checked
     to `territory_partner_id = (the logged-in TP's own id)` + `assign_date = CURDATE()` +
     `tp_approval_status = 'requested'` (so it can't be replayed after the fact).
   - **New, non-blocking, dismissable** "Tomorrow's Area" popup on
     `territory-partner/dashboard.php` — same 4:30pm / once-per-login pattern as the DM's
     own tomorrow reminder (separate session key `tp_tomorrow_reminder_date`). Purely
     informational, no Accept/Deny — that only applies once it's actually "today".
5. **ASM/Company resolve page** — `company/work-start-requests.php` (org-wide) and
   `marketing/work-start-requests.php` (ASM, scoped to their own subtree via
   `getMsSubtreeIds()`). Lists every `company_request_status='pending'` row with the
   TP's denial reason (or the auto-note for a direct Vacant/TP-not-available escalation),
   a TP-select dropdown (default = currently assigned TP, or "Vacant Area"), and
   "Approve & Unlock TP for Today" — sets `territory_partner_id` (possibly a *different*
   TP than before), `tp_approval_status='accepted'` directly (bypasses needing that TP to
   confirm again — company already vouched), `company_request_status='resolved'`.
   - Action endpoints: `company/work-start-resolve-action.php` (no extra scope check beyond
     `requirePermission('ms_edit')` — company sees everything) and
     `marketing/work-start-resolve-action.php` (ASM — re-derives their subtree's districts
     server-side before touching anything, **and** explicitly rejects a plain DM with no
     subordinates from calling this at all — see bug fix #1 below).

### DB — `tp_daily_assignments` table, 9 new columns (self-migrating)
`shared/TpWorkStart.php` → `tpWorkStartEnsureColumns()`:
```
tp_approval_status     ENUM('none','requested','accepted','denied') DEFAULT 'none'
tp_denial_reason       TEXT NULL
tp_requested_at        DATETIME NULL
tp_responded_at        DATETIME NULL
company_request_status ENUM('none','pending','resolved') DEFAULT 'none'
company_request_note   TEXT NULL
company_requested_at   DATETIME NULL
company_resolved_by    VARCHAR(100) NULL
company_resolved_at    DATETIME NULL
```
(An earlier, now-abandoned design added 4 differently-named columns —
`dm_approval_status`/`dm_denial_reason`/`dm_responded_by`/`dm_responded_at` — for the
scrapped "DM approves the assignment" feature. Those were never pushed anywhere; if you
see any stray reference to them anywhere, it's leftover from that discarded design and
should be removed, not resurrected.)

`shared/TpWorkStart.php` also has `tpDailyAssignSave()` — the **shared** save helper both
`company/tp-assign-firka-action.php` and `marketing/tp-assign-firka-action.php` now call
instead of inlining the INSERT themselves (de-duplicated during review). Important
behavior: it only resets the work-start chain back to `none` when the TP **actually
changes** (or it's a brand-new row) — re-saving the *same* TP for the *same* (date, Firka)
leaves an already-`accepted`/`requested` status untouched (verified: this was a real bug
in the first draft — every save, even a no-op re-save, was wiping an already-confirmed
TP's status, which would have spuriously re-locked their portal).

### Menu links added
- `company/femi_menu.php` (both branches): "Work Start Requests" (replacing the earlier,
  now-scrapped "Daily Assign Approvals" link name).
- `marketing/femi_menu.php`: "Daily Work Start" (visible to every marketing staff member,
  not gated) + "Work Start Requests" (gated behind `$hasTeamMN`, same flag "My Team"
  already uses).

### Bugs found by code review (2 rounds, high effort) and fixed — all re-verified after fixing
1. **Security** — a plain DM (no subordinates) could escalate their own Firka via
   "Ask ASM/Company", then call `marketing/work-start-resolve-action.php` directly and
   resolve/approve it themselves — `getMsSubtreeIds()` always includes the caller, so the
   scope check alone didn't stop this. Fixed: that endpoint now rejects any caller whose
   subtree is just themselves (`count($subtreeIds) <= 1`) before doing anything else.
2. **Correctness** — every Firka save (even an unchanged re-save) unconditionally reset
   the work-start chain to `none`. Fixed via `tpDailyAssignSave()`'s unchanged-TP check
   (see above).
3. **Correctness** — "Ask ASM/Company" didn't verify server-side that the row was actually
   in `none`/`denied` before escalating — a crafted request could escalate a `requested`
   row (TP hasn't even had a chance to respond yet). Fixed with an explicit status check in
   `daily-work-start-action.php`.
4. **Infinite redirect loop** (see work-start-confirm.php section above) — fixed before it
   ever reached testing against a real TP, caught in review.
5. A race condition on Accept/Deny (stale page re-submitting after the row changed
   underneath it) — both `work-start-confirm-action.php` and the resolve endpoints guard
   their UPDATEs with a `WHERE ... status = 'expected-current-status'` + check
   `affected_rows`, returning a "please refresh" error instead of silently corrupting state.

### Full manual re-test done at the end of this session (all passed)
- Order→TP / Order→CP / Order→Company (see Feature 4) all save and display correctly.
- Full TP login-gate cycle, twice, with two different TPs:
  assign → not blocked (status `none`) → DM sends TP Approve → **blocked** → TP Accepts →
  **unblocked**. And: assign → DM approve → **blocked** → TP Denies with reason → **still
  blocked** → DM escalates → Company resolves (re-confirms the same TP) → **unblocked**.
- DM-self-approve correctly rejected (403).
- ASM correctly can't see/resolve a request outside their own subtree's districts; company
  (unscoped) can see everything.
All test rows were cleaned out of the local DB afterward — nothing test-related should be
left in `tp_daily_assignments` locally, but it's worth a quick `SELECT * FROM
tp_daily_assignments WHERE id > <whatever the highest real id was before this session>` if
anything looks odd.

## Feature 4 — Manage Orders (Product Orders): Excel export + WhatsApp Share
`marketing/manage_order_product.php` — the small Excel icon link now points at a **new**
proper `.xlsx` export (previous one was a raw `.csv` file wearing an Excel icon) and a new
green WhatsApp "Share" icon next to it, using the exact same
fetch-the-export-as-a-blob-then-`navigator.share({files:[...]})` pattern already
established in `company/tp-invoice-print.php` (falls back to a plain download + "attach it
manually" alert on desktop browsers that don't support file sharing).

**New file:** `marketing/export-manage-orders-xlsx.php` — same date-range/team-mode/
`view_ms_id(s)` scoping as the page itself. Real-data-tested, 81 rows for a 5-day range.

**Performance note — already fixed, don't redo:** the first draft used
`$sheet->getColumnDimension(...)->setAutoSize(true)` across ~56 columns (one per product),
which measured at **1.8s** for a full month export. Replaced with a fixed-width map
(`$fixedWidths`), dropping it to **1.0s**. If any other export in this codebase feels slow,
`setAutoSize()` on many columns is a good first suspect.

**Also used `setCellValueByColumnAndRow()` in the first draft, which doesn't exist in this
installed PhpSpreadsheet version** (silent fatal, zero output, very hard to debug — see
`Coordinate::stringFromColumnIndex($idx) . $row` pattern used everywhere else in this
codebase's exports instead; already fixed here, just noting it so nobody reintroduces it
elsewhere).

## Feature 5 — Vacant Area orders: Assign to Channel Partner / Company
`marketing/add_order.php` (Get Order form) — when the DM picks a Firka with **no TP
covering it**, the form now shows an **"Assign To CP"** dropdown (district-filtered
Channel Partners, same free-text district-match convention the existing TP dropdown
already used) instead of letting the DM manually pick *any* TP from the whole district
(that old fallback behavior is gone for the vacant case specifically — the normal
auto-detect-and-lock-TP behavior for a Firka that *does* have a TP is unchanged). **"Company"
is always the first/default option** in that dropdown, so there's never a dead end even if
no CP's district text matches.

**Explicitly confirmed by the user, do not build further than this without asking again:**
this is a **record-only** attribution, not a real fulfillment handoff. The order is still
invoiced and stock-deducted by the company exactly as before — there is no CP-side order
pipeline (the Channel Partner module is a wholesale/stock-sales thing, structurally
unrelated to how TPs fulfill shop Get Orders; building a real CP fulfillment bridge was
explicitly declined as out of scope).

**New column:** `ms_orders.assigned_cp_id` (self-migrating, nullable int) — populated by
`marketing/order_action_get.php`, validated against active `channel_partners`. Shown as a
new "Assigned CP" column in `manage_order_product.php`'s table and in
`export-manage-orders-xlsx.php` (shows `—` for a real TP order, the CP's name, or
"Company"). All three paths (TP / CP / Company) tested end-to-end against real DB rows this
session and confirmed correct in both the table and the Excel export.

## Testing pattern used throughout (reuse this — still the only way to test here)
No PHP built-in server, no direct HTTP access to the live site. Real end-to-end testing is
simulating an authenticated session and `include`-ing the target file via a CLI scratchpad
script:
```php
session_id('sometest1');
session_start(); // or session_name('femi9_<portal>_sess') first for company/territory-partner
$_SESSION['LOGIN_USER'] = '<mobile or username>';
$_SESSION['LOGIN_USER_TYPE'] = 'company' | 'marketing' | 'territory_partner';
$_SESSION['LOGIN_USER_ID'] = <id>;      // IMPORTANT — see gotcha below
$_SESSION['LOGIN_USER_NAME'] = '<name>'; // IMPORTANT — see gotcha below
session_write_close();
```
then, in a second script, `session_id('sometest1');` (no `session_start()` — let the
target's own `checksession.php` do that) and `include $targetFile;`.

**Gotchas hit and worked around this session (all now-known, don't rediscover them):**
- **Marketing's `checksession.php` only back-fills `LOGIN_USER_ID`/`LOGIN_USER_NAME` into
  `$_SESSION` *if not already set* — but it reads them into local PHP variables
  (`$Login_user_IDvl` etc.) *before* that back-fill runs.** If your test session doesn't
  already have `LOGIN_USER_ID` set from the start, `$Login_user_IDvl` ends up empty for
  that whole request even though `$_SESSION['LOGIN_USER_ID']` gets fixed up for *next*
  time. Always set `LOGIN_USER_ID` and `LOGIN_USER_NAME` explicitly up front in test
  sessions, matching what a real second-request login would already have.
- **`$_REQUEST` is not automatically populated from a manually-set `$_GET`/`$_POST` in a
  CLI test harness** — PHP only auto-builds `$_REQUEST` from the real request at process
  start. If the target script reads `$_REQUEST[...]`, set `$_REQUEST` explicitly too (or
  set both `$_GET`/`$_POST` *and* `$_REQUEST` to the same array).
- **A script that reads `$_SERVER['PHP_SELF']` for an allowlist check** (e.g.
  `territory-partner/checksession.php`'s Agreement/work-start gates) **will use your own
  test-harness filename** unless you explicitly set `$_SERVER['PHP_SELF']` /
  `$_SERVER['SCRIPT_NAME']` to the real target filename first — otherwise it looks like an
  unrecognized page and gets redirected away, which can look exactly like a mystery bug.
- **`error_reporting(0)` near the top of almost every action endpoint in this codebase
  means a genuine fatal error in it produces ZERO visible output** (not even with
  `ini_set('display_errors','1')` set *before* the `include` in your harness — the
  target's own `error_reporting(0)` line overrides it once execution reaches it). To debug
  a silently-dying script: temporarily insert `die("CHECKPOINT-X");` at successive points
  (binary search) — `die()` with a string always prints regardless of
  `error_reporting`/`display_errors`. Always restore from a `cp file file.bak` you took
  right before, never leave a stray checkpoint in.
- **A "session expired" result after a long gap is a real 5-hour inactivity timeout**, not
  a bug — just recreate the test session (phase-1 script) again if a test session you set
  up more than ~a few hours of wall-clock time ago (not script time — real elapsed time,
  since `last_activity` is a real timestamp) stops working.
- The project's `php` on PATH is actually a **MAMP** install
  (`D:\MAMP\bin\php\php8.2.14`), not XAMPP's own `D:\xampp\php\php.exe` — they have
  **separate `session.save_path`** (MAMP's CLI default vs. XAMPP's configured
  `D:\xampp\tmp`). A real end-to-end test via `curl` against the actual local Apache
  (`http://localhost/Femi9-Billing-Server/...`) using a session file written by the CLI
  `php` will **not** be picked up by Apache unless you explicitly
  `session_save_path('D:\\xampp\\tmp')` to match XAMPP's php.ini before `session_start()`
  in the writer script — tried this, got the path right, but still couldn't get a
  CLI-written session recognized by the real Apache/XAMPP stack in the time available; gave
  up on true HTTP-level testing and fell back to the CLI-include method for everything,
  which is what this whole project has used all along anyway. Not worth re-attempting
  unless there's a specific reason CLI-include testing isn't sufficient.

## Conventions to keep following (unchanged from before, still true)
- **Self-migrating schema**: new columns added by each consuming file itself
  (`SHOW COLUMNS ... LIKE` then `ALTER TABLE ... ADD COLUMN`), not a one-time migration
  script — except genuinely new tables, which get a one-time `.sql` file run manually once.
- **Credential handling**: never `grep`/`cat` DB credentials out of `config.php`. Write a
  standalone scratchpad PHP script that `require_once`s `femi9/billing/shared/env-loader.php`
  and connects via `mysqli_connect($_ENV['DB_HOST'], ...)`.
- **No live DB access** — local XAMPP DB only.
- Confirm which folder is actually live before editing — has flipped before.
- After any DB reimport, check indexes on `invoice`/`ms_orders`/`user_invoice` before
  assuming a code bug. (This session hit exactly this on `tp_daily_assignments` locally —
  its `id` column had lost its `PRIMARY KEY`/`AUTO_INCREMENT` on the local DB, almost
  certainly from an earlier reimport; fixed locally with `UPDATE ... SET id = (@n:=@n+1)
  ... ; ALTER TABLE ... MODIFY id INT UNSIGNED AUTO_INCREMENT, ADD PRIMARY KEY (id);` — this
  was a **local-only** environment issue, not a bug in any code this session touched, but
  worth a `SHOW CREATE TABLE tp_daily_assignments;` check on the **live** DB too, since if
  it's missing there as well, the Accept/Deny/resolve `WHERE id = ?` updates could affect
  multiple rows instead of one.
- **Never `git push` without asking first, even after committing locally.**
- **Fix District feature is done** — `add-district-note.php`'s district-pinning toggle —
  don't suggest changes unasked.

## Still open / unresolved — needs user input, do not assume
- **Nothing in this session is committed or pushed.** This is the main thing to resolve
  first in the next session — ask whether to commit+push now.
- Whether to build a BDM-aware hard-block for `ms_prorders.php`/`ms_noorders.php` (see
  Feature 2) was never re-asked after the `die()` revert — only flagged.
- No open questions were left hanging from Features 3/4/5 — all explicit design questions
  the user raised mid-session (CP fulfillment scope, stock-deduction source, work-start
  gating granularity) were asked and answered via `AskUserQuestion` before building.
