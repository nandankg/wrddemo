# Contractor Registration & Empanelment Product Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn Contractor Registration & Empanelment into a self-contained product on the foundation: a contractor-branded login, a role-adaptive dashboard (contractor self-service portal vs. registering-authority back office), the ASO→AE→EE→EIC empanelment workflow, the registered-contractors register with risk scoring, the e-certificate (QR + DigiLocker), and public verification — all scoped to Contractor data only.

**Architecture:** Every Contractor page calls `set_app_context('contractor')` before rendering the shared themed shell. Pure workflow/metric/role logic lives in a testable `app/contractor/lib.php`; pages are thin presenters. The product reads only its own tables (`contractors`, `contractor_apps`, plus `workflow_log`); it never touches `projects`, `fund_requisitions`, `bills`, `payments`, `allocations`, or `content`. The empanelment stage chain is realigned from the legacy `ASO→SO→US→DS→JS→EIC` to the registry roles **ASO→AE→EE→EIC** (scrutiny → technical verification → recommendation → approve & issue). Mirrors the PPMS (`app/ppms/`) and E-Tariff (`app/etariff/`) products.

**Tech Stack:** PHP 8.2 + MariaDB (XAMPP), Tailwind (CDN), the zero-dependency test runner at `tests/run.php`.

**Prerequisite:** Foundation + PPMS + E-Tariff are merged to `main`. Contractor registry roles are `['CONTRACTOR','ASO','AE','EE','EIC']`, accent `#2563eb`, icon `⚒️`, home `app/contractor/index.php`. Patterns to copy: `ppms_require_login()`, `etariff_*` lib + role-adaptive dashboards, the POST authorization-guard pattern, and the consumer `login_user` scoping pattern.

---

## File Structure

- `app/contractor/lib.php` — **create** — pure logic: `contractor_role_view`, `contractor_next_stage`, `contractor_fee`, `contractor_kpis`, `contractor_pending_actions`, `contractor_require_login`. Only file with unit tests.
- `tests/contractor_test.php` — **create** — tests for the pure functions.
- `includes/apps.php` — **modify** — give the Contractor registry entry `applications` + `registry` nav items (keep `dashboard`, `verify`).
- `tests/apps_test.php` — **modify** — assert the Contractor nav keys.
- `setup.php` — **modify** — add a `login_user` column to the `contractors` table (consumer-style portal scoping).
- `sql/seed.php` — **modify** — realign the 4 seeded `contractor_apps` stages to ASO/AE/EE/EIC, and link the demo contractor to the `contractor` login.
- `app/contractor/login.php` — **create** — contractor-branded login + product-scoped role quick-pick.
- `app/contractor/index.php` — **rewrite** — role-adaptive dashboard: contractor self-service portal (with registration wizard) vs. registering-authority overview.
- `app/contractor/applications.php` — **create** — back-office processing inbox: applications list + per-stage workflow (forward/approve/reject) with role+stage guards.
- `app/contractor/registry.php` — **create** — registered-contractors register (class, risk score, blacklist) with certificate links.
- `app/contractor/certificate.php` — **modify** — scoped login guard.

`app/contractor/verify.php` is the public QR verification page (no login, standalone) and needs **no change**; the `verify` nav item links to it.

---

## Task 1: Contractor logic library (pure, tested)

**Files:**
- Create: `app/contractor/lib.php`
- Test: `tests/contractor_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/contractor_test.php`:

```php
<?php
require_once __DIR__ . '/../app/contractor/lib.php';

it('contractor_role_view maps roles to contractor/registry', function () {
    assert_eq('contractor', contractor_role_view('CONTRACTOR'));
    assert_eq('registry',   contractor_role_view('ASO'));
    assert_eq('registry',   contractor_role_view('AE'));
    assert_eq('registry',   contractor_role_view('EE'));
    assert_eq('registry',   contractor_role_view('EIC'));
    assert_eq('registry',   contractor_role_view('SOMETHING'));
});

it('contractor_next_stage walks ASO -> AE -> EE -> EIC -> null', function () {
    assert_eq('AE',  contractor_next_stage('ASO'));
    assert_eq('EE',  contractor_next_stage('AE'));
    assert_eq('EIC', contractor_next_stage('EE'));
    assert_eq(null,  contractor_next_stage('EIC'));
    assert_eq(null,  contractor_next_stage('UNKNOWN'));
});

it('contractor_fee returns class fee with a default', function () {
    assert_eq(45000.0, contractor_fee('I'));
    assert_eq(30000.0, contractor_fee('II'));
    assert_eq(20000.0, contractor_fee('III'));
    assert_eq(10000.0, contractor_fee('IV'));
    assert_eq(10000.0, contractor_fee('X'));
});

it('contractor_kpis counts in-process apps and contractor statuses', function () {
    $apps = [
      ['status'=>'Document Verification'],
      ['status'=>'Under Process'],
      ['status'=>'Approved'],
      ['status'=>'Rejected'],
    ];
    $contractors = [
      ['status'=>'Active'],['status'=>'Active'],['status'=>'Blacklisted'],['status'=>'Pending'],
    ];
    $k = contractor_kpis($apps, $contractors);
    assert_eq(2, $k['in_process']);   // DocVerif + UnderProcess
    assert_eq(1, $k['approved']);
    assert_eq(2, $k['active']);
    assert_eq(1, $k['blacklisted']);
    assert_eq(4, $k['total_apps']);
});

it('contractor_pending_actions returns stage work for the acting role', function () {
    $apps = [
      ['id'=>1,'ack_no'=>'A1','stage'=>'ASO','status'=>'Document Verification','cname'=>'X'],
      ['id'=>2,'ack_no'=>'A2','stage'=>'EIC','status'=>'Pending Approval','cname'=>'Y'],
      ['id'=>3,'ack_no'=>'A3','stage'=>'AE','status'=>'Approved','cname'=>'Z'],
    ];
    assert_eq('applications.php?id=1', contractor_pending_actions('ASO', $apps)[0]['url']);
    assert_eq('applications.php?id=2', contractor_pending_actions('EIC', $apps)[0]['url']);
    assert_eq(0, count(contractor_pending_actions('AE', $apps)));        // id3 is Approved
    assert_eq(0, count(contractor_pending_actions('CONTRACTOR', $apps)));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — require of `app/contractor/lib.php` fails.

- [ ] **Step 3: Write the library**

Create `app/contractor/lib.php`:

```php
<?php
declare(strict_types=1);

/**
 * Contractor Registration pure logic — no DB, no rendering.
 */

/** Map a role to its dashboard archetype. */
function contractor_role_view(string $role): string {
    return $role === 'CONTRACTOR' ? 'contractor' : 'registry';
}

/** Next empanelment stage after $stage, or null at the terminal (EIC). */
function contractor_next_stage(string $stage): ?string {
    return ['ASO'=>'AE', 'AE'=>'EE', 'EE'=>'EIC'][$stage] ?? null;
}

/** Registration fee by class. */
function contractor_fee(string $class): float {
    return ['I'=>45000.0, 'II'=>30000.0, 'III'=>20000.0, 'IV'=>10000.0][$class] ?? 10000.0;
}

/** KPIs. $apps: rows with status. $contractors: rows with status. */
function contractor_kpis(array $apps, array $contractors): array {
    $inProcess = 0; $approved = 0;
    foreach ($apps as $a) {
        if ($a['status'] === 'Approved') { $approved++; continue; }
        if ($a['status'] === 'Rejected') continue;
        $inProcess++;
    }
    $active = 0; $blacklisted = 0;
    foreach ($contractors as $c) {
        if ($c['status'] === 'Active') $active++;
        if ($c['status'] === 'Blacklisted') $blacklisted++;
    }
    return [
        'in_process'  => $inProcess,
        'approved'    => $approved,
        'active'      => $active,
        'blacklisted' => $blacklisted,
        'total_apps'  => count($apps),
    ];
}

/**
 * Pending actions for a back-office role: applications sitting at this role's stage
 * that are not yet Approved/Rejected. $apps rows: id, ack_no, stage, status, cname.
 */
function contractor_pending_actions(string $role, array $apps): array {
    if (!in_array($role, ['ASO','AE','EE','EIC'], true)) return [];
    $verb = ['ASO'=>'Scrutinise', 'AE'=>'Verify (technical)', 'EE'=>'Recommend', 'EIC'=>'Approve & issue'][$role];
    $out = [];
    foreach ($apps as $a) {
        if ($a['stage'] !== $role) continue;
        if (in_array($a['status'], ['Approved','Rejected'], true)) continue;
        $out[] = ['label'=>$verb.' '.$a['ack_no'].' · '.($a['cname'] ?? 'New applicant'), 'meta'=>'', 'status'=>$a['status'], 'url'=>'applications.php?id='.$a['id']];
    }
    return $out;
}

/** Require a logged-in user; bounce to the contractor login if not. */
function contractor_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/contractor/login.php')); exit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS — all `contractor_test.php` assertions plus the pre-existing tests.

- [ ] **Step 5: Commit**

```bash
git add app/contractor/lib.php tests/contractor_test.php
git commit -m "feat(contractor): pure workflow/kpi/role logic with tests"
```

---

## Task 2: Add nav items to the Contractor registry

**Files:**
- Modify: `includes/apps.php`
- Modify: `tests/apps_test.php`

