# Industrial Water Allocation Product Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn Industrial Water Allocation into a self-contained product on the foundation: an applicant-branded login, **role-adaptive dashboards** (industry applicant portal vs. officer allocation desk), the **AE→EE→SE→CE→EIC→Secretary** multi-level approval workflow, automated licence generation with a printable licence certificate, and a role-filtered sidebar — all scoped to allocation data only.

**Architecture:** Every page calls `set_app_context('allocation')` before the themed shell; pure logic lives in a tested `app/allocation/lib.php`; the sidebar/pages are role-aware via the foundation's `app_nav_visible` / `app_require_access`. Reads only allocation tables (`allocations`, plus `divisions`/`workflow_log`). Mirrors the PPMS/E-Tariff/Contractor products and the role-based-nav pattern. Per RFP §8.2 the chain is AE→EE→SE→CE→EIC→Secretary and the surfaces split into applicant (citizen tracking) vs officer (pending approvals, revenue, expiring licences).

**Tech Stack:** PHP 8.2 + MariaDB (XAMPP), Tailwind (CDN), the zero-dependency test runner at `tests/run.php`.

**Prerequisite:** Foundation + role-based-nav + PPMS + E-Tariff + Contractor are on `main`. Allocation registry: accent `#0891b2`, icon `💧`, home `app/allocation/index.php`. **This plan expands the allocation `roles`** from `['CONSUMER','AE','EE','CE','SECRETARY']` to `['CONSUMER','AE','EE','SE','CE','EIC','SECRETARY']` to match the RFP chain (all those users are already seeded).

---

## Role → nav map

| Nav item | Allowed roles |
|---|---|
| dashboard | all (role-adaptive: applicant portal vs officer desk) |
| applications | AE, EE, SE, CE, EIC, SECRETARY (officers) |
| licences | AE, EE, SE, CE, EIC, SECRETARY (officers) |

External `CONSUMER` (industry applicant) sees only **dashboard** — their portal (apply, track, download licence).

---

## File Structure

- `app/allocation/lib.php` — **create** — pure logic: `allocation_role_view`, `allocation_next_stage`, `allocation_annual_fee`, `allocation_kpis`, `allocation_pending_actions`, `allocation_require_login`. Only file with unit tests.
- `tests/allocation_test.php` — **create** — tests for the pure functions.
- `includes/apps.php` — **modify** — expand allocation `roles`; add `applications` + `licences` nav (role-tagged).
- `tests/apps_test.php` — **modify** — assert the allocation nav keys + expanded roles.
- `setup.php` — **modify** — add `allocations.login_user` column.
- `sql/seed.php` — **modify** — normalise the approved app's stage to `SECRETARY`; link the demo applicant to the `consumer` login.
- `app/allocation/login.php` — **create** — applicant/officer-branded login, product-scoped role quick-pick.
- `app/allocation/index.php` — **rewrite** — role-adaptive dashboard (applicant portal with apply wizard; officer desk).
- `app/allocation/applications.php` — **create** — officer processing inbox + AE→…→Secretary workflow with role+stage guards.
- `app/allocation/licences.php` — **create** — issued-licences register.
- `app/allocation/licence.php` — **create** — printable licence certificate (QR).

---

## Task 1: Allocation logic library (pure, tested)

**Files:**
- Create: `app/allocation/lib.php`
- Test: `tests/allocation_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/allocation_test.php`:

```php
<?php
require_once __DIR__ . '/../app/allocation/lib.php';

it('allocation_role_view splits applicant vs officer', function () {
    assert_eq('applicant', allocation_role_view('CONSUMER'));
    foreach (['AE','EE','SE','CE','EIC','SECRETARY'] as $r) assert_eq('officer', allocation_role_view($r));
    assert_eq('officer', allocation_role_view('SOMETHING'));
});

it('allocation_next_stage walks AE->EE->SE->CE->EIC->SECRETARY->null', function () {
    assert_eq('EE',        allocation_next_stage('AE'));
    assert_eq('SE',        allocation_next_stage('EE'));
    assert_eq('CE',        allocation_next_stage('SE'));
    assert_eq('EIC',       allocation_next_stage('CE'));
    assert_eq('SECRETARY', allocation_next_stage('EIC'));
    assert_eq(null,        allocation_next_stage('SECRETARY'));
    assert_eq(null,        allocation_next_stage('UNKNOWN'));
});

it('allocation_annual_fee is MLD * 50000 rounded to paise', function () {
    assert_eq(4750000.0, allocation_annual_fee(95.0));
    assert_eq(2000000.0, allocation_annual_fee(40.0));
});

it('allocation_kpis counts process/licensed/hold/total', function () {
    $rows = [
      ['status'=>'New'],
      ['status'=>'Under Review'],
      ['status'=>'On Hold'],
      ['status'=>'Approved'],
      ['status'=>'Rejected'],
    ];
    $k = allocation_kpis($rows);
    assert_eq(3, $k['in_process']);   // New + Under Review + On Hold
    assert_eq(1, $k['licensed']);
    assert_eq(1, $k['on_hold']);
    assert_eq(5, $k['total']);
});

it('allocation_pending_actions returns this officer stage work, none for applicant', function () {
    $rows = [
      ['id'=>1,'app_no'=>'A1','applicant'=>'Tata','stage'=>'AE','status'=>'New'],
      ['id'=>2,'app_no'=>'A2','applicant'=>'SAIL','stage'=>'SECRETARY','status'=>'Under Review'],
      ['id'=>3,'app_no'=>'A3','applicant'=>'Usha','stage'=>'EE','status'=>'Approved'],
    ];
    assert_eq('applications.php?id=1', allocation_pending_actions('AE', $rows)[0]['url']);
    assert_eq('applications.php?id=2', allocation_pending_actions('SECRETARY', $rows)[0]['url']);
    assert_eq(0, count(allocation_pending_actions('EE', $rows)));        // id3 Approved
    assert_eq(0, count(allocation_pending_actions('CONSUMER', $rows)));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — require of `app/allocation/lib.php` fails.

- [ ] **Step 3: Write the library**

Create `app/allocation/lib.php`:

```php
<?php
declare(strict_types=1);

