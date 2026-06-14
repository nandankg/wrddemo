# PPMS Product Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn PPMS into a self-contained product on the foundation: a PPMS-branded login, a role-adaptive State Command Centre, a project-progress capture/verify flow, and the existing fund-requisition lifecycle — all scoped to PPMS data only, retiring the old aggregate dashboard.

**Architecture:** Every PPMS page calls `set_app_context('ppms')` before rendering the shared themed shell (so the header/sidebar theme themselves). Pure metric/role logic lives in a testable `app/ppms/lib.php`; pages are thin presenters over it. PPMS reads only PPMS tables (`projects`, `fund_requisitions`, `progress_updates`, plus `schemes`/`divisions`/`workflow_log`); it never touches `payments`, `bills`, `allocations`, `contractors`, or `content`.

**Tech Stack:** PHP 8.2 + MariaDB (XAMPP), Tailwind (CDN), Leaflet + Chart.js (CDN), the zero-dependency test runner at `tests/run.php`.

**Prerequisite:** The foundation plan (`docs/superpowers/plans/2026-06-14-foundation-and-launcher.md`) is merged: `includes/apps.php`, `includes/app_context.php`, `includes/auth_roles.php`, the per-product `includes/sidebar.php`, and the themed `includes/header.php` all exist. PPMS registry roles are `['JE','AE','EE','SE','EIC','FINANCE','SECRETARY']`, accent `#0e7c86`, home `app/ppms/index.php`.

---

## File Structure

- `app/ppms/lib.php` — **create** — pure PPMS logic (KPIs, fund KPIs, role→view archetype, pending actions, pct validation) + `ppms_require_login()`. The only file with unit tests.
- `tests/ppms_test.php` — **create** — tests for the pure functions in `lib.php`.
- `includes/apps.php` — **modify** — add a `projects` nav item to the PPMS registry entry.
- `tests/apps_test.php` — **modify** — assert the PPMS nav now has four keys.
- `setup.php` — **modify** — add the `progress_updates` table (drop list + CREATE).
- `sql/seed.php` — **modify** — seed a few `progress_updates` rows.
- `app/ppms/login.php` — **create** — PPMS-branded landing + role quick-pick (entry point for the launcher card).
- `app/ppms/index.php` — **create** — role-adaptive PPMS State Command Centre (replaces `app/dashboard.php`).
- `app/ppms/projects.php` — **create** — project list + detail with JE submit / AE verify progress flow.
- `app/ppms/requisitions.php` — **modify** — set PPMS context; fix `$ACTIVE`; PPMS-scoped login redirect.
- `app/ppms/reports.php` — **modify** — set PPMS context; fix `$ACTIVE`; PPMS-scoped login redirect.
- `auth/role_switch.php` — **modify** — return to the referring page instead of the deleted aggregate dashboard.
- `auth/login.php` — **modify** — redirect to the launcher instead of the deleted aggregate dashboard.
- `app/dashboard.php` — **delete** — the cross-product aggregate Command Centre is replaced by `app/ppms/index.php`.

---

## Task 1: PPMS logic library (pure, tested)

**Files:**
- Create: `app/ppms/lib.php`
- Test: `tests/ppms_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/ppms_test.php`:

```php
<?php
require_once __DIR__ . '/../app/ppms/lib.php';

$projects = [
  ['status'=>'On Track','sanctioned_amount'=>'100','spent_amount'=>'50','physical_pct'=>60],
  ['status'=>'Delayed','sanctioned_amount'=>'100','spent_amount'=>'25','physical_pct'=>40],
  ['status'=>'Critical','sanctioned_amount'=>'200','spent_amount'=>'25','physical_pct'=>20],
];

it('ppms_kpis aggregates sanctioned/spent/utilisation/at-risk', function () use ($projects) {
    $k = ppms_kpis($projects);
    assert_eq(400.0, $k['sanctioned']);
    assert_eq(100.0, $k['spent']);
    assert_eq(25, $k['utilisation']);           // 100/400 = 25%
    assert_eq(3,  $k['count']);
    assert_eq(2,  $k['at_risk']);               // Delayed + Critical
    assert_eq(40, $k['avg_physical']);          // (60+40+20)/3
    assert_eq(1,  $k['by_status']['On Track']);
});

it('ppms_kpis is safe on an empty project set (no divide-by-zero)', function () {
    $k = ppms_kpis([]);
    assert_eq(0.0, $k['sanctioned']);
    assert_eq(0,   $k['utilisation']);
    assert_eq(0,   $k['count']);
});

it('ppms_fund_kpis counts queue stages and released amount', function () {
    $reqs = [
      ['status'=>'Approved by Finance','amount_requested'=>'10','allocated_amount'=>'9'],
      ['status'=>'Under Finance Review','amount_requested'=>'5','allocated_amount'=>null],
      ['status'=>'Released','amount_requested'=>'20','allocated_amount'=>'20'],
      ['status'=>'Released','amount_requested'=>'8','allocated_amount'=>'8'],
    ];
    $f = ppms_fund_kpis($reqs);
    assert_eq(1,    $f['pending_release']);      // Approved by Finance
    assert_eq(1,    $f['under_finance']);
    assert_eq(28.0, $f['released_amount']);      // 20 + 8
});

it('ppms_role_view maps each role to its dashboard archetype', function () {
    assert_eq('field',     ppms_role_view('JE'));
    assert_eq('field',     ppms_role_view('AE'));
    assert_eq('division',  ppms_role_view('EE'));
    assert_eq('finance',   ppms_role_view('FINANCE'));
    assert_eq('oversight', ppms_role_view('SE'));
    assert_eq('oversight', ppms_role_view('EIC'));
    assert_eq('oversight', ppms_role_view('SECRETARY'));
    assert_eq('oversight', ppms_role_view('SOMETHING_ELSE'));
});

it('ppms_valid_pct accepts 0..100 ints only', function () {
    assert_true(ppms_valid_pct(0));
    assert_true(ppms_valid_pct(100));
    assert_true(ppms_valid_pct(55));
    assert_true(ppms_valid_pct(-1) === false);
    assert_true(ppms_valid_pct(101) === false);
});

it('ppms_pending_actions returns review items for EE and verify items for AE', function () {
    $reqs = [
      ['id'=>7,'req_no'=>'R7','status'=>'Pending Review','amount_requested'=>'100'],
      ['id'=>8,'req_no'=>'R8','status'=>'Under Finance Review','amount_requested'=>'200'],
    ];
    $progress = [
      ['id'=>3,'project_name'=>'Dam X','status'=>'Submitted'],
    ];
    $ee = ppms_pending_actions('EE', $reqs, $progress);
    assert_eq(1, count($ee));
    assert_eq('requisitions.php?id=7', $ee[0]['url']);

    $ae = ppms_pending_actions('AE', $reqs, $progress);
    assert_eq(1, count($ae));
    assert_eq('projects.php?id=3', $ae[0]['url']);

    $fin = ppms_pending_actions('FINANCE', $reqs, $progress);
    assert_eq(1, count($fin));
    assert_eq('requisitions.php?id=8', $fin[0]['url']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — `require ... app/ppms/lib.php` fails (file does not exist).

- [ ] **Step 3: Write the library**

Create `app/ppms/lib.php`:

```php
<?php
declare(strict_types=1);