- [ ] **Step 1: Update the failing test first**

In `tests/apps_test.php`, append:

```php
it('contractor nav exposes dashboard, applications, registry, verify in order', function () {
    assert_eq(['dashboard','applications','registry','verify'], array_column(wrd_app('contractor')['nav'], 'key'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — the Contractor nav currently has `['dashboard','verify']`.

- [ ] **Step 3: Update the registry nav**

In `includes/apps.php`, in the `'contractor'` entry, replace the `'nav'` array with exactly:

```php
            'nav' => [
                ['key'=>'dashboard','label'=>'Registry Desk','url'=>'app/contractor/index.php','icon'=>'▤'],
                ['key'=>'applications','label'=>'Applications','url'=>'app/contractor/applications.php','icon'=>'📋'],
                ['key'=>'registry','label'=>'Registered Contractors','url'=>'app/contractor/registry.php','icon'=>'📒'],
                ['key'=>'verify','label'=>'Verify Certificate','url'=>'app/contractor/verify.php','icon'=>'✔'],
            ],
```

Do not change any other product entry.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/apps.php tests/apps_test.php
git commit -m "feat(contractor): add applications & registry nav items"
```

---

## Task 3: Contractor portal scoping column + stage realignment seed

**Files:**
- Modify: `setup.php`
- Modify: `sql/seed.php`

- [ ] **Step 1: Add a `login_user` column to the contractors table**

In `setup.php`, find the `contractors` `CREATE TABLE` block. Replace it with (adds the final `login_user` column):

```php
    $pdo->exec(<<<SQL
    CREATE TABLE contractors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reg_no VARCHAR(40) UNIQUE, name VARCHAR(160), name_hi VARCHAR(200),
        class VARCHAR(10), pan VARCHAR(15), gst VARCHAR(20),
        district VARCHAR(80), status VARCHAR(30), risk_score INT,
        valid_upto DATE, registered_on DATE, qr_token VARCHAR(40),
        login_user VARCHAR(60) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
```

- [ ] **Step 2: Realign the seeded application stages**

In `sql/seed.php`, find the `$apps` array (the `contractor_apps` seed). Replace it with exactly (stages now ASO/AE/EE/EIC so the inbox shows one application at each role):

```php
    $apps = [
        ['WRD/ACK/2526/1001',1,'Renewal','I','EIC','Pending Approval',45000,1,'2025-05-20'],
        ['WRD/ACK/2526/1002',5,'Renewal','II','EE','Under Process',30000,1,'2025-05-25'],
        ['WRD/ACK/2526/1003',null,'New','III','ASO','Document Verification',20000,1,'2025-06-01'],
        ['WRD/ACK/2526/1004',null,'New','IV','AE','Under Process',10000,0,'2025-06-02'],
    ];
```

- [ ] **Step 3: Link the demo contractor to the `contractor` login**

In `sql/seed.php`, immediately after the contractors seeding loop (the `foreach ($contractors as $c) { $c[] = bin2hex(random_bytes(6)); $ins->execute($c); }` line), insert:

```php
    // Link the demo contractor login to its firm (portal scoping, consumer-style).
    $pdo->prepare("UPDATE contractors SET login_user='contractor' WHERE reg_no=?")->execute(['WRD/REG/3/0451']);
```

- [ ] **Step 4: Verify syntax and reinstall the demo DB**

Run: `php -l setup.php && php -l sql/seed.php`
Expected: `No syntax errors detected` for both.

Then run: `php setup.php > /dev/null 2>&1; php -r "require 'config/config.php'; \$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME,DB_USER,DB_PASS); echo 'linked contractor: '.\$p->query(\"SELECT name FROM contractors WHERE login_user='contractor'\")->fetchColumn().PHP_EOL; echo 'EIC-stage apps: '.\$p->query(\"SELECT COUNT(*) FROM contractor_apps WHERE stage='EIC'\")->fetchColumn().PHP_EOL;"`
Expected: prints `linked contractor: Narayan Constructions Pvt Ltd` and `EIC-stage apps: 1`.

- [ ] **Step 5: Commit**

```bash
git add setup.php sql/seed.php
git commit -m "feat(contractor): add login_user scoping column; realign seed stages to ASO/AE/EE/EIC"
```

---

## Task 4: Contractor login / landing

**Files:**
- Create: `app/contractor/login.php`

- [ ] **Step 1: Create the contractor-branded login**

