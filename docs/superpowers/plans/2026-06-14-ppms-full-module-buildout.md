# PPMS Full Module Build-out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing PPMS product so it covers every module/feature in `ppms.md` — milestone tracking, a BI dashboard with drill-down, a multi-format Report Builder, scheduled/monthly-MIS reports, and SMS/OTP notifications — surfaced one-nav-item-per-module for the tender live demo.

**Architecture:** Pure, DB-free logic goes in `app/ppms/lib.php` and is unit-tested in `tests/ppms_test.php`. Each new capability is a thin presenter page under `app/ppms/` that calls `set_app_context('ppms')` then the shared themed shell (`includes/header.php` / `includes/footer.php`). All "hard" integrations are believable simulations: on-screen OTP, downloadable `.xls`/`.doc` via HTML-table mime types, and stored-but-simulated schedules. PPMS reads only PPMS tables.

**Tech Stack:** PHP 8.2 + MariaDB (XAMPP), Tailwind (CDN), Chart.js (CDN), the zero-dependency test runner at `tests/run.php` (`it()` / `assert_eq()` / `assert_true()`).

**Spec:** `docs/superpowers/specs/2026-06-14-ppms-full-module-buildout-design.md`

---

## File Structure

- `app/ppms/lib.php` — **modify** — add pure functions (`ppms_milestone_status`, `ppms_milestone_progress`, `ppms_bi_by_division`, `ppms_report_dataset`, `ppms_next_run`, `ppms_otp_generate`) + DB helpers (`ppms_notify`, `ppms_unread_count`). Pure functions are unit-tested; DB helpers are not.
- `tests/ppms_test.php` — **modify** — tests for every new pure function.
- `includes/apps.php` — **modify** — grow the PPMS nav to 8 items.
- `tests/apps_test.php` — **modify** — assert the new nav key order.
- `setup.php` — **modify** — add `milestones`, `notifications`, `scheduled_reports` tables + drop-list + count message.
- `sql/seed.php` — **modify** — seed those three tables.
- `app/ppms/milestones.php` — **create** — milestone tracking (list + per-project timeline + JE/AE update).
- `app/ppms/bi.php` — **create** — Chart.js BI dashboard with division drill-down.
- `app/ppms/reports.php` — **rewrite** — Report Builder + CSV/XLS/DOC/PDF export from one dataset.
- `app/ppms/scheduled.php` — **create** — scheduled reports + "Generate this month's MIS".
- `app/ppms/notifications.php` — **create** — SMS/OTP/email log; marks read on open.
- `includes/header.php` — **modify** — PPMS-scoped notification bell with unread count.
- `app/ppms/login.php` — **modify** — simulated OTP step on the username/password path.
- `app/ppms/requisitions.php` — **modify** — emit an SMS notification on release.
- `app/ppms/projects.php` — **modify** — emit an SMS notification when AE verifies progress.

**DRY note:** the spec mentioned a `ppms_bi_financials()` helper; the existing `ppms_kpis()` already returns sanctioned/spent/utilisation/at_risk/avg_physical/by_status, so the BI financial cards reuse `ppms_kpis()` and this extra function is intentionally NOT created.

---

## Task 1: Pure logic library + tests

**Files:**
- Modify: `app/ppms/lib.php`
- Test: `tests/ppms_test.php`

- [ ] **Step 1: Write the failing tests**

Append to the END of `tests/ppms_test.php` (it is a flat list of `it(...)` calls):

```php
it('ppms_milestone_status flags overdue non-done items as Delayed', function () {
    assert_eq('Done',       ppms_milestone_status('Done', '2026-05-01', '2026-04-01', '2026-06-14'));
    assert_eq('Delayed',    ppms_milestone_status('In-Progress', null, '2026-04-01', '2026-06-14'));
    assert_eq('Delayed',    ppms_milestone_status('Pending', null, '2026-01-01', '2026-06-14'));
    assert_eq('In-Progress',ppms_milestone_status('In-Progress', null, '2026-12-01', '2026-06-14'));
    assert_eq('Pending',    ppms_milestone_status('Pending', null, '2026-12-31', '2026-06-14'));
});

it('ppms_milestone_progress is weighted by Done milestones', function () {
    $ms = [
      ['weight'=>2,'status'=>'Done'],
      ['weight'=>2,'status'=>'In-Progress'],
      ['weight'=>1,'status'=>'Pending'],
    ];
    assert_eq(40, ppms_milestone_progress($ms));   // 2 / 5 = 40%
    assert_eq(0,  ppms_milestone_progress([]));     // no divide-by-zero
});

it('ppms_bi_by_division aggregates per division, sorted by name', function () {
    $projects = [
      ['divn'=>'B','physical_pct'=>80,'financial_pct'=>70,'sanctioned_amount'=>'200','spent_amount'=>'100'],
      ['divn'=>'A','physical_pct'=>60,'financial_pct'=>50,'sanctioned_amount'=>'100','spent_amount'=>'50'],
      ['divn'=>'A','physical_pct'=>40,'financial_pct'=>30,'sanctioned_amount'=>'100','spent_amount'=>'30'],
    ];
    $by = ppms_bi_by_division($projects);
    assert_eq('A', $by[0]['divn']);
    assert_eq(2,   $by[0]['count']);
    assert_eq(50,  $by[0]['phys']);          // (60+40)/2
    assert_eq(40,  $by[0]['fin']);           // (50+30)/2
    assert_eq(200.0, $by[0]['sanctioned']);
    assert_eq(80.0,  $by[0]['spent']);
    assert_eq(40,  $by[0]['utilisation']);   // 80/200
    assert_eq('B', $by[1]['divn']);
    assert_eq(50,  $by[1]['utilisation']);   // 100/200
});

it('ppms_report_dataset projects rows to columns by type', function () {
    $rows = [['name'=>'P1','scheme'=>'S1','divn'=>'D1','status'=>'On Track',
              'physical_pct'=>60,'financial_pct'=>55,'sanctioned_amount'=>'100','spent_amount'=>'50']];
    $ds = ppms_report_dataset('project', $rows);
    assert_eq(8, count($ds['columns']));
    assert_eq('Project', $ds['columns'][0]);
    assert_eq(['P1','S1','D1','On Track',60,55,'100','50'], $ds['rows'][0]);
    // unknown type falls back to the project layout
    assert_eq($ds['columns'], ppms_report_dataset('weird', $rows)['columns']);
});

it('ppms_next_run advances by frequency, defaulting to monthly', function () {
    assert_eq('2026-06-15', ppms_next_run('Daily',     '2026-06-14'));
    assert_eq('2026-06-21', ppms_next_run('Weekly',    '2026-06-14'));
    assert_eq('2026-07-14', ppms_next_run('Monthly',   '2026-06-14'));
    assert_eq('2026-09-14', ppms_next_run('Quarterly', '2026-06-14'));
    assert_eq('2026-07-14', ppms_next_run('???',       '2026-06-14'));
});

it('ppms_otp_generate returns a 6-digit numeric string', function () {
    $otp = ppms_otp_generate();
    assert_eq(6, strlen($otp));
    assert_true(ctype_digit($otp));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php`
Expected: FAIL — `Call to undefined function ppms_milestone_status()` (and the others).

- [ ] **Step 3: Implement the functions**

In `app/ppms/lib.php`, insert the following BEFORE the closing `ppms_require_login()` function definition (i.e. after `ppms_pending_actions()` and before the `/** Require a logged-in user... */` comment):

