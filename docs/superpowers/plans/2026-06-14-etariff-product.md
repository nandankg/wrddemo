# Water E-Tariff & Billing Product Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn Water E-Tariff & Billing into a self-contained product on the foundation: an E-Tariff-branded login, a role-adaptive Revenue & Billing Centre, the drawal→bill→verify→demand→pay lifecycle with slab tariffs, and the division-wise payment routing — all scoped to E-Tariff data only.

**Architecture:** Every E-Tariff page calls `set_app_context('etariff')` before rendering the shared themed shell. Pure billing/metric/role logic lives in a testable `app/etariff/lib.php`; pages are thin presenters. E-Tariff reads only its own tables (`consumers`, `drawal_entries`, `bills`, `payments`, plus `divisions`/`workflow_log`); it never touches `projects`, `fund_requisitions`, `allocations`, `contractors`, or `content`. This mirrors the PPMS product (`app/ppms/`) pattern exactly.

**Tech Stack:** PHP 8.2 + MariaDB (XAMPP), Tailwind (CDN), Chart.js (CDN, revenue view only), the zero-dependency test runner at `tests/run.php`.

**Prerequisite:** The foundation (`includes/apps.php`, `includes/app_context.php`, `includes/auth_roles.php`, per-product `includes/sidebar.php`, themed `includes/header.php`) and PPMS product are merged. E-Tariff registry roles are `['CONSUMER','JE','AE','EE','ACCOUNTS','SECRETARY']`, accent `#059669`, icon `🧾`, home `app/etariff/index.php`. The shared `auth/role_switch.php` already honours `?to=`/same-origin referer; `ppms_require_login()` is the pattern to copy.

---

## File Structure

- `app/etariff/lib.php` — **create** — pure logic: `etariff_compute_bill` (slab tariff), `etariff_role_view`, `etariff_bill_kpis`, `etariff_pending_actions`, `etariff_require_login`. Only file with unit tests.
- `tests/etariff_test.php` — **create** — tests for the pure functions.
- `includes/apps.php` — **modify** — give the E-Tariff registry entry a `bills` nav item (keep `dashboard`).
- `tests/apps_test.php` — **modify** — assert the E-Tariff nav keys.
- `sql/seed.php` — **modify** — add an `ACCOUNTS` user (the registry role with no seeded user yet).
- `app/etariff/login.php` — **create** — E-Tariff-branded login + product-scoped role quick-pick.
- `app/etariff/bills.php` — **create** — operational bills list + detail + JE→AE→EE workflow + drawal entry (moved from the current `index.php`), rescoped, with role/status guards, using `etariff_compute_bill`.
- `app/etariff/index.php` — **rewrite** — role-adaptive Revenue & Billing Centre (consumer portal / billing desk / revenue MIS).
- `app/etariff/pay.php` — **modify** — set E-Tariff context, scoped login, fix the receipt redirect, add a role guard.

---

## Task 1: E-Tariff logic library (pure, tested)

**Files:**
- Create: `app/etariff/lib.php`
- Test: `tests/etariff_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/etariff_test.php`:

```php
<?php
require_once __DIR__ . '/../app/etariff/lib.php';

it('etariff_compute_bill applies slab variable + category fixed + gst', function () {
    // Industrial, 600,000 units, no excess:
    // variable = 500000*0.75 + 100000*0.90 = 375000 + 90000 = 465000
    // fixed = 25000 ; sub = 490000 ; gst = 88200 ; total = 578200
    $b = etariff_compute_bill('Industrial Units', 600000, 0);
    assert_eq(25000.0, $b['fixed']);
    assert_eq(465000.0, $b['variable']);
    assert_eq(0.0,    $b['excessChg']);
    assert_eq(88200.0, $b['gst']);
    assert_eq(578200.0, $b['total']);
});

it('etariff_compute_bill uses the third slab above 1,000,000 units', function () {
    // 1,200,000 units: 500000*0.75 + 500000*0.90 + 200000*1.10
    //   = 375000 + 450000 + 220000 = 1,045,000
    $b = etariff_compute_bill('Industrial Units', 1200000, 0);
    assert_eq(1045000.0, $b['variable']);
});

it('etariff_compute_bill charges excess and lower municipal fixed', function () {
    // Municipal fixed = 15000 ; 100,000 units variable = 75000 ; excess 8000 @2.10 = 16800
    // sub = 15000 + 75000 + 16800 = 106800 ; gst = 19224 ; total = 126024
    $b = etariff_compute_bill('Municipal Bodies', 100000, 8000);
    assert_eq(15000.0, $b['fixed']);
    assert_eq(75000.0, $b['variable']);
    assert_eq(16800.0, $b['excessChg']);
    assert_eq(126024.0, $b['total']);
});

it('etariff_role_view maps roles to consumer/billing/revenue', function () {
    assert_eq('consumer', etariff_role_view('CONSUMER'));
    assert_eq('billing',  etariff_role_view('JE'));
    assert_eq('billing',  etariff_role_view('AE'));
    assert_eq('billing',  etariff_role_view('EE'));
    assert_eq('revenue',  etariff_role_view('ACCOUNTS'));
    assert_eq('revenue',  etariff_role_view('SECRETARY'));
    assert_eq('revenue',  etariff_role_view('ANYTHING_ELSE'));
});

it('etariff_bill_kpis counts statuses and sums outstanding/collected', function () {
    $bills = [
      ['status'=>'Draft','total'=>'100'],
      ['status'=>'Pending Verification','total'=>'200'],
      ['status'=>'Approved','total'=>'300'],
      ['status'=>'Demand Raised','total'=>'400'],
      ['status'=>'Demand Raised','total'=>'50'],
      ['status'=>'Paid','total'=>'1000'],
    ];
    $k = etariff_bill_kpis($bills);
    assert_eq(1, $k['draft']);
    assert_eq(1, $k['pending']);
    assert_eq(1, $k['approved']);
    assert_eq(2, $k['demand_raised']);
    assert_eq(1, $k['paid']);
    assert_eq(450.0,  $k['outstanding']);   // 400 + 50
    assert_eq(1000.0, $k['collected']);     // Paid totals
});

it('etariff_pending_actions returns stage work per role', function () {
    $bills = [
      ['id'=>1,'bill_no'=>'B1','status'=>'Draft','total'=>'100','cname'=>'Tata'],
      ['id'=>2,'bill_no'=>'B2','status'=>'Pending Verification','total'=>'200','cname'=>'SAIL'],
      ['id'=>3,'bill_no'=>'B3','status'=>'Approved','total'=>'300','cname'=>'Usha'],
      ['id'=>4,'bill_no'=>'B4','status'=>'Demand Raised','total'=>'400','cname'=>'Tata'],
    ];
    assert_eq('bills.php?id=1', etariff_pending_actions('JE', $bills)[0]['url']);
    assert_eq('bills.php?id=2', etariff_pending_actions('AE', $bills)[0]['url']);
    assert_eq('bills.php?id=3', etariff_pending_actions('EE', $bills)[0]['url']);
    assert_eq('bills.php?id=4', etariff_pending_actions('CONSUMER', $bills)[0]['url']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — require of `app/etariff/lib.php` fails (file does not exist).

- [ ] **Step 3: Write the library**

Create `app/etariff/lib.php`:

```php
<?php
declare(strict_types=1);

