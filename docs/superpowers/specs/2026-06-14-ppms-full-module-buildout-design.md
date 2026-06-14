# PPMS Full Module Build-out — Design

**Date:** 2026-06-14
**Status:** Approved (pending spec review)
**Goal:** Extend the existing PPMS product so it covers **every** feature and module listed in `ppms.md`, presented one-nav-item-per-module for a tender live demo (20-mark QCBS demo). Integrations that cannot be real in a demo are **believable simulations** (on-screen OTP, downloadable Word/Excel files, configurable-but-simulated scheduling).

---

## 1. Context & Gap Analysis

PPMS already exists at `app/ppms/` on the shared foundation (themed shell via `set_app_context('ppms')`, pure logic in `app/ppms/lib.php`, zero-dependency tests in `tests/`). Mapping `ppms.md` against current state:

| ppms.md module / item | Current state | Action |
|---|---|---|
| Module A – Fund Requisition (create, escalation, finance review, sanction, release) | Complete (`requisitions.php` + `certificate.php`, stage guards, audit) | Keep; emit notifications on events |
| Module B – Reporting (custom, PDF/Word/Excel, scheduled, monthly MIS, project/division-wise) | Partial: CSV + print-PDF + status/div filter (`reports.php`) | Replace with **Report Builder** + **Scheduled Reports** |
| Module C – MIS Dashboard (real-time, KPI, progress, pendency) | Mostly complete (`index.php`) | Keep; minor polish |
| Module D – BI Dashboard (interactive charts, GIS, drill-down, financial/perf metrics) | Partial: GIS map + 1 bar chart | Add dedicated **BI Dashboard** page |
| Scope: project monitoring & **milestone tracking** | Absent | Add **Milestones** page + table |
| Scope: **SMS & OTP** integration | Absent | Login OTP (simulated) + **Notifications** SMS/email log |
| Scope: web & **mobile** application | Responsive Tailwind shell | Responsive-throughout is sufficient (no separate mobile app) |
| Scope: security audit, hosting, training, AMC | Non-code / out of demo scope | Not built (mentioned in deck only) |

**Decisions made during brainstorming:**
- Fidelity: **believable simulation** for SMS/OTP, Word/Excel export, scheduled/monthly MIS.
- Navigation: **one nav item per module** (most explicit for evaluators).
- Milestones is its **own** page (not folded into Projects).
- **Report Builder replaces** the current "Reports / MIS" nav item (the old `reports.php` is superseded).
- Mobile: **responsive-throughout is enough**; no dedicated mobile screen.

---

## 2. Navigation (target PPMS sidebar)

Registered in `includes/apps.php` under the `ppms` entry, in this order:

1. `dashboard` — **Command Centre** — `app/ppms/index.php` — Module C
2. `projects` — **Projects & Progress** — `app/ppms/projects.php` — physical/financial progress
3. `milestones` — **Milestones** — `app/ppms/milestones.php` — milestone tracking *(new)*
4. `requisitions` — **Fund Requisition** — `app/ppms/requisitions.php` — Module A
5. `bi` — **BI Dashboard** — `app/ppms/bi.php` — Module D *(new)*
6. `reports` — **Report Builder** — `app/ppms/reports.php` — Module B (custom + exports) *(rewritten)*
7. `scheduled` — **Scheduled Reports** — `app/ppms/scheduled.php` — Module B (scheduled + monthly MIS) *(new)*
8. `notifications` — **Notifications** — `app/ppms/notifications.php` — SMS/OTP log *(new)*

Header gains a **notification bell** with an unread count (PPMS context only).
Login (`app/ppms/login.php`) gains a **simulated OTP step** after username/password.

---

## 3. Data Model (additions to `setup.php` + `sql/seed.php`)

All tables `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`, added to the setup drop-list.

### `milestones`
```
id INT PK AI
project_id INT
name VARCHAR(160), name_hi VARCHAR(160) NULL
planned_date DATE
actual_date DATE NULL
weight INT            -- contribution weight toward project completion (sums per project)
status VARCHAR(20)    -- Pending | In-Progress | Done | Delayed
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
```
Seed: 3–5 milestones across several seeded projects, mixing Done / In-Progress / overdue-Pending (so the "Delayed" auto-flag is visible).

