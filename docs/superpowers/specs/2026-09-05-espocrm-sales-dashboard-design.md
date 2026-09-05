# EspoCRM Sales Dashboard — Design Spec

Date: 2026-09-05
Status: Approved for planning

## 1. Purpose

Build a real-time sales dashboard inside this billing app that surfaces
data from an externally-hosted EspoCRM instance (separate cPanel
account on AWS). Two audiences see the same metrics at different scope:

- **Sales BDM login**: each rep sees only their own metrics — no
  picker, no team toggle.
- **Company login**: sees whole-team aggregate KPIs plus a per-rep
  ("person-wise") breakdown table.

Real-time data is a hard requirement (confirmed by user) — no
scheduled sync/cache layer. Every page load queries EspoCRM's database
directly.

## 2. Metrics (each computed two ways: whole-team aggregate, and
   per-rep)

### 2.1 Funnel / Conversion
- Lead → Opportunity conversion rate, trended (weekly/monthly)
- Opportunity → Closed Won conversion rate, trended (weekly/monthly)
- Funnel stage snapshot: counts at each stage for the selected period
- Won vs. Lost split, with reasons where populated
- Average sales-cycle length (days from Lead created → Opportunity
  Closed Won)

### 2.2 Call Activity
- Calls logged per period, broken down by status (Planned / Held /
  Not Held)
- Call outcome/direction breakdown (Inbound/Outbound, or custom
  outcome field if present)
- Overdue/upcoming planned calls (due date in the past / near future)

### 2.3 Cross-cutting
- Calls-per-conversion ratio (calls logged ÷ opportunities converted,
  for the same period/rep)

All metrics accept a date range filter (default: current month).

## 3. Data access

### 3.1 Connection
Direct remote MySQL read access to the EspoCRM database (separate
AWS/cPanel account). User will open cPanel's Remote MySQL allow-list
(or an AWS security-group rule) for this billing server's IP, and
create a **read-only** MySQL user scoped to the EspoCRM database.

New file: `femi9/billing/includes/EspoDb.php`
- Holds a second `mysqli` connection, distinct from the app's own
  `$db_conn`.
- Connection parameters (host, port, db name, user, password) live in
  a new gitignored config file, e.g.
  `femi9/billing/includes/espo_db_config.php`, following the same
  pattern as the app's existing DB config. Credentials are supplied by
  the user at deploy time — code is written against the standard
  EspoCRM schema in the meantime.
- No write queries are ever issued against this connection.
- All queries against Espo tables must include `AND deleted = 0`
  (EspoCRM soft-deletes everything).

### 3.2 Relevant EspoCRM tables (standard schema, no custom fields
   assumed)
- `user` — id, first_name, last_name, email, deleted
- `lead` — id, status, created_at, assigned_user_id, deleted
- `opportunity` — id, stage, amount, close_date, created_at,
  assigned_user_id, deleted
- `call` — id, status, date_start, assigned_user_id, parent_type,
  parent_id, deleted

If the real instance has custom fields/values (e.g. custom lead
statuses, a custom call-outcome field), those will be reconciled once
credentials are available — flagged explicitly as a follow-up, not
blocking initial build.

### 3.3 Failure handling
If the connection to EspoCRM's DB fails or times out, the dashboard
pages catch this and render a "CRM data unavailable" banner in place
of the affected metrics — this must never surface as a fatal PHP error
or take down any other part of the billing app.

## 4. Rep mapping

Add column `espo_user_id VARCHAR(24) NULL DEFAULT NULL` to the
existing `sales_bdm_staff` table (via the same
`ALTER TABLE ... ADD COLUMN` pattern already used in this codebase,
e.g. `salesbdm_manage.php:145-148`).

Mapping UI: extend the existing `salesbdm_edit.php` (or
`salesbdm_manage.php`) screen with a dropdown of EspoCRM users (id +
full name, queried live from `user` via `EspoDb.php`), pre-selecting
the closest match by comparing `bdm_email` to Espo's `user.email`
where possible, but always requiring explicit admin confirmation/save
— no silent auto-mapping.

A BDM with no `espo_user_id` set sees an empty-state message on their
CRM dashboard ("Ask your admin to link your CRM account") rather than
an error.

## 5. Metrics engine

New file: `femi9/billing/includes/EspoMetrics.php` — a single shared
class/function set, each method signature shaped like:

```
getFunnelSnapshot($espoUserId, $dateFrom, $dateTo)
getConversionTrend($espoUserId, $dateFrom, $dateTo, $granularity)
getCallActivity($espoUserId, $dateFrom, $dateTo)
getCallsPerConversion($espoUserId, $dateFrom, $dateTo)
```

`$espoUserId = null` → whole-team aggregate (no `assigned_user_id`
filter). `$espoUserId = '<id>'` → filtered to that rep. This is the
single parameter that drives both login views — one query path, not
duplicated logic per audience.

## 6. Pages

### 6.1 `femi9/billing/salesbdm/dashboard-crm.php`
- Sales BDM login only.
- Reads the logged-in BDM's `espo_user_id` from session/staff record.
- Calls every `EspoMetrics` method with that fixed ID.
- No rep picker, no "view whole team" option.
- KPI cards (funnel snapshot, conversion rates, call activity) +
  trend chart(s), following this app's existing dashboard/report
  visual patterns.

### 6.2 `femi9/billing/company/dashboard-crm.php`
- Company login only.
- Top section: KPI cards computed with `$espoUserId = null` (whole
  team).
- Below: a per-rep table, one row per `sales_bdm_staff` record that
  has an `espo_user_id` set, looping the same `EspoMetrics` calls per
  row (person-wise breakdown) — modeled on the existing KPI-cards +
  table structure used in `cp-district-sales-report.php`.
- Reps without a linked `espo_user_id` are shown in the table with a
  "not linked" indicator, not silently omitted.

## 7. Testing

- Unit-level: verify `EspoMetrics` methods produce correct
  aggregation with a small fixture/mock result set (conversion rate
  math, cycle-length averaging, ratio calculation).
- Manual: once real credentials are supplied, verify against actual
  Espo data — spot-check a few reps' numbers against EspoCRM's own UI
  reports for one period before trusting the dashboard.
- Failure path: verify the "CRM data unavailable" banner renders
  correctly when `EspoDb.php`'s connection is forced to fail (e.g.
  wrong port), and that no other part of the app is affected.

## 8. Explicit non-goals (YAGNI)

- No local caching/sync tables — real-time direct query only, per
  requirement.
- No write-back to EspoCRM.
- No auto-refresh/polling on the dashboard pages unless requested
  later.
- No custom-field reconciliation until real DB credentials are
  available.
