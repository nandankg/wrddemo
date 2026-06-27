# Contractor Phase 3 — Empanelment, Revenue & Oversight Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the contractor module's management dashboards — a filterable empanelment registry (Screen 9), a revenue dashboard (Screen 13), and a Secretary/CE command dashboard with a Jharkhand district map (Screen 14) — all driven by one consistent richer seed.

**Architecture:** Pure aggregation/filter logic in `app/contractor/lib.php` (DB-free, render-free, unit-tested). DB queries + rendering in page files. Revenue derives from `contractor_apps` (no payments table). Map and charts reuse the vendored, offline Leaflet + bundled GeoJSON + Chart.js already proven in `app/allocation/analytics.php`.

**Tech Stack:** PHP 8 (procedural), MySQL via PDO (`db()`), server-rendered HTML + Tailwind utility classes, Chart.js (`assets/vendor/chartjs/chart.umd.js`), Leaflet (`assets/vendor/leaflet/`), bundled `assets/geo/jharkhand-districts.geojson`. Zero-dependency test harness (`tests/run.php`).

## Global Constraints

- No new Composer/JS dependencies — reuse the vendored Chart.js/Leaflet/GeoJSON; server-rendered PHP only.
- All new pure functions live in `app/contractor/lib.php` and stay DB-free and render-free.
- Bilingual UI: every user-facing string uses `is_hi() ? 'हिन्दी' : 'English'`.
- Escape all dynamic HTML output with `e()`; JSON passed to JS uses `json_encode(...)`.
- Tests run with `php tests/run.php` (auto-globs `tests/*_test.php`); the suite is green (74 passing) and must stay green.
- GeoJSON districts are keyed by `properties.district`; seed contractor districts MUST use these exact 24 names: Bokaro, Chatra, Deoghar, Dhanbad, Dumka, East Singhbhum, Garhwa, Giridih, Godda, Gumla, Hazaribagh, Jamtara, Khunti, Koderma, Latehar, Lohardaga, Pakur, Palamu, Ramgarh, Ranchi, Sahibganj, Saraikela-Kharsawan, Simdega, West Singhbhum.
- Effective status rule (used everywhere): `Blacklisted` if stored status is Blacklisted; else `Expired` if `valid_upto < today`; else the stored status. Both filter and matrix classify by this.
- Registration fees by class: I=45000, II=30000, III=20000, IV=10000.
- Indian financial year runs Apr 1 – Mar 31.
- Officer/role gating lives in `includes/apps.php`; the new dashboards are gated `['EE','EIC','SECRETARY']`.

---

### Task 1: Aggregation & filter logic (pure functions)

**Files:**
- Modify: `app/contractor/lib.php` (append six functions)
- Test: `tests/contractor_test.php` (append cases)

**Interfaces:**
- Produces:
  - `contractor_effective_status(array $c, string $today): string`
  - `contractor_filter(array $contractors, array $f, ?string $today = null): array`
  - `contractor_empanelment_matrix(array $contractors, ?string $today = null): array`
  - `contractor_revenue_kpis(array $apps, ?string $today = null): array`
  - `contractor_monthly_collection(array $apps, ?string $today = null): array`
  - `contractor_district_rollup(array $contractors, array $apps): array`

- [ ] **Step 1: Write the failing tests**

Append to `tests/contractor_test.php`:

```php
it('contractor_effective_status applies Blacklisted > Expired > stored', function () {
    $t = '2026-06-27';
    assert_eq('Active',      contractor_effective_status(['status'=>'Active','valid_upto'=>'2027-03-31'], $t));
    assert_eq('Expired',     contractor_effective_status(['status'=>'Active','valid_upto'=>'2025-01-01'], $t));
    assert_eq('Suspended',   contractor_effective_status(['status'=>'Suspended','valid_upto'=>'2028-01-01'], $t));
    assert_eq('Blacklisted', contractor_effective_status(['status'=>'Blacklisted','valid_upto'=>'2024-01-01'], $t)); // outranks Expired
});

it('contractor_filter matches district/class/category/effective-status', function () {
    $t = '2026-06-27';
    $cs = [
      ['id'=>1,'class'=>'I','district'=>'Ranchi','category'=>'Civil','status'=>'Active','valid_upto'=>'2027-03-31'],
      ['id'=>2,'class'=>'I','district'=>'Ranchi','category'=>'Civil','status'=>'Active','valid_upto'=>'2025-01-01'], // -> Expired
      ['id'=>3,'class'=>'II','district'=>'Dhanbad','category'=>'Mechanical','status'=>'Suspended','valid_upto'=>'2028-01-01'],
      ['id'=>4,'class'=>'I','district'=>'Bokaro','category'=>'Civil','status'=>'Blacklisted','valid_upto'=>'2024-01-01'],
    ];
    assert_eq(1, count(contractor_filter($cs, ['status'=>'Active'], $t)));     // only id1
    assert_eq(1, count(contractor_filter($cs, ['status'=>'Expired'], $t)));    // only id2
    assert_eq(2, count(contractor_filter($cs, ['district'=>'Ranchi'], $t)));   // id1,id2
    assert_eq(3, count(contractor_filter($cs, ['class'=>'I'], $t)));           // id1,id2,id4
    assert_eq(3, count(contractor_filter($cs, ['category'=>'Civil'], $t)));    // id1,id2,id4
    assert_eq(1, count(contractor_filter($cs, ['district'=>'Ranchi','status'=>'Active'], $t)));
    assert_eq(4, count(contractor_filter($cs, [], $t)));                       // no filters
});

it('contractor_empanelment_matrix counts active/suspended/expired per class', function () {
    $t = '2026-06-27';
    $cs = [
      ['class'=>'I','status'=>'Active','valid_upto'=>'2027-03-31'],
      ['class'=>'I','status'=>'Active','valid_upto'=>'2025-01-01'],   // Expired
      ['class'=>'I','status'=>'Blacklisted','valid_upto'=>'2024-01-01'], // none of the 3
      ['class'=>'II','status'=>'Suspended','valid_upto'=>'2028-01-01'],
    ];
    $m = contractor_empanelment_matrix($cs, $t);
    assert_eq(1, $m['I']['active']);
    assert_eq(1, $m['I']['expired']);
    assert_eq(0, $m['I']['suspended']);
    assert_eq(1, $m['II']['suspended']);
    assert_eq(0, $m['III']['active']);
});

it('contractor_revenue_kpis splits total/new/renewal and current-FY (paid only)', function () {
    $apps = [
      ['contractor_id'=>1,'type'=>'New','fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-05-10'],
      ['contractor_id'=>1,'type'=>'Renewal','fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-06-01'],
      ['contractor_id'=>3,'type'=>'New','fee'=>30000,'fee_paid'=>0,'applied_on'=>'2026-06-02'], // unpaid -> excluded
      ['contractor_id'=>3,'type'=>'New','fee'=>30000,'fee_paid'=>1,'applied_on'=>'2026-02-15'], // before FY -> not in fy
      ['contractor_id'=>4,'type'=>'New','fee'=>10000,'fee_paid'=>1,'applied_on'=>'2024-12-01'],
    ];
    $k = contractor_revenue_kpis($apps, '2026-06-27'); // FY start 2026-04-01
    assert_eq(130000.0, $k['total']);
    assert_eq(45000.0,  $k['renewal']);
    assert_eq(85000.0,  $k['new']);
    assert_eq(90000.0,  $k['fy']);
});

it('contractor_monthly_collection buckets the trailing 12 months', function () {
    $apps = [
      ['fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-05-10'],
      ['fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-06-01'],
      ['fee'=>30000,'fee_paid'=>0,'applied_on'=>'2026-06-02'], // unpaid
      ['fee'=>30000,'fee_paid'=>1,'applied_on'=>'2026-02-15'],
      ['fee'=>10000,'fee_paid'=>1,'applied_on'=>'2024-12-01'], // out of window
    ];
    $mc = contractor_monthly_collection($apps, '2026-06-27');
    $keys = array_keys($mc);
    assert_eq(12, count($mc));
    assert_eq('2025-07', $keys[0]);
    assert_eq('2026-06', $keys[11]);
    assert_eq(45000.0, $mc['2026-05']);
    assert_eq(45000.0, $mc['2026-06']);
    assert_eq(30000.0, $mc['2026-02']);
    assert_eq(120000.0, array_sum($mc));
});

it('contractor_district_rollup aggregates count and paid revenue per district', function () {
    $cs = [
      ['id'=>1,'district'=>'Ranchi'],['id'=>2,'district'=>'Ranchi'],
      ['id'=>3,'district'=>'Dhanbad'],['id'=>4,'district'=>'Bokaro'],
    ];
    $apps = [
      ['contractor_id'=>1,'fee'=>45000,'fee_paid'=>1],
      ['contractor_id'=>1,'fee'=>45000,'fee_paid'=>1],
      ['contractor_id'=>3,'fee'=>30000,'fee_paid'=>1],
      ['contractor_id'=>3,'fee'=>30000,'fee_paid'=>0], // unpaid
      ['contractor_id'=>4,'fee'=>10000,'fee_paid'=>1],
    ];
    $r = contractor_district_rollup($cs, $apps);
    assert_eq(2, $r['Ranchi']['count']);
    assert_eq(90000.0, $r['Ranchi']['revenue']);
    assert_eq(1, $r['Dhanbad']['count']);
    assert_eq(30000.0, $r['Dhanbad']['revenue']);
    assert_eq(10000.0, $r['Bokaro']['revenue']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php`
Expected: FAIL — the six new functions are undefined.

- [ ] **Step 3: Add the functions**

Append to `app/contractor/lib.php`:

```php
/* ===========================================================================
 * Phase 3 — empanelment filtering, revenue aggregation, district rollup.
 * All deterministic and offline-safe (no DB, no network).
 * =========================================================================== */

/** Effective status: Blacklisted > Expired (valid_upto < today) > stored status. */
function contractor_effective_status(array $c, string $today): string {
    $s = (string)($c['status'] ?? '');
    if ($s === 'Blacklisted') return 'Blacklisted';
    if (!empty($c['valid_upto']) && (string)$c['valid_upto'] < $today) return 'Expired';
    return $s;
}

/** Filter contractors by district/class/category/status ('' = no filter for that key). */
function contractor_filter(array $contractors, array $f, ?string $today = null): array {
    $today = $today ?: date('Y-m-d');
    $out = [];
    foreach ($contractors as $c) {
        if (!empty($f['district']) && ($c['district'] ?? '') !== $f['district']) continue;
        if (!empty($f['class'])    && ($c['class'] ?? '')    !== $f['class'])    continue;
        if (!empty($f['category']) && ($c['category'] ?? '') !== $f['category']) continue;
        if (!empty($f['status'])   && contractor_effective_status($c, $today) !== $f['status']) continue;
        $out[] = $c;
    }
    return $out;
}

/** Per-class active/suspended/expired counts (by effective status). */
function contractor_empanelment_matrix(array $contractors, ?string $today = null): array {
    $today = $today ?: date('Y-m-d');
    $m = [];
    foreach (['I','II','III','IV'] as $cls) $m[$cls] = ['active'=>0,'suspended'=>0,'expired'=>0];
    foreach ($contractors as $c) {
        $cls = $c['class'] ?? '';
        if (!isset($m[$cls])) continue;
        switch (contractor_effective_status($c, $today)) {
            case 'Active':    $m[$cls]['active']++;    break;
            case 'Suspended': $m[$cls]['suspended']++; break;
            case 'Expired':   $m[$cls]['expired']++;   break;
        }
    }
    return $m;
}

/** Revenue KPIs from paid applications: total, renewal, new, and current-FY-to-date. */
function contractor_revenue_kpis(array $apps, ?string $today = null): array {
    $today = $today ?: date('Y-m-d');
    $ty = (int)substr($today, 0, 4); $tm = (int)substr($today, 5, 2);
    $fyStart = (($tm >= 4 ? $ty : $ty - 1)) . '-04-01';
    $k = ['total'=>0.0, 'renewal'=>0.0, 'new'=>0.0, 'fy'=>0.0];
    foreach ($apps as $a) {
        if ((int)($a['fee_paid'] ?? 0) !== 1) continue;
        $fee = (float)($a['fee'] ?? 0);
        $k['total'] += $fee;
        if (($a['type'] ?? '') === 'Renewal') $k['renewal'] += $fee; else $k['new'] += $fee;
        $on = (string)($a['applied_on'] ?? '');
        if ($on >= $fyStart && $on <= $today) $k['fy'] += $fee;
    }
    return $k;
}

/** Trailing-12-month collection series ['YYYY-MM'=>amount], oldest first, from paid apps. */
function contractor_monthly_collection(array $apps, ?string $today = null): array {
    $today = $today ?: date('Y-m-d');
    $anchor = strtotime(substr($today, 0, 7) . '-01');
    $months = [];
    for ($i = 11; $i >= 0; $i--) $months[date('Y-m', strtotime("-$i months", $anchor))] = 0.0;
    foreach ($apps as $a) {
        if ((int)($a['fee_paid'] ?? 0) !== 1) continue;
        $m = substr((string)($a['applied_on'] ?? ''), 0, 7);
        if (isset($months[$m])) $months[$m] += (float)($a['fee'] ?? 0);
    }
    return $months;
}

/** Per-district ['count'=>n,'revenue'=>paid fees] keyed by district name. */
function contractor_district_rollup(array $contractors, array $apps): array {
    $distOf = []; $roll = [];
    foreach ($contractors as $c) {
        $d = $c['district'] ?? '';
        if ($d === '') continue;
        $distOf[(int)$c['id']] = $d;
        if (!isset($roll[$d])) $roll[$d] = ['count'=>0, 'revenue'=>0.0];
        $roll[$d]['count']++;
    }
    foreach ($apps as $a) {
        if ((int)($a['fee_paid'] ?? 0) !== 1) continue;
        $d = $distOf[(int)($a['contractor_id'] ?? 0)] ?? null;
        if ($d !== null) $roll[$d]['revenue'] += (float)($a['fee'] ?? 0);
    }
    return $roll;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php`
Expected: PASS — `Passed: 80  Failed: 0` (74 prior + 6 new `it()` blocks).

- [ ] **Step 5: Commit**

```bash
git add app/contractor/lib.php tests/contractor_test.php
git commit -m "feat(contractor): empanelment filter, revenue & district-rollup logic (pure)"
```

---

### Task 2: `category` column + richer empanelment/revenue seed

**Files:**
- Modify: `setup.php` (add `category` to `CREATE TABLE contractors`)
- Modify: `sql/seed.php` (add `category` to the named-firm insert; append a generated dataset)

**Interfaces:**
- Produces: ~72 additional contractors across all 24 districts × 4 classes × 4 categories × statuses, each with a registration app plus annual renewal apps (all `fee_paid=1`) dated across past years and the trailing 12 months. Existing 8 named firms, the 4 workflow apps (ids 1–4), and the Phase-2 seeded queries are preserved.

- [ ] **Step 1: Add the `category` column to the schema**

In `setup.php`, in the `CREATE TABLE contractors (...)` block, add `category` after `turnover`:

```sql
        experience_yrs INT NULL, completed_projects INT NULL, turnover DECIMAL(14,2) NULL,
        category VARCHAR(20) NULL
```

- [ ] **Step 2: Add `category` to the named-firm seed insert**

In `sql/seed.php`, update the existing contractors INSERT to include `category`. Change the prepared statement column list and values:

```php
    $ins = $pdo->prepare('INSERT INTO contractors (reg_no,name,name_hi,class,pan,gst,district,status,risk_score,valid_upto,registered_on,cin,address,contact,experience_yrs,completed_projects,turnover,category,qr_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $namedCats = ['Civil','Civil','Mechanical','Irrigation','Civil','Electrical','Civil','Mechanical'];
    foreach ($contractors as $idx=>$c) { $c2 = $c; array_splice($c2, 17, 0, [$namedCats[$idx] ?? 'Civil']); $c2[] = bin2hex(random_bytes(6)); $ins->execute($c2); }
```

(The named `$contractors` rows have 17 positional values ending at `turnover`; this inserts the category as the 18th value before the generated `qr_token`.)

- [ ] **Step 3: Append the generated empanelment + revenue dataset**