```php
/** Effective milestone status: an unfinished item past its planned date is Delayed. */
function ppms_milestone_status(string $status, ?string $actual, string $planned, string $today): string {
    if ($status === 'Done') return 'Done';
    if ($planned < $today)  return 'Delayed';
    return $status;
}

/** Weighted completion % from a project's milestones (only Done counts). */
function ppms_milestone_progress(array $milestones): int {
    $total = 0; $done = 0;
    foreach ($milestones as $m) {
        $w = (int)$m['weight'];
        $total += $w;
        if ($m['status'] === 'Done') $done += $w;
    }
    return $total > 0 ? (int)round($done / $total * 100) : 0;
}

/** Per-division BI aggregates as an indexed list sorted by division name. */
function ppms_bi_by_division(array $projects): array {
    $acc = [];
    foreach ($projects as $p) {
        $d = $p['divn'];
        if (!isset($acc[$d])) $acc[$d] = ['divn'=>$d,'count'=>0,'physSum'=>0,'finSum'=>0,'sanctioned'=>0.0,'spent'=>0.0];
        $acc[$d]['count']++;
        $acc[$d]['physSum']    += (int)$p['physical_pct'];
        $acc[$d]['finSum']     += (int)$p['financial_pct'];
        $acc[$d]['sanctioned'] += (float)$p['sanctioned_amount'];
        $acc[$d]['spent']      += (float)$p['spent_amount'];
    }
    ksort($acc);
    $out = [];
    foreach ($acc as $a) {
        $out[] = [
            'divn'        => $a['divn'],
            'count'       => $a['count'],
            'phys'        => $a['count'] ? (int)round($a['physSum']/$a['count']) : 0,
            'fin'         => $a['count'] ? (int)round($a['finSum']/$a['count']) : 0,
            'sanctioned'  => $a['sanctioned'],
            'spent'       => $a['spent'],
            'utilisation' => $a['sanctioned'] > 0 ? (int)round($a['spent']/$a['sanctioned']*100) : 0,
        ];
    }
    return $out;
}

/** Shape already-fetched rows into {columns, rows} for a report type (drives preview + every export). */
function ppms_report_dataset(string $type, array $rows): array {
    $maps = [
        'project' => [
            'columns' => ['Project','Scheme','Division','Status','Physical %','Financial %','Sanctioned (₹)','Spent (₹)'],
            'keys'    => ['name','scheme','divn','status','physical_pct','financial_pct','sanctioned_amount','spent_amount'],
        ],
        'division' => [
            'columns' => ['Division','Projects','Avg Physical %','Avg Financial %','Sanctioned (₹)','Spent (₹)','Utilisation %'],
            'keys'    => ['divn','count','phys','fin','sanctioned','spent','utilisation'],
        ],
        'scheme' => [
            'columns' => ['Scheme','Projects','Avg Physical %','Sanctioned (₹)','Spent (₹)'],
            'keys'    => ['scheme','count','phys','sanctioned','spent'],
        ],
        'requisition' => [
            'columns' => ['Req No','Project','Division','Amount (₹)','Status','Allocated (₹)'],
            'keys'    => ['req_no','proj','divn','amount_requested','status','allocated_amount'],
        ],
    ];
    $m = $maps[$type] ?? $maps['project'];
    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($m['keys'] as $k) $line[] = $r[$k] ?? '';
        $out[] = $line;
    }
    return ['columns' => $m['columns'], 'rows' => $out];
}

/** Next scheduled-report run date from a base date; unknown frequency defaults to monthly. */
function ppms_next_run(string $frequency, string $from): string {
    $map = ['Daily'=>'+1 day','Weekly'=>'+1 week','Monthly'=>'+1 month','Quarterly'=>'+3 months'];
    $add = $map[$frequency] ?? '+1 month';
    return date('Y-m-d', strtotime($add, strtotime($from)));
}

/** Simulated 6-digit OTP (demo only; shown on-screen, never sent over a real channel). */
function ppms_otp_generate(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/** Write a simulated notification (SMS / OTP / EMAIL). DB side-effect; not unit-tested. */
function ppms_notify(PDO $pdo, string $channel, string $to, string $message, ?string $entity = null): void {
    $pdo->prepare('INSERT INTO notifications (channel,to_label,message,entity,status) VALUES (?,?,?,?,?)')
        ->execute([$channel, $to, $message, $entity, 'Sent']);
}

/** Unread notification count (for the header bell). DB read; not unit-tested. */
function ppms_unread_count(PDO $pdo): int {
    try {
        return (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read=0')->fetchColumn();
    } catch (Throwable $e) {
        return 0;   // table may not exist yet on a fresh checkout
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php`
Expected: PASS — all new `ppms_test.php` assertions plus the existing suite.

- [ ] **Step 5: Lint + commit**

```bash
php -l app/ppms/lib.php
git add app/ppms/lib.php tests/ppms_test.php
git commit -m "feat(ppms): milestone/BI/report/schedule/OTP pure logic + notify helpers"
```

---

## Task 2: Grow the PPMS nav to one item per module

**Files:**
- Modify: `includes/apps.php`
- Modify: `tests/apps_test.php`

- [ ] **Step 1: Update the failing test**

In `tests/apps_test.php`, REPLACE this test:

```php
it('ppms nav exposes dashboard, projects, requisitions, reports in order', function () {
    assert_eq(['dashboard','projects','requisitions','reports'], array_column(wrd_app('ppms')['nav'], 'key'));
});
```

with:

```php
it('ppms nav exposes all eight module items in order', function () {
    assert_eq(
      ['dashboard','projects','milestones','requisitions','bi','reports','scheduled','notifications'],
      array_column(wrd_app('ppms')['nav'], 'key')
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — current nav has four keys, assertion expects eight.

- [ ] **Step 3: Update the registry**

In `includes/apps.php`, REPLACE the entire `'nav'` array of the `'ppms'` entry (lines 19-24) with:

```php
            'nav' => [
                ['key'=>'dashboard','label'=>'Command Centre','url'=>'app/ppms/index.php','icon'=>'▤'],
                ['key'=>'projects','label'=>'Projects & Progress','url'=>'app/ppms/projects.php','icon'=>'📍'],
                ['key'=>'milestones','label'=>'Milestones','url'=>'app/ppms/milestones.php','icon'=>'🏁'],
                ['key'=>'requisitions','label'=>'Fund Requisition','url'=>'app/ppms/requisitions.php','icon'=>'₹','roles'=>['EE','SE','EIC','FINANCE','SECRETARY']],
                ['key'=>'bi','label'=>'BI Dashboard','url'=>'app/ppms/bi.php','icon'=>'📈'],
                ['key'=>'reports','label'=>'Report Builder','url'=>'app/ppms/reports.php','icon'=>'▦'],
                ['key'=>'scheduled','label'=>'Scheduled Reports','url'=>'app/ppms/scheduled.php','icon'=>'🗓'],
                ['key'=>'notifications','label'=>'Notifications','url'=>'app/ppms/notifications.php','icon'=>'🔔'],
            ],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS (the "each product has required fields" test still passes; PPMS nav just has more items).

- [ ] **Step 5: Lint + commit**

```bash
php -l includes/apps.php
git add includes/apps.php tests/apps_test.php
git commit -m "feat(ppms): expand sidebar to one nav item per ppms.md module"
```

---

## Task 3: Schema + seed for milestones, notifications, scheduled_reports

**Files:**
- Modify: `setup.php`
- Modify: `sql/seed.php`

- [ ] **Step 1: Extend the drop-list**

In `setup.php`, REPLACE the drop-list `foreach` (lines 25-27) with (adds the three new tables first):

```php
    foreach (['scheduled_reports','notifications','milestones','progress_updates','workflow_log','payments','bills','drawal_entries','consumers','allocations',
              'contractor_apps','contractors','fund_requisitions','projects','schemes',
              'divisions','content','grievances','rti_applications','users'] as $t) {
```

- [ ] **Step 2: Add the three CREATE TABLE blocks**

In `setup.php`, immediately AFTER the `progress_updates` `CREATE TABLE` block (after its `SQL);` near line 97, before the `contractors` block), insert:

```php
    $pdo->exec(<<<SQL
    CREATE TABLE milestones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        name VARCHAR(160), name_hi VARCHAR(160) NULL,
        planned_date DATE, actual_date DATE NULL,
        weight INT, -- contribution toward project completion
        status VARCHAR(20), -- Pending, In-Progress, Done, Delayed
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel VARCHAR(10), -- SMS, OTP, EMAIL
        to_label VARCHAR(120),
        message VARCHAR(255),
        entity VARCHAR(60) NULL,
        status VARCHAR(12), -- Sent, Delivered
        is_read TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE scheduled_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120),
        report_type VARCHAR(30), -- project, division, scheme, requisition, monthly_mis
        frequency VARCHAR(12),   -- Daily, Weekly, Monthly, Quarterly
        format VARCHAR(8),       -- PDF, XLS, DOC, CSV
        recipients VARCHAR(200),
        last_run DATETIME NULL, next_run DATE,
        active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
```

- [ ] **Step 3: Update the table-count message**

In `setup.php`, change the line `ok('All 17 tables created (utf8mb4 / Hindi-ready).');` to:

```php
    ok('All 20 tables created (utf8mb4 / Hindi-ready).');
```

- [ ] **Step 4: Seed the three tables**

In `sql/seed.php`, immediately AFTER the progress-updates seeding loop (after the `foreach ($prog as $i=>$g) { ... }` block that ends ~line 105, before `// ---- Contractors`), insert:

```php
    // ---- Milestones (per project; mix of done / in-progress / overdue) ----
    $ms = [
        // project_id, name, name_hi, planned_date, actual_date, weight, status
        [1,'Detailed survey & DPR','सर्वेक्षण एवं डीपीआर','2023-06-30','2023-06-20',1,'Done'],
        [1,'Land acquisition','भू-अर्जन','2023-12-31','2023-12-15',2,'Done'],
        [1,'Spillway & gates','स्पिलवे एवं गेट','2025-03-31',null,3,'In-Progress'],
        [1,'Canal network','नहर नेटवर्क','2026-01-31',null,2,'Pending'],
        [3,'Desilting works','गाद निकासी','2024-09-30','2024-10-10',2,'Done'],
        [3,'Embankment strengthening','तटबंध सुदृढ़ीकरण','2025-04-30',null,2,'In-Progress'],
        [3,'Gate rehabilitation','गेट पुनर्वास','2025-02-28',null,1,'Pending'],   // overdue → Delayed
        [5,'Site mobilisation','स्थल जुटाव','2024-06-30','2024-07-20',1,'Done'],
        [5,'Phase-III spillway','चरण-३ स्पिलवे','2025-01-31',null,3,'Pending'],    // overdue → Delayed
        [5,'Powerhouse civil','पावरहाउस सिविल','2026-09-30',null,2,'Pending'],
    ];
    $ins = $pdo->prepare('INSERT INTO milestones (project_id,name,name_hi,planned_date,actual_date,weight,status) VALUES (?,?,?,?,?,?,?)');
    foreach ($ms as $m) $ins->execute($m);

    // ---- Notifications (pre-existing SMS / OTP / email events) ----
    $notif = [
        ['SMS','EE · +91-9430xx521','Fund requisition WRD/FR/2526/0001 released: ₹2.50 Cr.','fund_requisition #1','Delivered',1,12],
        ['SMS','AE · +91-9430xx340','Progress for Konar Canal verified & applied.','project #2','Delivered',1,9],
        ['EMAIL','Secretariat','Monthly MIS (May 2025) generated and emailed.','monthly_mis','Sent',1,30],
        ['OTP','JE · +91-9430xx210','Your PPMS login OTP is 481922 (demo).','login','Delivered',0,1],
        ['SMS','EE · +91-9430xx521','Milestone "Land acquisition" marked Done for Subarnarekha.','project #1','Delivered',0,2],
    ];
    $ins = $pdo->prepare('INSERT INTO notifications (channel,to_label,message,entity,status,is_read,created_at) VALUES (?,?,?,?,?,?,?)');
    foreach ($notif as $x) {
        $ins->execute([$x[0],$x[1],$x[2],$x[3],$x[4],$x[5], date('Y-m-d H:i:s', strtotime('-'.$x[6].' days'))]);
    }

    // ---- Scheduled reports ----
    $sched = [
        // name, report_type, frequency, format, recipients, frequency-for-next, active
        ['Monthly MIS — Secretariat','monthly_mis','Monthly','PDF','Secretary, EIC, Finance',1],
        ['Division-wise Progress','division','Weekly','XLS','All Executive Engineers',1],
        ['Fund Requisition Register','requisition','Monthly','CSV','Finance Cell',1],
    ];
    $ins = $pdo->prepare('INSERT INTO scheduled_reports (name,report_type,frequency,format,recipients,last_run,next_run,active) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($sched as $s) {
        $next = ppms_next_run($s[2], date('Y-m-d'));
        $ins->execute([$s[0],$s[1],$s[2],$s[3],$s[4], date('Y-m-d H:i:s', strtotime('-'.rand(3,20).' days')), $next, $s[5]]);
    }
```

Also, at the TOP of `sql/seed.php`, after `declare(strict_types=1);` (line 5), add the require so `ppms_next_run()` is available to the seeder:

```php
require_once __DIR__ . '/../app/ppms/lib.php';
```

- [ ] **Step 5: Verify syntax + reinstall**

Run: `php -l setup.php && php -l sql/seed.php`
Expected: `No syntax errors detected` for both.

Then (Apache + MySQL running) open `http://localhost/WRD/setup.php`.
Expected: "All 20 tables created" and the seed success row, no error (⚠️) rows.

- [ ] **Step 6: Commit**

```bash
git add setup.php sql/seed.php
git commit -m "feat(ppms): milestones, notifications, scheduled_reports schema + seed"
```

---

## Task 4: Milestones page (tracking + JE/AE update)

**Files:**
- Create: `app/ppms/milestones.php`

- [ ] **Step 1: Create the page**