/**
 * Industrial Water Allocation pure logic — no DB, no rendering.
 * Approval chain per RFP §8.2.6: AE -> EE -> SE -> CE -> EIC -> SECRETARY (terminal, approves).
 */

/** Map a role to its dashboard archetype. */
function allocation_role_view(string $role): string {
    return $role === 'CONSUMER' ? 'applicant' : 'officer';
}

/** Next approval stage after $stage, or null at the terminal (SECRETARY). */
function allocation_next_stage(string $stage): ?string {
    return ['AE'=>'EE', 'EE'=>'SE', 'SE'=>'CE', 'CE'=>'EIC', 'EIC'=>'SECRETARY'][$stage] ?? null;
}

/** Annual allocation fee (demo rate: MLD x 50,000). */
function allocation_annual_fee(float $mld): float {
    return round(max(0.0, $mld) * 50000, 2);
}

/** KPIs. $rows: allocations with status. */
function allocation_kpis(array $rows): array {
    $inProcess = 0; $licensed = 0; $onHold = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'Approved') { $licensed++; continue; }
        if ($r['status'] === 'Rejected') continue;
        $inProcess++;
        if ($r['status'] === 'On Hold') $onHold++;
    }
    return ['in_process'=>$inProcess, 'licensed'=>$licensed, 'on_hold'=>$onHold, 'total'=>count($rows)];
}

/**
 * Pending actions for an officer role: applications at this role's stage that are
 * not yet Approved/Rejected. $rows: id, app_no, applicant, stage, status.
 */
function allocation_pending_actions(string $role, array $rows): array {
    if (!in_array($role, ['AE','EE','SE','CE','EIC','SECRETARY'], true)) return [];
    $verb = $role === 'SECRETARY' ? 'Approve & licence' : 'Scrutinise';
    $out = [];
    foreach ($rows as $r) {
        if ($r['stage'] !== $role) continue;
        if (in_array($r['status'], ['Approved','Rejected'], true)) continue;
        $out[] = ['label'=>$verb.' '.$r['app_no'].' · '.$r['applicant'], 'meta'=>'', 'status'=>$r['status'], 'url'=>'applications.php?id='.$r['id']];
    }
    return $out;
}

/** Require a logged-in user; bounce to the allocation login if not. */
function allocation_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/allocation/login.php')); exit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS — all `allocation_test.php` cases plus the pre-existing tests.

- [ ] **Step 5: Commit**

```bash
git add app/allocation/lib.php tests/allocation_test.php
git commit -m "feat(allocation): pure workflow/kpi/role logic with tests"
```

---

## Task 2: Registry roles + nav

**Files:**
- Modify: `includes/apps.php`
- Modify: `tests/apps_test.php`

- [ ] **Step 1: Update the failing test first**

In `tests/apps_test.php`, append:

```php
it('allocation roles include the full AE..SECRETARY chain plus applicant', function () {
    assert_eq(['CONSUMER','AE','EE','SE','CE','EIC','SECRETARY'], wrd_app('allocation')['roles']);
});
it('allocation nav exposes dashboard, applications, licences in order', function () {
    assert_eq(['dashboard','applications','licences'], array_column(wrd_app('allocation')['nav'], 'key'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — allocation roles are currently `['CONSUMER','AE','EE','CE','SECRETARY']` and nav has only `dashboard`.

- [ ] **Step 3: Update the registry**

In `includes/apps.php`, in the `'allocation'` entry, replace the `'roles'` line and the `'nav'` array so they read exactly:

```php
            'roles' => ['CONSUMER','AE','EE','SE','CE','EIC','SECRETARY'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Allocation Desk','url'=>'app/allocation/index.php','icon'=>'▤'],
                ['key'=>'applications','label'=>'Applications','url'=>'app/allocation/applications.php','icon'=>'📋','roles'=>['AE','EE','SE','CE','EIC','SECRETARY']],
                ['key'=>'licences','label'=>'Licences','url'=>'app/allocation/licences.php','icon'=>'📜','roles'=>['AE','EE','SE','CE','EIC','SECRETARY']],
            ],