In `sql/seed.php`, immediately AFTER the contractor-queries seed block added in Phase 2 (the two `$q->execute([...])` calls), add:

```php
    // ---- Phase 3: bulk empanelment + multi-year paid revenue history ----
    $P3_DIST = ['Bokaro','Chatra','Deoghar','Dhanbad','Dumka','East Singhbhum','Garhwa','Giridih','Godda','Gumla','Hazaribagh','Jamtara','Khunti','Koderma','Latehar','Lohardaga','Pakur','Palamu','Ramgarh','Ranchi','Sahibganj','Saraikela-Kharsawan','Simdega','West Singhbhum'];
    $P3_CAT  = ['Civil','Mechanical','Electrical','Irrigation'];
    $P3_CLS  = ['I','II','III','IV'];
    $P3_FEE  = ['I'=>45000.0,'II'=>30000.0,'III'=>20000.0,'IV'=>10000.0];
    $P3_TURN = ['I'=>60000000,'II'=>34000000,'III'=>17000000,'IV'=>6000000];
    $P3_EXP  = ['I'=>12,'II'=>8,'III'=>5,'IV'=>3];
    $cIns = $pdo->prepare('INSERT INTO contractors (reg_no,name,name_hi,class,pan,gst,district,status,risk_score,valid_upto,registered_on,cin,address,contact,experience_yrs,completed_projects,turnover,category,qr_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $aIns = $pdo->prepare('INSERT INTO contractor_apps (ack_no,contractor_id,type,class,stage,status,fee,fee_paid,applied_on) VALUES (?,?,?,?,?,?,?,?,?)');
    $seq = 459; $ack = 1100; $nowY = (int)date('Y'); $todayStr = date('Y-m-d');
    for ($i = 0; $i < 72; $i++) {
        $dist = $P3_DIST[$i % 24];
        $cls  = $P3_CLS[$i % 4];
        $cat  = $P3_CAT[intdiv($i, 4) % 4];
        $mod  = $i % 12;
        $status = $mod === 11 ? 'Suspended' : ($mod === 10 ? 'Blacklisted' : 'Active');
        $regYear = $nowY - (2 + ($i % 7));                          // registered 2..8 years ago
        $registered = sprintf('%04d-%02d-15', $regYear, 1 + ($i % 12));
        $valid = ($i % 6 === 5)
            ? date('Y-m-d', strtotime('-' . (20 + $i) . ' days'))   // lapsed -> effective Expired
            : sprintf('%04d-03-31', $nowY + 1 + ($i % 3));
        $reg  = sprintf('WRD/REG/3/%04d', $seq++);
        $risk = 12 + ($i * 13) % 75;
        $turn = $P3_TURN[$cls] + ($i % 9) * 1500000;
        $exp  = $P3_EXP[$cls] + ($i % 4);
        $proj = $exp + ($i % 7);
        $cIns->execute([$reg, "$dist $cat Works " . ($i + 1), '', $cls, 'AAAAA0000A', '20XXXX0000X1Z0',
            $dist, $status, $risk, $valid, $registered, 'U45200JH' . $regYear . 'PTC0' . $seq,
            "$dist, Jharkhand", '00000-000000', $exp, $proj, $turn, $cat, bin2hex(random_bytes(6))]);
        $cid = (int)$pdo->lastInsertId();
        // Registration fee in the registration year + an annual renewal each following year to date.
        $aIns->execute([sprintf('WRD/ACK/REG/%05d', $ack++), $cid, 'New', $cls, 'EIC', 'Approved', $P3_FEE[$cls], 1, $registered]);
        for ($y = $regYear + 1; $y <= $nowY; $y++) {
            $rd = sprintf('%04d-%02d-10', $y, 1 + (($i + $y) % 12));
            if ($rd > $todayStr) continue;
            $aIns->execute([sprintf('WRD/ACK/REN/%05d', $ack++), $cid, 'Renewal', $cls, 'EIC', 'Approved', $P3_FEE[$cls], 1, $rd]);
        }
    }
```

- [ ] **Step 4: Rebuild and sanity-check the aggregates**

Ensure XAMPP MySQL is running, then rebuild: `php setup.php` (or open `http://localhost/WRD/setup.php`).
Expected: completes without error.

Then verify the seed feeds the aggregates (uses the Task-1 functions):

```bash
php -r "chdir('C:/xampp/htdocs/WRD'); require 'includes/auth.php'; require 'app/contractor/lib.php'; \$p=db();
\$cs=\$p->query('SELECT * FROM contractors')->fetchAll();
\$apps=\$p->query('SELECT * FROM contractor_apps')->fetchAll();
\$k=contractor_revenue_kpis(\$apps);
echo 'contractors='.count(\$cs).' apps='.count(\$apps).PHP_EOL;
echo 'total=Rs'.number_format(\$k['total']).' renewal=Rs'.number_format(\$k['renewal']).' fy=Rs'.number_format(\$k['fy']).PHP_EOL;
\$m=contractor_empanelment_matrix(\$cs); echo 'ClassI active='.\$m['I']['active'].' expired='.\$m['I']['expired'].' suspended='.\$m['I']['suspended'].PHP_EOL;
\$r=contractor_district_rollup(\$cs,\$apps); echo 'districts='.count(\$r).' Ranchi count='.(\$r['Ranchi']['count']??0).PHP_EOL;"
```
Expected: contractors ≈ 80 (8 named + 72), apps in the hundreds, a multi-lakh/crore `total`, all 24 districts present in the rollup, and non-zero active/expired/suspended counts.

