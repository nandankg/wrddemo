# Contractor Portal — Phase 3: Empanelment, Revenue & Oversight

**Date:** 2026-06-27
**Module:** Component-B Contractor Registration (`app/contractor/`)
**Status:** Approved design — ready for implementation plan

## Purpose

Deliver the contractor module's management/oversight screens — the highest-value
dashboards in the storyboard:

- **Screen 9 — Empanelment Dashboard:** a filterable registry (District / Class /
  Category / Status) with per-class Active / Suspended / Expired counts.
- **Screen 13 — Revenue Dashboard** ("WRD Officers love this"): registration-fee
  collection KPIs and charts (monthly collection, renewal vs new, district-wise).
- **Screen 14 — Secretary / Chief Engineer Dashboard** ("Most important
  dashboard"): an executive KPI strip plus a Jharkhand district map of
  contractor distribution with click-drill-down.

All three read from **one consistent seeded dataset**, so the figures reconcile
if an evaluator cross-checks the registry against the dashboards.

## What already exists (reuse, do not rebuild)

- Map + charts are proven in the allocation module: `app/allocation/analytics.php`
  renders a colour-coded Jharkhand district map (Leaflet + bundled GeoJSON) with
  click drill-down and Chart.js trend charts. Phase 3 follows the same pattern.
- Vendored, offline-safe assets: `assets/vendor/leaflet/`, `assets/vendor/chartjs/chart.umd.js`,
  and `assets/geo/jharkhand-districts.geojson` (district polygons keyed by
  `properties.district`, all 24 districts: Bokaro, Chatra, Deoghar, Dhanbad,
  Dumka, East Singhbhum, Garhwa, Giridih, Godda, Gumla, Hazaribagh, Jamtara,
  Khunti, Koderma, Latehar, Lohardaga, Pakur, Palamu, Ramgarh, Ranchi, Sahibganj,
  Saraikela-Kharsawan, Simdega, West Singhbhum).
- `app/contractor/registry.php` already lists contractors (class · risk · blacklist);
  Phase 3 upgrades it with the four filters and status counts.
- `app/contractor/lib.php` pure-logic conventions; the `tests/contractor_test.php`
  harness (run via `php tests/run.php`).
- `contractors` columns: reg_no, name, name_hi, class, pan, gst, district, status,
  risk_score, valid_upto, registered_on, qr_token, login_user, cin, address,
  contact, experience_yrs, completed_projects, turnover.
- `contractor_apps` columns: ack_no, contractor_id, type, class, stage, status,
  fee, fee_paid, applied_on.
- Nav + role gating live in `includes/apps.php` (contractor app roles
  `CONTRACTOR, ASO, AE, EE, EIC`; officer nav items gated `['ASO','AE','EE','EIC']`).

## Architecture

Keep the established split: **pure aggregation/filter logic in `lib.php`** (no DB,
no rendering, unit-tested), **DB queries + rendering in page files**. Revenue is
derived from `contractor_apps` (no payments table is added).

### Schema change (`setup.php` + `sql/seed.php`)

- Add one column to `contractors`: `category VARCHAR(20)` — the contractor's work
  discipline, one of **Civil / Mechanical / Electrical / Irrigation**. This is the
  Screen-9 "Category" filter. (Add to the `CREATE TABLE contractors` block and to
  the seed INSERT column list.)

### Seed expansion (`sql/seed.php`)

- Grow `contractors` to **~55 firms** spread deterministically across the 24
  GeoJSON district names × Classes I–IV × `category` × `status`, where status ∈
  {`Active`, `Suspended`, `Expired`, `Blacklisted`}. Give each realistic
  `risk_score`, `turnover`, `experience_yrs`, `completed_projects`, and a
  `valid_upto` (some in the past so "Expired" is real). Keep the existing 8 named
  firms (and the `login_user='contractor'` link on WRD/REG/3/0451) intact;
  append the rest.
- Seed each firm one or more `contractor_apps` with `fee` = `contractor_fee(class)`,
  `fee_paid=1`, `type` ∈ {`New`, `Renewal`}, and `applied_on` spread across the
  **last 12 months** (so the monthly-collection chart and renewal-revenue split
  are populated). Keep the 4 existing workflow apps (ids 1–4) and the Phase-2
  seeded queries unchanged; append the revenue-bearing apps after them.

### Effective status (single rule used everywhere)

A contractor's **effective status** is `Expired` when `valid_upto < today`,
otherwise its stored `status` (`Active` / `Suspended` / `Blacklisted` / …). Both
`contractor_filter` and `contractor_empanelment_matrix` classify by effective
status, so an `Active`-stored row with a lapsed `valid_upto` is counted as
`Expired` and is excluded from `Active` — the registry and the dashboards never
double-count. Implement this once as a small helper (e.g.
`contractor_effective_status(array $c, string $today): string`) that both
functions call.

### New pure functions (`app/contractor/lib.php`)

```
contractor_filter(array $contractors, array $f, ?string $today = null): array
  // $f keys: district, class, category, status (any may be '' = no filter).
  // status is matched against the row's EFFECTIVE status (see rule above).

contractor_empanelment_matrix(array $contractors, ?string $today = null): array
  // -> [ 'I'=>['active'=>int,'suspended'=>int,'expired'=>int], 'II'=>..., 'III'=>..., 'IV'=>... ]

contractor_revenue_kpis(array $apps, ?string $today = null): array
  // -> ['total'=>float,'renewal'=>float,'new'=>float,'fy'=>float]
  //    counts only fee_paid=1 rows; 'fy' = current Indian FY (Apr 1–Mar 31) to date.

contractor_monthly_collection(array $apps, ?string $today = null): array
  // -> ordered ['YYYY-MM'=>float] for the trailing 12 months ending in $today's month.

contractor_district_rollup(array $contractors, array $apps): array
  // -> ['Ranchi'=>['count'=>int,'revenue'=>float], ...] keyed by district name.
```

All are deterministic and offline. "Expired" is computed from `valid_upto`, never
stored, so it stays correct as the demo date advances.

### Pages

- **`app/contractor/registry.php` (upgrade — Screen 9).** A filter bar of four
  `<select>`s (District / Class / Category / Status) submitted via GET; results
  run through `contractor_filter`. Above the table, per-class cards showing
  Active / Suspended / Expired from `contractor_empanelment_matrix`. Officer-gated.
- **`app/contractor/revenue.php` (new — Screen 13).** KPI row (Total Collected,
  Renewal Revenue, New Registrations, This FY) from `contractor_revenue_kpis`,
  then three Chart.js canvases: monthly collection (line/bar, from
  `contractor_monthly_collection`), renewal-vs-new (doughnut), district-wise
  revenue (horizontal bar, top districts from `contractor_district_rollup`).
  Loads the vendored `chart.umd.js`.
- **`app/contractor/oversight.php` (new — Screen 14).** Executive KPI strip
  (Total Contractors, Active, Pending Approvals, Revenue Collected, Renewals Due),
  then a Leaflet Jharkhand district map coloured by contractor count
  (`contractor_district_rollup`) with click-to-drill-down listing that district's
  firms — mirroring `app/allocation/analytics.php`'s map wiring (bundled GeoJSON,
  no online tiles). Senior-officer/Secretary gated.