/**
 * E-Tariff pure logic — no DB, no rendering. Callers pass already-fetched rows.
 */

/**
 * Compute a water bill with slab-based variable charges.
 * Slabs (units): 0–500,000 @ ₹0.75 ; 500,001–1,000,000 @ ₹0.90 ; above @ ₹1.10.
 * Category sets the fixed charge. Excess drawal is penalised at ₹2.10/unit. GST 18%.
 */
function etariff_compute_bill(string $category, float $consumption, float $excess): array {
    $fixedByCat = [
        'Industrial Units'              => 25000.0,
        'Public Sector Undertakings'    => 25000.0,
        'Private Companies'             => 25000.0,
        'Municipal Bodies'             => 15000.0,
    ];
    $fixed = $fixedByCat[$category] ?? 20000.0;

    $rem = max(0.0, $consumption); $variable = 0.0;
    $s1 = min($rem, 500000.0); $variable += $s1 * 0.75; $rem -= $s1;
    if ($rem > 0) { $s2 = min($rem, 500000.0); $variable += $s2 * 0.90; $rem -= $s2; }
    if ($rem > 0) { $variable += $rem * 1.10; }
    $variable = round($variable, 2);

    $excessChg = round(max(0.0, $excess) * 2.10, 2);
    $penalty = 0.0; $interest = 0.0;
    $sub = $fixed + $variable + $excessChg + $penalty + $interest;
    $gst = round($sub * 0.18, 2);
    $total = round($sub + $gst, 2);
    return compact('fixed','variable','excessChg','penalty','interest','gst','total');
}

/** Map a role to its dashboard archetype. */
function etariff_role_view(string $role): string {
    switch ($role) {
        case 'CONSUMER':                return 'consumer';
        case 'JE': case 'AE': case 'EE': return 'billing';
        case 'ACCOUNTS': case 'SECRETARY': case 'EIC': default: return 'revenue';
    }
}

/** Bill KPIs. $bills: rows with status, total. */
function etariff_bill_kpis(array $bills): array {
    $c = ['draft'=>0,'pending'=>0,'approved'=>0,'demand_raised'=>0,'paid'=>0];
    $outstanding = 0.0; $collected = 0.0;
    foreach ($bills as $b) {
        switch ($b['status']) {
            case 'Draft':                 $c['draft']++; break;
            case 'Pending Verification':  $c['pending']++; break;
            case 'Approved':              $c['approved']++; break;
            case 'Demand Raised':         $c['demand_raised']++; $outstanding += (float)$b['total']; break;
            case 'Paid':                  $c['paid']++; $collected += (float)$b['total']; break;
        }
    }
    return $c + ['outstanding'=>$outstanding, 'collected'=>$collected];
}

/**
 * Pending actions for a role. $bills: rows with id, bill_no, status, total, cname.
 * Returns rows: ['label','meta','status','url'].
 */
function etariff_pending_actions(string $role, array $bills): array {
    $map = [
        'JE'       => 'Draft',
        'AE'       => 'Pending Verification',
        'EE'       => 'Approved',
        'CONSUMER' => 'Demand Raised',
    ];
    $want = $map[$role] ?? null;
    if ($want === null) return [];
    $verb = ['JE'=>'Submit bill','AE'=>'Verify bill','EE'=>'Raise demand','CONSUMER'=>'Pay bill'][$role];
    $out = [];
    foreach ($bills as $b) if ($b['status'] === $want)
        $out[] = ['label'=>$verb.' '.$b['bill_no'].' · '.$b['cname'], 'meta'=>(string)$b['total'], 'status'=>$b['status'], 'url'=>'bills.php?id='.$b['id']];
    return $out;
}

/** Require a logged-in user; bounce to the E-Tariff login if not. */
function etariff_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/etariff/login.php')); exit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS — all `etariff_test.php` assertions plus the pre-existing tests.

- [ ] **Step 5: Commit**

```bash
git add app/etariff/lib.php tests/etariff_test.php
git commit -m "feat(etariff): pure slab-tariff/kpi/role logic with tests"
```