```

Do not change any other product entry.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/apps.php tests/apps_test.php
git commit -m "feat(allocation): full role chain + applications/licences nav (role-tagged)"
```

---

## Task 3: Applicant-scoping column + stage normalisation seed

**Files:**
- Modify: `setup.php`
- Modify: `sql/seed.php`

- [ ] **Step 1: Add a `login_user` column to the allocations table**

In `setup.php`, find the `allocations` `CREATE TABLE` block and replace it with (adds the final `login_user` column):

```php
    $pdo->exec(<<<SQL
    CREATE TABLE allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_no VARCHAR(40) UNIQUE, applicant VARCHAR(180),
        source VARCHAR(60), source_name VARCHAR(120),
        quantity_mld DECIMAL(10,2), season VARCHAR(20),
        division_id INT, district VARCHAR(80),
        stage VARCHAR(30), status VARCHAR(30),
        license_no VARCHAR(40) NULL, gst VARCHAR(20),
        annual_fee DECIMAL(14,2), applied_on DATE,
        login_user VARCHAR(60) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
```

- [ ] **Step 2: Normalise the approved application's stage to a role code**

In `sql/seed.php`, find the `$alloc` array and replace the **first** row (Tata Steel) so its stage reads `SECRETARY` instead of `Secretary`:

```php
        ['WRD/IWA/2526/201','Tata Steel Ltd','River','Subarnarekha River',95.0,'Perennial',5,'East Singhbhum','SECRETARY','Approved','LIC/2526/0044','20SUBAR3456J1Z7',4750000,'2025-04-15'],
```

(The other five rows already use role-code stages: EIC, CE, SE, AE, EE.)

- [ ] **Step 3: Link the demo applicant to the `consumer` login**

In `sql/seed.php`, immediately AFTER the allocations seeding loop (`foreach ($alloc as $a) $ins->execute($a);`), insert:

```php
    // Link the demo applicant login to its allocation (portal scoping, consumer-style).
    $pdo->prepare("UPDATE allocations SET login_user='consumer' WHERE app_no=?")->execute(['WRD/IWA/2526/201']);
```

- [ ] **Step 4: Verify syntax and reinstall the demo DB**

Run: `php -l setup.php && php -l sql/seed.php`
Expected: `No syntax errors detected` for both.

Then run: `php setup.php > /dev/null 2>&1; php -r "require 'config/config.php'; \$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME,DB_USER,DB_PASS); echo 'linked applicant: '.\$p->query(\"SELECT applicant FROM allocations WHERE login_user='consumer'\")->fetchColumn().PHP_EOL; echo 'SECRETARY-stage: '.\$p->query(\"SELECT COUNT(*) FROM allocations WHERE stage='SECRETARY'\")->fetchColumn().PHP_EOL;"`
Expected: prints `linked applicant: Tata Steel Ltd` and `SECRETARY-stage: 1`.

- [ ] **Step 5: Commit**

```bash
git add setup.php sql/seed.php
git commit -m "feat(allocation): add login_user scoping; normalise approved stage to SECRETARY"
```

---

## Task 4: Allocation login / landing

**Files:**
- Create: `app/allocation/login.php`

- [ ] **Step 1: Create the login**