Create `app/ppms/milestones.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$actor = $u['name'] . ' (' . $role . ')';
$today = date('Y-m-d');

// ---------- Action: JE/AE update a milestone ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($role,['JE','AE'],true)) {
    $mid = (int)($_POST['milestone_id'] ?? 0);
    $new = $_POST['status'] ?? '';
    if (in_array($new,['In-Progress','Done'],true) && $mid) {
        $m = $pdo->query("SELECT * FROM milestones WHERE id=$mid")->fetch();
        if ($m) {
            $actual = $new==='Done' ? $today : null;
            $pdo->prepare('UPDATE milestones SET status=?,actual_date=? WHERE id=?')->execute([$new,$actual,$mid]);
            add_audit($pdo,'project',(int)$m['project_id'],'Milestone '.$new,$role,$role,$actor,$m['name']);
            if ($new==='Done') {
                $proj = $pdo->query("SELECT name FROM projects WHERE id=".(int)$m['project_id'])->fetch();
                ppms_notify($pdo,'SMS','EE · +91-9430xx521','Milestone "'.$m['name'].'" marked Done for '.($proj['name']??'project').'.','project #'.(int)$m['project_id']);
            }
            flash('Milestone updated.');
        }
    }
    header('Location: ?project='.(int)($_POST['project_id'] ?? 0)); exit;
}

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='milestones'; $PAGE_TITLE='Milestones';
require __DIR__ . '/../../includes/header.php';

$viewId = (int)($_GET['project'] ?? 0);
$myDiv = (int)($u['division_id'] ?? 0);
$scopeDiv = in_array(ppms_role_view($role), ['field','division'], true) && $myDiv > 0;

// =================== DETAIL VIEW ===================
if ($viewId):
  $p = $pdo->query("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id WHERE p.id=$viewId")->fetch();
  if (!$p) { echo '<p class="text-slate-500">Project not found.</p>'; require __DIR__.'/../../includes/footer.php'; exit; }
  $rows = $pdo->query("SELECT * FROM milestones WHERE project_id=$viewId ORDER BY planned_date")->fetchAll();
  $prog = ppms_milestone_progress($rows);
?>
  <a href="milestones.php" class="text-sm text-slate-500 hover:underline">← <?= is_hi()?'सभी परियोजनाएँ':'All projects' ?></a>
  <div class="card p-6 mt-3">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="font-display text-2xl font-semibold text-ink"><?= bi($p['name'],$p['name_hi']) ?></h1>
        <p class="text-sm text-slate-500 mt-0.5"><?= e($p['divn']) ?> · <?= is_hi()?'मील-पत्थर ट्रैकिंग':'Milestone tracking' ?></p>
      </div>
      <div class="text-right">
        <div class="text-xs text-slate-400"><?= is_hi()?'मील-पत्थर पूर्णता':'Milestone completion' ?></div>
        <div class="font-display text-2xl font-semibold" style="color:<?= e($APP['accent']) ?>"><?= $prog ?>%</div>
      </div>
    </div>
    <div class="mt-3 h-2.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full" style="width:<?= $prog ?>%;background:<?= e($APP['accent']) ?>"></div></div>
  </div>

  <div class="card p-6 mt-6">
    <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'मील-पत्थर':'Milestones' ?></h2>
    <ol class="relative border-l-2 border-slate-200 ml-2 space-y-5">
      <?php foreach ($rows as $m):
        $eff = ppms_milestone_status($m['status'],$m['actual_date'],$m['planned_date'],$today); ?>
        <li class="ml-5">
          <span class="absolute -left-[7px] w-3 h-3 rounded-full ring-4 ring-brandsoft" style="background:<?= $eff==='Done'?'#10b981':($eff==='Delayed'?'#ef4444':'#94a3b8') ?>"></span>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-semibold text-ink"><?= bi($m['name'],$m['name_hi']) ?></span>
            <?= badge($eff) ?>
            <span class="text-[11px] text-slate-400"><?= is_hi()?'भार':'weight' ?> <?= (int)$m['weight'] ?></span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5"><?= is_hi()?'नियोजित':'Planned' ?>: <?= date('d M Y',strtotime($m['planned_date'])) ?><?= $m['actual_date']?' · '.(is_hi()?'वास्तविक':'Actual').': '.date('d M Y',strtotime($m['actual_date'])):'' ?></p>
          <?php if (in_array($role,['JE','AE'],true) && $eff!=='Done'): ?>
            <form method="post" class="mt-2 flex gap-2">
              <input type="hidden" name="milestone_id" value="<?= (int)$m['id'] ?>">
              <input type="hidden" name="project_id" value="<?= $viewId ?>">
              <button name="status" value="In-Progress" class="text-xs bg-sky-100 text-sky-800 font-semibold px-3 py-1.5 rounded-lg">▶ <?= is_hi()?'प्रगति पर':'Mark In-Progress' ?></button>
              <button name="status" value="Done" class="text-xs bg-emerald-600 text-white font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'पूर्ण':'Mark Done' ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if(!$rows): ?><li class="ml-5 text-sm text-slate-400"><?= is_hi()?'कोई मील-पत्थर नहीं।':'No milestones defined.' ?></li><?php endif; ?>
    </ol>
  </div>

<?php
// =================== LIST VIEW ===================
else:
  $sql = "SELECT p.id,p.name,p.name_hi,d.name divn,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id=p.id) total,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id=p.id AND m.status='Done') done,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id=p.id AND m.status<>'Done' AND m.planned_date < '$today') delayed
          FROM projects p JOIN divisions d ON d.id=p.division_id";
  if ($scopeDiv) $sql .= " WHERE p.division_id=".$myDiv;
  $sql .= " ORDER BY delayed DESC, p.name";
  $rows = $pdo->query($sql)->fetchAll();
?>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'मील-पत्थर ट्रैकिंग':'Milestone Tracking' ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'परियोजना-वार मील-पत्थर एवं विलंब':'Per-project milestones & delays' ?> · PPMS</p></div>
  </div>
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
        <tr><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th><th class="text-left px-4 py-3 hidden md:table-cell">Division</th>
        <th class="text-left px-4 py-3"><?= is_hi()?'मील-पत्थर':'Milestones' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'विलंबित':'Delayed' ?></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($rows as $r): ?>
          <tr class="hover:bg-paper cursor-pointer" onclick="location.href='?project=<?= $r['id'] ?>'">
            <td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['name'],$r['name_hi']) ?></td>
            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['divn']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= (int)$r['done'] ?>/<?= (int)$r['total'] ?> <?= is_hi()?'पूर्ण':'done' ?></td>
            <td class="px-4 py-3"><?= (int)$r['delayed']>0 ? '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset bg-rose-100 text-rose-800 ring-rose-600/20">'.(int)$r['delayed'].'</span>' : '<span class="text-xs text-slate-400">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/ppms/milestones.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual check (demo DB installed)**

Log in (PPMS) as **JE** → Milestones → open a project with an overdue item → it shows **Delayed**; "Mark Done" raises the completion %. List view shows a Delayed count chip.

- [ ] **Step 4: Commit**

```bash
git add app/ppms/milestones.php
git commit -m "feat(ppms): milestone tracking page with JE/AE updates and delay flagging"
```

---

## Task 5: BI Dashboard with drill-down

**Files:**
- Create: `app/ppms/bi.php`

- [ ] **Step 1: Create the page**

Create `app/ppms/bi.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$myDiv = (int)($u['division_id'] ?? 0);
$scopeDiv = in_array(ppms_role_view($role), ['field','division'], true) && $myDiv > 0;

// Drill-down: ?div=ID (oversight/finance may pick any; field/division locked to own)
$drill = (int)($_GET['div'] ?? 0);
if ($scopeDiv) $drill = $myDiv;

$where = $drill ? "WHERE p.division_id=$drill" : '';
$projects = $pdo->query("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id $where ORDER BY p.physical_pct DESC")->fetchAll();

$k       = ppms_kpis($projects);
$byDiv   = ppms_bi_by_division($projects);
$divName = $drill ? ($pdo->query("SELECT name FROM divisions WHERE id=$drill")->fetchColumn() ?: '') : '';
$delayed = array_values(array_filter($projects, fn($p)=>in_array($p['status'],['Delayed','Critical'],true)));
usort($delayed, fn($a,$b)=>(int)$a['physical_pct']-(int)$b['physical_pct']);
$delayed = array_slice($delayed,0,6);

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='bi'; $PAGE_TITLE='BI Dashboard';
$EXTRA_HEAD = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'बीआई डैशबोर्ड':'BI Dashboard' ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'इंटरैक्टिव विश्लेषण · ड्रिल-डाउन':'Interactive analytics · drill-down' ?><?= $divName?' · '.e($divName):'' ?></p>
  </div>
  <?php if ($drill && !$scopeDiv): ?>
    <a href="bi.php" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">← <?= is_hi()?'सभी प्रमंडल':'All divisions' ?></a>
  <?php endif; ?>
</div>

<!-- Financial / performance metric cards (reuse ppms_kpis) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach ([
    [is_hi()?'स्वीकृत परिव्यय':'Sanctioned Outlay', inr($k['sanctioned']), 'text-ink'],
    [is_hi()?'व्यय':'Expenditure', inr($k['spent']), 'text-emerald-700'],
    [is_hi()?'उपयोगिता':'Utilisation', $k['utilisation'].'%', 'text-sky-700'],
    [is_hi()?'जोखिम पर':'At Risk', (string)$k['at_risk'], 'text-rose-700'],
  ] as $kp): ?>
    <div class="card acc-kpi p-5 lift"><div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div></div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-6">
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'प्रमंडल-वार भौतिक बनाम वित्तीय':'Physical vs Financial by Division' ?></h2>
    <p class="text-[11px] text-slate-400 mb-2"><?= is_hi()?'किसी प्रमंडल पर क्लिक कर ड्रिल-डाउन करें':'Click a bar to drill into a division' ?></p>
    <canvas id="divChart" height="150"></canvas>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'निधि उपयोगिता % (प्रमंडल)':'Fund Utilisation % (Division)' ?></h2>
    <canvas id="utilChart" height="150"></canvas>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'स्थिति वितरण':'Status Distribution' ?></h2>
    <canvas id="statusChart" height="150"></canvas>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'सर्वाधिक विलंबित परियोजनाएँ':'Top Delayed Projects' ?></h2>
    <canvas id="delayChart" height="150"></canvas>
  </div>
</div>

