# Contractor Registration & Empanelment Portal — High-Scoring Demo Design

**Date:** 2026-06-17
**Component:** Tender §9, Component-B (`wrdreg.jharkhand.gov.in`) — Redesign & Redevelopment
**Goal:** Build out the thin existing `app/contractor/` module into the full 15-screen storyboard in `contractor.md`, targeting the 20-mark live-presentation criterion of the WRD Jharkhand QCBS evaluation.

## Context

The WRD Jharkhand RFP bundles five independent products. This spec covers **Component-B only**. The existing module (`app/contractor/`) already has: a 4-step registration wizard (in `index.php`), an officer applications inbox (`applications.php`), a registered-contractors register (`registry.php`), a QR + DigiLocker certificate (`certificate.php`), a public verify page (`verify.php`), and a branded login. It is built on PHP 8.2 + MariaDB (XAMPP), Tailwind via CDN, bilingual Hindi/English, with role-based nav in `includes/apps.php`.

This build extends that module to match the `contractor.md` storyboard and the official §9 scope, following the patterns proven in the Industrial Water Allocation module (`app/allocation/`, the richest module in the suite).

### Decisions locked in brainstorming
1. **Delivery:** phased and reviewable — 3 phases, each independently demoable, checkpoint after each.
2. **AI:** offline deterministic pure-PHP functions (mirroring `allocation/lib.php`) — no network, identical every demo run, unit-tested.
3. **Public landing:** yes — a no-login GIGW landing page + public certificate-verification portal (§9.3.1).
4. **Officer chain:** upgrade to the full **ASO → SO → US → DS → JS → EIC** chain named in §9.4.1 (replaces the current simplified ASO → AE → EE → EIC).
5. **AI chat:** curated FAQ matcher — floating widget, deterministic keyword match over ~10 seeded bilingual WRD-contractor FAQs.

## Architecture

Build on `app/contractor/` using established suite conventions:
- `set_app_context('contractor')`, role-based nav via `includes/apps.php` (`roles` allow-lists), `app_require_access()` guards on officer pages.
- Bilingual via `is_hi()` / `bi()` / `t()`; shared UI helpers `badge()`, `inr()`, `add_audit()`, `base_url()`.
- All business logic in **pure functions** in `app/contractor/lib.php` (no DB, no rendering), unit-tested by the zero-dep runner `tests/run.php`.
- GIS reuses the already-vendored offline Leaflet + Jharkhand district GeoJSON in `assets/` (no online tiles). Charts reuse vendored Chart.js.
- DigiLocker, Aadhaar e-KYC, E-GRAS payment, and AI are all **simulated deterministically** — presented honestly as a prototype; production stack (React/PostgreSQL) shown in slides.

### Component boundaries
- `public/contractor.php` — public GIGW landing (no login). Pulls public stats via a small read-only query.
- `app/contractor/lib.php` — all pure logic: workflow chain, fees, KPIs, scoring engine, eligibility, doc-verify, risk, exec-summary, FAQ matcher. Independently testable.
- Page files (`index.php`, `applications.php`, `registry.php`, `queries.php`, `renewals.php`, `revenue.php`, `leadership.php`, `certificate.php`, `verify.php`) — thin rendering + POST handlers, delegating to `lib.php`.

## Data model changes

**Extend `contractors`** (add columns): `cin VARCHAR(30)`, `address VARCHAR(255)`, `contact VARCHAR(120)`, `experience_yrs INT`, `completed_projects INT`, `turnover DECIMAL(14,2)`, `exp_score INT`, `fin_score INT`, `comp_score INT`, `overall_score INT`, `risk_reason VARCHAR(255)`. (Keeps existing `risk_score`, `qr_token`, `valid_upto`, `login_user`, `status`, `name_hi`.)

**New table `contractor_queries`** (Screen 7):
```
id INT PK, app_id INT, raised_by VARCHAR, raised_role VARCHAR,
query_text VARCHAR(500), status VARCHAR(20) [Open|Responded|Closed],
response_text VARCHAR(500) NULL, raised_on DATE, responded_on DATE NULL
```

**Revenue dashboard** needs no new table — it aggregates `contractor_apps.fee / fee_paid / applied_on / type` joined to `contractors.district`.

**Seed** (`sql/seed.php`): ~12 contractors across districts and Classes I–IV including one Blacklisted and one near-expiry (for renewal); several in-flight `contractor_apps` parked at different stages of the 6-stage chain; 2–3 seeded queries (Open/Responded). Seed the 4 new officer roles (SO, US, DS, JS) in `users`.

## Pure functions in `lib.php` (all unit-tested)