Create `app/allocation/login.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/app_context.php';
set_app_context('allocation');
$APP = app_ctx();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . base_url('app/allocation/index.php')); exit;
    }
    $error = is_hi() ? 'अमान्य उपयोगकर्ता नाम या पासवर्ड।' : 'Invalid username or password.';
}
if (is_logged_in()) { header('Location: ' . base_url('app/allocation/index.php')); exit; }

// Allocation role quick-pick (only this product's roles)
$quick = [
  ['SECRETARY','Secretary','🏛'],['EIC','Engineer-in-Chief','⚙'],['CE','Chief Engineer','📐'],
  ['SE','Superintending Engr','📋'],['EE','Executive Engineer','🗂'],['AE','Assistant Engineer','📏'],
  ['CONSUMER','Industry Applicant','🏭'],
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

  <div class="flex items-center justify-center p-6 bg-paper">
    <div class="w-full max-w-md">
      <div class="card p-7">
        <a href="<?= base_url('index.php') ?>" class="text-xs text-slate-500 hover:underline">← <?= is_hi()?'सभी उत्पाद':'All products' ?></a>
        <h2 class="d text-2xl font-bold text-ink mt-2"><?= e($APP['short']) ?> · <?= t('login') ?></h2>
        <p class="text-sm text-slate-500 mt-1"><?= is_hi()?'अपने खाते से प्रवेश करें':'Sign in to your account' ?></p>

        <?php if ($error): ?><div class="mt-4 bg-rose-50 text-rose-700 text-sm rounded-lg px-3 py-2 ring-1 ring-rose-200"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="mt-5 space-y-4">
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'उपयोगकर्ता नाम':'Username' ?></label>
            <input name="username" required autofocus class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. ae">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'पासवर्ड':'Password' ?></label>
            <input name="password" type="password" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="demo123">
          </div>
          <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= t('login') ?> →</button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-200">
          <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-2.5">Demo · one-click sign-in (Allocation roles)</p>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach ($quick as $q): ?>
              <a href="<?= base_url('auth/role_switch.php') ?>?role=<?= e($q[0]) ?>&to=<?= urlencode(base_url('app/allocation/index.php')) ?>"
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

Run: `php -l app/allocation/login.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/allocation/login.php
git commit -m "feat(allocation): branded login with product-scoped role quick-pick"
```

---

## Task 5: Officer processing inbox + workflow

**Files:**
- Create: `app/allocation/applications.php`

- [ ] **Step 1: Create `app/allocation/applications.php` with EXACTLY this content:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act=$_POST['action']??'';
  $id=(int)($_POST['id']??0);
  $a=$pdo->query("SELECT * FROM allocations WHERE id=$id")->fetch();
  if ($a) {
    $stage=$a['stage']; $rem=trim($_POST['remarks']??'');
    $active = !in_array($a['status'],['Approved','Rejected'],true);
    $permit = [
      'forward' => allocation_next_stage($stage)!==null && $role===$stage && $active,
      'approve' => $stage==='SECRETARY' && $role==='SECRETARY' && $a['status']!=='Approved',
      'hold'    => $role===$stage && $active,
      'reject'  => $role===$stage && $active,
    ][$act] ?? false;
    if (!$permit) { flash('Action not permitted for your role at this stage.'); header('Location: applications.php'); exit; }
    if ($act==='forward') {
      $next=allocation_next_stage($stage);
      $pdo->prepare("UPDATE allocations SET stage=?,status='Under Review' WHERE id=?")->execute([$next,$id]);
      add_audit($pdo,'allocation',$id,'Forwarded',$stage,$next,$actor,$rem);
      flash("Forwarded to $next.");
    } elseif ($act==='approve') {
      $lic='LIC/2526/'.str_pad((string)$id,4,'0',STR_PAD_LEFT);
      $pdo->prepare("UPDATE allocations SET status='Approved',license_no=? WHERE id=?")->execute([$lic,$id]);
      add_audit($pdo,'allocation',$id,'Approved & licence generated','SECRETARY','Issued',$actor,'Licence '.$lic);
      flash("Approved. Licence $lic generated.");
    } elseif ($act==='hold') {
      $pdo->prepare("UPDATE allocations SET status='On Hold' WHERE id=?")->execute([$id]);
      add_audit($pdo,'allocation',$id,'Held',$stage,$stage,$actor,$rem?:'Held for clarification.'); flash('Application held.');
    } elseif ($act==='reject') {
      $pdo->prepare("UPDATE allocations SET status='Rejected' WHERE id=?")->execute([$id]);
      add_audit($pdo,'allocation',$id,'Rejected',$stage,$stage,$actor,$rem?:'Rejected.'); flash('Rejected.');
    }
    header('Location: applications.php'); exit;
  }
}

set_app_context('allocation');
app_require_access('applications');
$LAYOUT='app'; $ACTIVE='applications'; $PAGE_TITLE='Applications';
require __DIR__ . '/../../includes/header.php';

$STAGES=['AE','EE','SE','CE','EIC','SECRETARY'];
$rows=$pdo->query("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id ORDER BY a.id DESC")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'आवेदन':'Applications' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'बहु-स्तरीय अनुमोदन':'Multi-level approval' ?> · AE → EE → SE → CE → EIC → SECRETARY</p></div>
</div>

<div class="space-y-3">
  <?php foreach($rows as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div><div class="font-display text-lg font-semibold text-ink"><?= e($a['applicant']) ?></div>
          <div class="text-sm text-slate-500"><span class="font-mono text-xs"><?= e($a['app_no']) ?></span> · <?= e($a['source']) ?>: <?= e($a['source_name']) ?> · <?= (float)$a['quantity_mld'] ?> MLD · <?= e($a['season']) ?></div>
          <div class="text-xs text-slate-400 mt-0.5"><?= e($a['divn']) ?> · GST <?= e($a['gst']) ?> · <?= is_hi()?'वार्षिक शुल्क':'Annual fee' ?> <?= inr((float)$a['annual_fee']) ?></div>
        </div>
        <div class="text-right"><?= badge($a['status']) ?><?php if($a['license_no']): ?><div class="text-xs text-emerald-700 font-semibold mt-1">📜 <a href="<?= base_url('app/allocation/licence.php') ?>?id=<?= $a['id'] ?>" target="_blank" class="hover:underline"><?= e($a['license_no']) ?></a></div><?php endif; ?></div>
      </div>

      <!-- hierarchy tracker -->
      <div class="flex items-center gap-1 mt-4">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="px-2 h-6 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i&&$a['status']!=='Rejected'?'text-white':'bg-slate-100 text-slate-400') ?>" <?= ($si===$i&&$a['status']!=='Rejected'&&$a['status']!=='Approved')?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if(!in_array($a['status'],['Approved','Rejected'],true) && $role===$a['stage']): ?>
        <form method="post" class="flex flex-wrap gap-2 mt-4 items-center">
          <input type="hidden" name="id" value="<?= $a['id'] ?>">
          <input name="remarks" placeholder="<?= is_hi()?'टिप्पणी / आपत्ति':'Remarks / objection' ?>" class="flex-1 min-w-[160px] border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
          <?php if($a['stage']==='SECRETARY'): ?>
            <button name="action" value="approve" class="bg-emerald-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'स्वीकृत + लाइसेंस':'Approve + Licence' ?></button>
          <?php else: ?>
            <button name="action" value="forward" class="btn-acc text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'अग्रेषित':'Forward' ?> →</button>
          <?php endif; ?>
          <button name="action" value="hold" class="bg-amber-100 text-amber-800 text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'रोकें':'Hold' ?></button>
          <button name="action" value="reject" class="bg-rose-100 text-rose-700 text-sm font-semibold px-3 py-1.5 rounded-lg">✕</button>
        </form>
      <?php elseif(!in_array($a['status'],['Approved','Rejected'],true)): ?>
        <p class="text-xs text-slate-400 mt-3"><?= is_hi()?'वर्तमान चरण':'Currently at stage' ?> <b><?= e($a['stage']) ?></b> — <?= is_hi()?'उस भूमिका में बदलें (बाएँ नीचे) कार्रवाई हेतु।':'switch to that role (bottom-left) to act.' ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/allocation/applications.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/allocation/applications.php
git commit -m "feat(allocation): officer inbox + AE..SECRETARY workflow with stage guards"
```