---

## Task 2: Add the `bills` nav item to the E-Tariff registry

**Files:**
- Modify: `includes/apps.php`
- Modify: `tests/apps_test.php`

- [ ] **Step 1: Update the failing test first**

In `tests/apps_test.php`, append:

```php
it('etariff nav exposes dashboard and bills in order', function () {
    assert_eq(['dashboard','bills'], array_column(wrd_app('etariff')['nav'], 'key'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL — the E-Tariff nav currently has only `['dashboard']`.

- [ ] **Step 3: Update the registry nav**

In `includes/apps.php`, in the `'etariff'` entry, replace the `'nav'` array with exactly:

```php
            'nav' => [
                ['key'=>'dashboard','label'=>'Revenue & Billing','url'=>'app/etariff/index.php','icon'=>'▤'],
                ['key'=>'bills','label'=>'Bills & Drawal','url'=>'app/etariff/bills.php','icon'=>'🧾'],
            ],
```

Do not change any other product entry.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/apps.php tests/apps_test.php
git commit -m "feat(etariff): add Bills & Drawal nav item to registry"
```

---

## Task 3: Seed the ACCOUNTS user

**Files:**
- Modify: `sql/seed.php`

- [ ] **Step 1: Add the ACCOUNTS user**

In `sql/seed.php`, find the `$users` array (the list of `[username, name, name_hi, role, designation, division_id]` rows). Add this entry immediately after the `['finance', ...]` row:

```php
        ['accounts','M. Ekka','एम. एक्का','ACCOUNTS','Divisional Accounts Officer',1],
```

(The registry lists `ACCOUNTS` as an E-Tariff role but no seeded user had it; the demo role-switcher and login quick-pick need it to resolve.)

- [ ] **Step 2: Verify syntax and reinstall the demo DB**

Run: `php -l sql/seed.php`
Expected: `No syntax errors detected`.

Then open `http://localhost/WRD/setup.php` (Apache + MySQL running) and confirm it reports "Seed data inserted" with no error rows.

- [ ] **Step 3: Confirm the user resolves**

Run: `php -r "require 'config/config.php'; \$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME,DB_USER,DB_PASS); echo \$p->query(\"SELECT COUNT(*) FROM users WHERE role='ACCOUNTS'\")->fetchColumn();"`
Expected: prints `1`.

- [ ] **Step 4: Commit**

```bash
git add sql/seed.php
git commit -m "feat(etariff): seed ACCOUNTS (Divisional Accounts Officer) user"
```

---

## Task 4: E-Tariff login / landing

**Files:**
- Create: `app/etariff/login.php`

- [ ] **Step 1: Create the E-Tariff-branded login**