- [ ] **Step 5: Confirm the pure suite still passes and commit**

Run: `php tests/run.php`
Expected: PASS — `Passed: 80  Failed: 0` (DB-only changes; tests unaffected).

```bash
git add setup.php sql/seed.php
git commit -m "feat(contractor): category column + richer empanelment & revenue seed"
```

---

### Task 3: Nav & access for the new dashboards

**Files:**
- Modify: `includes/apps.php` (contractor app: add `SECRETARY` role + two nav items)

**Interfaces:**
- Produces: nav keys `revenue` (`app/contractor/revenue.php`) and `oversight` (`app/contractor/oversight.php`), both gated `['EE','EIC','SECRETARY']`, so the Task-5/6 pages' `app_require_access(...)` checks resolve.

- [ ] **Step 1: Add SECRETARY to the contractor app roles and the two nav items**

In `includes/apps.php`, in the contractor app definition: add `'SECRETARY'` to its top-level `'roles'` array (currently `['CONTRACTOR','ASO','AE','EE','EIC']`), and append two items to its `'nav'` array after the existing `verify` item:

```php
                ['key'=>'revenue','label'=>'Revenue MIS','url'=>'app/contractor/revenue.php','icon'=>'₹','roles'=>['EE','EIC','SECRETARY']],
                ['key'=>'oversight','label'=>'Command Centre','url'=>'app/contractor/oversight.php','icon'=>'🗺','roles'=>['EE','EIC','SECRETARY']],
```

- [ ] **Step 2: Verify nothing broke**

Run: `php -l includes/apps.php` (Expected: "No syntax errors") and `php tests/run.php` (Expected: `Passed: 80  Failed: 0` — the nav-access tests in `tests/nav_access_test.php` still pass).

- [ ] **Step 3: Commit**

```bash
git add includes/apps.php
git commit -m "feat(contractor): nav + access for Revenue MIS and Command Centre (EE/EIC/SECRETARY)"
```

---

### Task 4: Screen 9 — empanelment registry with filters

**Files:**
- Modify: `app/contractor/registry.php` (add filter bar + per-class status cards + filtered table)

**Interfaces:**
- Consumes: `contractor_filter`, `contractor_empanelment_matrix`, `contractor_effective_status` (Task 1); helpers `risk_band`, `bi`, `badge`, `e`, `base_url`, `is_hi`, `$APP`.

- [ ] **Step 1: Load filters + matrix and filter the rows**

In `app/contractor/registry.php`, replace the data-load line (`$contractors=$pdo->query(...)->fetchAll();`) with:

```php
$all = $pdo->query("SELECT * FROM contractors ORDER BY status='Blacklisted' DESC, name")->fetchAll();
$today = date('Y-m-d');
$f = [
  'district' => trim((string)($_GET['district'] ?? '')),
  'class'    => trim((string)($_GET['class'] ?? '')),
  'category' => trim((string)($_GET['category'] ?? '')),
  'status'   => trim((string)($_GET['status'] ?? '')),
];
$contractors = contractor_filter($all, $f, $today);
$matrix = contractor_empanelment_matrix($all, $today);
$districts = array_values(array_unique(array_filter(array_map(fn($c)=>$c['district'] ?? '', $all))));
sort($districts);
$categories = ['Civil','Mechanical','Electrical','Irrigation'];
$statuses = ['Active','Suspended','Expired','Blacklisted'];
```

- [ ] **Step 2: Render the per-class status cards and filter bar**

In `app/contractor/registry.php`, immediately after the page `<h1>`/intro block (after its closing `</div>` on the header row, before `<div class="card overflow-hidden">`), insert:

```php
<!-- Empanelment by class -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach (['I','II','III','IV'] as $cls): $row=$matrix[$cls]; ?>
    <div class="card p-4">
      <div class="flex items-center justify-between mb-2">
        <span class="font-display font-semibold text-ink"><?= is_hi()?'श्रेणी':'Class' ?>-<?= e($cls) ?></span>
        <span class="text-xs text-slate-400"><?= $row['active']+$row['suspended']+$row['expired'] ?></span>
      </div>
      <div class="flex gap-3 text-xs">
        <span class="text-emerald-700 font-semibold"><?= (int)$row['active'] ?> <?= is_hi()?'सक्रिय':'Active' ?></span>
        <span class="text-amber-700 font-semibold"><?= (int)$row['suspended'] ?> <?= is_hi()?'निलंबित':'Susp.' ?></span>
        <span class="text-rose-700 font-semibold"><?= (int)$row['expired'] ?> <?= is_hi()?'समाप्त':'Exp.' ?></span>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="get" class="card p-4 mb-6 flex flex-wrap items-end gap-3">
  <?php
    $sel = function(string $name, array $opts, string $cur, string $anyLabel) {
      echo '<div><label class="block text-xs text-slate-500 mb-1">'.e($name).'</label><select name="'.e(strtolower($name)).'" class="border border-slate-300 rounded-xl px-3 py-2 text-sm">';
      echo '<option value="">'.e($anyLabel).'</option>';
      foreach ($opts as $o) echo '<option value="'.e($o).'"'.($o===$cur?' selected':'').'>'.e($o).'</option>';
      echo '</select></div>';
    };
    $sel(is_hi()?'District':'District', $districts, $f['district'], is_hi()?'सभी जिले':'All districts');
    $sel(is_hi()?'Class':'Class', ['I','II','III','IV'], $f['class'], is_hi()?'सभी':'All');
    $sel('Category', $categories, $f['category'], is_hi()?'सभी श्रेणियाँ':'All categories');
    $sel('Status', $statuses, $f['status'], is_hi()?'सभी':'All');
  ?>
  <button class="btn-acc font-semibold px-4 py-2 rounded-xl text-sm"><?= is_hi()?'फ़िल्टर':'Filter' ?></button>
  <a href="<?= base_url('app/contractor/registry.php') ?>" class="text-sm text-slate-500 px-2 py-2"><?= is_hi()?'रीसेट':'Reset' ?></a>
  <span class="text-xs text-slate-400 ml-auto"><?= count($contractors) ?> <?= is_hi()?'परिणाम':'results' ?></span>
</form>
```

