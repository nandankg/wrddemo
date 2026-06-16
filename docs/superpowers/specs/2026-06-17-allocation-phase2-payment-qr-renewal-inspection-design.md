# Industrial Water Allocation Portal — Phase 2 Design

**Date:** 2026-06-17
**Module:** `app/allocation` (Component-C, RFP §8)
**Goal:** Close the remaining scope-coverage gap for the demo — JE-GRASS payment, a real
scannable QR + digitally-signed licence with public verification, licence renewal, and field
inspection/enforcement — extending the Phase 1 build.

## Why these

Phase 1 delivered the "presentation gold" screens (GIS, AI assist, analytics). Phase 2 makes the
demo visibly cover the rest of RFP §8.2 so the panel scores "Understanding of the Scope of Work":
- §8.2.5 Secure Treasury Payment Integration (JE-GRASS) — challan, receipt, reconciliation.
- §8.2.2 Automated licence generation — now with a real verifiable QR + digital signature.
- §8.2.3 Renewal & annual demand.
- §8.2.8 Inspection & Enforcement.

§8.2.4 (billing) is intentionally folded into the annual-fee/payment view — consumption billing is
Component-D (E-Tariff)'s domain and must not be duplicated here.

## Locked decisions

- **Real scannable QR:** vendor a small offline QR JS library; the licence QR encodes an absolute
  URL to a new public `licence_verify.php`. A panelist can scan it on the Hostinger live site.
- **Scope:** Payment + QR licence + Renewal + Inspection. No separate billing subsystem; no real PKI
  (the digital signature is a deterministic simulated block); no real treasury API.

## Schema additions (`setup.php` + `sql/seed.php`)

Add columns to `allocations`:

```sql
qr_token     VARCHAR(24) NULL,   -- public verification token (licence)
fee_status   VARCHAR(20) DEFAULT 'Unpaid',  -- Unpaid | Paid
challan_no   VARCHAR(40) NULL,
paid_on      DATETIME    NULL,
valid_upto   DATE        NULL,   -- 5-year licence validity
renewed_from INT         NULL    -- parent allocation id when this is a renewal
```

New table:

```sql
CREATE TABLE inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    allocation_id INT, app_no VARCHAR(40),
    inspector VARCHAR(120),
    finding VARCHAR(40),   -- Compliant | Minor Violation | Major Violation
    action  VARCHAR(40),   -- None | Show-Cause | Penalty
    notes   TEXT,
    inspected_on DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`inspections` is added to the setup drop-list. The `payments` table is reused as-is via its
`source_module` field (`'allocation'`). Seed: the existing approved licence (WRD/IWA/2526/201) is
back-filled with `qr_token`, `fee_status='Paid'`, `challan_no`, `paid_on`, and a 5-year `valid_upto`;
plus one seeded inspection record so the log is non-empty on first load.

## A. JE-GRASS payment — `app/allocation/payment.php` (§8.2.5)

Reachable post-approval (`status='Approved'`) via `payment.php?id=<allocation_id>`.

1. **Generate challan** — on first load (or button), assign a deterministic `challan_no`
   (`JEGRAS/2526/<id>`), display fee = `annual_fee`.
2. **Pay via JE-GRASS** — POST simulates success: insert a `payments` row
   (`source_module='allocation'`, `txn_ref`, `amount`, `channel='JE-GRASS'`,
   `credited_account` = division bank a/c, `status='Success'`); set `fee_status='Paid'`,
   `challan_no`, `paid_on`; write an audit entry.
3. **Receipt** — printable receipt panel after success (txn ref, challan, amount, date, credited a/c).

Failed-transaction handling: re-visiting an already-paid record shows the receipt, never double-pays
(guard on `fee_status`). Dashboard surfaces "Pay licence fee" when approved+unpaid; the licence page
shows a "Fee Paid" stamp once paid. Payment does **not** hard-gate licence download (keeps the
approval→licence demo path smooth).

Pure helpers (lib.php): `allocation_challan_no(int $id)`, `allocation_fee_paid(array $a): bool`.

## B. QR + digitally-signed licence (§8.2.2)

- Vendor QR JS lib to `assets/vendor/qrcodejs/`. In `licence.php`, replace the decorative SVG QR with
  a `<div id="qrcode">` rendered by the lib, encoding `allocation_abs_url('app/allocation/licence_verify.php?token='.$qr_token)`.
- `allocation_abs_url()` builds an absolute `scheme://host/...` URL from `$_SERVER` (QR must be
  scannable off-device). Lives in lib.php, no DB.