Create `app/contractor/login.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/app_context.php';
set_app_context('contractor');
$APP = app_ctx();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . base_url('app/contractor/index.php')); exit;
    }
    $error = is_hi() ? 'अमान्य उपयोगकर्ता नाम या पासवर्ड।' : 'Invalid username or password.';
}
if (is_logged_in()) { header('Location: ' . base_url('app/contractor/index.php')); exit; }

// Contractor role quick-pick (only this product's roles)
$quick = [
  ['EIC','Engineer-in-Chief','⚙'],['EE','Executive Engineer','📋'],['AE','Assistant Engineer','📏'],
  ['ASO','Section Officer','🗂'],['CONTRACTOR','Contractor','⚒'],
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
            <input name="username" required autofocus class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. aso">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'पासवर्ड':'Password' ?></label>
            <input name="password" type="password" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="demo123">
          </div>
          <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= t('login') ?> →</button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-200">
          <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-2.5">Demo · one-click sign-in (Contractor roles)</p>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach ($quick as $q): ?>
              <a href="<?= base_url('auth/role_switch.php') ?>?role=<?= e($q[0]) ?>&to=<?= urlencode(base_url('app/contractor/index.php')) ?>"
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

Run: `php -l app/contractor/login.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/contractor/login.php
git commit -m "feat(contractor): contractor-branded login with product-scoped role quick-pick"
```

---

## Task 5: Applications processing inbox (back office)

**Files:**
- Create: `app/contractor/applications.php`

This is the back-office workflow page: the ASO→AE→EE→EIC processing inbox with per-stage actions, plus the read-only view a contractor gets of their own applications. The registration **wizard** lives on the dashboard (Task 6); this page handles forward/approve/reject only.

- [ ] **Step 1: Create `app/contractor/applications.php` with EXACTLY this content:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act=$_POST['action']??'';
  $aid=(int)($_POST['app_id']??0);
  $app=$pdo->query("SELECT * FROM contractor_apps WHERE id=$aid")->fetch();
  if ($app) {
    $stage=$app['stage']; $rem=trim($_POST['remarks']??'');
    $permit = [
      'forward' => contractor_next_stage($stage)!==null && $role===$stage && !in_array($app['status'],['Approved','Rejected'],true),
      'approve' => $stage==='EIC' && $role==='EIC' && $app['status']!=='Approved',
      'reject'  => in_array($role,['ASO','AE','EE','EIC'],true) && $role===$stage && !in_array($app['status'],['Approved','Rejected'],true),
    ][$act] ?? false;
    if (!$permit) { flash('Action not permitted for your role at this stage.'); header('Location: applications.php'); exit; }
    if ($act==='forward') {
      $next=contractor_next_stage($stage);
      $pdo->prepare("UPDATE contractor_apps SET stage=?,status='Under Process' WHERE id=?")->execute([$next,$aid]);
      add_audit($pdo,'contractor_app',$aid,'Forwarded',$stage,$next,$actor,$rem);
      flash("Forwarded to $next.");
    } elseif ($act==='approve') {
      $pdo->prepare("UPDATE contractor_apps SET status='Approved' WHERE id=?")->execute([$aid]);
      if ($app['contractor_id']) $pdo->prepare("UPDATE contractors SET status='Active' WHERE id=?")->execute([$app['contractor_id']]);
      add_audit($pdo,'contractor_app',$aid,'Approved & certificate issued','EIC','Issued',$actor,'Certificate generated with QR.');
      flash('Approved. Digital certificate issued.');
    } elseif ($act==='reject') {
      $pdo->prepare("UPDATE contractor_apps SET status='Rejected' WHERE id=?")->execute([$aid]);
      add_audit($pdo,'contractor_app',$aid,'Rejected',$stage,$stage,$actor,$rem?:'Rejected.');
      flash('Application rejected.');
    }
    header('Location: applications.php'); exit;
  }
}

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='applications'; $PAGE_TITLE='Applications';
require __DIR__ . '/../../includes/header.php';

$STAGES=['ASO','AE','EE','EIC'];
$isContractor = contractor_role_view($role)==='contractor';
if ($isContractor) {
  $st=$pdo->prepare("SELECT a.*,c.name cname FROM contractor_apps a JOIN contractors c ON c.id=a.contractor_id WHERE c.login_user=? ORDER BY a.id DESC");
  $st->execute([$u['username']]); $apps=$st->fetchAll();
} else {
  $apps=$pdo->query("SELECT a.*,c.name cname FROM contractor_apps a LEFT JOIN contractors c ON c.id=a.contractor_id ORDER BY a.id DESC")->fetchAll();
}
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'आवेदन':'Applications' ?></h1>
  <p class="text-sm text-slate-500"><?= $isContractor?(is_hi()?'आपके आवेदन':'Your applications'):(is_hi()?'प्रसंस्करण इनबॉक्स':'Processing inbox') ?> · ASO → AE → EE → EIC</p></div>
</div>

<div class="space-y-3">
  <?php foreach($apps as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div><div class="font-medium text-slate-800"><?= e($a['cname']??'New Applicant') ?> <span class="text-xs text-slate-400 font-mono">· <?= e($a['ack_no']) ?></span></div>
        <div class="text-xs text-slate-500"><?= e($a['type']) ?> · Class <?= e($a['class']) ?> · Fee <?= inr((float)$a['fee']) ?> <?= $a['fee_paid']?'<span class="text-emerald-600">✓ paid</span>':'<span class="text-rose-600">unpaid</span>' ?></div></div>
        <?= badge($a['status']) ?>
      </div>
      <!-- stage tracker -->
      <div class="flex items-center gap-1 mt-3">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="w-7 h-7 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i?'text-white':'bg-slate-100 text-slate-400') ?>" <?= $si===$i?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if(!$isContractor && !in_array($a['status'],['Approved','Rejected'],true)): ?>
        <form method="post" class="flex flex-wrap gap-2 mt-3 items-center">
          <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
          <input name="remarks" placeholder="<?= is_hi()?'टिप्पणी':'Remarks' ?>" class="flex-1 min-w-[160px] border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
          <span class="text-xs text-slate-400"><?= is_hi()?'भूमिका':'You are' ?>: <b style="color:<?= e($APP['accent']) ?>"><?= e($role) ?></b> · <?= is_hi()?'चरण':'stage' ?> <b><?= e($a['stage']) ?></b></span>
          <?php if($a['stage']==='EIC'): ?>
            <button name="action" value="approve" class="bg-emerald-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'स्वीकृत + प्रमाणपत्र':'Approve + Issue' ?></button>
          <?php else: ?>
            <button name="action" value="forward" class="btn-acc text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'अग्रेषित':'Forward' ?> →</button>
          <?php endif; ?>
          <button name="action" value="reject" class="bg-rose-100 text-rose-700 text-sm font-semibold px-3 py-1.5 rounded-lg">✕</button>
        </form>
      <?php elseif($a['status']==='Approved' && $a['contractor_id']): ?>
        <a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= $a['contractor_id'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">📄 <?= is_hi()?'प्रमाणपत्र देखें':'View Certificate' ?> →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$apps): ?><div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'कोई आवेदन नहीं।':'No applications.' ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/contractor/applications.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/contractor/applications.php
git commit -m "feat(contractor): applications inbox with ASO->AE->EE->EIC guards"
```

