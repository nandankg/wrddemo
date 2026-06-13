# WRD Jharkhand — Five Independent Products Demo: Design Spec

**Date:** 2026-06-14
**Status:** Approved (design) — ready for implementation planning
**Context:** Government of Jharkhand, Water Resources Department (WRD) tender. Evaluation is 85/15 QCBS with a 20-mark live demonstration. The demo's UI/UX is a primary success factor.

---

## 1. Summary

The current demo is built as a **single integrated portal** (one landing page, one shared "Command Centre" dashboard aggregating all modules, one cross-module login + role switcher, and live cross-feeds between modules). This is wrong for the tender: the five RFP components are **separate, independent projects** that happen to be procured under one tender. They do not interoperate.

This spec restructures the demo into an **official "WRD Project Suite" launcher** that opens **five fully self-contained applications**, each with its own login, its own per-stakeholder dashboards, its own end-to-end workflows, and its own visual identity — bound together only by a shared premium design system. The goal: the board of directors / evaluation committee can open any one product and understand exactly how that real project will work, look, and feel — as a standalone system.

## 2. Goals

- Present five **independent** products; no cross-integration, no shared aggregate dashboard.
- A premium, modern, **government-grade** UI that conveys professionalism, trust, transparency, and technical excellence on every screen.
- Each product ships a distinct **dashboard for every stakeholder role** in its real workflow.
- Each product's demo conveys its real **look, feel, and workflow** to a non-technical board.
- Bilingual (Hindi / English) and accessibility-forward (GIGW 3.0 / WCAG 2.1 AA cues) throughout.

## 3. Non-Goals

- No rebuild to the production stack. The demo stays on **PHP 8.2 + Tailwind + MariaDB (XAMPP)**; the production React 18 + TypeScript + PostgreSQL 16 stack is presented in architecture slides only.
- No cross-product data sharing, no unified "Command Centre", no website←PPMS live API.
- No real external integrations (DigiLocker, GRAS/treasury, SMS) — these remain simulated within their owning product, as today.

## 4. Decisions (locked during brainstorming)

| # | Decision |
|---|----------|
| 1 | **Launcher hub + 5 independent apps.** A thin official landing page selects a product; each opens self-contained. |
| 2 | Launcher is an **official "WRD Project Suite"** page (premium, branded), not a neutral demo selector. |
| 3 | **Unified premium design system + per-project identity** — shared typography/components/accessibility; each product has its own accent colour, icon, name. |
| 4 | **Keep PHP + Tailwind + MariaDB**; production stack shown in slides. |
| 5 | **Every stakeholder role** in each product gets its own dashboard. |
| 6 | **Remove cross-integration** — website no longer pulls PPMS stats; each product's MIS/command view is scoped to itself. |
| 7 | **Bilingual Hindi/English** across all five products. |

## 5. Visual / Design System

A single shared design language (the "visual bar" approved in the mockup) themed per product via an **accent token**.

- **Palette:** deep government navy (`#0a2a44` / `#0f2740`) base; teal family for primary actions; per-product accent:
  - PPMS `#0e7c86` · Contractor `#2563eb` · Allocation `#0891b2` · E-Tariff `#059669` · Website/CMS `#4f46e5`
- **Typography:** display = Plus Jakarta Sans; body/UI = Inter.
- **Chrome on every screen:**
  - **Government utility bar** — "Government of Jharkhand", skip-to-content, font-size A−/A/A+, high-contrast toggle, Hindi/English switch.
  - **Department header** — Jharkhand state emblem + "Water Resources Department" + product name/accent.
  - **Trust signals** — GIGW 3.0, WCAG 2.1 AA, DPDP Act 2023, CERT-In, bilingual badges (on launcher; condensed in product footers).
- **Components:** cards with accent top-border, KPI tiles with accent left-border, themed sidebar with active-item accent rail, doughnut/bar/line charts (Chart.js), GIS via Leaflet.
- **Motion:** subtle lift/translate on hover, restrained entrance animations. Polished, never flashy.

## 6. Architecture

### 6.1 Launcher
`index.php` → **WRD Project Suite** page: emblem + department identity, bilingual hero, five premium product cards (accent + icon + one-liner + "Open demo →"), plus a "Standards & Security" card linking an architecture/compliance overview. No aggregated stats. No live cross-feeds.

### 6.2 Five self-contained apps
Each lives under `app/<project>/` and provides:
- **Own landing/login** — app-branded, with one-click role quick-pick for the demo.
- **Own themed shell** — header + sidebar carrying that product's name, accent, icon. The retired integrated sidebar ("Integrated Suite · 5 Components") is replaced by a **per-app sidebar**.
- **Own role-scoped dashboards** — one per stakeholder, showing only that product's data.
- **Own end-to-end workflows.**
- **Own demo role switcher** — scoped to that product's roles only (not the global hierarchy).