/**
 * PPMS pure logic — no DB, no rendering. Callers pass already-fetched rows.
 * Keeps the dashboards/pages thin and these rules unit-testable.
 */

/** Aggregate project KPIs. $projects: rows with status, sanctioned_amount, spent_amount, physical_pct. */
function ppms_kpis(array $projects): array {
    $sanctioned = 0.0; $spent = 0.0; $physSum = 0; $atRisk = 0; $byStatus = [];
    foreach ($projects as $p) {
        $sanctioned += (float)$p['sanctioned_amount'];
        $spent      += (float)$p['spent_amount'];
        $physSum    += (int)$p['physical_pct'];
        $st = $p['status'];
        $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        if ($st === 'Delayed' || $st === 'Critical') $atRisk++;
    }
    $n = count($projects);
    return [
        'sanctioned'   => $sanctioned,
        'spent'        => $spent,
        'utilisation'  => $sanctioned > 0 ? (int)round($spent / $sanctioned * 100) : 0,
        'count'        => $n,
        'at_risk'      => $atRisk,
        'avg_physical' => $n > 0 ? (int)round($physSum / $n) : 0,
        'by_status'    => $byStatus,
    ];
}

/** Fund-requisition KPIs. $reqs: rows with status, amount_requested, allocated_amount. */
function ppms_fund_kpis(array $reqs): array {
    $pendingRelease = 0; $underFinance = 0; $released = 0.0;
    foreach ($reqs as $r) {
        if ($r['status'] === 'Approved by Finance')  $pendingRelease++;
        if ($r['status'] === 'Under Finance Review') $underFinance++;
        if ($r['status'] === 'Released')             $released += (float)$r['allocated_amount'];
    }
    return [
        'pending_release' => $pendingRelease,
        'under_finance'   => $underFinance,
        'released_amount' => $released,
        'count'           => count($reqs),
    ];
}

/** Map a role to its dashboard archetype. */
function ppms_role_view(string $role): string {
    switch ($role) {
        case 'JE': case 'AE':      return 'field';
        case 'EE':                 return 'division';
        case 'FINANCE':            return 'finance';
        case 'SE': case 'EIC': case 'SECRETARY': default: return 'oversight';
    }
}

/** Validate a progress percentage. */
function ppms_valid_pct(int $v): bool { return $v >= 0 && $v <= 100; }

/**
 * Pending actions for a role.
 * $reqs: fund requisitions (id, req_no, status, amount_requested).
 * $progress: progress updates (id, project_name, status).
 * Returns rows: ['label','meta','status','url'].
 */
function ppms_pending_actions(string $role, array $reqs, array $progress): array {
    $out = [];
    if (in_array($role, ['EE','SE','EIC'], true)) {
        foreach ($reqs as $r) if ($r['status'] === 'Pending Review')
            $out[] = ['label'=>'Review requisition '.$r['req_no'], 'meta'=>(string)$r['amount_requested'], 'status'=>$r['status'], 'url'=>'requisitions.php?id='.$r['id']];
    }
    if ($role === 'EIC') {
        foreach ($reqs as $r) if ($r['status'] === 'Approved by Finance')
            $out[] = ['label'=>'Release fund '.$r['req_no'], 'meta'=>(string)$r['amount_requested'], 'status'=>$r['status'], 'url'=>'requisitions.php?id='.$r['id']];
    }
    if ($role === 'FINANCE') {
        foreach ($reqs as $r) if ($r['status'] === 'Under Finance Review')
            $out[] = ['label'=>'Finance review '.$r['req_no'], 'meta'=>(string)$r['amount_requested'], 'status'=>$r['status'], 'url'=>'requisitions.php?id='.$r['id']];
    }
    if ($role === 'AE') {
        foreach ($progress as $g) if ($g['status'] === 'Submitted')
            $out[] = ['label'=>'Verify progress · '.$g['project_name'], 'meta'=>'', 'status'=>$g['status'], 'url'=>'projects.php?id='.$g['id']];
    }
    if ($role === 'JE') {
        foreach ($progress as $g) if ($g['status'] === 'Rejected')
            $out[] = ['label'=>'Resubmit progress · '.$g['project_name'], 'meta'=>'', 'status'=>$g['status'], 'url'=>'projects.php?id='.$g['id']];
    }
    return $out;
}

/** Require a logged-in user; bounce to the PPMS login (not the generic one) if not. */
function ppms_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/ppms/login.php')); exit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS — all `ppms_test.php` assertions plus the pre-existing foundation tests.

- [ ] **Step 5: Commit**

```bash
git add app/ppms/lib.php tests/ppms_test.php
git commit -m "feat(ppms): pure metric/role/pending-action logic with tests"
```

---

## Task 2: Add the `projects` nav item to the PPMS registry

**Files:**
- Modify: `includes/apps.php`
- Modify: `tests/apps_test.php`

- [ ] **Step 1: Update the failing test first**