---

## Task 6: Role-adaptive dashboard + registration wizard

**Files:**
- Rewrite: `app/contractor/index.php`

- [ ] **Step 1: Replace the ENTIRE contents of `app/contractor/index.php` with:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

// Contractor self-registration (creates contractor + application at ASO).
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='register' && $role==='CONTRACTOR') {
  $class=$_POST['class']??'IV';
  $cnt=(int)$pdo->query('SELECT COUNT(*) FROM contractors')->fetchColumn()+451;
  $reg=sprintf('WRD/REG/3/%04d',$cnt);
  $qr=bin2hex(random_bytes(6));
  $pdo->prepare("INSERT INTO contractors (reg_no,name,class,pan,gst,district,status,risk_score,valid_upto,registered_on,qr_token,login_user) VALUES (?,?,?,?,?,?, 'Pending',?,?,CURDATE(),?,?)")
      ->execute([$reg,trim($_POST['name']),$class,strtoupper(trim($_POST['pan'])),strtoupper(trim($_POST['gst'])),trim($_POST['district']),rand(15,40),date('Y-m-d',strtotime('+3 years')),$qr,$u['username']]);
  $cid=(int)$pdo->lastInsertId();
  $ackcnt=(int)$pdo->query('SELECT COUNT(*) FROM contractor_apps')->fetchColumn()+1001;
  $ack=sprintf('WRD/ACK/2526/%04d',$ackcnt);
  $pdo->prepare("INSERT INTO contractor_apps (ack_no,contractor_id,type,class,stage,status,fee,fee_paid,applied_on) VALUES (?,?, 'New',?,'ASO','Document Verification',?,1,CURDATE())")
      ->execute([$ack,$cid,$class,contractor_fee($class)]);
  add_audit($pdo,'contractor_app',(int)$pdo->lastInsertId(),'Application submitted (Aadhaar e-KYC)','Citizen','ASO',$actor,'E-GRAS fee paid · Ack '.$ack);
  flash("Registration submitted. Acknowledgement $ack");
  header('Location: index.php'); exit;
}