- [ ] **Step 3: Show the effective status in the table**

In `app/contractor/registry.php`, in the table row, replace the status cell `<?= badge($c['status']) ?>` with the effective status so the registry reconciles with the filter:

```php
          <td class="px-4 py-3"><?= badge(contractor_effective_status($c, $today)) ?></td>
```

- [ ] **Step 4: Syntax check + tests**

Run: `php -l app/contractor/registry.php` (Expected: "No syntax errors") and `php tests/run.php` (Expected: `Passed: 80  Failed: 0`).

- [ ] **Step 5: Manual verification (browser)**

Rebuild via `setup.php`, log in as an officer, open **Registered Contractors**. Confirm the four class cards show Active/Suspended/Expired counts; pick District=Ranchi and Status=Active and confirm the table narrows and the results count updates; confirm a lapsed-`valid_upto` firm shows the `Expired` badge.

- [ ] **Step 6: Commit**

```bash
git add app/contractor/registry.php
git commit -m "feat(contractor): Screen 9 — empanelment filters + per-class status counts"
```

---

### Task 5: Screen 13 — Revenue dashboard

**Files:**
- Create: `app/contractor/revenue.php`

**Interfaces:**
- Consumes: `contractor_revenue_kpis`, `contractor_monthly_collection`, `contractor_district_rollup` (Task 1); the `revenue` nav key (Task 3); vendored `assets/vendor/chartjs/chart.umd.js`; helpers `e`, `is_hi`, `base_url`, `set_app_context`, `app_require_access`, `$APP`.

- [ ] **Step 1: Create the revenue page**