- **Digital-signature block** replaces the plain "Secretary" line: "Digitally signed by Secretary,
  WRD · Sig ID `<allocation_signature_id()>` · `<timestamp>`". `allocation_signature_id(string
  $licenceNo, string $token): string` returns a short uppercase hex hash — deterministic.
- **`licence_verify.php`** — public (no auth), mirrors `app/contractor/verify.php`: looks up the
  allocation by `qr_token` with `status='Approved'`; renders an Authentic & Valid card (applicant,
  licence no, source + quantity, valid-upto, fee status) or a Not Found state.

## C. Renewal — `app/allocation/renewals.php` (§8.2.3)

Lists approved licences with `valid_upto`; flags those within 90 days of expiry (or expired).
Applicant action **File Renewal** creates a new `allocations` row (`renewed_from`=parent,
`stage='AE'`, `status='New'`, copied source/quantity/division/gst), reusing the entire
approval+licence pipeline. Officers see all; applicants see their own.

Pure helpers: `allocation_days_to_expiry(string $validUpto, string $today): int`,
`allocation_is_due_renewal(string $validUpto, string $today, int $window=90): bool`.

## D. Inspection & Enforcement — `app/allocation/inspections.php` (§8.2.8)

Officer-only. Form to log an inspection against a licensed allocation (finding + enforcement action +
notes + date) → insert into `inspections` + audit. Table lists existing inspections with finding /
action badges. Show-cause and penalty are captured as the `action` value.

## Navigation (`includes/apps.php`)

Allocation nav becomes: `dashboard, map, applications, renewals, licences, inspections, analytics`.
`renewals` visible to all roles; `inspections` to officers (`AE,EE,SE,CE,EIC,SECRETARY`). Payment is
per-record (no nav item; linked from dashboard/licences).

## Components & boundaries

| Unit | Purpose | Depends on |
|------|---------|-----------|
| schema + seed | new columns, `inspections`, back-fill | `setup.php`, `sql/seed.php` |
| `lib.php` (new pure fns) | challan no, fee-paid, abs url, signature id, expiry/renewal | none (pure) |
| `payment.php` | challan → pay → receipt | `payments`, `allocations`, lib |
| `licence.php` (edit) | real QR + signature | qrcodejs, lib |
| `licence_verify.php` | public verification | `allocations` |
| `renewals.php` | expiry list + file renewal | `allocations`, lib |
| `inspections.php` | inspection log | `inspections`, `allocations` |
| `assets/vendor/qrcodejs/` | offline QR rendering | — |

## Error handling

- Payment guards against double-pay (`fee_status` check) and missing/non-approved records (404/redirect).
- `licence_verify.php` handles unknown/blank token with a Not Found card; never errors.
- Renewal copies only from approved parents; `valid_upto` null tolerated (treated as not-due).
- Inspection form validates allocation_id belongs to a real allocation.

## Testing

Pure-function unit tests in `tests/allocation_test.php`: `allocation_challan_no` format,
`allocation_fee_paid`, `allocation_days_to_expiry` / `allocation_is_due_renewal` boundaries,
`allocation_signature_id` determinism + shape, `allocation_abs_url` composition. Update the nav-order
assertion in `tests/apps_test.php`. Flows verified live (login, pay, scan-verify, renew, inspect) as
in Phase 1.

## Out of scope

Separate consumption-billing/demand-note subsystem (E-Tariff/Component-D); real PKI signing; real
JE-GRASS/treasury API; SMS/email dispatch (PPMS already demonstrates notifications).