$view = contractor_role_view($role);
if ($view==='contractor') {
  $st=$pdo->prepare("SELECT a.*,c.name cname,c.name_hi cname_hi,c.id cid,c.status cstatus FROM contractor_apps a JOIN contractors c ON c.id=a.contractor_id WHERE c.login_user=? ORDER BY a.id DESC");
  $st->execute([$u['username']]); $apps=$st->fetchAll();
  $contractors=[];
} else {
  $apps=$pdo->query("SELECT a.*,c.name cname FROM contractor_apps a LEFT JOIN contractors c ON c.id=a.contractor_id ORDER BY a.id DESC")->fetchAll();
  $contractors=$pdo->query("SELECT * FROM contractors")->fetchAll();
}
$k=contractor_kpis($apps,$contractors);
$tasks=contractor_pending_actions($role,$apps);
$STAGES=['ASO','AE','EE','EIC'];

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Registry Desk';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= $view==='contractor'?(is_hi()?'मेरा पंजीकरण':'My Registration'):(is_hi()?'पंजीयन कार्यालय':'Registering Authority Desk') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <?php if($view==='contractor'): ?>
    <button onclick="document.getElementById('wiz').showModal()" class="btn-acc font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नया पंजीकरण':'New Registration' ?></button>
  <?php endif; ?>
</div>

