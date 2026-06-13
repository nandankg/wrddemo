# WRD Jharkhand — Integrated Software Solution (Demo)

A fully working demonstration of the **Integrated Digital Backbone** for the Water Resources
Department, Government of Jharkhand, covering all five RFP components:

| # | Component | Module folder |
|---|-----------|---------------|
| A | Project Progress Monitoring System (PPMS) — Fund Requisition, MIS, BI dashboard, GIS | `app/ppms/` |
| B | Contractor Registration & Empanelment — wizard, ASO→EIC workflow, QR cert, DigiLocker | `app/contractor/` |
| C | Industrial Water Allocation — allocation engine, AE→Secretary workflow, licence | `app/allocation/` |
| D | Water E-Tariff & Billing — drawal entry, JE→AE→EE workflow, **division-wise revenue routing** | `app/etariff/` |
| E | CMS Departmental Website — bilingual public site + admin CMS | `index.php`, `public/`, `app/cms/` |

> **Stack note:** This demo runs on **PHP 8.2 + MariaDB (XAMPP)** for speed of build. The
> *production* solution per the RFP is **React 18 + TypeScript + Node/Django/Laravel + PostgreSQL 16**.
> Present the demo as a functional prototype; show the React/PostgreSQL stack in the architecture slides.

---

## Setup (one time, ~30 seconds)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open **http://localhost/WRD/setup.php** — this auto-creates the `wrd_demo` database, all tables,
   and rich Jharkhand seed data. (Re-run any time to reset to a pristine demo state.)
3. Click **Launch the Portal** → http://localhost/WRD/

## Logins

All accounts use password **`demo123`**. The login page has **one-click role buttons**, and every
internal screen has a **role-switcher (bottom-left sidebar)** so you can jump across the approval
hierarchy instantly during the presentation.

| Username | Role | Use for |
|----------|------|---------|
| `secretary` | Secretary | Command Centre, allocation final approval |
| `eic` | Engineer-in-Chief | Approvals, contractor issue |
| `ee` | Executive Engineer | Raise fund requisition, approve bills |
| `ae` | Assistant Engineer | Verify bills |
| `je` | Junior Engineer | Drawal entry, draft bills |
| `finance` | Finance Officer | Fund allocation |
| `aso` | Section Officer | Contractor processing |
| `consumer` | Industrial Consumer | View & pay water bill |

---

## 🎬 Suggested demo-day sequence (≈12 min of live system)

1. **Landing page** (`/`) — toggle **हिंदी/English**, show accessibility toolbar (A+/contrast),
   live stats, ticker. *(NIC/GIGW marks.)*
2. **Command Centre** (login as `secretary`) — GIS map of Jharkhand, drill-down, division-wise
   revenue, fund-requisition doughnut. *(The "boss view" moment.)*
3. **PPMS Fund Requisition** — open requisition #4 (*Mandal Dam*, "Pending Review"). Switch roles
   `SE → FINANCE → ADMIN`, advancing it to **Released**, then open the **PDF Fund Release Certificate**.
4. **E-Tariff division-wise revenue** — open a "Demand Raised" bill → **Pay Now** → watch the money
   route **Consumer → JE-GRAS → correct Division account** → receipt. *(The centerpiece.)*
5. **Contractor Registration** — run the Aadhaar-OTP wizard; then issue a certificate and show the
   **QR verify** page + **Push to DigiLocker**.
6. **Industrial Allocation** — forward an application `AE→…→Secretary`, approve → **licence generated**.
7. **CMS** — publish a notice as `admin`; show it appear instantly on the public **Tenders & Notices** page.

## Key files
- `setup.php` / `sql/seed.php` — installer & seed data
- `config/` — DB connection & config
- `includes/` — shared layout, i18n, auth/RBAC, design system
- `assets/css/app.css` — "Institutional Water Authority" design system
- `api/ppms_stats.php` — live read-only feed (website ← PPMS)