### `notifications`
```
id INT PK AI
channel VARCHAR(10)   -- SMS | OTP | EMAIL
to_label VARCHAR(120) -- e.g. "EE · +91-9xxxxxx210"
message VARCHAR(255)
entity VARCHAR(60) NULL   -- e.g. "fund_requisition #12"
status VARCHAR(12)        -- Sent | Delivered
is_read TINYINT DEFAULT 0
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
```
Seed: a handful of prior SMS/email rows so the page and bell are populated on first load.

### `scheduled_reports`
```
id INT PK AI
name VARCHAR(120)
report_type VARCHAR(30)   -- project | division | scheme | requisition | monthly_mis
frequency VARCHAR(12)     -- Daily | Weekly | Monthly | Quarterly
format VARCHAR(8)         -- PDF | XLS | DOC | CSV
recipients VARCHAR(200)   -- comma list of role/email labels
last_run DATETIME NULL
next_run DATE
active TINYINT DEFAULT 1
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
```
Seed: 2–3 schedules incl. a "Monthly MIS to Secretariat".

**OTP:** session-only (`$_SESSION['ppms_otp']`), no table.

---

## 4. Pure Logic (`app/ppms/lib.php`, unit-tested)

New pure functions (no DB, no echo), each covered in `tests/ppms_test.php`:

- `ppms_milestone_status(string $status, ?string $actual, string $planned, string $today): string`
  Returns the effective status, upgrading `Pending`/`In-Progress` to `Delayed` when `planned < today` and not Done.
- `ppms_milestone_progress(array $milestones): int`
  Weighted completion % = sum(weight of Done) / sum(weight) × 100 (0 when no weights).
- `ppms_bi_by_division(array $projects): array`
  Per-division aggregates: avg physical, avg financial, sanctioned, spent, count. (For grouped bar + drill-down.)
- `ppms_bi_financials(array $projects): array`
  Totals: sanctioned, spent, utilisation %, released pipeline counts. (Financial metric cards.)
- `ppms_report_dataset(string $type, array $rows): array`
  Returns `['columns'=>[...], 'rows'=>[[...]]]` for a given report type — the single source used by both the on-screen preview and every export format, guaranteeing parity.
- `ppms_next_run(string $frequency, string $from): string`
  Computes the next run date (+1 day/week/month/quarter) from a base date.
- `ppms_otp_generate(): string` — 6-digit numeric (string, zero-padded). (Determinism handled by caller seeding; test asserts format/length.)

Existing `ppms_kpis`, `ppms_fund_kpis`, `ppms_role_view`, `ppms_valid_pct`, `ppms_pending_actions`, `ppms_require_login` stay unchanged.

A small shared helper `ppms_notify($pdo, $channel, $to, $message, $entity)` (thin DB insert) lives in `lib.php` but is exercised via page flows, not unit tests (it touches the DB).

---

## 5. Page Behaviour

### Milestones — `app/ppms/milestones.php`
- List view: per-project milestone rollup with weighted completion % and a Delayed count; division-scoped for field/division roles (same scoping rule as `projects.php`).
- Detail (`?project=ID`): milestone timeline (planned vs actual), each row showing effective status via `ppms_milestone_status`.
- Action: JE/AE may mark a milestone In-Progress/Done (sets `actual_date`). Marking the last milestone Done emits an SMS notification. Overdue items render as Delayed automatically (computed, not stored toggling required).

### BI Dashboard — `app/ppms/bi.php`
- Chart.js board (CDN, same as Command Centre):
  - Grouped bar: physical vs financial % by division (`ppms_bi_by_division`).
  - Line: fund utilisation % by division (spent ÷ sanctioned per division).
  - Doughnut: project status mix.
  - Horizontal bar: top delayed projects.
- Financial/performance metric cards from `ppms_bi_financials`.
- **Drill-down:** clicking a division (chart legend or a division chip) reloads with `?div=ID`; all charts + cards recompute server-side for that division. A "← All divisions" reset clears it.
- Oversight/finance see all; field/division roles are scoped to their division.

### Report Builder — `app/ppms/reports.php` (rewritten)
- Controls: report type (project / division / scheme / requisition register), status & division filters, optional group-by.
- Live preview table built from `ppms_report_dataset`.
- Export buttons produce **real downloadable files** from the same dataset:
  - CSV — `text/csv`.
  - Excel — `application/vnd.ms-excel` HTML table, `.xls` (opens natively in Excel).
  - Word — `application/msword` HTML doc, `.doc` (opens natively in Word).
  - PDF — styled print view (`window.print()`), as today.