Create `app/etariff/login.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/app_context.php';
set_app_context('etariff');
$APP = app_ctx();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: ' . base_url('app/etariff/index.php')); exit;
    }
    $error = is_hi() ? 'अमान्य उपयोगकर्ता नाम या पासवर्ड।' : 'Invalid username or password.';
}
if (is_logged_in()) { header('Location: ' . base_url('app/etariff/index.php')); exit; }

// E-Tariff role quick-pick (only this product's roles)
$quick = [
  ['SECRETARY','Secretary','🏛'],['ACCOUNTS','Accounts Officer','₹'],['EE','Executive Engineer','📋'],
  ['AE','Assistant Engineer','📏'],['JE','Junior Engineer','🛠'],['CONSUMER','Industrial Consumer','🏭'],
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
            <input name="username" required autofocus class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. je">
          </div>
          <div>
            <label class="text-sm font-medium text-slate-700"><?= is_hi()?'पासवर्ड':'Password' ?></label>
            <input name="password" type="password" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="demo123">
          </div>
          <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= t('login') ?> →</button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-200">
          <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold mb-2.5">Demo · one-click sign-in (E-Tariff roles)</p>
          <div class="grid grid-cols-3 gap-2">
            <?php foreach ($quick as $q): ?>
              <a href="<?= base_url('auth/role_switch.php') ?>?role=<?= e($q[0]) ?>&to=<?= urlencode(base_url('app/etariff/index.php')) ?>"
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

Run: `php -l app/etariff/login.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/etariff/login.php
git commit -m "feat(etariff): E-Tariff-branded login with product-scoped role quick-pick"
```

---

## Task 5: Bills & Drawal operational page

**Files:**
- Create: `app/etariff/bills.php`

This is the current `app/etariff/index.php` content, rescoped onto the foundation shell, using `etariff_compute_bill` from the lib, with a POST authorization guard, and consumer-scoped listing.

- [ ] **Step 1: Create `app/etariff/bills.php` with EXACTLY this content:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
etariff_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act=$_POST['action']??'';
  if ($act==='create' && $role==='JE') { // JE creates drawal + draft bill
    $cid=(int)$_POST['consumer_id']; $prev=(float)$_POST['prev']; $curr=(float)$_POST['curr'];
    $c=$pdo->query("SELECT allocation_qty,category FROM consumers WHERE id=$cid")->fetch();
    $alloc=(float)$c['allocation_qty'];
    $consumption=max(0,$curr-$prev);
    $excess=max(0,$consumption-($alloc*1000)); // excess vs allocation (MLD→units heuristic)
    $anomaly=$excess>0?1:0;
    $pdo->prepare("INSERT INTO drawal_entries (consumer_id,period,prev_reading,curr_reading,consumption,excess,anomaly,entered_by,entered_on) VALUES (?,?,?,?,?,?,?,?,CURDATE())")
        ->execute([$cid,$_POST['period'],$prev,$curr,$consumption,$excess,$anomaly,$u['id']]);
    $did=(int)$pdo->lastInsertId();
    $b=etariff_compute_bill((string)$c['category'],(float)$consumption,(float)$excess);
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM bills')->fetchColumn()+1;
    $bno=sprintf('WRD/BILL/2526/%05d',$cnt);
    $pdo->prepare("INSERT INTO bills (bill_no,consumer_id,drawal_id,period,fixed_charge,variable_charge,excess_charge,penalty,interest,gst,total,status,stage,created_on) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'Draft','Draft',CURDATE())")
        ->execute([$bno,$cid,$did,$_POST['period'],$b['fixed'],$b['variable'],$b['excessChg'],$b['penalty'],$b['interest'],$b['gst'],$b['total']]);
    $bid=(int)$pdo->lastInsertId();
    add_audit($pdo,'bill',$bid,'Drawal entered & draft bill prepared','JE','JE',$actor,'Consumption '.number_format($consumption).' units'.($anomaly?' · ⚠ anomaly flagged':''));
    flash("Draft bill $bno prepared".($anomaly?' (anomaly flagged)':'').'.');
    header('Location: ?id='.$bid); exit;
  }
  $id=(int)($_POST['id']??0); $bill=$pdo->query("SELECT * FROM bills WHERE id=$id")->fetch();
  if($bill){ $rem=trim($_POST['remarks']??''); $s=$bill['status'];
    // Guard: only the right role can act, and only at the right stage.
    $permit = [
      'submit' => $s==='Draft'                && $role==='JE',
      'verify' => $s==='Pending Verification' && $role==='AE',
      'raise'  => $s==='Approved'             && $role==='EE',
      'return' => in_array($s,['Pending Verification','Approved'],true) && in_array($role,['AE','EE'],true),
    ][$act] ?? false;
    if (!$permit) { flash('Action not permitted for your role at this stage.'); header('Location: ?id='.$id); exit; }
    switch($act){
      case 'submit': $pdo->prepare("UPDATE bills SET status='Pending Verification',stage='AE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Submitted for verification','JE','AE',$actor,$rem); flash('Submitted to AE.'); break;
      case 'verify': $pdo->prepare("UPDATE bills SET status='Approved',stage='EE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Verified','AE','EE',$actor,$rem?:'Consumption & tariff verified.'); flash('Verified → EE.'); break;
      case 'raise': $pdo->prepare("UPDATE bills SET status='Demand Raised',stage='Consumer' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Demand approved & raised','EE','Consumer',$actor,$rem?:'Final demand approved.'); flash('Demand raised. Payment link active.'); break;
      case 'return': $pdo->prepare("UPDATE bills SET status='Draft',stage='JE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Returned for correction',$role,'JE',$actor,$rem?:'Returned.'); flash('Returned to JE.'); break;
    }
    header('Location: ?id='.$id); exit;
  }
}

set_app_context('etariff');
$LAYOUT='app'; $ACTIVE='bills'; $PAGE_TITLE='Bills & Drawal';
require __DIR__ . '/../../includes/header.php';
$viewId=(int)($_GET['id']??0);

// Consumer scoping: a CONSUMER sees only their own consumer record(s).
$isConsumer = etariff_role_view($role)==='consumer';
$myConsumerIds = [];
if ($isConsumer) {
  $st=$pdo->prepare("SELECT id FROM consumers WHERE login_user=?"); $st->execute([$u['username']]);
  $myConsumerIds = array_map('intval', array_column($st->fetchAll(), 'id'));
}

if ($viewId):
  $b=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,c.consumer_id cno,c.category,c.login_user,d.name divn,d.bank_account,d.id div_id,
    de.consumption,de.excess,de.anomaly,de.prev_reading,de.curr_reading
    FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id
    LEFT JOIN drawal_entries de ON de.id=b.drawal_id WHERE b.id=$viewId")->fetch();
  if (!$b || ($isConsumer && !in_array((int)$b['consumer_id'],$myConsumerIds,true))) { echo '<p class="text-slate-500">Bill not found.</p>'; require __DIR__.'/../../includes/footer.php'; exit; }
  $logs=$pdo->query("SELECT * FROM workflow_log WHERE entity_type='bill' AND entity_id=$viewId ORDER BY id")->fetchAll();
  $s=$b['status'];
?>
  <a href="bills.php" class="text-sm text-slate-500 hover:underline">← <?= is_hi()?'सभी बिल':'All bills' ?></a>
  <div class="grid lg:grid-cols-3 gap-6 mt-3">
    <div class="lg:col-span-2 space-y-6">
      <div class="card p-6">
        <div class="flex items-start justify-between gap-3">
          <div><div class="text-xs text-slate-400 font-mono"><?= e($b['bill_no']) ?> · <?= e($b['period']) ?></div>
          <h1 class="font-display text-2xl font-semibold text-ink mt-1"><?= bi($b['cname'],$b['cname_hi']) ?></h1>
          <p class="text-sm text-slate-500"><?= e($b['cno']) ?> · <?= e($b['category']) ?> · <?= e($b['divn']) ?></p></div>
          <?= badge($s) ?>
        </div>

        <?php if($b['anomaly']): ?>
          <div class="mt-4 bg-rose-50 ring-1 ring-rose-200 rounded-xl p-3 flex items-start gap-2 text-sm text-rose-700">
            ⚠ <div><b><?= is_hi()?'असामान्य खपत':'Anomaly detected' ?></b> — <?= is_hi()?'अत्यधिक जल आहरण चिह्नित।':'Excess drawal flagged for review.' ?> (<?= number_format((float)$b['excess']) ?> units excess)</div>
          </div>
        <?php endif; ?>

        <div class="grid grid-cols-3 gap-3 mt-5 text-center">
          <div class="bg-paper rounded-xl p-3"><div class="text-xs text-slate-400"><?= is_hi()?'पिछली रीडिंग':'Previous' ?></div><div class="font-semibold text-ink mt-0.5"><?= number_format((float)$b['prev_reading']) ?></div></div>
          <div class="bg-paper rounded-xl p-3"><div class="text-xs text-slate-400"><?= is_hi()?'वर्तमान रीडिंग':'Current' ?></div><div class="font-semibold text-ink mt-0.5"><?= number_format((float)$b['curr_reading']) ?></div></div>
          <div class="bg-paper rounded-xl p-3"><div class="text-xs text-slate-400"><?= is_hi()?'कुल खपत':'Consumption' ?></div><div class="font-semibold mt-0.5" style="color:<?= e($APP['accent']) ?>"><?= number_format((float)$b['consumption']) ?></div></div>
        </div>

        <table class="w-full text-sm mt-6">
          <tbody class="divide-y divide-slate-100">
            <?php foreach([
              [is_hi()?'स्थिर प्रभार':'Fixed charge',$b['fixed_charge']],
              [is_hi()?'परिवर्तनीय प्रभार (स्लैब)':'Variable charge (slab)',$b['variable_charge']],
              [is_hi()?'अधिक उपयोग प्रभार':'Excess usage charge',$b['excess_charge']],
              [is_hi()?'विलंब शुल्क':'Penalty',$b['penalty']],
              [is_hi()?'जीएसटी (18%)':'GST (18%)',$b['gst']],
            ] as $tr): ?>
              <tr><td class="py-2 text-slate-600"><?= $tr[0] ?></td><td class="py-2 text-right font-medium"><?= inr_full((float)$tr[1]) ?></td></tr>
            <?php endforeach; ?>
            <tr class="border-t-2 border-slate-200"><td class="py-3 font-semibold text-ink"><?= is_hi()?'कुल देय':'Total Payable' ?></td><td class="py-3 text-right font-display text-xl font-semibold text-ink"><?= inr_full((float)$b['total']) ?></td></tr>
          </tbody>
        </table>

        <?php if($s==='Demand Raised' && in_array($role,['CONSUMER','EE','ADMIN'])): ?>
          <a href="<?= base_url('app/etariff/pay.php') ?>?id=<?= $viewId ?>" class="inline-flex items-center gap-2 mt-5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-3 rounded-xl">💳 <?= is_hi()?'अभी भुगतान करें':'Pay Now' ?> (JE-GRAS / UPI)</a>
        <?php elseif($s==='Paid'): ?>
          <div class="mt-5 bg-emerald-50 ring-1 ring-emerald-200 rounded-xl p-4 text-emerald-800 text-sm">✓ <?= is_hi()?'भुगतान प्राप्त — प्रमंडल खाते में जमा':'Paid — credited to division account' ?> <b><?= e($b['bank_account']) ?></b></div>
        <?php endif; ?>
      </div>

      <div class="card p-6">
        <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'अनुमोदन कार्यप्रवाह':'Approval Workflow' ?> (JE → AE → EE → <?= is_hi()?'उपभोक्ता':'Consumer' ?>)</h2>
        <ol class="relative border-l-2 border-slate-200 ml-2 space-y-5">
          <?php foreach($logs as $lg): ?>
            <li class="ml-5"><span class="absolute -left-[7px] w-3 h-3 rounded-full ring-4 ring-brandsoft" style="background:<?= e($APP['accent']) ?>"></span>
              <div class="text-sm font-semibold text-ink"><?= e($lg['action']) ?> <?php if($lg['from_role']):?><span class="text-[11px] font-normal text-slate-400"><?= e($lg['from_role']) ?> → <?= e($lg['to_role']) ?></span><?php endif;?></div>
              <p class="text-xs text-slate-500"><?= e($lg['actor']) ?> · <?= date('d M Y, H:i',strtotime($lg['created_at'])) ?></p>
              <?php if($lg['remarks']):?><p class="text-sm text-slate-600 mt-1 bg-paper rounded-lg px-3 py-1.5"><?= e($lg['remarks']) ?></p><?php endif;?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>

    <div><div class="card p-6 sticky top-24">
      <h2 class="font-display text-lg font-semibold text-ink mb-1"><?= is_hi()?'कार्रवाई':'Take Action' ?></h2>
      <p class="text-xs text-slate-500 mb-4"><?= is_hi()?'भूमिका':'Acting as' ?>: <span class="font-semibold" style="color:<?= e($APP['accent']) ?>"><?= e($role) ?></span></p>
      <?php
        $a=null;
        if($s==='Draft'&&$role==='JE') $a='submit';
        elseif($s==='Pending Verification'&&$role==='AE') $a='verify';
        elseif($s==='Approved'&&$role==='EE') $a='raise';
        if($a): ?>
        <form method="post" class="space-y-3"><input type="hidden" name="id" value="<?= $viewId ?>">
          <textarea name="remarks" rows="2" placeholder="<?= is_hi()?'टिप्पणी':'Remarks' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
          <?php if($a==='submit'):?><button name="action" value="submit" class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= is_hi()?'AE को सत्यापन हेतु भेजें':'Submit to AE' ?> →</button>
          <?php elseif($a==='verify'):?><button name="action" value="verify" class="w-full bg-emerald-600 text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'सत्यापित कर EE को भेजें':'Verify → EE' ?></button>
            <button name="action" value="return" class="w-full bg-amber-100 text-amber-800 font-semibold py-2 rounded-xl text-sm">↩ <?= is_hi()?'JE को वापस':'Return to JE' ?></button>
          <?php elseif($a==='raise'):?><button name="action" value="raise" class="w-full bg-ink text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'मांग स्वीकृत करें':'Approve & Raise Demand' ?></button>
            <button name="action" value="return" class="w-full bg-amber-100 text-amber-800 font-semibold py-2 rounded-xl text-sm">↩ <?= is_hi()?'वापस':'Return' ?></button><?php endif;?>
        </form>
      <?php else: ?>
        <div class="text-center py-8 text-slate-400 text-sm"><div class="text-3xl mb-2">🔒</div>
          <?= is_hi()?'इस चरण हेतु कोई कार्रवाई नहीं।':'No action at this stage for your role.' ?>
          <?php $need=['Draft'=>'JE','Pending Verification'=>'AE','Approved'=>'EE','Demand Raised'=>'CONSUMER'][$s]??null;
          if($need) echo '<p class="text-xs mt-2">'.(is_hi()?'भूमिका बदलें':'Switch to').' <b style="color:'.e($APP['accent']).'">'.$need.'</b></p>';?>
        </div>
      <?php endif; ?>
    </div></div>
  </div>

<?php else:
  if ($isConsumer) {
    if ($myConsumerIds) {
      $in = implode(',', $myConsumerIds);
      $bills=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,d.name divn FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id WHERE b.consumer_id IN ($in) ORDER BY b.id DESC")->fetchAll();
    } else { $bills=[]; }
  } else {
    $bills=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,d.name divn FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id ORDER BY b.id DESC")->fetchAll();
  }
  $cons=$pdo->query("SELECT id,name,consumer_id,allocation_qty FROM consumers ORDER BY name")->fetchAll();
?>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'बिल एवं आहरण':'Bills & Drawal' ?></h1>
    <p class="text-sm text-slate-500"><?= $isConsumer?(is_hi()?'आपके जल बिल':'Your water bills'):(is_hi()?'जल आहरण · टैरिफ · अनुमोदन':'Drawal · tariff · approval workflow') ?> · E-Tariff</p></div>
    <?php if($role==='JE'): ?><button onclick="document.getElementById('newBill').showModal()" class="btn-acc font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'जल आहरण प्रविष्टि':'New Drawal Entry' ?></button><?php endif; ?>
  </div>

  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
        <th class="text-left px-4 py-3">Bill No</th><th class="text-left px-4 py-3"><?= is_hi()?'उपभोक्ता':'Consumer' ?></th>
        <th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'अवधि':'Period' ?></th>
        <th class="text-right px-4 py-3"><?= is_hi()?'राशि':'Amount' ?></th><th class="text-left px-4 py-3">Status</th></tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach($bills as $r): ?>
          <tr class="hover:bg-paper cursor-pointer" onclick="location.href='?id=<?= $r['id'] ?>'">
            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($r['bill_no']) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['cname'],$r['cname_hi']) ?><div class="text-xs text-slate-400"><?= e($r['divn']) ?></div></td>
            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['period']) ?></td>
            <td class="px-4 py-3 text-right font-semibold text-ink"><?= inr((float)$r['total']) ?></td>
            <td class="px-4 py-3"><?= badge($r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$bills): ?><tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 text-sm"><?= is_hi()?'कोई बिल नहीं।':'No bills.' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($role==='JE'): ?>
  <dialog id="newBill" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/40">
    <form method="post" class="p-6"><input type="hidden" name="action" value="create">
      <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'जल आहरण प्रविष्टि (JE)':'Water Drawal Entry (JE)' ?></h2>
      <div class="space-y-4">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'उपभोक्ता':'Consumer' ?></label>
          <select name="consumer_id" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($cons as $c):?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['consumer_id']) ?>)</option><?php endforeach;?></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'अवधि':'Billing Period' ?></label>
          <input name="period" required value="<?= date('M Y') ?>" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'पिछली रीडिंग':'Previous Reading' ?></label><input name="prev" type="number" step="0.01" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'वर्तमान रीडिंग':'Current Reading' ?></label><input name="curr" type="number" step="0.01" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        </div>
        <p class="text-[11px] text-slate-400"><?= is_hi()?'टैरिफ स्लैब अनुसार स्वतः गणना; अत्यधिक खपत स्वतः चिह्नित।':'Tariff auto-calculated by slab; excess drawal auto-flagged.' ?></p>
      </div>
      <div class="flex gap-2 mt-5">
        <button type="button" onclick="document.getElementById('newBill').close()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600">Cancel</button>
        <button class="flex-1 btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'बिल तैयार करें':'Prepare Bill' ?></button>
      </div>
    </form>
  </dialog>
  <?php endif; ?>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/etariff/bills.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/etariff/bills.php
git commit -m "feat(etariff): Bills & Drawal page with slab tariff, guards, consumer scoping"
```