---

## Task 6: Role-adaptive dashboard + apply wizard

**Files:**
- Rewrite: `app/allocation/index.php`

- [ ] **Step 1: Replace the ENTIRE contents of `app/allocation/index.php` with:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

// Applicant self-application (creates an allocation at stage AE).
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='apply' && $role==='CONSUMER') {
  $cnt=(int)$pdo->query('SELECT COUNT(*) FROM allocations')->fetchColumn()+207;
  $ano=sprintf('WRD/IWA/2526/%03d',$cnt);
  $fee=allocation_annual_fee((float)$_POST['quantity']);
  $src=in_array($_POST['source']??'',['River','Canal','Reservoir'],true)?$_POST['source']:'River';
  $season=in_array($_POST['season']??'',['Perennial','Seasonal'],true)?$_POST['season']:'Perennial';
  $pdo->prepare("INSERT INTO allocations (app_no,applicant,source,source_name,quantity_mld,season,division_id,district,stage,status,gst,annual_fee,applied_on,login_user) VALUES (?,?,?,?,?,?,?,?, 'AE','New',?,?,CURDATE(),?)")
      ->execute([$ano,trim($_POST['applicant']),$src,trim($_POST['source_name']),(float)$_POST['quantity'],$season,(int)$_POST['division_id'],trim($_POST['district']),strtoupper(trim($_POST['gst'])),$fee,$u['username']]);
  add_audit($pdo,'allocation',(int)$pdo->lastInsertId(),'Application submitted (SWCS)','Applicant','AE',$actor,'Allocation engine validated source & season.');
  flash("Allocation application $ano submitted."); header('Location: index.php'); exit;
}

$view = allocation_role_view($role);
if ($view==='applicant') {
  $st=$pdo->prepare("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.login_user=? ORDER BY a.id DESC");
  $st->execute([$u['username']]); $rows=$st->fetchAll();
} else {
  $rows=$pdo->query("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id ORDER BY a.id DESC")->fetchAll();
}
$k=allocation_kpis($rows);
$tasks=allocation_pending_actions($role,$rows);
$STAGES=['AE','EE','SE','CE','EIC','SECRETARY'];

set_app_context('allocation');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Allocation Desk';
require __DIR__ . '/../../includes/header.php';
$divs=$pdo->query("SELECT id,name FROM divisions")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= $view==='applicant'?(is_hi()?'मेरा जल आवंटन':'My Water Allocation'):(is_hi()?'आवंटन कार्यालय':'Allocation Desk') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <?php if($view==='applicant'): ?>
    <button onclick="document.getElementById('newAlloc').showModal()" class="btn-acc font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नया आवेदन':'New Application' ?></button>
  <?php endif; ?>
</div>

