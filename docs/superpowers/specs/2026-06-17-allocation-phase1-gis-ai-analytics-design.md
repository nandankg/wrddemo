# Industrial Water Allocation Portal — Phase 1 Design

**Date:** 2026-06-17
**Module:** `app/allocation` (Component-C, RFP §8)
**Goal:** Build the three "presentation gold" demo screens that win the 20-mark live-presentation
criterion of the WRD Jharkhand tender — GIS source selection, an AI assist panel, and an analytics /
CEO dashboard — extending the existing PHP/MySQL allocation module.

## Why these three

The technical bid is 85% of the award. Its single biggest line item is *"Presentation —
Understanding of the Scope of Work, User-friendly Application and Timeline for Deliverables —
20 marks."* The demo must prove (a) scope understanding, (b) a user-friendly working app, and
(c) deliverable timeline. The storyboard (`waterallocation.md`) explicitly flags GIS ("where you
gain marks"), analytics ("presentation gold"), and the AI engine (bonus technical marks) as the
high-value screens. The base allocation module already covers apply / track / officer desk /
workflow / licence; these three close the gap.

## Demo-day reliability decisions (locked)

- **GIS map:** self-hosted Leaflet + a bundled Jharkhand district GeoJSON, rendered as polygons on a
  light background with **no online tile layer**. Nothing loads from the internet — the map cannot
  break on flaky venue WiFi.
- **AI engine:** rule-based and deterministic — recommendation, risk, and executive summary come
  from our own scoring logic + templates. Reads like AI, runs instantly, identical output every
  rehearsal and on stage. No API key, no latency, no live-failure risk.

## Shared data backbone — `water_sources`

One new table is the single source of truth for all three features.

```sql
CREATE TABLE water_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120), name_hi VARCHAR(160),
    type VARCHAR(30),                 -- Reservoir | Dam | River | Canal
    district VARCHAR(80),
    lat DECIMAL(9,6), lng DECIMAL(9,6),
    total_capacity_mld DECIMAL(10,2),
    allocated_mld DECIMAL(10,2),
    season VARCHAR(20),               -- Perennial | Seasonal
    status VARCHAR(20)                -- Active | Restricted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Seeded with ~12 real Jharkhand sources (Tenughat, Getalsud, Maithon, Panchet, Konar, Tilaiya,
Subarnarekha, Damodar, Chandil, Massanjore, Kanke, Hatia) with plausible capacity / utilisation and
approximate coordinates. Utilisation % = `allocated_mld / total_capacity_mld`.

Added to `setup.php`: schema block, the drop-table list, and a seed block.

## Feature 1 — GIS source selection (`app/allocation/map.php` + assets)

- **Self-hosted Leaflet** vendored to `assets/vendor/leaflet/` (js + css + marker images).
- **Bundled Jharkhand district GeoJSON** at `assets/geo/jharkhand-districts.geojson`, drawn as
  polygons on a light background. No tile layer.
- Source pins coloured by utilisation: green <70%, amber 70–90%, red >90%. Click a pin → popup with
  capacity %, allocated MLD, distance from the applicant's district (haversine), and existing
  allocation count for that source.
- **Integration:** reachable from the New Application flow. Applicant selects district → map focuses
  → clicks a source → "Select this source" writes `source`, `source_name` (and a `source_id`) back
  into the existing application form. The current dropdown remains as a fallback.
- Satisfies RFP §8.3 (GIS integration) and storyboard Screen 3.

## Feature 2 — AI assist panel (logic in `lib.php`, UI in apply flow)

Three pure, deterministic functions (no DB, no rendering — unit-testable):

- `allocation_recommend_source(string $district, float $quantity, array $sources): array`
  Ranks candidate sources by available headroom (`total - allocated >= quantity`) then proximity;
  returns the best with reason fields. Output e.g. *"Suggested: Tenughat Reservoir — 78% capacity,
  14 km, perennial."*
- `allocation_risk(array $app): string` — returns `LOW` / `MEDIUM` / `HIGH` from document
  completeness, capacity headroom, and season match, with a short justification list.
- `allocation_exec_summary(array $app): string` — templated paragraph naming applicant, quantity,
  source, clearances, and recommendation.

UI: a panel on the application form showing recommendation + risk badge, plus a **Generate Executive
Summary** button that reveals the summary text instantly. Satisfies storyboard bonus features.

## Feature 3 — Analytics & CEO dashboard (`app/allocation/analytics.php`)

- District-wise and source-wise **availability bars** computed from `water_sources`.
- **Chart.js** vendored to `assets/vendor/chartjs/` — allocation trend, consumption, and
  source-utilisation charts (data from `water_sources` + `allocations`).
- A clickable, colour-coded **Jharkhand district map** (reuses the same Leaflet GeoJSON), shaded by
  allocation load; clicking a district drills down to a panel listing that district's sources and
  allocations.
- Access: officer/leadership roles (CE / EIC / SECRETARY) via a link from the allocation dashboard.
- Satisfies RFP §8.2.9–8.2.10 (dashboard, reports & MIS) and storyboard Screens 7 & 10.

## Components & boundaries

| Unit | Purpose | Depends on |
|------|---------|-----------|
| `water_sources` table + seed | shared data | `setup.php` |
| `assets/vendor/leaflet/`, `assets/vendor/chartjs/`, `assets/geo/jharkhand-districts.geojson` | offline vendored libs + map data | — |
| `map.php` | GIS source picker | Leaflet, GeoJSON, `water_sources` |
| `lib.php` (3 new functions) | recommendation / risk / summary | none (pure) |
| apply flow in `index.php` | surfaces map + AI panel | `lib.php`, `map.php` |
| `analytics.php` | dashboards + drill-down | Chart.js, Leaflet, `water_sources`, `allocations` |

## Error handling

- `map.php` / `analytics.php` degrade gracefully if `water_sources` is empty (show an empty-state
  card, no JS errors).
- Distance calc tolerates missing coordinates (skips distance, still ranks by headroom).
- AI functions never throw on missing fields — absent documents simply raise risk.

## Testing

- Pure-logic unit tests in `tests/` (existing pattern) for `allocation_recommend_source`,
  `allocation_risk`, `allocation_exec_summary`, and the existing `allocation_annual_fee`.
- Map and charts are visual — verified by running the app and walking the ABC Steel path.

## Out of scope (Phase 1)

JE-GRASS challan flow, QR/digital-signature licence, renewal/inspection/billing tabs (Phase 2);
the app's Tailwind/fonts CDN dependency (pre-existing); any change to the approval workflow or
existing tables beyond adding `water_sources`.