---

## Task 6: Role-adaptive Revenue & Billing Centre

**Files:**
- Rewrite: `app/etariff/index.php`

- [ ] **Step 1: Replace the ENTIRE contents of `app/etariff/index.php` with:**

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
etariff_require_login();
$pdo=db(); $u=current_user(); $role=user_role();
$view=etariff_role_view($role);

// Consumer scoping
$isConsumer = $view==='consumer';
$myIds = [];
if ($isConsumer) {
  $st=$pdo->prepare("SELECT id FROM consumers WHERE login_user=?"); $st->execute([$u['username']]);
  $myIds = array_map('intval', array_column($st->fetchAll(),'id'));
}
if ($isConsumer && $myIds) {
  $in=implode(',',$myIds);
  $bills=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,d.name divn FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id WHERE b.consumer_id IN ($in) ORDER BY b.id DESC")->fetchAll();
} elseif ($isConsumer) {
  $bills=[];
} else {
  $bills=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,d.name divn FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id ORDER BY b.id DESC")->fetchAll();
}
$k=etariff_bill_kpis($bills);
$tasks=etariff_pending_actions($role,$bills);

// Revenue MIS data (revenue view only) — E-Tariff payments only
$revDiv=[]; $monthly=[];
if ($view==='revenue') {
  $revDiv=$pdo->query("SELECT d.name, COALESCE(SUM(p.amount),0) amt FROM divisions d
    LEFT JOIN payments p ON p.division_id=d.id AND p.status='Success' AND p.source_module='etariff'
    GROUP BY d.id ORDER BY amt DESC")->fetchAll();
  $monthly=$pdo->query("SELECT DATE_FORMAT(paid_on,'%b %Y') m, SUM(amount) amt FROM payments
    WHERE status='Success' AND source_module='etariff' GROUP BY DATE_FORMAT(paid_on,'%Y-%m') ORDER BY MIN(paid_on)")->fetchAll();
}

set_app_context('etariff');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Revenue & Billing';
if ($view==='revenue') {
  $EXTRA_HEAD = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
}
require __DIR__ . '/../../includes/header.php';

$viewLabel=[
  'consumer'=>is_hi()?'मेरे जल बिल':'My Water Bills',
  'billing' =>is_hi()?'बिलिंग डेस्क':'Billing Desk',
  'revenue' =>is_hi()?'राजस्व एवं संग्रह केंद्र':'Revenue & Collection Centre',
][$view];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= e($viewLabel) ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <span class="text-xs text-slate-500 bg-white border border-slate-200 rounded-full px-3 py-1.5">● <?= is_hi()?'लाइव डेटा':'Live data' ?> · <?= date('d M Y, H:i') ?></span>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $kpis = $view==='revenue' ? [
    [is_hi()?'संग्रहित राजस्व':'Revenue Collected', inr($k['collected']), 'text-emerald-700'],
    [is_hi()?'बकाया मांग':'Outstanding Demand', inr($k['outstanding']), 'text-rose-700'],
    [is_hi()?'भुगतान किए बिल':'Bills Paid', (string)$k['paid'], 'text-ink'],
    [is_hi()?'मांग जारी':'Demands Raised', (string)$k['demand_raised'], 'text-amber-700'],
  ] : ($view==='consumer' ? [
    [is_hi()?'देय राशि':'Amount Due', inr($k['outstanding']), 'text-rose-700'],
    [is_hi()?'भुगतान किए बिल':'Bills Paid', (string)$k['paid'], 'text-emerald-700'],
    [is_hi()?'कुल बिल':'Total Bills', (string)count($bills), 'text-ink'],
    [is_hi()?'मांग जारी':'Awaiting Payment', (string)$k['demand_raised'], 'text-amber-700'],
  ] : [
    [is_hi()?'ड्राफ्ट (JE)':'Drafts (JE)', (string)$k['draft'], 'text-slate-700'],
    [is_hi()?'सत्यापन हेतु (AE)':'To Verify (AE)', (string)$k['pending'], 'text-amber-700'],
    [is_hi()?'मांग हेतु (EE)':'To Raise (EE)', (string)$k['approved'], 'text-sky-700'],
    [is_hi()?'बकाया मांग':'Outstanding', inr($k['outstanding']), 'text-rose-700'],
  ]);
  foreach ($kpis as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <?php if ($view==='revenue'): ?>
      <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'प्रमंडल-वार राजस्व (जेई-ग्रास)':'Division-wise Revenue (JE-GRAS)' ?></h2>
        <canvas id="revChart" height="130"></canvas>
      </div>
      <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'मासिक संग्रह':'Monthly Collection' ?></h2>
        <canvas id="moChart" height="110"></canvas>
      </div>
    <?php else: ?>
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-display text-lg font-semibold text-ink"><?= $isConsumer?(is_hi()?'मेरे बिल':'My Bills'):(is_hi()?'हाल के बिल':'Recent Bills') ?></h2>
          <a href="<?= base_url('app/etariff/bills.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
            <tr><th class="text-left px-4 py-3">Bill No</th><?php if(!$isConsumer):?><th class="text-left px-4 py-3"><?= is_hi()?'उपभोक्ता':'Consumer' ?></th><?php endif;?><th class="text-right px-4 py-3"><?= is_hi()?'राशि':'Amount' ?></th><th class="text-left px-4 py-3">Status</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach (array_slice($bills,0,8) as $r): ?>
              <tr class="hover:bg-paper cursor-pointer" onclick="location.href='<?= base_url('app/etariff/bills.php') ?>?id=<?= $r['id'] ?>'">
                <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($r['bill_no']) ?></td>
                <?php if(!$isConsumer):?><td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['cname'],$r['cname_hi']) ?></td><?php endif;?>
                <td class="px-4 py-3 text-right font-semibold text-ink"><?= inr((float)$r['total']) ?></td>
                <td class="px-4 py-3"><?= badge($r['status']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(!$bills): ?><tr><td colspan="4" class="px-4 py-10 text-center text-slate-400 text-sm"><?= is_hi()?'कोई बिल नहीं।':'No bills.' ?></td></tr><?php endif; ?>
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
          <a href="<?= base_url('app/etariff/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
            <div class="min-w-0"><p class="text-sm font-medium text-slate-700 truncate"><?= e($tk['label']) ?></p><p class="text-xs text-slate-400"><?= inr((float)$tk['meta']) ?></p></div>
            <?= badge($tk['status']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm">
        <div class="text-4xl mb-2">✓</div>
        <?= is_hi()?'कोई लंबित कार्य नहीं।':'No pending tasks.' ?><br>
        <span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे)।':'Switch role (bottom-left) to see other workflows.' ?></span>
      </div>
    <?php endif; ?>
    <a href="<?= base_url('app/etariff/bills.php') ?>" class="block text-center mt-4 text-sm font-semibold hover:underline" style="color:<?= e($APP['accent']) ?>"><?= is_hi()?'सभी बिल':'All bills' ?> →</a>
  </div>
</div>

<?php if ($view==='revenue'): ?>
<script>
const REVDIV = <?= json_encode($revDiv, JSON_UNESCAPED_UNICODE) ?>;
const MONTHLY = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const acc = '<?= e($APP['accent']) ?>';
new Chart(document.getElementById('revChart'),{
  type:'bar',
  data:{labels:REVDIV.map(r=>r.name.replace(/ (Division|Irrigation|Reservoir|Water Ways|Canal).*/,'')),
    datasets:[{label:'Revenue (₹)',data:REVDIV.map(r=>+r.amt),backgroundColor:acc,borderRadius:6}]},
  options:{plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'₹'+(v/100000).toFixed(1)+'L'}}}}
});
new Chart(document.getElementById('moChart'),{
  type:'line',
  data:{labels:MONTHLY.map(m=>m.m),datasets:[{label:'Collection (₹)',data:MONTHLY.map(m=>+m.amt),borderColor:acc,backgroundColor:acc+'22',fill:true,tension:.35}]},
  options:{plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'₹'+(v/100000).toFixed(1)+'L'}}}}
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/etariff/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/etariff/index.php
git commit -m "feat(etariff): role-adaptive Revenue & Billing Centre"
```

---

## Task 7: Rescope the payment page

**Files:**
- Modify: `app/etariff/pay.php`

- [ ] **Step 1: Replace the opening block of `app/etariff/pay.php`**

The file currently starts with:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db(); $u=current_user();
$id=(int)($_GET['id']??0);
```