Create `app/contractor/revenue.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo = db();
$apps = $pdo->query("SELECT type,fee,fee_paid,applied_on,contractor_id FROM contractor_apps")->fetchAll();
$contractors = $pdo->query("SELECT id,district FROM contractors")->fetchAll();

$k   = contractor_revenue_kpis($apps);
$mc  = contractor_monthly_collection($apps);
$roll = contractor_district_rollup($contractors, $apps);
// Top districts by revenue for the bar chart.
arsort($roll);
$topDist = array_slice($roll, 0, 10, true);

$cr = fn(float $v): string => '₹' . number_format($v / 10000000, 2) . ' Cr';

set_app_context('contractor');
app_require_access('revenue');
$LAYOUT='app'; $ACTIVE='revenue'; $PAGE_TITLE='Revenue MIS';
$EXTRA_HEAD = '<script src="' . base_url('assets/vendor/chartjs/chart.umd.js') . '"></script>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'राजस्व एमआईएस':'Revenue MIS' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'पंजीकरण एवं नवीनीकरण शुल्क संग्रह':'Registration & renewal fee collection' ?></p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach ([
      [is_hi()?'कुल संग्रह':'Total Collected', $cr($k['total']), 'text-emerald-700'],
      [is_hi()?'नवीनीकरण राजस्व':'Renewal Revenue', $cr($k['renewal']), 'text-sky-700'],
      [is_hi()?'नया पंजीकरण':'New Registration', $cr($k['new']), 'text-violet-700'],
      [is_hi()?'चालू वित्त वर्ष':'This FY', $cr($k['fy']), 'text-amber-700'],
    ] as $kpi): ?>
    <div class="card p-5">
      <div class="text-2xl font-display font-bold <?= $kpi[2] ?>"><?= e($kpi[1]) ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= e($kpi[0]) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card p-5 mb-6">
  <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'मासिक संग्रह (12 माह)':'Monthly Collection (12 months)' ?></h2>
  <canvas id="monthChart" height="90"></canvas>
</div>

<div class="grid lg:grid-cols-2 gap-6">
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'नया बनाम नवीनीकरण':'New vs Renewal' ?></h2>
    <canvas id="splitChart" height="200"></canvas>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिलावार राजस्व (शीर्ष 10)':'District-wise Revenue (Top 10)' ?></h2>
    <canvas id="distChart" height="200"></canvas>
  </div>
</div>

<script>
window.RV_MONTHS = <?= json_encode(array_keys($mc)) ?>;
window.RV_MVALS  = <?= json_encode(array_map(fn($v)=>round($v), array_values($mc))) ?>;
window.RV_SPLIT  = <?= json_encode([round($k['new']), round($k['renewal'])]) ?>;
window.RV_DIST   = <?= json_encode(array_map(fn($d)=>round($d['revenue']), $topDist), JSON_UNESCAPED_UNICODE) ?>;
window.RV_DLBL   = <?= json_encode(array_keys($topDist), JSON_UNESCAPED_UNICODE) ?>;
(function(){
  var acc = <?= json_encode($APP['accent']) ?>;
  new Chart(document.getElementById('monthChart'), {
    type:'bar',
    data:{ labels:window.RV_MONTHS, datasets:[{ label:'₹', data:window.RV_MVALS, backgroundColor:acc }] },
    options:{ plugins:{legend:{display:false}}, scales:{y:{ticks:{callback:function(v){return '₹'+(v/100000).toFixed(0)+'L';}}}} }
  });
  new Chart(document.getElementById('splitChart'), {
    type:'doughnut',
    data:{ labels:['New','Renewal'], datasets:[{ data:window.RV_SPLIT, backgroundColor:[acc,'#0ea5e9'] }] }
  });
  new Chart(document.getElementById('distChart'), {
    type:'bar',
    data:{ labels:window.RV_DLBL, datasets:[{ label:'₹', data:window.RV_DIST, backgroundColor:acc }] },
    options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{ticks:{callback:function(v){return '₹'+(v/100000).toFixed(0)+'L';}}}} }
  });
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

> If the header template uses a variable other than `$EXTRA_HEAD` to inject `<head>` scripts, mirror what `app/allocation/analytics.php` sets (it adds Leaflet/Chart.js the same way). Confirm the variable name in `includes/header.php` before relying on it.

- [ ] **Step 2: Syntax check + tests**

Run: `php -l app/contractor/revenue.php` (Expected: "No syntax errors") and `php tests/run.php` (Expected: `Passed: 80  Failed: 0`).

- [ ] **Step 3: Manual verification (browser)**

Rebuild via `setup.php`, log in as an `EE`/`EIC`/`SECRETARY`, open **Revenue MIS** from the nav. Confirm the four KPI cards show ₹-crore figures, the monthly bar chart renders 12 bars, and the New-vs-Renewal doughnut and district bar chart render. Confirm an `ASO` login does NOT see the Revenue MIS nav item.

- [ ] **Step 4: Commit**

```bash
git add app/contractor/revenue.php
git commit -m "feat(contractor): Screen 13 — revenue dashboard (KPIs + Chart.js charts)"
```

---

### Task 6: Screen 14 — Secretary/CE command dashboard with district map

**Files:**
- Create: `app/contractor/oversight.php`

**Interfaces:**
- Consumes: `contractor_district_rollup`, `contractor_revenue_kpis`, `contractor_effective_status` (Task 1); the `oversight` nav key (Task 3); vendored `assets/vendor/leaflet/*` + `assets/geo/jharkhand-districts.geojson`; helpers `e`, `is_hi`, `base_url`, `set_app_context`, `app_require_access`, `$APP`.

- [ ] **Step 1: Create the oversight page**

Create `app/contractor/oversight.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo = db();
$today = date('Y-m-d');
$contractors = $pdo->query("SELECT id,name,name_hi,class,district,status,valid_upto FROM contractors")->fetchAll();
$apps = $pdo->query("SELECT type,fee,fee_paid,applied_on,contractor_id,status FROM contractor_apps")->fetchAll();

$total = count($contractors);
$active = 0; $renewalsDue = 0;
foreach ($contractors as $c) {
    if (contractor_effective_status($c, $today) === 'Active') $active++;
    // due if valid within next 60 days and not already expired
    if (!empty($c['valid_upto']) && $c['valid_upto'] >= $today && $c['valid_upto'] <= date('Y-m-d', strtotime('+60 days'))) $renewalsDue++;
}
$pending = 0;
foreach ($apps as $a) if (!in_array($a['status'], ['Approved','Rejected'], true)) $pending++;
$k = contractor_revenue_kpis($apps);
$roll = contractor_district_rollup($contractors, $apps);

// Per-district drill payload: count, revenue, and the firm list.
$firmsByDist = [];
foreach ($contractors as $c) {
    $d = $c['district'] ?? ''; if ($d==='') continue;
    $firmsByDist[$d][] = ['name'=>(is_hi() ? ($c['name_hi'] ?: $c['name']) : $c['name']), 'class'=>$c['class'], 'status'=>contractor_effective_status($c, $today)];
}
$distPayload = [];
foreach ($roll as $d=>$v) $distPayload[$d] = ['count'=>$v['count'], 'revenue'=>round($v['revenue']), 'firms'=>array_slice($firmsByDist[$d] ?? [], 0, 12)];
$cr = fn(float $v): string => '₹' . number_format($v / 10000000, 2) . ' Cr';

set_app_context('contractor');
app_require_access('oversight');
$LAYOUT='app'; $ACTIVE='oversight'; $PAGE_TITLE='Command Centre';
$EXTRA_HEAD = '<link rel="stylesheet" href="' . base_url('assets/vendor/leaflet/leaflet.css') . '">'
            . '<script src="' . base_url('assets/vendor/leaflet/leaflet.js') . '"></script>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'कमांड सेंटर':'Command Centre' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'सचिव / प्रधान अभियंता — राज्यव्यापी ठेकेदार दृष्टि':'Secretary / Chief Engineer — statewide contractor view' ?></p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
  <?php foreach ([
      [is_hi()?'कुल ठेकेदार':'Total Contractors', (string)$total, 'text-ink'],
      [is_hi()?'सक्रिय':'Active', (string)$active, 'text-emerald-700'],
      [is_hi()?'लंबित अनुमोदन':'Pending Approvals', (string)$pending, 'text-amber-700'],
      [is_hi()?'राजस्व':'Revenue Collected', $cr($k['total']), 'text-sky-700'],
      [is_hi()?'नवीनीकरण देय':'Renewals Due', (string)$renewalsDue, 'text-rose-700'],
    ] as $kpi): ?>
    <div class="card p-5">
      <div class="text-2xl font-display font-bold <?= $kpi[2] ?>"><?= e($kpi[1]) ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= e($kpi[0]) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिलावार ठेकेदार वितरण':'District-wise Contractor Distribution' ?></h2>
    <div id="distmap" class="rounded-xl overflow-hidden" style="height:460px"></div>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिला विवरण':'District Detail' ?></h2>
    <p id="drillHint" class="text-sm text-slate-400"><?= is_hi()?'विवरण हेतु किसी जिले पर क्लिक करें।':'Click a district for its firms.' ?></p>
    <div id="drill" class="hidden">
      <div class="font-semibold text-ink mb-2" id="drillName"></div>
      <div id="drillBody" class="space-y-1.5"></div>
    </div>
  </div>
</div>

<script>
window.OV_DIST = <?= json_encode($distPayload, JSON_UNESCAPED_UNICODE) ?>;
window.OV_GEO  = <?= json_encode(base_url('assets/geo/jharkhand-districts.geojson')) ?>;
(function(){
  var counts = Object.keys(window.OV_DIST).map(function(d){return window.OV_DIST[d].count;});
  var maxC = counts.length ? Math.max.apply(null, counts) : 1;
  function shade(c){ if(!c) return '#e2e8f0'; var t=c/maxC; return t>0.66?'#06314a':(t>0.33?'#0E7C86':'#7dd3c0'); }
  var map = L.map('distmap',{scrollWheelZoom:false,attributionControl:false}).setView([23.6,85.4],7);
  var drill=document.getElementById('drill'), hint=document.getElementById('drillHint'),
      dName=document.getElementById('drillName'), dBody=document.getElementById('drillBody');
  function show(name){
    var v=window.OV_DIST[name]; if(!v) return;
    drill.classList.remove('hidden'); hint.classList.add('hidden');
    dName.textContent=name+' — '+v.count+' firms · ₹'+(v.revenue/100000).toFixed(1)+'L';
    dBody.innerHTML=(v.firms||[]).map(function(f){
      return '<div class="rounded-lg border border-slate-100 p-2"><div class="font-medium text-slate-700">'+f.name+'</div><div class="text-[11px] text-slate-400">Class '+f.class+' · '+f.status+'</div></div>';
    }).join('')||'<div class="text-xs text-slate-400">No firms.</div>';
  }
  fetch(window.OV_GEO).then(function(r){return r.json();}).then(function(geo){
    L.geoJSON(geo,{
      style:function(f){ var v=window.OV_DIST[f.properties.district];
        return {color:'#fff',weight:1,fillColor:shade(v?v.count:0),fillOpacity:.7}; },
      onEachFeature:function(f,layer){ var nm=f.properties.district;
        layer.bindTooltip(nm + (window.OV_DIST[nm] ? ' — '+window.OV_DIST[nm].count+' firms' : ' — 0'));
        layer.on('click',function(){ show(nm); });
      }
    }).addTo(map);
  }).catch(function(){});
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Syntax check + tests**

Run: `php -l app/contractor/oversight.php` (Expected: "No syntax errors") and `php tests/run.php` (Expected: `Passed: 80  Failed: 0`).

- [ ] **Step 3: Manual verification (browser)**

Rebuild via `setup.php`, log in as `EE`/`EIC`/`SECRETARY`, open **Command Centre**. Confirm the five KPI cards populate, the Jharkhand map renders with districts shaded by contractor count (no online tiles), tooltips show counts, and clicking a district lists its firms in the drill panel.

- [ ] **Step 4: Commit**

```bash
git add app/contractor/oversight.php
git commit -m "feat(contractor): Screen 14 — Secretary/CE command centre with district map"
```

---

## Self-Review

**Spec coverage:**
- Screen 9 empanelment filters + per-class status counts → Task 4 (uses Task 1 `contractor_filter`/`contractor_empanelment_matrix`). ✓
- Screen 13 revenue dashboard (KPIs + monthly/renewal/district charts) → Task 5 (Task 1 `contractor_revenue_kpis`/`contractor_monthly_collection`/`contractor_district_rollup`). ✓
- Screen 14 Secretary/CE dashboard + Jharkhand map → Task 6 (Task 1 rollup + reused Leaflet/GeoJSON pattern). ✓
- `category` column + richer consistent seed → Task 2. ✓
- Effective-status rule applied everywhere → Task 1 helper, consumed in Tasks 4/6. ✓
- Nav + senior-officer/Secretary gating → Task 3. ✓
- Unit tests for all aggregation/filter functions → Task 1. ✓
- Out-of-scope screens (12/15) excluded. ✓

**Placeholder scan:** No TBD/TODO; every code step shows complete code; the two `> If…` notes ask the implementer to confirm an existing template variable name against `includes/header.php`/`app/allocation/analytics.php` (both real, in-repo references) rather than leaving a blank.

**Type consistency:** `contractor_revenue_kpis` keys (`total/renewal/new/fy`) produced in Task 1 and consumed in Tasks 5/6. `contractor_district_rollup` shape (`count/revenue` per district) consistent between Task 1, Task 5 (`$roll[$d]['revenue']`), and Task 6 (`$v.count`). `contractor_empanelment_matrix` keys (`active/suspended/expired` per `I/II/III/IV`) match between Task 1 and Task 4. Nav keys `revenue`/`oversight` defined in Task 3 and consumed by `app_require_access` + `$ACTIVE` in Tasks 5/6. The `category` column added in Task 2 is read by `contractor_filter` (Task 1) and the Task 4 filter UI.
