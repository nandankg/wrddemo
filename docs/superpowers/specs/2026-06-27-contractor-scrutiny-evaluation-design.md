# Contractor Portal — Phase 2: Scrutiny & Evaluation

**Date:** 2026-06-27
**Module:** Component-B Contractor Registration (`app/contractor/`)
**Status:** Approved design — ready for implementation plan

## Purpose

Complete the officer/back-office side of the contractor registration demo by
delivering the three highest-value evaluation screens:

- **Screen 6 — Officer Scrutiny Dashboard** (finish): status breakdown + a real
  per-application scrutiny detail combining document review and AI-verify results.
- **Screen 7 — Query Management**: a full officer⇄contractor round-trip with audit
  trail. ("Most bidders forget this.")
- **Screen 8 — Contractor Scoring Engine**: deterministic four-part score with
  visual gauges. ("Huge differentiator.")

This builds directly on the existing Phase-1 workflow (`applications.php` already
does Forward / Approve / Reject across stages ASO→AE→EE→EIC with `add_audit`).

## What already exists (do not rebuild)

- `app/contractor/applications.php` — scrutiny inbox: stage workflow
  ASO→AE→EE→EIC, Forward / Approve (issues certificate) / Reject + remarks,
  visual stage tracker, full audit trail via `add_audit`.
- `app/contractor/index.php` — role-adaptive (`contractor_role_view` →
  `contractor` vs `registry`) with KPI row (`contractor_kpis`).
- `app/contractor/lib.php` — pure logic: `contractor_eligibility`,
  `contractor_doc_verify`, `contractor_fee`, `contractor_kpis`,
  `contractor_pending_actions`, `contractor_next_stage`.
- `contractors` columns include: `class`, `district`, `status`, `risk_score`,
  `experience_yrs`, `completed_projects`, `turnover`, `name_hi`.
- `contractor_apps` columns include: `ack_no`, `contractor_id`, `type`, `class`,
  `stage`, `status`, `fee`, `fee_paid`, `applied_on`.

## Architecture

Keep the established split: **pure logic in `lib.php`** (no DB, no rendering,
fully unit-testable), **DB + render in the page files**.

### New pure functions (`app/contractor/lib.php`)

```
contractor_score(array $c, array $docResults = []): array
  -> ['experience'=>int, 'financial'=>int, 'compliance'=>int,
      'overall'=>int, 'band'=>'A'|'B'|'C']   // each 0..100

contractor_app_breakdown(array $apps): array
  -> ['new'=>int, 'verifying'=>int, 'approval_pending'=>int,
      'query'=>int, 'approved'=>int, 'rejected'=>int]

contractor_can_forward(array $app, int $openQueries): bool
  -> false while an Open query exists or the app is terminal
```

#### Scoring formula (deterministic; reacts to live data)

- **Experience (0–100):** normalise against the Class-I bar (10 yrs / 10 proj):
  `min(100, round(min($years,10)/10*50 + min($projects,10)/10*50))`.
- **Financial (0–100):** turnover vs the applicant's class threshold from the
  `contractor_eligibility` tiers (I=₹5 Cr, II=₹3 Cr, III=₹1.5 Cr, IV=₹0.5 Cr
  baseline): `clamp(round(40 + min(turnover / threshold, 1.2) * 50), 0, 100)`
  (base 40, slope 50, ratio capped at 1.2 — so meeting the class bar exactly
  scores 90, and 1.2× the bar or above saturates at 100).
- **Compliance (0–100):** start 100; subtract `risk_score`; subtract a fixed
  penalty per AI doc issue found in `$docResults`; **0 if status is
  `Blacklisted`**.
- **Overall:** weighted average — Experience 0.35, Financial 0.30,
  Compliance 0.35, rounded.
- **Band:** A ≥ 80, B 60–79, C < 60.

Exact penalty constants are fixed in code and pinned by tests so the numbers are
defensible under questioning.

### New table `contractor_queries` (`sql/seed.php`)

| column        | type                                      |
|---------------|-------------------------------------------|
| id            | INT PK AUTO_INCREMENT                      |
| app_id        | INT (→ contractor_apps.id)                 |
| raised_by     | VARCHAR (actor name)                       |
| raised_role   | VARCHAR (ASO/AE/EE/EIC)                    |
| query_text    | TEXT                                       |
| status        | ENUM('Open','Responded','Resolved')        |
| response_text | TEXT NULL                                  |
| raised_on     | DATE                                       |
| responded_on  | DATE NULL                                  |
| resolved_on   | DATE NULL                                  |

Seed two illustrative rows (one Open, one Resolved) against existing apps.

### New page `app/contractor/scrutiny.php?app_id=`

Officer-only detail view that combines Screens 6, 7, 8:

1. **Applicant header** — name (bilingual), class, district, ack no, fee/paid.
2. **Document checklist** — each required doc with its `contractor_doc_verify`
   badge (Verified / Issue + reason).
3. **Scoring panel** — four gauges (Experience / Financial / Compliance /
   Overall) from `contractor_score`, with the band.
4. **Query thread** — existing `contractor_queries` for this app, a **Raise
   Query** form, and **Resolve** on open queries.
5. **Decision actions** — Forward / Approve / Reject, reusing the role/stage
   guards already in `applications.php`; Forward disabled when
   `contractor_can_forward` is false.

### Edits to existing files

- `index.php` (registry view): add the Screen-6 status-breakdown strip using
  `contractor_app_breakdown`.
- `applications.php`: each row gains **"Open scrutiny →"** linking to
  `scrutiny.php`; Forward is blocked while an Open query exists.
- Contractor view (`index.php`): surface open queries with a **respond form**
  that writes `response_text` and moves the query to `Responded`.
- `contractor_pending_actions`: repoint item URLs to `scrutiny.php?app_id=`.

### Query lifecycle (status interplay)

```
Officer raises query  -> queries(Open)      ; app.status = 'Query Raised'   ; forward blocked
Contractor responds   -> queries(Responded) ; app.status = 'Under Process'  ; sets response_text, responded_on
Officer resolves      -> queries(Resolved)  ; resolved_on set               ; forward re-enabled
```

Every transition writes `add_audit` (the Screen-7 transparency selling point).

## Testing

Extend `tests/contractor_test.php` (run via `tests/run.php`; 71 passing today):

- `contractor_score`: known inputs → expected sub-scores, overall, and band;
  clamps at 0 and 100; `Blacklisted` → compliance 0; doc issues lower compliance.
- `contractor_app_breakdown`: correct bucket counts including `Query Raised`.
- `contractor_can_forward`: false with an open query / terminal status; true
  otherwise.

## Out of scope (later phases)

- Screen 9 empanelment filters, Screen 12 renewal, Screen 13 revenue dashboard,
  Screen 14 secretary dashboard + Jharkhand district map, Screen 15 mobile pass.