<?php if($view==='officer'): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach([
    [is_hi()?'प्रक्रियाधीन':'In Process', (string)$k['in_process'], 'text-amber-700'],
    [is_hi()?'जारी लाइसेंस':'Licences Issued', (string)$k['licensed'], 'text-emerald-700'],
    [is_hi()?'रोके गए':'On Hold', (string)$k['on_hold'], 'text-rose-700'],
    [is_hi()?'कुल आवेदन':'Total Applications', (string)$k['total'], 'text-ink'],
  ] as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-5">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'मेरे चरण की लंबित फाइलें':'Files Pending at My Stage' ?></h2>
      <a href="<?= base_url('app/allocation/applications.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
    </div>
    <?php if($tasks): ?>
      <div class="space-y-2">
        <?php foreach($tasks as $tk): ?>
          <a href="<?= base_url('app/allocation/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
            <p class="text-sm font-medium text-slate-700 truncate"><?= e($tk['label']) ?></p><?= badge($tk['status']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm"><div class="text-4xl mb-2">✓</div><?= is_hi()?'आपके चरण पर कोई लंबित फाइल नहीं।':'No files pending at your stage.' ?><br><span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे)।':'Switch role (bottom-left).' ?></span></div>
    <?php endif; ?>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'त्वरित लिंक':'Quick Links' ?></h2>
    <a href="<?= base_url('app/allocation/applications.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📋 <?= is_hi()?'आवेदन इनबॉक्स':'Applications Inbox' ?></span></a>
    <a href="<?= base_url('app/allocation/licences.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper"><span class="font-medium text-slate-700">📜 <?= is_hi()?'जारी लाइसेंस':'Issued Licences' ?></span></a>
  </div>
</div>

<?php else: // ===== applicant portal ===== ?>
<div class="space-y-3">
  <?php foreach($rows as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div><div class="font-display text-lg font-semibold text-ink"><?= e($a['applicant']) ?></div>
          <div class="text-sm text-slate-500"><span class="font-mono text-xs"><?= e($a['app_no']) ?></span> · <?= e($a['source']) ?>: <?= e($a['source_name']) ?> · <?= (float)$a['quantity_mld'] ?> MLD · <?= e($a['season']) ?></div>
          <div class="text-xs text-slate-400 mt-0.5"><?= e($a['divn']) ?> · <?= is_hi()?'वार्षिक शुल्क':'Annual fee' ?> <?= inr((float)$a['annual_fee']) ?></div>
        </div>
        <div class="text-right"><?= badge($a['status']) ?></div>
      </div>
      <div class="flex items-center gap-1 mt-4">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="px-2 h-6 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i&&$a['status']!=='Rejected'?'text-white':'bg-slate-100 text-slate-400') ?>" <?= ($si===$i&&!in_array($a['status'],['Rejected','Approved'],true))?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if($a['status']==='Approved' && $a['license_no']): ?>
        <a href="<?= base_url('app/allocation/licence.php') ?>?id=<?= $a['id'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">📜 <?= is_hi()?'लाइसेंस डाउनलोड करें':'Download Licence' ?> (<?= e($a['license_no']) ?>) →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$rows): ?><div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई आवेदन नहीं। "नया आवेदन" से आरंभ करें।':'No applications yet. Start with "New Application".' ?></div><?php endif; ?>
</div>

<dialog id="newAlloc" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/40">
  <form method="post" class="p-6"><input type="hidden" name="action" value="apply">
    <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'जल आवंटन आवेदन':'Water Allocation Application' ?></h2>
    <div class="space-y-3">
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'आवेदक / उद्योग':'Applicant / Industry' ?></label><input name="applicant" required value="<?= e($u['name']) ?>" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'जल स्रोत':'Water Source' ?></label>
          <select name="source" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>River</option><option>Canal</option><option>Reservoir</option></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'स्रोत नाम':'Source Name' ?></label><input name="source_name" required value="Subarnarekha River" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'मात्रा (MLD)':'Quantity (MLD)' ?></label><input name="quantity" type="number" step="0.1" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'मौसम':'Season' ?></label>
          <select name="season" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>Perennial</option><option>Seasonal</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'प्रमंडल':'Division' ?></label>
          <select name="division_id" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($divs as $d):?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach;?></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'ज़िला':'District' ?></label><input name="district" required value="Ranchi" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <div><label class="text-sm font-medium text-slate-700">GSTIN</label><input name="gst" required placeholder="20XXXXX1234X1Z5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      <div class="rounded-xl p-3 text-xs" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 12%,#fff);color:<?= e($APP['accent']) ?>">🜄 <?= is_hi()?'आवंटन इंजन स्रोत उपलब्धता एवं मौसमी नीति की जाँच करेगा। SWCS से सत्यापित।':'Allocation engine validates source availability & seasonal policy. Verified via SWCS.' ?></div>
    </div>
    <div class="flex gap-2 mt-5">
      <button type="button" onclick="document.getElementById('newAlloc').close()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600">Cancel</button>
      <button class="flex-1 btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'आवेदन जमा करें':'Submit' ?></button>
    </div>
  </form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/allocation/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/allocation/index.php
git commit -m "feat(allocation): role-adaptive dashboard (applicant portal vs officer desk) + apply wizard"
```

---

## Task 7: Licences register + printable licence certificate

**Files:**
- Create: `app/allocation/licences.php`
- Create: `app/allocation/licence.php`

- [ ] **Step 1: Create `app/allocation/licences.php` with EXACTLY this content:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db();
set_app_context('allocation');
app_require_access('licences');
$LAYOUT='app'; $ACTIVE='licences'; $PAGE_TITLE='Licences';
require __DIR__ . '/../../includes/header.php';
$rows=$pdo->query("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.status='Approved' AND a.license_no IS NOT NULL ORDER BY a.id DESC")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'जारी लाइसेंस':'Issued Licences' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'अनुमोदित जल आवंटन लाइसेंस':'Approved water allocation licences' ?></p></div>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
      <th class="text-left px-4 py-3"><?= is_hi()?'लाइसेंस':'Licence' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'उद्योग':'Industry' ?></th>
      <th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'स्रोत':'Source' ?></th><th class="text-right px-4 py-3">MLD</th>
      <th class="text-right px-4 py-3"><?= is_hi()?'वार्षिक शुल्क':'Annual Fee' ?></th><th class="px-4 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($rows as $a): ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= e($a['license_no']) ?></td>
          <td class="px-4 py-3 font-medium text-slate-800"><?= e($a['applicant']) ?><div class="text-xs text-slate-400"><?= e($a['divn']) ?></div></td>
          <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($a['source']) ?>: <?= e($a['source_name']) ?></td>
          <td class="px-4 py-3 text-right font-semibold text-ink"><?= (float)$a['quantity_mld'] ?></td>
          <td class="px-4 py-3 text-right"><?= inr((float)$a['annual_fee']) ?></td>
          <td class="px-4 py-3 text-right"><a href="<?= base_url('app/allocation/licence.php') ?>?id=<?= $a['id'] ?>" target="_blank" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">Licence →</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="6" class="px-4 py-10 text-center text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई लाइसेंस जारी नहीं।':'No licences issued yet.' ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Create `app/allocation/licence.php` with EXACTLY this content:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $id=(int)($_GET['id']??0);
$a=$pdo->query("SELECT a.*,d.name divn,d.circle FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.id=$id AND a.status='Approved'")->fetch();
if(!$a){ http_response_code(404); exit('Licence not available.'); }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Water Allocation Licence · <?= e($a['license_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Mukta:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Mukta',sans-serif}.d{font-family:'Fraunces',serif}@media print{.noprint{display:none}}</style></head>
<body class="bg-slate-100 py-8">
<div class="max-w-3xl mx-auto mb-4 noprint flex justify-between">
  <a href="<?= base_url('app/allocation/index.php') ?>" class="text-sm text-slate-600">← Back</a>
  <button onclick="print()" class="bg-[#0891b2] text-white px-5 py-2 rounded-lg font-semibold text-sm">🖨 Print / Save PDF</button>
</div>
<div class="max-w-3xl mx-auto bg-white shadow-xl p-12 border-t-4 border-[#0891b2] relative">
  <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
    <span class="d text-[120px] text-[#0891b2]/5 font-semibold rotate-[-25deg]">LICENSED</span>
  </div>
  <div class="relative">
    <div class="flex items-center justify-between border-b border-slate-200 pb-5">
      <div class="flex items-center gap-3">
        <span class="grid place-items-center w-12 h-12 rounded-xl bg-[#06314a] text-white">
          <svg width="24" height="24" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg></span>
        <div><div class="d font-semibold text-[#06314a] text-lg">Water Resources Department</div><div class="text-xs text-slate-500">Government of Jharkhand</div></div>
      </div>
      <div class="text-right text-xs text-slate-500">Licence: <span class="font-mono"><?= e($a['license_no']) ?></span><br>Date: <?= date('d M Y',strtotime($a['applied_on'])) ?></div>
    </div>
    <h1 class="d text-2xl font-semibold text-center text-[#06314a] mt-8">Industrial Water Allocation Licence</h1>
    <p class="text-center text-sm text-slate-500">औद्योगिक जल आवंटन लाइसेंस</p>
    <p class="text-sm text-slate-700 mt-8 leading-relaxed text-center">This licence authorises <b><?= e($a['applicant']) ?></b> to draw water as allocated below, subject to the terms and seasonal policy of the Water Resources Department, Government of Jharkhand.</p>
    <table class="w-full text-sm mt-6 border border-slate-200 rounded-lg overflow-hidden">
      <tbody class="divide-y divide-slate-200">
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600 w-1/2">Source</td><td class="px-4 py-2.5"><?= e($a['source']) ?> — <?= e($a['source_name']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Allocated Quantity</td><td class="px-4 py-2.5 font-semibold"><?= (float)$a['quantity_mld'] ?> MLD (<?= e($a['season']) ?>)</td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Division / Circle</td><td class="px-4 py-2.5"><?= e($a['divn']) ?> · <?= e($a['circle']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Annual Fee</td><td class="px-4 py-2.5 font-semibold text-emerald-700"><?= inr_full((float)$a['annual_fee']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">GSTIN</td><td class="px-4 py-2.5"><?= e($a['gst']) ?></td></tr>
      </tbody>
    </table>
    <div class="flex items-end justify-between mt-12">
      <div class="text-center">
        <div class="w-28 h-28 bg-white border border-slate-300 grid place-items-center">
          <svg width="92" height="92" viewBox="0 0 29 29" shape-rendering="crispEdges"><?php
            mt_srand(crc32((string)$a['license_no'])); echo '<rect width="29" height="29" fill="#fff"/>';
            for($y=0;$y<29;$y++)for($x=0;$x<29;$x++){ if(mt_rand(0,2)===0) echo '<rect x="'.$x.'" y="'.$y.'" width="1" height="1" fill="#06314a"/>'; }
            foreach([[0,0],[22,0],[0,22]] as $cc){ echo '<rect x="'.$cc[0].'" y="'.$cc[1].'" width="7" height="7" fill="none" stroke="#06314a"/><rect x="'.($cc[0]+2).'" y="'.($cc[1]+2).'" width="3" height="3" fill="#06314a"/>'; }
          ?></svg>
        </div>
        <p class="text-[10px] text-slate-400 mt-1">Scan to verify · <?= e($a['license_no']) ?></p>
      </div>
      <div class="text-center"><div class="h-12"></div><div class="border-t border-slate-400 pt-1 px-6 text-sm font-semibold text-[#06314a]">Secretary</div><div class="text-xs text-slate-500">WRD, Jharkhand</div></div>
    </div>
  </div>
</div>
</body></html>
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/allocation/licences.php && php -l app/allocation/licence.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add app/allocation/licences.php app/allocation/licence.php
git commit -m "feat(allocation): issued-licences register + printable licence certificate"
```

---

## Task 8: Full Allocation verification

- [ ] **Step 1: Run the whole test suite**

Run: `php tests/run.php`
Expected: all tests pass, exit 0 (everything + new `allocation_test.php` + updated `apps_test.php`).

- [ ] **Step 2: Lint every touched/created PHP file**

Run: `for f in app/allocation/lib.php app/allocation/login.php app/allocation/index.php app/allocation/applications.php app/allocation/licences.php app/allocation/licence.php includes/apps.php setup.php sql/seed.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: Render-check per role (Apache + MySQL, after re-running setup.php)**

```
php -r "require 'config/config.php'; \$_SESSION['user']=['id'=>12,'username'=>'consumer','name'=>'Tata Steel Ltd','role'=>'CONSUMER']; \$_GET=[]; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); include 'app/allocation/index.php'; \$h=ob_get_clean(); echo 'CONSUMER: '.(preg_match('/Fatal|Uncaught/i',\$h)?'FATAL':'ok').' · My Water Allocation:'.(strpos(\$h,'My Water Allocation')!==false?'yes':'no').' · nav-links:'.substr_count(\$h,'class=\"nav-link').' (expect 1)'.PHP_EOL;"
php -r "require 'config/config.php'; \$_SESSION['user']=['id'=>6,'username'=>'ae','name'=>'AE','role'=>'AE']; \$_GET=[]; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); include 'app/allocation/index.php'; \$h=ob_get_clean(); echo 'AE: '.(preg_match('/Fatal|Uncaught/i',\$h)?'FATAL':'ok').' · Allocation Desk:'.(strpos(\$h,'Allocation Desk')!==false?'yes':'no').' · nav-links:'.substr_count(\$h,'class=\"nav-link').' (expect 3)'.PHP_EOL;"
php -r "require 'config/config.php'; \$_SESSION['user']=['id'=>11,'username'=>'consumer','name'=>'Tata','role'=>'CONSUMER']; \$_GET=[]; \$_SERVER['REQUEST_METHOD']='GET'; ob_start(); include 'app/allocation/applications.php'; \$h=ob_get_clean(); echo 'CONSUMER applications body chars: '.strlen(\$h).' (0 = guard redirected)'.PHP_EOL;"
```
Expected: CONSUMER ok / My Water Allocation yes / nav-links 1; AE ok / Allocation Desk yes / nav-links 3; CONSUMER applications body chars 0.

- [ ] **Step 4: Push**

```bash
git push origin <current-branch>
```

---

## Notes

- Allocation reads only `allocations` (+ `divisions`/`workflow_log`). No cross-product tables.
- Chain AE→EE→SE→CE→EIC→SECRETARY (RFP §8.2.6); SECRETARY approves and the licence number is generated then.
- Applicant portal scoping uses the new `allocations.login_user` column; the demo `consumer` login maps to `WRD/IWA/2526/201` (Tata Steel, already Approved → it can download its licence). Self-applications set `login_user` to the applicant's account so they appear in that portal.
- `app/allocation/licence.php` is a standalone print certificate (like the contractor cert / FRC), reached from the portal and the licences register; it requires login (it is officer/applicant content, not public).
- Role-based nav: `CONSUMER` sees only the dashboard (portal); officers see dashboard + applications + licences; `app_require_access` guards the two officer pages.