<?php if($view==='registry'): ?>
<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach([
    [is_hi()?'प्रक्रियाधीन आवेदन':'Applications In Process', (string)$k['in_process'], 'text-amber-700'],
    [is_hi()?'सक्रिय ठेकेदार':'Active Contractors', (string)$k['active'], 'text-emerald-700'],
    [is_hi()?'ब्लैकलिस्टेड':'Blacklisted', (string)$k['blacklisted'], 'text-rose-700'],
    [is_hi()?'कुल आवेदन':'Total Applications', (string)$k['total_apps'], 'text-ink'],
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
      <a href="<?= base_url('app/contractor/applications.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
    </div>
    <?php if($tasks): ?>
      <div class="space-y-2">
        <?php foreach($tasks as $tk): ?>
          <a href="<?= base_url('app/contractor/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
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
    <a href="<?= base_url('app/contractor/applications.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📋 <?= is_hi()?'आवेदन इनबॉक्स':'Applications Inbox' ?></span></a>
    <a href="<?= base_url('app/contractor/registry.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📒 <?= is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors' ?></span></a>
    <a href="<?= base_url('app/contractor/verify.php') ?>" target="_blank" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper"><span class="font-medium text-slate-700">✔ <?= is_hi()?'प्रमाणपत्र सत्यापन':'Verify Certificate' ?></span></a>
  </div>
</div>

<?php else: // ===== contractor portal ===== ?>
<div class="space-y-3">
  <?php foreach($apps as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div><div class="font-medium text-slate-800"><?= bi($a['cname'],$a['cname_hi']) ?> <span class="text-xs text-slate-400 font-mono">· <?= e($a['ack_no']) ?></span></div>
        <div class="text-xs text-slate-500"><?= e($a['type']) ?> · Class <?= e($a['class']) ?> · Fee <?= inr((float)$a['fee']) ?></div></div>
        <?= badge($a['status']) ?>
      </div>
      <div class="flex items-center gap-1 mt-3">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="w-7 h-7 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i?'text-white':'bg-slate-100 text-slate-400') ?>" <?= $si===$i?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if($a['status']==='Approved'): ?>
        <a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= (int)$a['cid'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">📄 <?= is_hi()?'प्रमाणपत्र डाउनलोड करें':'Download Certificate' ?> →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$apps): ?><div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई आवेदन नहीं। "नया पंजीकरण" से आरंभ करें।':'No applications yet. Start with "New Registration".' ?></div><?php endif; ?>
</div>

<!-- Registration wizard -->
<dialog id="wiz" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/50">
  <form method="post" class="p-6"><input type="hidden" name="action" value="register">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'ठेकेदार पंजीकरण':'Contractor Registration' ?></h2>
      <button type="button" onclick="document.getElementById('wiz').close()" class="text-slate-400 text-xl">✕</button>
    </div>
    <div class="flex items-center gap-1 mb-5" id="steps">
      <?php foreach(['Aadhaar e-KYC','Details','Documents','Payment'] as $si=>$ss): ?>
        <div class="flex-1 text-center"><div class="step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold <?= $si===0?'text-white':'bg-slate-100 text-slate-400' ?>" <?= $si===0?'style="background:'.e($APP['accent']).'"':'' ?> data-step="<?= $si ?>"><?= $si+1 ?></div><div class="text-[10px] text-slate-400 mt-1"><?= $ss ?></div></div>
      <?php endforeach; ?>
    </div>
    <div class="wiz-pane" data-pane="0">
      <div class="rounded-xl p-4 text-sm" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 12%,#fff);color:<?= e($APP['accent']) ?>">📱 <?= is_hi()?'आधार सत्यापन':'Aadhaar verification' ?></div>
      <input placeholder="XXXX XXXX 1234" class="mt-3 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="mt-2 flex gap-2"><input placeholder="OTP (any 6 digits)" maxlength="6" class="flex-1 border border-slate-300 rounded-xl px-3 py-2.5"><span class="bg-emerald-50 text-emerald-700 text-sm font-semibold px-3 py-2.5 rounded-xl">✓ Verified</span></div>
    </div>
    <div class="wiz-pane hidden" data-pane="1">
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'फर्म का नाम':'Firm Name' ?></label>
      <input name="name" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">PAN</label><input name="pan" required placeholder="AABCN1234K" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700">Class</label><select name="class" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>I</option><option>II</option><option>III</option><option selected>IV</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">GSTIN (JH only)</label><input name="gst" required placeholder="20XXXXX1234X1Z5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'ज़िला':'District' ?></label><input name="district" required value="Ranchi" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <p id="gsterr" class="text-xs text-rose-600 mt-1 hidden"><?= is_hi()?'केवल झारखंड (20) जीएसटीआईएन मान्य।':'Only Jharkhand (code 20) GSTIN is valid.' ?></p>
    </div>
    <div class="wiz-pane hidden" data-pane="2">
      <p class="text-sm text-slate-500 mb-3"><?= is_hi()?'दस्तावेज़ अपलोड (डेमो):':'Upload documents (demo):' ?></p>
      <?php foreach(['Photograph','Signature','PAN Card','Incorporation Certificate','GST Certificate'] as $doc): ?>
        <label class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 mb-2 text-sm"><span><?= $doc ?></span><span class="text-emerald-600 text-xs font-semibold">✓ uploaded</span></label>
      <?php endforeach; ?>
    </div>
    <div class="wiz-pane hidden" data-pane="3">
      <div class="bg-paper rounded-xl p-4 text-sm"><div class="flex justify-between"><span class="text-slate-500"><?= is_hi()?'पंजीकरण शुल्क':'Registration Fee' ?></span><span class="font-semibold" id="feeAmt">₹10,000</span></div>
      <div class="text-xs text-slate-400 mt-1">via E-GRAS · Net Banking / UPI / Card</div></div>
      <div class="mt-3 text-sm text-emerald-700 bg-emerald-50 rounded-xl px-3 py-2.5">✓ <?= is_hi()?'भुगतान सफल (डेमो)। आवेदन जमा करने हेतु तैयार।':'Payment successful (demo). Ready to submit.' ?></div>
    </div>
    <div class="flex gap-2 mt-5">
      <button type="button" id="prevBtn" onclick="wizStep(-1)" class="border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-600 hidden"><?= is_hi()?'पीछे':'Back' ?></button>
      <button type="button" id="nextBtn" onclick="wizStep(1)" class="flex-1 btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'आगे':'Next' ?> →</button>
      <button type="submit" id="subBtn" class="flex-1 bg-emerald-600 text-white rounded-xl py-2.5 font-semibold hidden"><?= is_hi()?'आवेदन जमा करें':'Submit Application' ?></button>
    </div>
  </form>
</dialog>
<script>
let cur=0; const panes=document.querySelectorAll('.wiz-pane'), dots=document.querySelectorAll('.step-dot');
const ACC='<?= e($APP['accent']) ?>';
function wizStep(dir){
  if(dir>0 && cur===1){ const g=document.querySelector('[name=gst]').value.trim();
    if(g && !g.startsWith('20')){ document.getElementById('gsterr').classList.remove('hidden'); return; } }
  cur=Math.max(0,Math.min(3,cur+dir));
  panes.forEach((p,i)=>p.classList.toggle('hidden',i!==cur));
  dots.forEach((d,i)=>{ d.className='step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold '+(i<=cur?'text-white':'bg-slate-100 text-slate-400'); d.style.background=i<=cur?ACC:''; });
  document.getElementById('prevBtn').classList.toggle('hidden',cur===0);
  document.getElementById('nextBtn').classList.toggle('hidden',cur===3);
  document.getElementById('subBtn').classList.toggle('hidden',cur!==3);
  const fees={'I':'₹45,000','II':'₹30,000','III':'₹20,000','IV':'₹10,000'};
  document.getElementById('feeAmt').textContent=fees[document.querySelector('[name=class]').value]||'₹10,000';
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/contractor/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/contractor/index.php
git commit -m "feat(contractor): role-adaptive dashboard + self-service registration wizard"
```

---

## Task 7: Registered-contractors register + certificate login

**Files:**
- Create: `app/contractor/registry.php`
- Modify: `app/contractor/certificate.php`

- [ ] **Step 1: Create `app/contractor/registry.php` with EXACTLY this content:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db();
$contractors=$pdo->query("SELECT * FROM contractors ORDER BY status='Blacklisted' DESC, name")->fetchAll();

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='registry'; $PAGE_TITLE='Registered Contractors';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'श्रेणी · जोखिम स्कोरिंग · ब्लैकलिस्ट':'Class · risk scoring · blacklist' ?></p></div>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
      <th class="text-left px-4 py-3"><?= is_hi()?'ठेकेदार':'Contractor' ?></th><th class="text-left px-4 py-3">Class</th>
      <th class="text-left px-4 py-3 hidden md:table-cell">GST</th><th class="text-left px-4 py-3"><?= is_hi()?'जोखिम':'Risk' ?></th>
      <th class="text-left px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($contractors as $c): [$rb,$rc]=risk_band((int)$c['risk_score']); ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><div class="font-medium text-slate-800"><?= bi($c['name'],$c['name_hi']) ?></div><div class="text-xs text-slate-400 font-mono"><?= e($c['reg_no']) ?> · <?= e($c['district']) ?></div></td>
          <td class="px-4 py-3"><span class="inline-grid place-items-center w-7 h-7 rounded-lg bg-ink text-white text-xs font-bold"><?= e($c['class']) ?></span></td>
          <td class="px-4 py-3 text-xs font-mono text-slate-500 hidden md:table-cell"><?= e($c['gst']) ?></td>
          <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-1 rounded-full <?= $rc ?>"><?= $rb ?> · <?= (int)$c['risk_score'] ?></span></td>
          <td class="px-4 py-3"><?= badge($c['status']) ?></td>
          <td class="px-4 py-3 text-right"><?php if($c['status']==='Active'): ?><a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= $c['id'] ?>" target="_blank" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">Cert →</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3">🔴 <?= is_hi()?'ब्लैकलिस्ट सार्वजनिक रूप से प्रदर्शित (आरएफपी पारदर्शिता)।':'Blacklisted contractors shown publicly per RFP transparency requirement.' ?></p>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Scope the certificate page login guard**

In `app/contractor/certificate.php`, replace lines 1-4:

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
contractor_require_login();
```

(The rest of `certificate.php` — the standalone print layout with QR + DigiLocker push — is unchanged.)

- [ ] **Step 3: Verify syntax**

Run: `php -l app/contractor/registry.php && php -l app/contractor/certificate.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add app/contractor/registry.php app/contractor/certificate.php
git commit -m "feat(contractor): registered-contractors register; scope certificate login"
```

---

## Task 8: Full Contractor verification

- [ ] **Step 1: Run the whole test suite**

Run: `php tests/run.php`
Expected: all tests pass, exit 0 (foundation + PPMS + E-Tariff + new `contractor_test.php` + updated `apps_test.php`).

- [ ] **Step 2: Lint every touched/created PHP file**

Run: `for f in app/contractor/lib.php app/contractor/login.php app/contractor/index.php app/contractor/applications.php app/contractor/registry.php app/contractor/certificate.php includes/apps.php setup.php sql/seed.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: End-to-end demo walk (Apache + MySQL, after re-running setup.php)**

1. Launcher → **Contractor Registration** card → contractor login.
2. Sign in as **Contractor** → My Registration → "New Registration" → run the Aadhaar/Details/Documents/Payment wizard → submit → the new application appears at stage ASO.
3. Switch to **ASO** (sidebar) → Applications → Scrutinise/Forward to AE → **AE** → Forward to EE → **EE** → Forward to EIC → **EIC** → Approve + Issue.
4. Switch back to **Contractor** → My Registration → Download Certificate (QR + Push to DigiLocker).
5. Open **Registered Contractors** → see the new Active contractor with risk score; open **Verify Certificate** (public) and confirm a blacklisted contractor shows the red warning.
Confirm: the sidebar role-switcher lists only Contractor roles, accent is blue throughout, the contractor sees only their own application, and a non-stage role cannot forward/approve someone else's file.

- [ ] **Step 4: Push**

```bash
git push origin <current-branch>
```

---

## Notes

- Contractor reads only its own tables; no projects/bills/payments/allocations/content.
- The stage chain is **ASO→AE→EE→EIC** (registry roles), replacing the legacy 6-step `ASO→SO→US→DS→JS→EIC`. Seed stages were realigned in Task 3.
- Contractor portal scoping uses a new `contractors.login_user` column (consumer-style); the demo `contractor` login maps to `WRD/REG/3/0451` (Narayan Constructions). Self-registrations set `login_user` to the registering account so they appear in that contractor's portal.
- `app/contractor/verify.php` stays public and standalone (QR landing) — unchanged.
- Registration is contractor self-service (role `CONTRACTOR`); the POST is guarded to that role. Workflow actions (forward/approve/reject) are guarded to the acting role matching the application's current stage.