- A single `?export=<fmt>&type=<t>&...filters` entrypoint streams the chosen format using the shared dataset, so preview and exports never diverge.

### Scheduled Reports — `app/ppms/scheduled.php`
- Table of `scheduled_reports` (name, type, frequency, format, recipients, next run, active).
- Create/edit schedule (modal); `next_run` computed via `ppms_next_run`.
- "Run now" on a row: sets `last_run`, recomputes `next_run`, and writes an EMAIL notification ("Report '…' generated & emailed to …").
- "Generate this month's MIS" button: renders a formatted Monthly MIS document (printable + downloadable .doc) summarising KPIs, status mix, division rollup, fund pipeline; logs a notification.

### Notifications — `app/ppms/notifications.php`
- Reverse-chronological log of all `notifications` (SMS / OTP / EMAIL) with channel chips and entity links.
- Opening the page marks rows read (clears the header bell count).

### Login OTP — `app/ppms/login.php` (modify)
- After valid username/password (or quick-pick), generate a session OTP via `ppms_otp_generate`, **display it on-screen** ("Demo OTP: 482913 — auto-filled below"), and require confirmation before landing on the dashboard. A "resend" link regenerates. Purely client-convenient: the demo never blocks on a real channel.
- Quick-pick role buttons may bypass OTP (one-click demo) — OTP is shown only on the username/password path to demonstrate the capability without slowing the role tour.

### Header bell — `includes/header.php` (modify, PPMS-scoped)
- When `app_ctx()` is PPMS and user logged in, show a bell with unread `notifications` count linking to `notifications.php`. Other products unaffected.

---

## 6. Architecture & Constraints

- **Pattern parity:** every new page mirrors existing PPMS pages — requires `auth.php`/`functions.php`/`lib.php`, `ppms_require_login()`, `set_app_context('ppms')`, themed shell, bilingual (`is_hi()` / `bi()`), `inr()` for money, `badge()` for status.
- **Product isolation:** PPMS reads only PPMS tables (`projects`, `fund_requisitions`, `progress_updates`, `milestones`, `notifications`, `scheduled_reports`, plus `schemes`/`divisions`/`workflow_log`). Never touches payments/bills/allocations/contractors/content.
- **Testing:** all new pure functions get tests in `tests/ppms_test.php`; `tests/apps_test.php` updated for the new nav keys. `php tests/run.php` must stay green. Every touched PHP file must pass `php -l`.
- **Scoping rule reused:** field/division roles see only their `division_id`; oversight/finance see all — identical to current `index.php`/`projects.php`.

---

## 7. Out of Scope

- Real SMS gateway / email server / cron daemon (simulated only).
- Security audit, hosting, training, AMC (commercial/deck items, not software).
- A separate native/mobile app (responsive web covers the "mobile" requirement).
- Any cross-product or aggregate dashboard changes.

---

## 8. File Plan

**Create:**
- `app/ppms/milestones.php`, `app/ppms/bi.php`, `app/ppms/scheduled.php`, `app/ppms/notifications.php`

**Rewrite:**
- `app/ppms/reports.php` (Report Builder + multi-format export)

**Modify:**
- `app/ppms/lib.php` (new pure functions + `ppms_notify`)
- `app/ppms/login.php` (OTP step)
- `includes/apps.php` (nav items)
- `includes/header.php` (PPMS notification bell)
- `app/ppms/requisitions.php`, `app/ppms/projects.php` (emit notifications on key events)
- `setup.php` (3 new tables + drop-list + table count message)
- `sql/seed.php` (seed milestones, notifications, scheduled_reports)
- `tests/ppms_test.php`, `tests/apps_test.php`

---

## 9. Demo Flow (acceptance)

1. PPMS login → username/password → **on-screen OTP** → Command Centre.
2. **Milestones** → open a project → mark a milestone Done → completion % rises; an overdue one shows **Delayed**.
3. **BI Dashboard** → interactive charts → click a division → everything drills down → reset.
4. **Report Builder** → pick division-wise → preview → download **Excel** and **Word** files that open natively; **PDF** via print.
5. **Scheduled Reports** → "Generate this month's MIS" → formatted MIS doc; a schedule "Run now" logs an email.
6. **Fund Requisition** lifecycle (existing) → release emits an **SMS notification**.
7. **Notifications** bell shows the new SMS/OTP/email events; opening clears the count.
8. Switch roles → field roles see only their division across Milestones/BI/Reports.