Replace it with:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
etariff_require_login();
$pdo=db(); $u=current_user(); $role=user_role();
$id=(int)($_GET['id']??0);
```

- [ ] **Step 2: Add a role guard to the payment POST**

In `app/etariff/pay.php`, find:

```php
if($_SERVER['REQUEST_METHOD']==='POST' && $b['status']==='Demand Raised'){
```

Replace it with:

```php
if($_SERVER['REQUEST_METHOD']==='POST' && $b['status']==='Demand Raised' && in_array($role,['CONSUMER','EE','ADMIN'],true)){
```

- [ ] **Step 3: Set the E-Tariff context on the page**

In `app/etariff/pay.php`, find:

```php
$LAYOUT='app'; $ACTIVE='etariff'; $PAGE_TITLE='Payment';
require __DIR__ . '/../../includes/header.php';
```

Replace it with:

```php
set_app_context('etariff');
$LAYOUT='app'; $ACTIVE='bills'; $PAGE_TITLE='Payment';
require __DIR__ . '/../../includes/header.php';
```

- [ ] **Step 4: Fix the receipt "Dashboard" link**

In `app/etariff/pay.php`, find (in the receipt block):

```php
        <a href="<?= base_url('index.php') ?>" class="flex-1 bg-brand text-white rounded-xl py-2.5 font-semibold text-center"><?= t('dashboard') ?></a>
```

Replace it with:

```php
        <a href="<?= base_url('app/etariff/index.php') ?>" class="flex-1 btn-acc rounded-xl py-2.5 font-semibold text-center"><?= t('dashboard') ?></a>
```

- [ ] **Step 5: Verify syntax**

Run: `php -l app/etariff/pay.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add app/etariff/pay.php
git commit -m "feat(etariff): rescope payment page onto product shell + role guard"
```

---

## Task 8: Full E-Tariff verification

- [ ] **Step 1: Run the whole test suite**

Run: `php tests/run.php`
Expected: all tests pass, exit 0 (foundation + PPMS + new `etariff_test.php` + updated `apps_test.php`).

- [ ] **Step 2: Lint every touched/created PHP file**

Run: `for f in app/etariff/lib.php app/etariff/login.php app/etariff/index.php app/etariff/bills.php app/etariff/pay.php includes/apps.php sql/seed.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: End-to-end demo walk (Apache + MySQL, after re-running setup.php)**

1. Launcher → **E-Tariff** card → E-Tariff login.
2. Sign in as **JE** → Billing Desk → Bills & Drawal → New Drawal Entry → prepare a draft bill (slab tariff applies).
3. Switch to **AE** → verify the draft → **EE** → raise demand.
4. Switch to **Consumer** → My Water Bills → open the demand → **Pay Now** → watch Consumer → JE-GRAS → division-account routing → receipt.
5. Switch to **Accounts** → Revenue & Collection Centre → division-wise revenue bar + monthly collection line.
Confirm: the sidebar role-switcher lists only E-Tariff roles, the accent is emerald throughout, the consumer sees only their own bills, and nothing references projects/contractors/allocations.

- [ ] **Step 4: Push**

```bash
git push origin <current-branch>
```

---

## Notes

- E-Tariff reads only its own tables; `payments` queries always filter `source_module='etariff'` so revenue MIS reflects this product only.
- `etariff_compute_bill` introduces slab tariffs for **new** bills; historical seed bills keep their stored amounts (the breakdown table renders whatever is stored).
- Demand-raised payment is reachable by CONSUMER (and EE/ADMIN for demo convenience); the routing animation + receipt are unchanged from the original centerpiece.
- New role `ACCOUNTS` is seeded in Task 3 so the registry role resolves in the role-switcher and login.