<script>
const BYDIV   = <?= json_encode($byDiv, JSON_UNESCAPED_UNICODE) ?>;
const BYSTATUS= <?= json_encode($k['by_status'], JSON_UNESCAPED_UNICODE) ?>;
const DELAYED = <?= json_encode(array_map(fn($p)=>['name'=>$p['name'],'phys'=>(int)$p['physical_pct']], $delayed), JSON_UNESCAPED_UNICODE) ?>;
const DIVIDS  = <?= json_encode(array_column($pdo->query("SELECT id,name FROM divisions ORDER BY name")->fetchAll(),'id','name'), JSON_UNESCAPED_UNICODE) ?>;
const accent = '<?= e($APP['accent']) ?>';

const divChart = new Chart(document.getElementById('divChart'), {
  type:'bar',
  data:{labels:BYDIV.map(d=>d.divn),datasets:[
    {label:'Physical %',data:BYDIV.map(d=>d.phys),backgroundColor:accent},
    {label:'Financial %',data:BYDIV.map(d=>d.fin),backgroundColor:'#94a3b8'}]},
  options:{responsive:true,scales:{y:{max:100}},onClick:(e,els)=>{
    if(!els.length) return; const name=BYDIV[els[0].index].divn; const id=DIVIDS[name];
    if(id) location.href='bi.php?div='+id;
  }}
});
new Chart(document.getElementById('utilChart'),{
  type:'line',
  data:{labels:BYDIV.map(d=>d.divn),datasets:[{label:'Utilisation %',data:BYDIV.map(d=>d.utilisation),borderColor:accent,backgroundColor:accent+'33',fill:true,tension:.3}]},
  options:{scales:{y:{max:100}}}
});
new Chart(document.getElementById('statusChart'),{
  type:'doughnut',
  data:{labels:Object.keys(BYSTATUS),datasets:[{data:Object.values(BYSTATUS),backgroundColor:['#10b981','#f59e0b','#ef4444','#0ea5e9','#6366f1']}]}
});
new Chart(document.getElementById('delayChart'),{
  type:'bar',
  data:{labels:DELAYED.map(d=>d.name),datasets:[{label:'Physical %',data:DELAYED.map(d=>d.phys),backgroundColor:'#ef4444'}]},
  options:{indexAxis:'y',scales:{x:{max:100}},plugins:{legend:{display:false}}}
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/ppms/bi.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual check**

Log in as **Secretary** → BI Dashboard → four charts render. Click a bar in "Physical vs Financial by Division" → page reloads scoped to that division (heading shows the division name); "← All divisions" resets. As **JE**, the page is locked to the user's division with no reset link.

- [ ] **Step 4: Commit**

```bash
git add app/ppms/bi.php
git commit -m "feat(ppms): BI dashboard with interactive charts and division drill-down"
```

---

## Task 6: Report Builder (multi-format export)

**Files:**
- Rewrite: `app/ppms/reports.php`

- [ ] **Step 1: Replace the file contents**

Replace the ENTIRE contents of `app/ppms/reports.php` with:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$myDiv = (int)($u['division_id'] ?? 0);
$scopeDiv = in_array(ppms_role_view($role), ['field','division'], true) && $myDiv > 0;

$type   = $_GET['type']   ?? 'project';
$fStatus= $_GET['status'] ?? '';
$fDiv   = (int)($_GET['div'] ?? 0);
if ($scopeDiv) $fDiv = $myDiv;
$valid = ['project','division','scheme','requisition'];
if (!in_array($type,$valid,true)) $type = 'project';

// ---- Build the row set for the chosen report type ----
function ppms_fetch_report(PDO $pdo, string $type, string $fStatus, int $fDiv): array {
    if ($type === 'requisition') {
        $w=[]; $p=[];
        if ($fDiv) { $w[]='fr.division_id=?'; $p[]=$fDiv; }
        $wsql = $w ? ('WHERE '.implode(' AND ',$w)) : '';
        $st=$pdo->prepare("SELECT fr.req_no,p.name proj,d.name divn,fr.amount_requested,fr.status,fr.allocated_amount
                           FROM fund_requisitions fr JOIN projects p ON p.id=fr.project_id
                           JOIN divisions d ON d.id=fr.division_id $wsql ORDER BY fr.id DESC");
        $st->execute($p); return $st->fetchAll();
    }
    // project rows underpin project / division / scheme
    $w=[]; $p=[];
    if ($fStatus){ $w[]='pr.status=?'; $p[]=$fStatus; }
    if ($fDiv)   { $w[]='pr.division_id=?'; $p[]=$fDiv; }
    $wsql = $w ? ('WHERE '.implode(' AND ',$w)) : '';
    $st=$pdo->prepare("SELECT pr.*,s.name scheme,d.name divn FROM projects pr
                       JOIN schemes s ON s.id=pr.scheme_id JOIN divisions d ON d.id=pr.division_id
                       $wsql ORDER BY pr.physical_pct DESC");
    $st->execute($p); $proj=$st->fetchAll();
    if ($type === 'project') return $proj;
    if ($type === 'division') return ppms_bi_by_division($proj);
    // scheme: aggregate by scheme name
    $acc=[];
    foreach ($proj as $r){ $s=$r['scheme'];
        if(!isset($acc[$s])) $acc[$s]=['scheme'=>$s,'count'=>0,'physSum'=>0,'sanctioned'=>0.0,'spent'=>0.0];
        $acc[$s]['count']++; $acc[$s]['physSum']+=(int)$r['physical_pct'];
        $acc[$s]['sanctioned']+=(float)$r['sanctioned_amount']; $acc[$s]['spent']+=(float)$r['spent_amount'];
    }
    ksort($acc); $out=[];
    foreach($acc as $a){ $out[]=['scheme'=>$a['scheme'],'count'=>$a['count'],
        'phys'=>$a['count']?(int)round($a['physSum']/$a['count']):0,'sanctioned'=>$a['sanctioned'],'spent'=>$a['spent']]; }
    return $out;
}

$rows = ppms_fetch_report($pdo,$type,$fStatus,$fDiv);
$ds   = ppms_report_dataset($type,$rows);
$tLabel = ['project'=>'Project-wise','division'=>'Division-wise','scheme'=>'Scheme-wise','requisition'=>'Fund Requisition Register'][$type];

// ---- Export (CSV / XLS / DOC) — same dataset, different mime ----
$export = $_GET['export'] ?? '';
if ($export) {
    $fname = 'WRD_PPMS_'.ucfirst($type).'_'.date('Ymd');
    if ($export === 'csv') {
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$fname.'.csv"');
        $out=fopen('php://output','w'); fputcsv($out,$ds['columns']);
        foreach($ds['rows'] as $r) fputcsv($out,$r); fclose($out); exit;
    }
    if (in_array($export,['xls','doc'],true)) {
        $mime = $export==='xls' ? 'application/vnd.ms-excel' : 'application/msword';
        header('Content-Type: '.$mime.'; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'.'.$export.'"');
        echo '<meta charset="utf-8"><h3>WRD Jharkhand · PPMS · '.htmlspecialchars($tLabel).' Report</h3>';
        echo '<p>Generated '.date('d M Y, H:i').'</p><table border="1" cellspacing="0" cellpadding="6"><tr>';
        foreach($ds['columns'] as $c) echo '<th>'.htmlspecialchars($c).'</th>';
        echo '</tr>';
        foreach($ds['rows'] as $r){ echo '<tr>'; foreach($r as $cell) echo '<td>'.htmlspecialchars((string)$cell).'</td>'; echo '</tr>'; }
        echo '</table>'; exit;
    }
}

$divs = $pdo->query("SELECT id,name FROM divisions ORDER BY name")->fetchAll();
$qbase = 'type='.urlencode($type).($fStatus?'&status='.urlencode($fStatus):'').($fDiv?'&div='.$fDiv:'');

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='reports'; $PAGE_TITLE='Report Builder';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'रिपोर्ट बिल्डर':'Report Builder' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'कस्टम रिपोर्ट · PDF · Word · Excel · CSV':'Custom reports · PDF · Word · Excel · CSV' ?> · <?= e($tLabel) ?></p></div>
  <div class="flex gap-2">
    <a href="?export=csv&<?= $qbase ?>" class="border border-slate-300 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">⬇ CSV</a>
    <a href="?export=xls&<?= $qbase ?>" class="border border-slate-300 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">⬇ Excel</a>
    <a href="?export=doc&<?= $qbase ?>" class="border border-slate-300 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">⬇ Word</a>
    <button onclick="print()" class="border border-slate-300 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">🖨 PDF</button>
  </div>
</div>

<form class="card p-4 mb-5 flex flex-wrap gap-3 items-end">
  <div><label class="text-xs font-medium text-slate-500"><?= is_hi()?'रिपोर्ट प्रकार':'Report type' ?></label>
    <select name="type" class="mt-1 border border-slate-300 rounded-lg px-3 py-2 text-sm">
      <?php foreach(['project'=>'Project-wise','division'=>'Division-wise','scheme'=>'Scheme-wise','requisition'=>'Requisition Register'] as $tk=>$tl): ?>
        <option value="<?= $tk ?>" <?= $type===$tk?'selected':'' ?>><?= $tl ?></option>
      <?php endforeach; ?>
    </select></div>
  <div><label class="text-xs font-medium text-slate-500"><?= is_hi()?'स्थिति':'Status' ?></label>
    <select name="status" class="mt-1 border border-slate-300 rounded-lg px-3 py-2 text-sm"><option value="">All</option>
      <?php foreach(['On Track','Delayed','Critical'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <?php if(!$scopeDiv): ?>
  <div><label class="text-xs font-medium text-slate-500"><?= is_hi()?'प्रमंडल':'Division' ?></label>
    <select name="div" class="mt-1 border border-slate-300 rounded-lg px-3 py-2 text-sm"><option value="0">All</option>
      <?php foreach($divs as $d): ?><option value="<?= $d['id'] ?>" <?= $fDiv===(int)$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <button class="bg-brand text-white rounded-lg px-4 py-2 text-sm font-semibold"><?= is_hi()?'बनाएँ':'Build' ?></button>
  <a href="reports.php" class="text-sm text-slate-500 px-2 py-2">Reset</a>
</form>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
      <?php foreach($ds['columns'] as $c): ?><th class="text-left px-4 py-3"><?= e($c) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($ds['rows'] as $r): ?>
        <tr class="hover:bg-paper"><?php foreach($r as $cell): ?><td class="px-4 py-3 text-slate-700"><?= e((string)$cell) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if(!$ds['rows']): ?><tr><td class="px-4 py-6 text-center text-slate-400" colspan="<?= count($ds['columns']) ?>"><?= is_hi()?'कोई पंक्ति नहीं।':'No rows match.' ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3"><?= is_hi()?'एक ही डेटासेट से पूर्वावलोकन एवं सभी निर्यात — निरंतरता सुनिश्चित।':'Preview and every export derive from one dataset — guaranteed parity.' ?></p>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/ppms/reports.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual check**

Open **Report Builder**. Switch type to **Division-wise** → table columns change to the division aggregate. Click **Excel** → a `.xls` downloads and opens in Excel; **Word** → a `.doc` opens in Word; **CSV** downloads; **PDF** opens the print dialog. As a field role, the Division filter is hidden and data is locked to the user's division.

- [ ] **Step 4: Commit**

```bash
git add app/ppms/reports.php
git commit -m "feat(ppms): Report Builder with project/division/scheme/requisition + CSV/XLS/DOC/PDF"
```

---

## Task 7: Scheduled Reports + Monthly MIS

**Files:**
- Create: `app/ppms/scheduled.php`

- [ ] **Step 1: Create the page**

Create `app/ppms/scheduled.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$today = date('Y-m-d');

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action'] ?? '';
    if ($act==='create') {
        $freq=$_POST['frequency'] ?? 'Monthly';
        $st=$pdo->prepare('INSERT INTO scheduled_reports (name,report_type,frequency,format,recipients,next_run,active) VALUES (?,?,?,?,?,?,1)');
        $st->execute([trim($_POST['name']),$_POST['report_type'],$freq,$_POST['format'],trim($_POST['recipients']),ppms_next_run($freq,$today)]);
        flash('Schedule created.');
    } elseif ($act==='run') {
        $id=(int)$_POST['id']; $sr=$pdo->query("SELECT * FROM scheduled_reports WHERE id=$id")->fetch();
        if ($sr) {
            $pdo->prepare('UPDATE scheduled_reports SET last_run=NOW(),next_run=? WHERE id=?')->execute([ppms_next_run($sr['frequency'],$today),$id]);
            ppms_notify($pdo,'EMAIL',$sr['recipients'],'Report "'.$sr['name'].'" generated & emailed ('.$sr['format'].').','scheduled #'.$id);
            flash('Report generated & emailed to '.$sr['recipients'].'.');
        }
    } elseif ($act==='monthly_mis') {
        ppms_notify($pdo,'EMAIL','Secretary, EIC, Finance','Monthly MIS ('.date('F Y').') generated and emailed.','monthly_mis');
        flash('Monthly MIS generated for '.date('F Y').'.');
        header('Location: ?mis=1'); exit;
    }
    header('Location: scheduled.php'); exit;
}

// ---- Monthly MIS preview ----
$showMis = isset($_GET['mis']);
if ($showMis) {
    $projects = $pdo->query("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id")->fetchAll();
    $reqs = $pdo->query("SELECT status,amount_requested,allocated_amount FROM fund_requisitions")->fetchAll();
    $k = ppms_kpis($projects); $fund = ppms_fund_kpis($reqs); $byDiv = ppms_bi_by_division($projects);
}
$sched = $pdo->query("SELECT * FROM scheduled_reports ORDER BY active DESC, next_run")->fetchAll();

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='scheduled'; $PAGE_TITLE='Scheduled Reports';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'अनुसूचित रिपोर्ट':'Scheduled Reports' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'स्वचालित आवधिक रिपोर्ट एवं मासिक एमआईएस':'Automated periodic reports & monthly MIS' ?></p></div>
  <div class="flex gap-2">
    <form method="post"><input type="hidden" name="action" value="monthly_mis">
      <button class="bg-ink hover:bg-ink2 text-white font-semibold px-4 py-2.5 rounded-xl">📄 <?= is_hi()?'इस माह का एमआईएस बनाएँ':"Generate this month's MIS" ?></button></form>
    <button onclick="document.getElementById('newSch').showModal()" class="bg-brand hover:bg-branddeep text-white font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नई अनुसूची':'New Schedule' ?></button>
  </div>
</div>

<?php if ($showMis): ?>
<div class="card p-6 mb-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'मासिक एमआईएस':'Monthly MIS' ?> · <?= date('F Y') ?></h2>
    <button onclick="print()" class="border border-slate-300 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700">🖨 <?= is_hi()?'प्रिंट / PDF':'Print / PDF' ?></button>
  </div>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <?php foreach([
      [is_hi()?'स्वीकृत':'Sanctioned',inr($k['sanctioned'])],[is_hi()?'व्यय':'Spent',inr($k['spent'])],
      [is_hi()?'उपयोगिता':'Utilisation',$k['utilisation'].'%'],[is_hi()?'निर्गत राशि':'Released',inr($fund['released_amount'])],
    ] as $c): ?><div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= $c[0] ?></div><div class="font-display text-lg font-semibold text-ink mt-1"><?= $c[1] ?></div></div><?php endforeach; ?>
  </div>
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase"><tr><th class="text-left px-4 py-2">Division</th><th class="text-left px-4 py-2">Projects</th><th class="text-left px-4 py-2">Avg Physical</th><th class="text-left px-4 py-2">Utilisation</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($byDiv as $d): ?><tr><td class="px-4 py-2 text-slate-700"><?= e($d['divn']) ?></td><td class="px-4 py-2"><?= (int)$d['count'] ?></td><td class="px-4 py-2"><?= (int)$d['phys'] ?>%</td><td class="px-4 py-2"><?= (int)$d['utilisation'] ?>%</td></tr><?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
      <tr><th class="text-left px-4 py-3"><?= is_hi()?'नाम':'Name' ?></th><th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'आवृत्ति':'Frequency' ?></th>
      <th class="text-left px-4 py-3">Format</th><th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'अगला':'Next run' ?></th>
      <th class="text-left px-4 py-3"><?= is_hi()?'स्थिति':'Status' ?></th><th class="px-4 py-3"></th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($sched as $s): ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><div class="font-medium text-slate-800"><?= e($s['name']) ?></div><div class="text-xs text-slate-400"><?= e($s['recipients']) ?></div></td>
          <td class="px-4 py-3 text-slate-600 hidden md:table-cell"><?= e($s['frequency']) ?></td>
          <td class="px-4 py-3"><span class="text-xs font-mono bg-slate-100 rounded px-2 py-0.5"><?= e($s['format']) ?></span></td>
          <td class="px-4 py-3 text-slate-600 hidden md:table-cell"><?= e(date('d M Y',strtotime($s['next_run']))) ?></td>
          <td class="px-4 py-3"><?= $s['active']?'<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset bg-emerald-100 text-emerald-800 ring-emerald-600/20">Active</span>':'<span class="text-xs text-slate-400">Paused</span>' ?></td>
          <td class="px-4 py-3 text-right"><form method="post"><input type="hidden" name="action" value="run"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-white">▶ <?= is_hi()?'अभी चलाएँ':'Run now' ?></button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<dialog id="newSch" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/40">
  <form method="post" class="p-6"><input type="hidden" name="action" value="create">
    <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'नई अनुसूची':'New Schedule' ?></h2>
    <div class="space-y-4">
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'नाम':'Name' ?></label>
        <input name="name" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. Weekly Division Progress"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'प्रकार':'Report type' ?></label>
          <select name="report_type" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
            <option value="project">Project-wise</option><option value="division">Division-wise</option>
            <option value="scheme">Scheme-wise</option><option value="requisition">Requisition Register</option>
            <option value="monthly_mis">Monthly MIS</option></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'आवृत्ति':'Frequency' ?></label>
          <select name="frequency" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
            <option>Daily</option><option>Weekly</option><option selected>Monthly</option><option>Quarterly</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700">Format</label>
          <select name="format" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>PDF</option><option>XLS</option><option>DOC</option><option>CSV</option></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'प्राप्तकर्ता':'Recipients' ?></label>
          <input name="recipients" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. EIC, Finance"></div>
      </div>
    </div>
    <div class="flex gap-2 mt-5">
      <button type="button" onclick="document.getElementById('newSch').close()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600"><?= is_hi()?'रद्द':'Cancel' ?></button>
      <button class="flex-1 bg-brand text-white rounded-xl py-2.5 font-semibold"><?= is_hi()?'सहेजें':'Save Schedule' ?></button>
    </div>
  </form>
</dialog>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/ppms/scheduled.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual check**

Open **Scheduled Reports** → seeded schedules listed. "New Schedule" → create one (next-run auto-computed). "Run now" → flash confirms email + a Notification is written. "Generate this month's MIS" → a formatted MIS block renders with print button; a notification is logged.

- [ ] **Step 4: Commit**

```bash
git add app/ppms/scheduled.php
git commit -m "feat(ppms): scheduled reports CRUD, run-now, and monthly MIS generation"
```

---

## Task 8: Notifications page + header bell

**Files:**
- Create: `app/ppms/notifications.php`
- Modify: `includes/header.php`

- [ ] **Step 1: Create the notifications page**

Create `app/ppms/notifications.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();

// Opening the page clears the unread bell count.
$pdo->exec('UPDATE notifications SET is_read=1 WHERE is_read=0');
$rows = $pdo->query("SELECT * FROM notifications ORDER BY id DESC")->fetchAll();

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='notifications'; $PAGE_TITLE='Notifications';
require __DIR__ . '/../../includes/header.php';

$chip = ['SMS'=>'bg-sky-100 text-sky-800','OTP'=>'bg-violet-100 text-violet-800','EMAIL'=>'bg-emerald-100 text-emerald-800'];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'सूचनाएँ':'Notifications' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'एसएमएस / ओटीपी / ईमेल लॉग (सिम्युलेटेड)':'SMS / OTP / Email log (simulated gateway)' ?></p></div>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
      <tr><th class="text-left px-4 py-3"><?= is_hi()?'चैनल':'Channel' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'प्राप्तकर्ता':'To' ?></th>
      <th class="text-left px-4 py-3"><?= is_hi()?'संदेश':'Message' ?></th><th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'समय':'When' ?></th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($rows as $n): ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $chip[$n['channel']] ?? 'bg-slate-100 text-slate-700' ?>"><?= e($n['channel']) ?></span></td>
          <td class="px-4 py-3 text-slate-600"><?= e($n['to_label']) ?></td>
          <td class="px-4 py-3 text-slate-800"><?= e($n['message']) ?><?php if($n['entity']): ?><span class="text-xs text-slate-400"> · <?= e($n['entity']) ?></span><?php endif; ?></td>
          <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= date('d M Y, H:i',strtotime($n['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td class="px-4 py-6 text-center text-slate-400" colspan="4"><?= is_hi()?'कोई सूचना नहीं।':'No notifications yet.' ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Add the PPMS-scoped bell to the header**

In `includes/header.php`, find the logged-in block (lines 103-109) that begins `<?php if ($u): ?>` and starts with the dashboard link. Insert the bell immediately AFTER the opening `<?php if ($u): ?>` line and BEFORE the existing dashboard `<a ...>` link:

```php
        <?php if (($APP['key'] ?? '') === 'ppms'):
            require_once __DIR__ . '/../app/ppms/lib.php';
            $unread = ppms_unread_count(db()); ?>
          <a href="<?= base_url('app/ppms/notifications.php') ?>" class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg hover:bg-slate-100" title="Notifications">
            <span class="text-lg">🔔</span>
            <?php if ($unread > 0): ?><span class="absolute -top-0.5 -right-0.5 bg-rose-600 text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 px-1 grid place-items-center"><?= $unread ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
```

(For reference, the surrounding block becomes:
```php
      <?php if ($u): ?>
        <?php if (($APP['key'] ?? '') === 'ppms'): ... bell ... endif; ?>
        <a href="<?= base_url($APP['home'] ?? 'index.php') ?>" ...>Dashboard</a>
        ...
```
`db()` is available because `auth.php` (required at the top of `header.php`) pulls in `config/db.php`. `ppms_unread_count()` swallows DB errors and returns 0, so non-PPMS contexts and fresh checkouts are unaffected.)

- [ ] **Step 3: Verify syntax**

Run: `php -l app/ppms/notifications.php && php -l includes/header.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Manual check**

On any PPMS page the bell shows an unread count (seeded unread rows). Open **Notifications** → list renders, the page marks all read, and the bell count clears on the next PPMS page load. Visit a non-PPMS product (e.g. E-Tariff) → no bell appears.

- [ ] **Step 5: Commit**

```bash
git add app/ppms/notifications.php includes/header.php
git commit -m "feat(ppms): notifications log page + PPMS-scoped header bell"
```

---

## Task 9: Login OTP step (simulated 2FA)

**Files:**
- Modify: `app/ppms/login.php`

- [ ] **Step 1: Replace the PHP logic block**

In `app/ppms/login.php`, REPLACE the top logic block (lines 1-24, from `<?php` through the `$acc = $APP['accent'];` line) with:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/app_context.php';
require_once __DIR__ . '/lib.php';
set_app_context('ppms');
$APP = app_ctx();

$error = ''; $stage = 'login';
if (is_logged_in()) { header('Location: ' . base_url('app/ppms/index.php')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? 'credentials';
    if ($step === 'credentials') {
        $st = db()->prepare('SELECT id,username,name,role,phone FROM users WHERE username=?');
        $st->execute([trim($_POST['username'] ?? '')]);
        $cand = $st->fetch();
        // Verify password without logging in yet (OTP gate first).
        $ok = false;
        if ($cand) {
            $h = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $h->execute([$cand['id']]);
            $ok = password_verify($_POST['password'] ?? '', (string)$h->fetchColumn());
        }
        if ($ok) {
            $_SESSION['ppms_otp'] = ppms_otp_generate();
            $_SESSION['ppms_otp_user'] = trim($_POST['username']);
            $phone = $cand['phone'] ?: '+91-9430xxxxxx';
            ppms_notify(db(),'OTP',$cand['name'].' · '.$phone,'Your PPMS login OTP is '.$_SESSION['ppms_otp'].' (demo).','login');
            $stage = 'otp';
        } else {
            $error = is_hi() ? 'अमान्य उपयोगकर्ता नाम या पासवर्ड।' : 'Invalid username or password.';
        }
    } elseif ($step === 'otp') {
        if (($_POST['otp'] ?? '') === ($_SESSION['ppms_otp'] ?? '_') && !empty($_SESSION['ppms_otp_user'])) {
            // OTP verified — establish the real session via the demo password.
            login_user($_SESSION['ppms_otp_user'], DEMO_PASSWORD);
            unset($_SESSION['ppms_otp'], $_SESSION['ppms_otp_user']);
            header('Location: ' . base_url('app/ppms/index.php')); exit;
        }
        $error = is_hi() ? 'गलत ओटीपी।' : 'Incorrect OTP. Try the code shown above.';
        $stage = 'otp';
    }
}

// PPMS role quick-pick (only this product's roles) — bypasses OTP for the fast demo tour.
$quick = [
  ['SECRETARY','Secretary','🏛'],['EIC','Engineer-in-Chief','⚙'],['SE','Superintending Engr','📐'],
  ['EE','Executive Engineer','📋'],['AE','Assistant Engineer','📏'],['JE','Junior Engineer','🛠'],
  ['FINANCE','Finance Officer','₹'],
];
$acc = $APP['accent'];
```

- [ ] **Step 2: Replace the credentials form with a stage-aware form**

In `app/ppms/login.php`, REPLACE the existing `<form method="post" ...>...</form>` block (the username/password form, originally lines 59-69) with:

```php
        <?php if ($stage === 'otp'): ?>
          <div class="mt-4 bg-violet-50 ring-1 ring-violet-200 rounded-lg px-3 py-2.5 text-sm text-violet-800">
            <?= is_hi()?'पंजीकृत मोबाइल पर ओटीपी भेजा गया।':'OTP sent to your registered mobile.' ?>
            <div class="mt-1"><?= is_hi()?'डेमो ओटीपी':'Demo OTP' ?>: <b class="font-mono tracking-widest text-base"><?= e($_SESSION['ppms_otp'] ?? '') ?></b></div>
          </div>
          <form method="post" class="mt-5 space-y-4"><input type="hidden" name="step" value="otp">
            <div>
              <label class="text-sm font-medium text-slate-700"><?= is_hi()?'ओटीपी दर्ज करें':'Enter OTP' ?></label>
              <input name="otp" required autofocus inputmode="numeric" value="<?= e($_SESSION['ppms_otp'] ?? '') ?>" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5 font-mono tracking-widest" placeholder="6-digit code">
            </div>
            <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= is_hi()?'सत्यापित करें':'Verify & Sign in' ?> →</button>
            <a href="login.php" class="block text-center text-xs text-slate-500 hover:underline"><?= is_hi()?'पुनः प्रारंभ करें':'Start over' ?></a>
          </form>
        <?php else: ?>
          <form method="post" class="mt-5 space-y-4"><input type="hidden" name="step" value="credentials">
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
        <?php endif; ?>
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/ppms/login.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual check**

Open `app/ppms/login.php` → enter `ee` / `demo123` → the **OTP stage** appears with the demo code shown (and pre-filled) → "Verify & Sign in" lands on the Command Centre. A wrong code shows the error. The quick-pick role buttons still sign in directly (no OTP) for the fast tour. An OTP notification appears in the Notifications log.

- [ ] **Step 5: Commit**

```bash
git add app/ppms/login.php
git commit -m "feat(ppms): simulated OTP 2FA on the credentials login path"
```

---

## Task 10: Emit SMS notifications on key workflow events

**Files:**
- Modify: `app/ppms/requisitions.php`
- Modify: `app/ppms/projects.php`

- [ ] **Step 1: Notify on fund release**

In `app/ppms/requisitions.php`, find the `case 'release':` block. After its `add_audit(...)` line and before `flash('Fund released. Certificate available.'); break;`, insert:

```php
        ppms_notify($pdo,'SMS','EE · +91-9430xx521','Fund requisition '.$fr['req_no'].' released: '.inr((float)$fr['allocated_amount']).'.','fund_requisition #'.$id);
```

(`ppms_notify` is available because `requisitions.php` already requires `app/ppms/lib.php`.)

- [ ] **Step 2: Notify on progress verification**

In `app/ppms/projects.php`, find the `if ($act==='verify') {` block. After its `add_audit(...)` line and before `flash('Progress verified and applied.');`, insert:

```php
      $pname = $pdo->query("SELECT name FROM projects WHERE id=".(int)$g['project_id'])->fetchColumn();
      ppms_notify($pdo,'SMS','JE · +91-9430xx210','Progress for '.$pname.' verified & applied ('.$g['physical_pct'].'% physical).','project #'.(int)$g['project_id']);
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/ppms/requisitions.php && php -l app/ppms/projects.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Manual check**

As **EIC**, release an "Approved by Finance" requisition → an SMS row appears in Notifications and the bell count rises. As **AE**, verify a submitted progress update → another SMS row appears.

- [ ] **Step 5: Commit**

```bash
git add app/ppms/requisitions.php app/ppms/projects.php
git commit -m "feat(ppms): emit SMS notifications on fund release and progress verification"
```

---

## Task 11: Full verification

- [ ] **Step 1: Run the whole test suite**

Run: `php tests/run.php`
Expected: all tests pass, exit 0 (foundation + updated `ppms_test.php` + updated `apps_test.php`).

- [ ] **Step 2: Lint every touched/created PHP file**

Run:
```bash
for f in app/ppms/lib.php app/ppms/milestones.php app/ppms/bi.php app/ppms/reports.php \
         app/ppms/scheduled.php app/ppms/notifications.php app/ppms/login.php \
         app/ppms/requisitions.php app/ppms/projects.php includes/apps.php includes/header.php \
         setup.php sql/seed.php; do php -l "$f"; done
```
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: Reinstall the demo DB**

Open `http://localhost/WRD/setup.php` (Apache + MySQL running).
Expected: "All 20 tables created" + seed success, no error rows.

- [ ] **Step 4: End-to-end demo walk**

1. PPMS login → `ee` / `demo123` → **OTP** shown → Command Centre.
2. **Milestones** → open a project → an overdue item shows **Delayed**; "Mark Done" raises completion %.
3. **BI Dashboard** → four charts → click a division bar → drill-down → reset.
4. **Report Builder** → Division-wise → download **Excel** + **Word** (open natively) + CSV + PDF.
5. **Scheduled Reports** → "Generate this month's MIS" → MIS block; a schedule "Run now" → email logged.
6. **Fund Requisition** → as EIC release one → **SMS** logged.
7. **Notifications** bell reflects new events; opening clears the count.
8. Switch to **JE** → Milestones/BI/Report Builder are scoped to the user's division only.

- [ ] **Step 5: Push**

```bash
git push origin main
```

---

## Notes

- All integrations are simulations by design (decided in brainstorming): OTP/SMS/email rows are written to `notifications`; no external gateway is contacted.
- PPMS remains product-isolated: only PPMS tables are read/written; payments/bills/allocations/contractors/content are untouched.
- The header bell is guarded to the PPMS context and degrades to count 0 if the `notifications` table is absent, so other products and pre-setup checkouts are unaffected.
- `ppms_bi_financials()` from the spec was intentionally dropped in favour of reusing `ppms_kpis()` (DRY).