### 6.3 Shared layer (design system, not navigation)
`includes/` keeps the premium component base, auth/RBAC engine, i18n, and helpers — parameterised by an **accent token + branding context** per app. Refactor required:
- `includes/sidebar.php` → per-app navigation driven by an app config (nav items, roles, accent) instead of the hard-coded integrated menu.
- `includes/header.php` / `footer.php` → consume the active app's branding/accent + government chrome.
- Introduce a small **app-context** mechanism (e.g. `includes/app_context.php`) that each product's pages set (product key, name, accent, role list, nav) before rendering the shell.

### 6.4 Data
One demo MariaDB (`wrd_demo`) retained for convenience, but **each app queries only its own tables** (logical separation by product). Seed data (`setup.php` / `sql/seed.php`) is reorganised so each product has self-consistent, realistic Jharkhand data independent of the others. Slides note production isolates each product in its own PostgreSQL database.

### 6.5 Removals
- The aggregate `app/dashboard.php` "Command Centre" (cross-module) is **replaced** by a PPMS-scoped command centre and removed as a shared page.
- `api/ppms_stats.php` live feed into the public website is **removed**; the website (product ⑤) is self-contained.
- Global cross-module role switcher and "5 Components / One Backbone" branding removed.

## 7. The Five Products

Each product = own login → per-role dashboards → core workflow. Roles confirmed with the user.

### ① PPMS — Project Progress Monitoring System  (accent `#0e7c86`)
- **Roles:** Junior Engineer (field progress/measurement entry), Assistant Engineer (verify), Executive Engineer (raise fund requisition, division oversight), Superintending/Chief Engineer (circle/zone), Engineer-in-Chief (state), Finance Officer (fund allocation/release), Secretary (Command Centre).
- **Dashboards:** field-entry · division progress · **state Command Centre (GIS + BI/MIS)**.
- **Core flow:** progress capture → fund requisition → finance release → PDF fund-release certificate.

### ② Contractor Registration & Empanelment  (accent `#2563eb`)
- **Roles:** Contractor/applicant, Section Officer (ASO scrutiny), AE/EE (technical verification), Registering Authority/EIC (approve & issue), Public verifier.
- **Dashboards:** contractor self-service portal · registering-authority back office · public QR certificate verification.
- **Core flow:** register + upload → scrutiny → verification → e-certificate (QR + DigiLocker push, simulated).

### ③ Industrial Water Allocation  (accent `#0891b2`)
- **Roles:** Industry applicant, Assistant Engineer (feasibility/hydrology scrutiny), Executive Engineer (recommend), Chief Engineer/Secretary (approve), licence holder.
- **Dashboards:** applicant portal · reviewing-officer desk · approving-authority view.
- **Core flow:** application → technical scrutiny → recommendation → approval → licence issued.

### ④ Water E-Tariff & Billing  (accent `#059669`)
- **Roles:** Industrial consumer, Junior Engineer (drawal/meter entry, draft bill), Assistant Engineer (verify), Executive Engineer (finalize demand), Accounts/Division (revenue), Secretary/EIC (revenue MIS).
- **Dashboards:** consumer portal · billing-officer desk · **revenue & collection MIS**.
- **Core flow:** drawal entry → bill generation (tariff slabs) → approval → online payment (GRAS/treasury, simulated) → receipt.

### ⑤ Departmental Website + CMS  (accent `#4f46e5`)
- **Roles:** Citizen/public, Content Editor (draft), Approver/Admin (publish), Grievance/RTI officer.
- **Dashboards:** public bilingual website · CMS admin (editor + approver workspace).
- **Core flow:** editor drafts → approver publishes → appears on public site; citizen services (tenders, schemes, RTI, grievance). **Self-contained — no PPMS feed.**

## 8. Component Boundaries

- **Launcher** — purpose: choose a product. Depends on: nothing but branding. No data queries.
- **App shell** (header/sidebar/footer + app-context) — purpose: consistent themed chrome + per-app nav/roles. Depends on: app-context, i18n, auth. Each app sets its own context; shell does not know about other apps.
- **Auth/RBAC engine** — purpose: login + role for the active product only. Depends on: nothing app-specific; role lists supplied per app.
- **Per-product modules** — purpose: that product's dashboards + workflow. Depends on: app shell + its own tables only. Independently understandable and testable.

A reader can understand any one product without reading another's internals; changing one product must not affect others (enforced by no shared nav/data/feeds).

## 9. Demo Walkthrough (board-facing)

1. Open **WRD Project Suite** launcher — show branding, bilingual toggle, accessibility, five products.
2. Open each product in turn; for each: log in (role quick-pick) → show that role's dashboard → run the core workflow → switch roles to show the approval chain → show the artifact (certificate / licence / receipt / published notice).
3. Each product stands alone — close it, return to the launcher, open the next. The board sees five distinct, complete systems.

## 10. Risks & Mitigations

- **Inconsistency across five apps** → mitigated by the shared design-system layer + app-context theming.
- **Demo time budget** → each product self-contained with a clear 2–3 min core flow; launcher enables jumping straight to any product.
- **Refactor regressions** (shared includes now parameterised) → migrate one product first as the reference implementation, validate, then apply the pattern to the rest.

## 11. Open Items

None blocking. Production database-per-product isolation and real external integrations are explicitly out of scope for the demo (covered in slides).