### Nav & access (`includes/apps.php`)

- Add `SECRETARY` to the contractor app's top-level `roles`.
- Add two nav items to the contractor app: **"Revenue MIS"** (`app/contractor/revenue.php`)
  and **"Command Centre"** (`app/contractor/oversight.php`), both gated
  `'roles'=>['EE','EIC','SECRETARY']`. The existing `registry` item stays gated to
  `['ASO','AE','EE','EIC']`.

## Testing (`tests/contractor_test.php`)

Unit tests, all via `php tests/run.php`, for each pure function with fixed inputs:

- `contractor_filter`: each single filter; a combined filter; the derived-Expired
  case (a row with past `valid_upto` matches status `Expired` but not `Active`).
- `contractor_empanelment_matrix`: per-class active/suspended/expired counts,
  including a past-`valid_upto` Active row counted as Expired.
- `contractor_revenue_kpis`: total/new/renewal split; `fee_paid=0` excluded; FY
  boundary (a payment before Apr 1 excluded from `fy`).
- `contractor_monthly_collection`: 12 buckets in order; an app older than 12
  months excluded; correct month bucketing.
- `contractor_district_rollup`: per-district count and revenue aggregation.

## Out of scope (later)

Screen 12 renewal workflow, Screen 15 mobile/responsive pass, and the deferred
Phase-2 minors (bilingual `flash()` pass, multi-open-query revert asymmetry,
`contractor_queries` FK/enum hardening).