| Function | Purpose |
|---|---|
| `contractor_next_stage($stage)` | ASO→SO→US→DS→JS→EIC chain (replaces 4-stage map) |
| `contractor_fee($class)` | Registration fee by class (existing) |
| `contractor_experience_score($years,$projects)` | 0–100 from experience + completed projects |
| `contractor_financial_score($turnover,$class)` | 0–100 from turnover vs class threshold |
| `contractor_compliance_score($docsPresent,$docsTotal,$blacklisted,$openQueries)` | 0–100 |
| `contractor_overall_score($exp,$fin,$comp)` | Weighted composite |
| `contractor_eligibility($years,$projects,$turnover)` | Recommended Class I–IV + reason |
| `contractor_doc_verify($docName,$seed)` | Deterministic Verified / "Missing Signature" etc. |
| `contractor_risk($contractor)` | level (LOW/MED/HIGH) + reasons[] |
| `contractor_exec_summary($contractor)` | Project-experience summary paragraph |
| `contractor_chat_answer($question)` | Keyword match over seeded FAQ set → bilingual answer |
| `contractor_kpis`, `contractor_pending_actions` | Dashboard aggregates (extend existing for 6 roles) |

## Phase breakdown

### Phase 1 — Public face + smart registration (Screens 1, 2, 3, 4, 11)
- **Screen 1** `public/contractor.php`: GIGW hero ("Water Resources Department — Contractor Registration & Empanelment Portal"), 6 quick-action tiles (New Registration, Renew, Track, Download Certificate, Verify, Pay Fees), live public statistics strip (Registered / Active / Applications-this-year / Avg approval time). No login. Registered in `apps.php` as the contractor `home`.
- **Screen 2**: expand the wizard from 4 to **6 steps** — Company Details → Contractor Classification (dynamic eligibility readout) → Technical Credentials → Financial Credentials → Bank Details → Document Upload (drag & drop UI). Persists the new credential fields.
- **Screen 3**: DigiLocker "Connect" → animated auto-fetch of PAN / Aadhaar / GST / company docs → "Verification Successful".
- **Screen 4**: AI Document Verification panel — per-document Verified / issue (e.g. "Missing Signature") using `contractor_doc_verify`.
- **Screen 11**: polish `verify.php` into the public verification portal (enter Contractor ID → Verified / Active / Validity / Blacklisted), linked from the landing page.

### Phase 2 — Officer workflow + intelligence (Screens 5, 6, 7, 8, 9, 10 + AI bonus)
- **Screen 5**: applicant tracking dashboard — progress tracker (Submitted → Doc Verification → Technical → Financial → Approval → Certificate), current officer, expected approval.
- **Screen 6**: officer scrutiny dashboard — pending counts (New / Verification Pending / Approval Pending / Rejected), per-role inbox; actions View / Verify / Raise Query / Approve / Reject. Upgrade chain to **ASO→SO→US→DS→JS→EIC**; tag nav + guard pages for the 4 new roles.
- **Screen 7** `queries.php`: officer raises a query → contractor notified → contractor responds → full audit trail.
- **Screen 8**: contractor scoring engine — visual gauge for Experience / Financial / Compliance / Overall on the application detail.
- **Screen 9**: empanelment dashboard — `registry.php` gains District / Class / Status filters and per-class active/suspended/expired counts.
- **Screen 10**: certificate generation — already built (QR + DigiLocker push); polish only.
- **AI bonus**: risk score (with reason), eligibility recommendation, document/experience summarization, and a floating **AI chat assistant** widget (FAQ matcher) available across the contractor surface.

### Phase 3 — Lifecycle + leadership dashboards (Screens 12, 13, 14, 15)
- **Screen 12** `renewals.php`: "Registration Expiring in 30 Days" alert → Update Documents → Pay Fees → Verification → Renewal Certificate. Seeded near-expiry contractor drives this.
- **Screen 13** `revenue.php`: registration fees collected (₹ total), Chart.js monthly collection / renewal revenue / district-wise revenue.
- **Screen 14** `leadership.php`: Secretary / Chief Engineer dashboard — Total / Active contractors, Pending approvals, Revenue, Renewals due, plus a **Jharkhand district map** of contractor distribution (vendored Leaflet + GeoJSON, colour-coded by count). Officer-only.
- **Screen 15**: mobile-responsiveness verification pass across Registration, Tracking, and Certificate Download (Tailwind responsive utilities; PWA-flavoured per §9.2).

## Testing & verification
- Every `lib.php` function gets unit tests in the existing `tests/run.php` runner; keep the suite green (current count grows from the contractor additions).
- Live-render each page via `php` CLI with a faked `$_SESSION['user']` before claiming any screen done.
- Per-phase checkpoint: present what was built, run the test suite, and verify the headline demo path on XAMPP before moving on.
- Known gotchas (from prior modules): `header.php` clobbers `$f`/`$u`/`$nav`/`$APP` — don't name page vars `$f`; add role+status guards to every workflow POST handler; new registry roles (SO/US/DS/JS) must be seeded in `sql/seed.php`; `app_require_access()` after `set_app_context()` on officer-only pages.

## Out of scope (YAGNI for the demo)
Real DigiLocker/Aadhaar/E-GRAS API integration; real LLM calls; SMS/email gateway (shown as in-app notifications only); the production React/PostgreSQL rewrite (architecture shown in slides, not built here).
