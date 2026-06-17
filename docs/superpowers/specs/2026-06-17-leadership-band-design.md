# Leadership / Dignitaries Band — Design

**Date:** 2026-06-17
**Goal:** Show a dignified "Our Leadership / नेतृत्व" band with the WRD Jharkhand leadership (CM, Minister, Secretary, Joint Secretary) on the public home pages of the demo suite.

## Decisions (from brainstorming)
- **Scope:** public home pages only — `index.php` (suite launcher) and `public/contractor.php`. (When `public/home.php` for the website product is built, it should also call `render_leaders()`.) Not on login-gated app dashboards.
- **Photos:** dignified placeholders now (initials on an ink→brand gradient circle), each card auto-upgrades to `assets/img/leaders/<slug>.jpg` if that file exists — no code change to add real photos.
- **Lineup (protocol order):** CM Shri Hemant Soren → Minister Shri Hafizul Hassan → Secretary Shri Prashant Kumar → Joint Secretary (unnamed). No Governor.
- **Style:** bordered band below the hero; bilingual heading; responsive portrait row (4 across desktop, 2×2 mobile); CM first/leftmost.

## Architecture
One reusable partial `includes/leaders.php`, two units:
- `wrd_leaders(): array` — pure data; each entry `['slug','name','name_hi','designation','designation_hi']`. Unit-tested.
- `render_leaders(): void` — echoes the band HTML using `is_hi()`, `base_url()`, `e()`. For each leader: render `assets/img/leaders/<slug>.jpg` if `file_exists`, else a placeholder circle with initials derived from the English name (honorifics like "Shri" stripped; e.g. Hemant Soren → "HS", Joint Secretary → "JS").

Data:
| slug | name / name_hi | designation / designation_hi |
|---|---|---|
| hemant-soren | Shri Hemant Soren / श्री हेमंत सोरेन | Hon'ble Chief Minister, Jharkhand / माननीय मुख्यमंत्री, झारखंड |
| hafizul-hassan | Shri Hafizul Hassan / श्री हफीजुल हसन | Hon'ble Minister, Water Resources Dept. / माननीय मंत्री, जल संसाधन विभाग |
| prashant-kumar | Shri Prashant Kumar / श्री प्रशांत कुमार | Secretary, WRD / सचिव, जल संसाधन विभाग |
| joint-secretary | Joint Secretary / संयुक्त सचिव | Water Resources Department / जल संसाधन विभाग |

## Integration
- `index.php`: `require_once includes/leaders.php` and call `render_leaders()` after the hero `<section>`, before the product-cards section.
- `public/contractor.php`: same, after its hero section.
- `assets/img/leaders/README.txt`: lists exact drop-in filenames + recommended square dimensions (~400×400). No binary images committed.

## Testing
- Unit test `tests/leaders_test.php`: `wrd_leaders()` returns 4 entries, each with all five keys; slugs are the expected set.
- `php -l` on the partial and both home pages; smoke-render each home page (band appears with placeholders); full suite stays green.

## Out of scope
Real photographs (dropped in later by the user); a CMS-managed leadership list; rendering on internal app dashboards.