In `tests/apps_test.php`, add this test at the end of the file (before the closing — it's a flat list of `it(...)` calls):

```php
it('ppms nav exposes dashboard, projects, requisitions, reports in order', function () {
    assert_eq(['dashboard','projects','requisitions','reports'], array_column(wrd_app('ppms')['nav'], 'key'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — the PPMS nav currently has `['dashboard','requisitions','reports']`, so the new assertion fails.

- [ ] **Step 3: Add the nav item**

In `includes/apps.php`, in the `'ppms'` entry's `'nav'` array, insert the `projects` item between `dashboard` and `requisitions`. The PPMS nav array must become exactly:

```php
            'nav' => [
                ['key'=>'dashboard','label'=>'Command Centre','url'=>'app/ppms/index.php','icon'=>'▤'],
                ['key'=>'projects','label'=>'Projects & Progress','url'=>'app/ppms/projects.php','icon'=>'📍'],
                ['key'=>'requisitions','label'=>'Fund Requisition','url'=>'app/ppms/requisitions.php','icon'=>'₹'],
                ['key'=>'reports','label'=>'Reports / MIS','url'=>'app/ppms/reports.php','icon'=>'▦'],
            ],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS (the existing "each product has the required fields" test still passes; nav just has one more entry).

- [ ] **Step 5: Commit**

```bash
git add includes/apps.php tests/apps_test.php
git commit -m "feat(ppms): add Projects & Progress nav item to registry"
```

---

## Task 3: `progress_updates` schema + seed

**Files:**
- Modify: `setup.php`
- Modify: `sql/seed.php`

- [ ] **Step 1: Add the table to the drop list**

In `setup.php`, find the drop-list array (the `foreach ([...] as $t)` near the top of the try block) and add `'progress_updates'` as the first element so it reads:

```php
    foreach (['progress_updates','workflow_log','payments','bills','drawal_entries','consumers','allocations',
              'contractor_apps','contractors','fund_requisitions','projects','schemes',
              'divisions','content','grievances','rti_applications','users'] as $t) {
```

- [ ] **Step 2: Add the CREATE TABLE**

In `setup.php`, immediately after the `fund_requisitions` `CREATE TABLE` block (after its `SQL);` and before the `contractors` block), insert:

```php
    $pdo->exec(<<<SQL
    CREATE TABLE progress_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        physical_pct INT, financial_pct INT,
        note VARCHAR(255),
        status VARCHAR(20), -- Submitted, Verified, Rejected
        submitted_by INT, verified_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
```

Also update the table-count message — change the line `ok('All 16 tables created (utf8mb4 / Hindi-ready).');` to:

```php
    ok('All 17 tables created (utf8mb4 / Hindi-ready).');
```

- [ ] **Step 3: Seed progress rows**

In `sql/seed.php`, immediately after the fund-requisitions seeding block (after the `foreach ($reqs as $r) { ... }` loop that ends around the `}` before `// ---- Contractors`), insert:

```php
    // ---- Project progress updates (JE submits → AE verifies) ----
    $prog = [
        // project_id, physical, financial, note, status, submitted_by, verified_by
        [1, 75, 70, 'Spillway gates erection completed; RD 0-2 km lined.', 'Submitted', $uid['JE'], null],
        [3, 45, 52, 'Desilting 60% done; embankment pitching in progress.', 'Submitted', $uid['JE'], null],
        [2, 60, 57, 'Canal earthwork RD 0-8 km verified on site.', 'Verified', $uid['JE'], $uid['AE']],
        [5, 30, 36, 'Mobilisation resumed; access road restored.', 'Rejected', $uid['JE'], $uid['AE']],
    ];
    $ins = $pdo->prepare('INSERT INTO progress_updates (project_id,physical_pct,financial_pct,note,status,submitted_by,verified_by,created_at) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($prog as $i=>$g) {
        $ins->execute([$g[0],$g[1],$g[2],$g[3],$g[4],$g[5],$g[6], date('Y-m-d H:i:s', strtotime('-'.(3+$i*2).' days'))]);
    }
```

- [ ] **Step 4: Verify syntax and reinstall the demo DB**

Run: `php -l setup.php && php -l sql/seed.php`
Expected: `No syntax errors detected` for both.

Then (requires Apache + MySQL running) open `http://localhost/WRD/setup.php` in a browser.
Expected: the installer reports "All 17 tables created" and "Seed data inserted" with no error rows.

- [ ] **Step 5: Commit**

```bash
git add setup.php sql/seed.php
git commit -m "feat(ppms): add progress_updates table and seed data"
```

---

## Task 4: PPMS login / landing

**Files:**
- Create: `app/ppms/login.php`

- [ ] **Step 1: Create the PPMS-branded login**

Create `app/ppms/login.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/app_context.php';
set_app_context('ppms');
$APP = app_ctx();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . base_url('app/ppms/index.php')); exit;
    }
    $error = is_hi() ? 'अमान्य उपयोगकर्ता नाम या पासवर्ड।' : 'Invalid username or password.';
}
if (is_logged_in()) { header('Location: ' . base_url('app/ppms/index.php')); exit; }

// PPMS role quick-pick (only this product's roles)
$quick = [
  ['SECRETARY','Secretary','🏛'],['EIC','Engineer-in-Chief','⚙'],['SE','Superintending Engr','📐'],
  ['EE','Executive Engineer','📋'],['AE','Assistant Engineer','📏'],['JE','Junior Engineer','🛠'],
  ['FINANCE','Finance Officer','₹'],
];
$acc = $APP['accent'];
?><!doctype html>
<html lang="<?= lang() ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($APP['short']) ?> · Sign in · WRD Jharkhand</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<style>body{font-family:'Inter',sans-serif} .d{font-family:'Plus Jakarta Sans',sans-serif}</style>
</head>
<body class="min-h-screen grid lg:grid-cols-2" style="--acc:<?= e($acc) ?>">
  <!-- brand panel -->
  <div class="hidden lg:flex flex-col justify-between p-12 text-white relative"
       style="background:radial-gradient(1000px 300px at 80% -10%, <?= e($acc) ?>55, transparent), linear-gradient(180deg,#0a2a44,#0c3350)">
    <a href="<?= base_url('index.php') ?>" class="relative z-10 flex items-center gap-3">
      <span class="grid place-items-center w-11 h-11 rounded-xl text-2xl" style="background:<?= e($acc) ?>33"><?= $APP['icon'] ?></span>
      <div><div class="d font-bold"><?= e($APP['short']) ?></div><div class="text-xs text-cyan-100"><?= t('govt') ?></div></div>
    </a>
    <div class="relative z-10">
      <h1 class="d text-4xl font-bold leading-tight"><?= is_hi()?e($APP['name_hi']):e($APP['name']) ?></h1>
      <p class="text-cyan-100/90 mt-4 max-w-md"><?= is_hi()?e($APP['tagline_hi']):e($APP['tagline']) ?></p>
    </div>
    <p class="relative z-10 text-xs text-cyan-200/70">Secured with MFA · RBAC · TLS 1.3 · CERT-In VAPT</p>
  </div>

  <!-- form panel -->
  <div class="flex items-center justify-center p-6 bg-paper">
    <div class="w-full max-w-md">
      <div class="card p-7">
        <a href="<?= base_url('index.php') ?>" class="text-xs text-slate-500 hover:underline">← <?= is_hi()?'सभी उत्पाद':'All products' ?></a>
        <h2 class="d text-2xl font-bold text-ink mt-2"><?= e($APP['short']) ?> · <?= t('login') ?></h2>
        <p class="text-sm text-slate-500 mt-1"><?= is_hi()?'अपने विभागीय खाते से प्रवेश करें':'Sign in to your departmental account' ?></p>

        <?php if ($error): ?><div class="mt-4 bg-rose-50 text-rose-700 text-sm rounded-lg px-3 py-2 ring-1 ring-rose-200"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="mt-5 space-y-4">
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'उपयोगकर्ता नाम':'Username' ?></label>
            <input name="username" required autofocus class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. ee">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'पासवर्ड':'Password' ?></label>
            <input name="password" type="password" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="demo123">
          </div>
          <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= t('login') ?> →</button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-200">
          <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-2.5">Demo · one-click sign-in (PPMS roles)</p>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach ($quick as $q): ?>
              <a href="<?= base_url('auth/role_switch.php') ?>?role=<?= e($q[0]) ?>&to=<?= urlencode(base_url('app/ppms/index.php')) ?>"
                 class="text-center border border-slate-200 rounded-xl px-2 py-2.5 hover:border-slate-400 hover:bg-white transition">
                <div class="text-lg"><?= $q[2] ?></div><div class="text-[11px] text-slate-600 mt-0.5 leading-tight"><?= e($q[1]) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
          <p class="text-[11px] text-slate-400 mt-3 text-center">All accounts · password <code class="bg-slate-100 px-1 rounded">demo123</code></p>
        </div>
      </div>
    </div>
  </div>
</body></html>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/ppms/login.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/ppms/login.php
git commit -m "feat(ppms): PPMS-branded login with product-scoped role quick-pick"
```

> Note: the role quick-pick passes `&to=<ppms index>`; Task 8 makes `role_switch.php` honour a `to`/referer target so the demo lands inside PPMS.

---

## Task 5: PPMS State Command Centre (role-adaptive)

**Files:**
- Create: `app/ppms/index.php`

- [ ] **Step 1: Create the command centre**

Create `app/ppms/index.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$view = ppms_role_view($role);
$myDiv = (int)($u['division_id'] ?? 0);

// Division roles see only their division's projects; oversight/finance see all.
$scopeDiv = in_array($view, ['field','division'], true) && $myDiv > 0;
if ($scopeDiv) {
    $st = $pdo->prepare("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id WHERE p.division_id=? ORDER BY p.physical_pct DESC");
    $st->execute([$myDiv]); $projects = $st->fetchAll();
} else {
    $projects = $pdo->query("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id ORDER BY p.physical_pct DESC")->fetchAll();
}
$reqs = $pdo->query("SELECT id,req_no,status,amount_requested,allocated_amount FROM fund_requisitions ORDER BY id DESC")->fetchAll();
$progress = $pdo->query("SELECT pu.id,pu.status,p.name project_name FROM progress_updates pu JOIN projects p ON p.id=pu.project_id ORDER BY pu.id DESC")->fetchAll();

$k = ppms_kpis($projects);
$fund = ppms_fund_kpis($reqs);   // not $f — header.php uses $f for the flash message
$tasks = ppms_pending_actions($role, $reqs, $progress);

// Map data for GIS (oversight only)
$mapData = array_map(fn($p)=>[
  'name'=>is_hi()?($p['name_hi']?:$p['name']):$p['name'],'lat'=>(float)$p['lat'],'lng'=>(float)$p['lng'],
  'status'=>$p['status'],'phys'=>(int)$p['physical_pct'],'fin'=>(int)$p['financial_pct'],
  'amt'=>inr((float)$p['sanctioned_amount']),'div'=>$p['divn']
], $projects);

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Command Centre';
if ($view === 'oversight') {
  $EXTRA_HEAD = '
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
}
require __DIR__ . '/../../includes/header.php';

$viewLabel = [
  'oversight'=>is_hi()?'राज्य कमांड सेंटर':'State Command Centre',
  'division' =>is_hi()?'प्रमंडल डैशबोर्ड':'Division Dashboard',
  'field'    =>is_hi()?'क्षेत्र प्रगति डैशबोर्ड':'Field Progress Dashboard',
  'finance'  =>is_hi()?'निधि निर्गत डैशबोर्ड':'Fund Release Dashboard',
][$view];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= e($viewLabel) ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?><?= $scopeDiv?' · '.e($projects[0]['divn'] ?? ''):'' ?></p>
  </div>
  <span class="text-xs text-slate-500 bg-white border border-slate-200 rounded-full px-3 py-1.5">● <?= is_hi()?'लाइव डेटा':'Live data' ?> · <?= date('d M Y, H:i') ?></span>
</div>

<!-- KPI row (PPMS-scoped) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $kpis = $view==='finance' ? [
    [is_hi()?'निर्गत राशि':'Funds Released', inr($fund['released_amount']), 'text-emerald-700'],
    [is_hi()?'वित्त समीक्षा हेतु':'Awaiting Finance', (string)$fund['under_finance'], 'text-amber-700'],
    [is_hi()?'निर्गत हेतु तैयार':'Ready to Release', (string)$fund['pending_release'], 'text-sky-700'],
    [is_hi()?'कुल माँगें':'Total Requisitions', (string)$fund['count'], 'text-ink'],
  ] : [
    [is_hi()?'स्वीकृत परिव्यय':'Sanctioned Outlay', inr($k['sanctioned']), 'text-ink'],
    [is_hi()?'उपयोगिता':'Utilisation', $k['utilisation'].'%', 'text-emerald-700'],
    [is_hi()?'औसत भौतिक प्रगति':'Avg Physical Progress', $k['avg_physical'].'%', 'text-sky-700'],
    [is_hi()?'जोखिम पर परियोजनाएँ':'Projects at Risk', (string)$k['at_risk'], 'text-rose-700'],
  ];
  foreach ($kpis as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Primary panel -->
  <div class="lg:col-span-2 space-y-6">
    <?php if ($view==='oversight'): ?>
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'परियोजना भू-मानचित्र (जीआईएस)':'Project Geo-Monitoring (GIS)' ?></h2>
          <div class="flex gap-3 text-[11px]">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>On Track</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Delayed</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>Critical</span>
          </div>
        </div>
        <div id="map" class="h-[360px] rounded-xl overflow-hidden ring-1 ring-slate-200 z-0"></div>
      </div>
      <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'स्थिति-वार परियोजनाएँ':'Projects by Status' ?></h2>
        <canvas id="statusChart" height="110"></canvas>
      </div>
    <?php else: ?>
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-display text-lg font-semibold text-ink"><?= $scopeDiv?(is_hi()?'मेरे प्रमंडल की परियोजनाएँ':'Projects in My Division'):(is_hi()?'परियोजनाएँ':'Projects') ?></h2>
          <a href="<?= base_url('app/ppms/projects.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
            <tr><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'भौतिक':'Physical' ?></th><th class="text-left px-4 py-3">Status</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach (array_slice($projects,0,8) as $p): ?>
              <tr class="hover:bg-paper cursor-pointer" onclick="location.href='<?= base_url('app/ppms/projects.php') ?>?id=<?= $p['id'] ?>'">
                <td class="px-4 py-3 font-medium text-slate-800"><?= bi($p['name'],$p['name_hi']) ?></td>
                <td class="px-4 py-3 w-40"><div class="flex items-center gap-2"><div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full" style="width:<?= (int)$p['physical_pct'] ?>%;background:<?= e($APP['accent']) ?>"></div></div><span class="text-xs font-semibold text-slate-600"><?= (int)$p['physical_pct'] ?>%</span></div></td>
                <td class="px-4 py-3"><?= badge($p['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Pending actions -->
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'आपकी लंबित कार्रवाई':'Your Pending Actions' ?></h2>
    <?php if ($tasks): ?>
      <div class="space-y-2">
        <?php foreach ($tasks as $tk): ?>
          <a href="<?= base_url('app/ppms/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
            <div class="min-w-0"><p class="text-sm font-medium text-slate-700 truncate"><?= e($tk['label']) ?></p><?php if($tk['meta']): ?><p class="text-xs text-slate-400"><?= inr((float)$tk['meta']) ?></p><?php endif; ?></div>
            <?= badge($tk['status']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm">
        <div class="text-4xl mb-2">✓</div>
        <?= is_hi()?'इस भूमिका हेतु कोई लंबित कार्य नहीं।':'No pending tasks for this role.' ?><br>
        <span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे)।':'Switch role (bottom-left) to see other workflows.' ?></span>
      </div>
    <?php endif; ?>
    <a href="<?= base_url('app/ppms/reports.php') ?>" class="block text-center mt-4 text-sm font-semibold hover:underline" style="color:<?= e($APP['accent']) ?>"><?= t('reports') ?> →</a>
  </div>
</div>

<?php if ($view==='oversight'): ?>
<script>
const PROJECTS = <?= json_encode($mapData, JSON_UNESCAPED_UNICODE) ?>;
const BYSTATUS = <?= json_encode($k['by_status'], JSON_UNESCAPED_UNICODE) ?>;
const map = L.map('map', {scrollWheelZoom:false}).setView([23.6, 85.3], 7);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18, attribution:'© OpenStreetMap'}).addTo(map);
const colour = {'On Track':'#10b981','Delayed':'#f59e0b','Critical':'#ef4444'};
PROJECTS.forEach(p=>{
  L.circleMarker([p.lat,p.lng],{radius:9,color:'#fff',weight:2,fillColor:colour[p.status]||'#64748b',fillOpacity:.95})
   .addTo(map)
   .bindPopup(`<b>${p.name}</b><br>${p.div}<br>Status: <b style="color:${colour[p.status]}">${p.status}</b><br>Physical: ${p.phys}% · Financial: ${p.fin}%<br>Outlay: ${p.amt}`);
});
new Chart(document.getElementById('statusChart'),{
  type:'bar',
  data:{labels:Object.keys(BYSTATUS),datasets:[{label:'Projects',data:Object.values(BYSTATUS),
    backgroundColor:['#10b981','#f59e0b','#ef4444','#0ea5e9','#6366f1']}]},
  options:{plugins:{legend:{display:false}},scales:{y:{ticks:{stepSize:1}}}}
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/ppms/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual check (Apache + MySQL running, demo DB installed)**

Open `http://localhost/WRD/app/ppms/login.php`, click **Secretary** → lands on the State Command Centre with GIS map + status chart + KPIs. Use the sidebar role-switcher to pick **JE** → the view changes to the Field Progress Dashboard (division-scoped project table, no map). Pick **FINANCE** → fund KPIs + finance pending actions.

- [ ] **Step 4: Commit**

```bash
git add app/ppms/index.php
git commit -m "feat(ppms): role-adaptive State Command Centre (PPMS-scoped)"
```

---

## Task 6: Project progress capture (JE submit → AE verify)

**Files:**
- Create: `app/ppms/projects.php`

- [ ] **Step 1: Create the projects page**

Create `app/ppms/projects.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$actor = $u['name'] . ' (' . $role . ')';

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';
  $pid = (int)($_POST['project_id'] ?? 0);

  if ($act==='submit' && $role==='JE') {
    $phys=(int)$_POST['physical_pct']; $fin=(int)$_POST['financial_pct']; $note=trim($_POST['note'] ?? '');
    if (ppms_valid_pct($phys) && ppms_valid_pct($fin)) {
      $st=$pdo->prepare('INSERT INTO progress_updates (project_id,physical_pct,financial_pct,note,status,submitted_by) VALUES (?,?,?,?,?,?)');
      $st->execute([$pid,$phys,$fin,$note,'Submitted',$u['id']]);
      add_audit($pdo,'project',$pid,'Progress submitted','JE','AE',$actor,"Physical $phys% · Financial $fin%".($note?" · $note":''));
      flash('Progress submitted for verification.');
    } else { flash('Percentages must be between 0 and 100.'); }
    header('Location: ?id='.$pid); exit;
  }

  $gid = (int)($_POST['update_id'] ?? 0);
  $g = $pdo->query("SELECT * FROM progress_updates WHERE id=$gid")->fetch();
  if ($g && $role==='AE' && $g['status']==='Submitted') {
    if ($act==='verify') {
      $pdo->prepare("UPDATE progress_updates SET status='Verified',verified_by=? WHERE id=?")->execute([$u['id'],$gid]);
      $pdo->prepare("UPDATE projects SET physical_pct=?,financial_pct=? WHERE id=?")->execute([(int)$g['physical_pct'],(int)$g['financial_pct'],$g['project_id']]);
      add_audit($pdo,'project',(int)$g['project_id'],'Progress verified','AE','AE',$actor,'Applied Physical '.$g['physical_pct'].'% · Financial '.$g['financial_pct'].'%');
      flash('Progress verified and applied.');
    } elseif ($act==='reject') {
      $pdo->prepare("UPDATE progress_updates SET status='Rejected',verified_by=? WHERE id=?")->execute([$u['id'],$gid]);
      add_audit($pdo,'project',(int)$g['project_id'],'Progress rejected','AE','JE',$actor,trim($_POST['remarks'] ?? '')?:'Returned for correction.');
      flash('Progress update rejected.');
    }
    header('Location: ?id='.$g['project_id']); exit;
  }
  header('Location: projects.php'); exit;
}

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='projects'; $PAGE_TITLE='Projects & Progress';
require __DIR__ . '/../../includes/header.php';

$viewId = (int)($_GET['id'] ?? 0);
$myDiv = (int)($u['division_id'] ?? 0);
$scopeDiv = in_array(ppms_role_view($role), ['field','division'], true) && $myDiv > 0;

// =================== DETAIL VIEW ===================
if ($viewId):
  $p = $pdo->query("SELECT p.*,s.name scheme,d.name divn FROM projects p JOIN schemes s ON s.id=p.scheme_id JOIN divisions d ON d.id=p.division_id WHERE p.id=$viewId")->fetch();
  if (!$p) { echo '<p class="text-slate-500">Project not found.</p>'; require __DIR__.'/../../includes/footer.php'; exit; }
  $updates = $pdo->query("SELECT * FROM progress_updates WHERE project_id=$viewId ORDER BY id DESC")->fetchAll();
  $logs = $pdo->query("SELECT * FROM workflow_log WHERE entity_type='project' AND entity_id=$viewId ORDER BY id DESC")->fetchAll();
  $pending = null; foreach ($updates as $up) if ($up['status']==='Submitted') { $pending=$up; break; }
?>
  <a href="projects.php" class="text-sm text-slate-500 hover:underline">← <?= is_hi()?'सभी परियोजनाएँ':'All projects' ?></a>
  <div class="grid lg:grid-cols-3 gap-6 mt-3">
    <div class="lg:col-span-2 space-y-6">
      <div class="card p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h1 class="font-display text-2xl font-semibold text-ink"><?= bi($p['name'],$p['name_hi']) ?></h1>
            <p class="text-sm text-slate-500 mt-0.5"><?= e($p['scheme']) ?> · <?= e($p['divn']) ?></p>
          </div>
          <?= badge($p['status']) ?>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 mt-6">
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'भौतिक प्रगति':'Physical Progress' ?></div>
            <div class="flex items-center gap-2 mt-2"><div class="flex-1 h-2.5 bg-slate-200 rounded-full overflow-hidden"><div class="h-full" style="width:<?= (int)$p['physical_pct'] ?>%;background:<?= e($APP['accent']) ?>"></div></div><span class="font-semibold text-ink"><?= (int)$p['physical_pct'] ?>%</span></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'वित्तीय प्रगति':'Financial Progress' ?></div>
            <div class="flex items-center gap-2 mt-2"><div class="flex-1 h-2.5 bg-slate-200 rounded-full overflow-hidden"><div class="h-full bg-emerald-500" style="width:<?= (int)$p['financial_pct'] ?>%"></div></div><span class="font-semibold text-ink"><?= (int)$p['financial_pct'] ?>%</span></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'स्वीकृत राशि':'Sanctioned' ?></div><div class="font-display text-lg font-semibold text-ink mt-1"><?= inr((float)$p['sanctioned_amount']) ?></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'व्यय':'Spent' ?></div><div class="font-display text-lg font-semibold text-ink mt-1"><?= inr((float)$p['spent_amount']) ?></div></div>
        </div>
      </div>

      <!-- Progress history + audit -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'प्रगति इतिहास':'Progress History' ?></h2>
        <ol class="relative border-l-2 border-slate-200 ml-2 space-y-5">
          <?php foreach ($updates as $up): ?>
            <li class="ml-5">
              <span class="absolute -left-[7px] w-3 h-3 rounded-full ring-4 ring-brandsoft" style="background:<?= e($APP['accent']) ?>"></span>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-ink"><?= is_hi()?'भौतिक':'Physical' ?> <?= (int)$up['physical_pct'] ?>% · <?= is_hi()?'वित्तीय':'Financial' ?> <?= (int)$up['financial_pct'] ?>%</span>
                <?= badge($up['status']) ?>
              </div>
              <p class="text-xs text-slate-500 mt-0.5"><?= date('d M Y, H:i',strtotime($up['created_at'])) ?></p>
              <?php if($up['note']): ?><p class="text-sm text-slate-600 mt-1 bg-paper rounded-lg px-3 py-1.5"><?= e($up['note']) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
          <?php if(!$updates): ?><li class="ml-5 text-sm text-slate-400"><?= is_hi()?'अभी तक कोई प्रगति अद्यतन नहीं।':'No progress updates yet.' ?></li><?php endif; ?>
        </ol>
      </div>
    </div>

    <!-- Action panel -->
    <div>
      <div class="card p-6 sticky top-24">
        <h2 class="font-display text-lg font-semibold text-ink mb-1"><?= is_hi()?'कार्रवाई':'Take Action' ?></h2>
        <p class="text-xs text-slate-500 mb-4"><?= is_hi()?'वर्तमान भूमिका':'Acting as' ?>: <span class="font-semibold" style="color:<?= e($APP['accent']) ?>"><?= e($role) ?></span></p>

        <?php if ($role==='JE'): ?>
          <form method="post" class="space-y-3">
            <input type="hidden" name="project_id" value="<?= $viewId ?>"><input type="hidden" name="action" value="submit">
            <label class="text-xs font-medium text-slate-600"><?= is_hi()?'भौतिक प्रगति (%)':'Physical Progress (%)' ?></label>
            <input name="physical_pct" type="number" min="0" max="100" value="<?= (int)$p['physical_pct'] ?>" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <label class="text-xs font-medium text-slate-600"><?= is_hi()?'वित्तीय प्रगति (%)':'Financial Progress (%)' ?></label>
            <input name="financial_pct" type="number" min="0" max="100" value="<?= (int)$p['financial_pct'] ?>" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <textarea name="note" rows="2" placeholder="<?= is_hi()?'टिप्पणी':'Site note' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
            <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= is_hi()?'सत्यापन हेतु भेजें':'Submit for Verification' ?> →</button>
          </form>
        <?php elseif ($role==='AE' && $pending): ?>
          <div class="bg-amber-50 ring-1 ring-amber-200 rounded-xl p-3 text-sm text-amber-800 mb-3">
            <?= is_hi()?'जेई द्वारा प्रस्तुत':'Submitted by JE' ?>: <b><?= (int)$pending['physical_pct'] ?>% / <?= (int)$pending['financial_pct'] ?>%</b>
          </div>
          <form method="post" class="space-y-3">
            <input type="hidden" name="update_id" value="<?= (int)$pending['id'] ?>">
            <button name="action" value="verify" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'सत्यापित कर लागू करें':'Verify & Apply' ?></button>
            <textarea name="remarks" rows="2" placeholder="<?= is_hi()?'अस्वीकृति कारण':'Reason (if rejecting)' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
            <button name="action" value="reject" class="w-full bg-rose-100 text-rose-700 font-semibold py-2 rounded-xl text-sm">✕ <?= is_hi()?'अस्वीकृत':'Reject' ?></button>
          </form>
        <?php else: ?>
          <div class="text-center py-8 text-slate-400 text-sm">
            <div class="text-3xl mb-2">🔒</div>
            <?= is_hi()?'इस चरण पर आपकी भूमिका हेतु कोई कार्रवाई नहीं।':'No action for your role here.' ?>
            <p class="text-xs mt-2"><?= is_hi()?'जेई प्रगति प्रस्तुत करता है; एई सत्यापित करता है।':'JE submits progress; AE verifies.' ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php
// =================== LIST VIEW ===================
else:
  if ($scopeDiv) {
    $st=$pdo->prepare("SELECT p.*,d.name divn,(SELECT status FROM progress_updates pu WHERE pu.project_id=p.id ORDER BY pu.id DESC LIMIT 1) last_status FROM projects p JOIN divisions d ON d.id=p.division_id WHERE p.division_id=? ORDER BY p.physical_pct DESC");
    $st->execute([$myDiv]); $rows=$st->fetchAll();
  } else {
    $rows=$pdo->query("SELECT p.*,d.name divn,(SELECT status FROM progress_updates pu WHERE pu.project_id=p.id ORDER BY pu.id DESC LIMIT 1) last_status FROM projects p JOIN divisions d ON d.id=p.division_id ORDER BY p.physical_pct DESC")->fetchAll();
  }
?>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'परियोजनाएँ एवं प्रगति':'Projects & Progress' ?></h1>
    <p class="text-sm text-slate-500"><?= $scopeDiv?(is_hi()?'मेरे प्रमंडल की परियोजनाएँ':'Projects in my division'):(is_hi()?'सभी परियोजनाएँ':'All projects') ?> · PPMS</p></div>
  </div>
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
        <tr><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th><th class="text-left px-4 py-3 hidden md:table-cell">Division</th>
        <th class="text-left px-4 py-3"><?= is_hi()?'भौतिक':'Physical' ?></th><th class="text-left px-4 py-3">Status</th><th class="text-left px-4 py-3"><?= is_hi()?'अद्यतन':'Update' ?></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($rows as $r): ?>
          <tr class="hover:bg-paper cursor-pointer" onclick="location.href='?id=<?= $r['id'] ?>'">
            <td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['name'],$r['name_hi']) ?></td>
            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['divn']) ?></td>
            <td class="px-4 py-3 w-40"><div class="flex items-center gap-2"><div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full" style="width:<?= (int)$r['physical_pct'] ?>%;background:<?= e($APP['accent']) ?>"></div></div><span class="text-xs font-semibold text-slate-600"><?= (int)$r['physical_pct'] ?>%</span></div></td>
            <td class="px-4 py-3"><?= badge($r['status']) ?></td>
            <td class="px-4 py-3"><?= $r['last_status']?badge($r['last_status']):'<span class="text-xs text-slate-400">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/ppms/projects.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual check (demo DB installed)**

Log in via `app/ppms/login.php` as **JE** → open a project → submit new physical/financial % → "Submitted for verification". Switch role to **AE** (sidebar) → open the same project → **Verify & Apply** → the project's headline percentages update and history shows "Verified".

- [ ] **Step 4: Commit**

```bash
git add app/ppms/projects.php
git commit -m "feat(ppms): project progress capture with JE submit / AE verify"
```

---

## Task 7: Rescope existing PPMS pages onto the foundation shell

**Files:**
- Modify: `app/ppms/requisitions.php`
- Modify: `app/ppms/reports.php`

- [ ] **Step 1: Update requisitions.php header block**

In `app/ppms/requisitions.php`, replace lines 1-4 (the opening requires + `require_login()`):

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
```

with:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
```

Then find the render line:

```php
$LAYOUT='app'; $ACTIVE='ppms_req'; $PAGE_TITLE='Fund Requisition';
require __DIR__ . '/../../includes/header.php';
```

and replace it with:

```php
set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='requisitions'; $PAGE_TITLE='Fund Requisition';
require __DIR__ . '/../../includes/header.php';
```

- [ ] **Step 2: Update reports.php header block**

In `app/ppms/reports.php`, replace lines 1-4:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
```

with:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
```

Then find:

```php
$LAYOUT='app'; $ACTIVE='ppms_reports'; $PAGE_TITLE='Reports & MIS';
require __DIR__ . '/../../includes/header.php';
```

and replace it with:

```php
set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='reports'; $PAGE_TITLE='Reports & MIS';
require __DIR__ . '/../../includes/header.php';
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/ppms/requisitions.php && php -l app/ppms/reports.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Manual check**

Open `app/ppms/requisitions.php` and `app/ppms/reports.php` while logged in: the PPMS sidebar shows with **Fund Requisition** / **Reports / MIS** highlighted respectively, and the masthead carries the PPMS accent.

- [ ] **Step 5: Commit**

```bash
git add app/ppms/requisitions.php app/ppms/reports.php
git commit -m "feat(ppms): rescope requisitions & reports onto the product shell"
```

---

## Task 8: Fix shared role-switch / login redirects, retire the aggregate dashboard

**Files:**
- Modify: `auth/role_switch.php`
- Modify: `auth/login.php`
- Delete: `app/dashboard.php`

- [ ] **Step 1: Make role_switch return to where the demo came from**

Replace the **entire contents** of `auth/role_switch.php` with:

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$role = strtoupper($_GET['role'] ?? '');
$allowed = ['SECRETARY','EIC','CE','SE','EE','AE','JE','FINANCE','ADMIN','ASO','ACCOUNTS','CITIZEN','EDITOR','CONSUMER','CONTRACTOR'];

// Return target: explicit ?to=, else the referring page, else the launcher.
$to = $_GET['to'] ?? ($_SERVER['HTTP_REFERER'] ?? base_url('index.php'));

if (in_array($role, $allowed, true) && switch_to_role($role)) {
    flash('Switched to ' . $role . ' view.');
    header('Location: ' . $to);
} else {
    header('Location: ' . base_url('index.php'));
}
exit;
```

- [ ] **Step 2: Fix the login redirect (aggregate dashboard is going away)**

In `auth/login.php`, replace both occurrences of:

```php
header('Location: ' . base_url('app/dashboard.php')); exit;
```

with:

```php
header('Location: ' . base_url('index.php')); exit;
```

(There are two: one inside the POST success branch, one in the `if (is_logged_in())` guard. Both become the launcher.)

- [ ] **Step 3: Delete the aggregate dashboard**

Run: `grep -rn "app/dashboard.php" --include=*.php .`
Expected after Steps 1-2: no remaining references in live code (only — if anything — inside `app/dashboard.php` itself and docs). If any other live caller appears, repoint it to `base_url('index.php')` before deleting.

Then:

```bash
git rm app/dashboard.php
```

- [ ] **Step 4: Verify syntax + tests**

Run: `php -l auth/role_switch.php && php -l auth/login.php && php tests/run.php`
Expected: both lint clean; test suite passes.

- [ ] **Step 5: Commit**

```bash
git add auth/role_switch.php auth/login.php
git commit -m "feat(ppms): role-switch returns to product; retire aggregate dashboard"
```

---

## Task 9: Full PPMS verification

- [ ] **Step 1: Run the whole test suite**

Run: `php tests/run.php`
Expected: all tests pass, exit 0 (foundation tests + the new `ppms_test.php` + updated `apps_test.php`).

- [ ] **Step 2: Lint every touched/created PHP file**

Run: `for f in app/ppms/lib.php app/ppms/login.php app/ppms/index.php app/ppms/projects.php app/ppms/requisitions.php app/ppms/reports.php auth/role_switch.php auth/login.php includes/apps.php setup.php sql/seed.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: End-to-end demo walk (Apache + MySQL, after re-running setup.php)**

1. Launcher → click the **PPMS** card → PPMS login.
2. Sign in as **Secretary** → State Command Centre (GIS + status chart + KPIs).
3. Switch to **JE** → Field dashboard; open a project → submit progress.
4. Switch to **AE** → verify the submitted progress; project % updates.
5. Switch to **EE** → open Fund Requisition → create/submit one.
6. Switch to **SE** → recommend it; **FINANCE** → approve; **EIC** → release → open the Fund Release Certificate.
7. Open **Reports / MIS** → filter + CSV export.
Confirm the sidebar role-switcher only lists PPMS roles, the accent is PPMS teal throughout, and nothing links back to a generic/aggregate dashboard.

- [ ] **Step 4: Push**

```bash
git push origin <current-branch>
```

---

## Notes

- PPMS reads only PPMS tables; revenue/payments/grievances/allocations/contractors are intentionally absent (they belong to other products).
- The fund-release step is available to **EIC** (a PPMS role); the legacy `ADMIN` owner label on seeded rows is cosmetic and does not block the EIC release path.
- `app/ppms/certificate.php` already renders standalone (its own print layout) and needs no changes; it remains linked from the requisition detail view.
- Carried-forward foundation items addressed here: PPMS `home` now exists (Task 5), `$ACTIVE` keys fixed (Task 7), role-switch redirect fixed (Task 8), aggregate dashboard removed (Task 8).
