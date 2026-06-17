# Contractor Portal — Phase 1 Implementation Plan (Public Face + Smart Registration)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the public-facing GIGW landing page, a 6-step smart registration wizard with DigiLocker auto-fill and AI document verification, and a public certificate-verification portal for the WRD Contractor Registration & Empanelment Portal (Tender §9, Component-B) — Screens 1, 2, 3, 4, 11 of `contractor.md`.

**Architecture:** Extend the existing `app/contractor/` module. All new business logic lives as deterministic pure functions in `app/contractor/lib.php`, unit-tested by the zero-dep runner `tests/run.php`. Pages are thin renderers using the shared suite layout (`includes/header.php` + `footer.php`), bilingual via `is_hi()`/`bi()`/`t()`, styled with Tailwind (CDN). DigiLocker / Aadhaar e-KYC / E-GRAS / AI are simulated deterministically and presented honestly as a prototype.

**Tech Stack:** PHP 8.2, MariaDB (XAMPP), Tailwind CSS (CDN), vanilla JS. No new dependencies.

## Global Constraints

- PHP 8.2; no Composer; no new runtime dependencies.
- Every `lib.php` function is a **pure function** (no DB, no `echo`, no session) and gets a unit test in `tests/contractor_test.php`.
- Bilingual Hindi/English on every user-facing string via `is_hi()` / `bi($en,$hi)` / `t($key)`.
- Officer/applicant pages call `set_app_context('contractor')` before including `includes/header.php`; officer-only pages additionally call `app_require_access('<navKey>')`.
- Do **not** name page-level PHP variables `$f`, `$u`, `$nav`, or `$APP` — `includes/header.php` owns them.
- Tests are run with `php tests/run.php` from the repo root; all must pass (current suite is green). Page syntax checked with `php -l <file>`.
- Accent colour for this product is `#2563eb` (from `includes/apps.php`); use `$APP['accent']` in app-layout pages.
- This is Phase 1 of 3. Do NOT touch the officer workflow chain (still ASO→AE→EE→EIC) — that is Phase 2.

---

### Task 1: Phase-1 pure functions in `lib.php`

Add three deterministic functions: class eligibility recommendation (Screen 2 dynamic eligibility + AI bonus), AI document verification (Screen 4), and public landing statistics (Screen 1).

**Files:**
- Modify: `app/contractor/lib.php` (append new functions before the closing of the file)
- Test: `tests/contractor_test.php` (append new `it(...)` blocks)

**Interfaces:**
- Consumes: nothing (pure logic).
- Produces:
  - `contractor_eligibility(int $years, int $projects, float $turnover): array` → `['class'=>'I'|'II'|'III'|'IV', 'reason'=>string]`. `$turnover` is annual turnover in rupees.
  - `contractor_doc_verify(string $doc, int $seed = 0): array` → `['status'=>'Verified'|'Issue', 'issue'=>?string]`. Deterministic for a given `($doc,$seed)`.
  - `contractor_public_stats(array $contractors, array $apps, ?string $today = null, array $base = ['registered'=>12532,'active'=>9840,'apps_year'=>2140]): array` → `['registered'=>int,'active'=>int,'apps_year'=>int,'avg_days'=>int]`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/contractor_test.php`:

```php
it('contractor_eligibility recommends the highest class the credentials qualify for', function () {
    // Class I: >=10 yrs, >=10 projects, >=5 Cr turnover
    assert_eq('I',   contractor_eligibility(14, 28, 85000000)['class']);
    // Class II: >=7 yrs, >=6 projects, >=3 Cr
    assert_eq('II',  contractor_eligibility(8, 11, 31000000)['class']);
    // Class III: >=4 yrs, >=3 projects, >=1.5 Cr
    assert_eq('III', contractor_eligibility(5, 6, 16000000)['class']);
    // Falls back to Class IV (entry level)
    assert_eq('IV',  contractor_eligibility(2, 1, 4000000)['class']);
    // High experience but low turnover cannot reach Class I
    assert_eq('III', contractor_eligibility(20, 30, 16000000)['class']);
    // Reason is a non-empty string
    assert_true(contractor_eligibility(14, 28, 85000000)['reason'] !== '');
});

it('contractor_doc_verify is deterministic and well-formed', function () {
    $docs = ['PAN Card','Balance Sheet','GST Certificate','CA Certificate','Cancelled Cheque','Affidavit'];
    foreach ($docs as $d) {
        $a = contractor_doc_verify($d, 0);
        $b = contractor_doc_verify($d, 0);
        assert_eq($a, $b, "deterministic for $d");                 // same input -> same output
        assert_true(in_array($a['status'], ['Verified','Issue'], true), "status valid for $d");
        if ($a['status'] === 'Verified') assert_eq(null, $a['issue'], "verified has no issue for $d");
        else assert_true(is_string($a['issue']) && $a['issue'] !== '', "issue text present for $d");
    }
    // Different seeds can change the outcome but never the shape
    assert_true(in_array(contractor_doc_verify('Balance Sheet', 6)['status'], ['Verified','Issue'], true));
});

it('contractor_public_stats blends a production baseline with live seed counts', function () {
    $contractors = [
        ['status'=>'Active'],['status'=>'Active'],['status'=>'Blacklisted'],['status'=>'Renewal Due'],
    ];
    $apps = [
        ['applied_on'=>'2026-04-10'],['applied_on'=>'2026-01-02'],['applied_on'=>'2025-12-31'],
    ];
    $s = contractor_public_stats($contractors, $apps, '2026-06-17');
    assert_eq(12536, $s['registered']);   // 12532 + 4
    assert_eq(9842,  $s['active']);       // 9840 + 2 Active
    assert_eq(2142,  $s['apps_year']);    // 2140 + 2 in 2026
    assert_eq(7,     $s['avg_days']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php`
Expected: FAIL — `Call to undefined function contractor_eligibility()` (and the others).

- [ ] **Step 3: Implement the functions**

Append to `app/contractor/lib.php`, before the final newline (after `contractor_require_login()`):

```php
/* ===========================================================================
 * Phase 1 — eligibility, AI document verification, public statistics.
 * All deterministic and offline-safe (no network, no DB).
 * =========================================================================== */

/**
 * Recommend the highest contractor class the credentials qualify for.
 * Thresholds (turnover in rupees): I = 10yr/10proj/5Cr, II = 7yr/6proj/3Cr,
 * III = 4yr/3proj/1.5Cr, else IV (entry level).
 */
function contractor_eligibility(int $years, int $projects, float $turnover): array {
    $tiers = [
        ['class'=>'I',   'yr'=>10, 'proj'=>10, 'turn'=>50000000.0],
        ['class'=>'II',  'yr'=>7,  'proj'=>6,  'turn'=>30000000.0],
        ['class'=>'III', 'yr'=>4,  'proj'=>3,  'turn'=>15000000.0],
    ];
    foreach ($tiers as $t) {
        if ($years >= $t['yr'] && $projects >= $t['proj'] && $turnover >= $t['turn']) {
            $cr = rtrim(rtrim(number_format($t['turn']/10000000, 1), '0'), '.');
            return ['class'=>$t['class'],
                    'reason'=>"Meets Class {$t['class']} bar: {$t['yr']}+ yrs, {$t['proj']}+ projects, ₹{$cr} Cr+ turnover."];
        }
    }
    return ['class'=>'IV', 'reason'=>'Entry-level eligibility — Class IV registration.'];
}

/**
 * Simulated AI document verification. Deterministic per ($doc,$seed): most
 * documents read Verified; a stable minority surface a realistic issue.
 */
function contractor_doc_verify(string $doc, int $seed = 0): array {
    $issues = ['Signature not detected', 'Date mismatch with PAN record', 'Low-resolution scan'];
    $h = crc32(mb_strtolower(trim($doc)) . '|' . $seed);
    if ($h % 6 === 0) {
        return ['status'=>'Issue', 'issue'=>$issues[$h % count($issues)]];
    }
    return ['status'=>'Verified', 'issue'=>null];
}

/**
 * Public landing statistics: a production-scale baseline plus live seed counts,
 * so the figures look real and still move with demo data.
 * $apps rows carry 'applied_on' (Y-m-d); $today defaults to the system date.
 */
function contractor_public_stats(array $contractors, array $apps, ?string $today = null,
        array $base = ['registered'=>12532, 'active'=>9840, 'apps_year'=>2140]): array {
    $year = (int)date('Y', $today ? (strtotime($today) ?: time()) : time());
    $active = 0;
    foreach ($contractors as $c) if (($c['status'] ?? '') === 'Active') $active++;
    $appsYear = 0;
    foreach ($apps as $a) if ((int)date('Y', strtotime((string)($a['applied_on'] ?? '1970-01-01'))) === $year) $appsYear++;
    return [
        'registered' => $base['registered'] + count($contractors),
        'active'     => $base['active'] + $active,
        'apps_year'  => $base['apps_year'] + $appsYear,
        'avg_days'   => 7,
    ];
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php`
Expected: PASS — all contractor tests green, `Failed: 0`.

- [ ] **Step 5: Commit**

```bash
git add app/contractor/lib.php tests/contractor_test.php
git commit -m "feat(contractor): Phase-1 lib — eligibility, AI doc verify, public stats"
```

---

### Task 2: Extend `contractors` schema + seed credentials

Add the credential columns the 6-step wizard collects, and backfill realistic values for the seeded firms so Phase 2 scoring has data.

**Files:**
- Modify: `setup.php` (the `CREATE TABLE contractors` block, ~line 139)
- Modify: `sql/seed.php` (the contractors block, ~lines 152–163)

**Interfaces:**
- Produces: `contractors` rows gain `cin`, `address`, `contact`, `experience_yrs`, `completed_projects`, `turnover` — consumed by Task 4 (registration insert) and Phase 2 scoring.

- [ ] **Step 1: Add columns to the schema**

In `setup.php`, replace the `contractors` table definition body so the column list reads (add the six new columns after `qr_token`):

```sql
    CREATE TABLE contractors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reg_no VARCHAR(40) UNIQUE, name VARCHAR(160), name_hi VARCHAR(200),
        class VARCHAR(10), pan VARCHAR(15), gst VARCHAR(20),
        district VARCHAR(80), status VARCHAR(30), risk_score INT,
        valid_upto DATE, registered_on DATE, qr_token VARCHAR(40),
        login_user VARCHAR(60) NULL,
        cin VARCHAR(30) NULL, address VARCHAR(255) NULL, contact VARCHAR(120) NULL,
        experience_yrs INT NULL, completed_projects INT NULL, turnover DECIMAL(14,2) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Extend the seed rows and INSERT**

In `sql/seed.php`, replace the `$contractors` array and its `INSERT` (lines ~152–163) with the version below. Each row appends `cin, address, contact, experience_yrs, completed_projects, turnover` after `registered_on`; `qr_token` is still appended in the loop.

```php
    // ---- Contractors (with risk scores + credentials; one blacklisted) ----
    $contractors = [
        ['WRD/REG/3/0451','Narayan Constructions Pvt Ltd','नारायण कंस्ट्रक्शन्स','I','AABCN1234K','20ABCDE1234F1Z5','Ranchi','Active',18,'2027-03-31','2021-05-10','U45200JH2010PTC001451','Plot 12, HEC Colony, Dhurwa, Ranchi','0651-2345678',14,28,85000000.00],
        ['WRD/REG/3/0452','Jharkhand Infra Builders','झारखंड इंफ्रा बिल्डर्स','II','AACFJ5678L','20JHARK5678G1Z2','Dhanbad','Active',34,'2026-09-30','2020-08-22','U45200JH2012PTC001452','Bank More, Dhanbad','0326-2233445',9,14,42000000.00],
        ['WRD/REG/3/0453','Koel Engineering Works','कोयल इंजीनियरिंग','III','AADCK9012M','20KOELE9012H1Z9','Palamu','Active',22,'2026-12-31','2022-01-15','U45200JH2014PTC001453','Daltonganj, Palamu','06562-223344',6,8,18000000.00],
        ['WRD/REG/3/0454','Subarnarekha Civil Co.','स्वर्णरेखा सिविल','I','AAECS3456N','20SUBAR3456J1Z7','Jamshedpur','Active',12,'2028-01-31','2019-11-03','U45200JH2009PTC001454','Sakchi, Jamshedpur','0657-2998877',16,35,120000000.00],
        ['WRD/REG/3/0455','Damodar Valley Contractors','दामोदर वैली','II','AAFCD7890P','20DAMOD7890K1Z4','Bokaro','Renewal Due',45,'2025-07-31','2021-02-28','U45200JH2011PTC001455','Sector 4, Bokaro Steel City','06542-265432',8,11,31000000.00],
        ['WRD/REG/3/0456','Hilltop Project Pvt Ltd','हिलटॉप प्रोजेक्ट','IV','AAGCH2345Q','27HILLT2345L1Z1','Mumbai','Blacklisted',88,'2025-03-31','2020-06-19','U45200MH2010PTC001456','Andheri East, Mumbai','022-26781234',5,4,9000000.00],
        ['WRD/REG/3/0457','Ranchi Builders Syndicate','रांची बिल्डर्स','III','AAHCR6789R','20RANCH6789M1Z8','Ranchi','Active',29,'2027-06-30','2022-09-12','U45200JH2015PTC001457','Lalpur, Ranchi','0651-2567890',5,6,16000000.00],
        ['WRD/REG/3/0458','Santhal Infra Solutions','संथाल इंफ्रा','II','AAICS0123S','20SANTH0123N1Z6','Dumka','Active',38,'2026-11-30','2021-12-01','U45200JH2013PTC001458','Dumka Town, Dumka','06434-224466',7,9,28000000.00],
    ];
    $ins = $pdo->prepare('INSERT INTO contractors (reg_no,name,name_hi,class,pan,gst,district,status,risk_score,valid_upto,registered_on,cin,address,contact,experience_yrs,completed_projects,turnover,qr_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($contractors as $c) { $c[] = bin2hex(random_bytes(6)); $ins->execute($c); }
```

- [ ] **Step 3: Re-run the installer and verify the columns**

Run: `php -r "require 'config/db.php'; require 'setup.php';"` is NOT how setup runs — instead reinstall via the browser-equivalent CLI:
Run: `php setup.php`
Expected: prints the seed success line `Seed data inserted: ...` with no SQL error.

Then verify columns and data:
Run: `php -r "require 'config/db.php'; \$r=db()->query('SELECT reg_no,class,experience_yrs,turnover FROM contractors LIMIT 3')->fetchAll(PDO::FETCH_ASSOC); var_export(\$r);"`
Expected: rows show non-null `experience_yrs` and `turnover` (e.g. `14`, `85000000.00`).

- [ ] **Step 4: Commit**

```bash
git add setup.php sql/seed.php
git commit -m "feat(contractor): add credential columns + seed firm financials"
```

---

### Task 3: Public GIGW landing page (Screen 1)

A no-login landing page with hero, six quick-action tiles, and a live statistics strip. Becomes the product's `home` so the suite launcher opens it.

**Files:**
- Create: `public/contractor.php`
- Modify: `includes/apps.php` (the `contractor` product `home` value, line ~36)

**Interfaces:**
- Consumes: `contractor_public_stats()` (Task 1), `contractors` + `contractor_apps` tables (Task 2).
- Produces: the public entry point; links to `app/contractor/login.php` and `app/contractor/verify.php`.

- [ ] **Step 1: Point the product home at the new landing page**

In `includes/apps.php`, in the `'contractor' => [...]` block, change:

```php
            'home' => 'app/contractor/index.php',
```
to:
```php
            'home' => 'public/contractor.php',
```

- [ ] **Step 2: Create the landing page**

Create `public/contractor.php`:

```php
<?php
require_once __DIR__ . '/../includes/header.php';   // $LAYOUT defaults to 'public'
require_once __DIR__ . '/../includes/apps.php';
require_once __DIR__ . '/../app/contractor/lib.php';
$pdo = db();
$contractors = $pdo->query('SELECT status FROM contractors')->fetchAll();
$capps       = $pdo->query('SELECT applied_on FROM contractor_apps')->fetchAll();
$stats = contractor_public_stats($contractors, $capps);
$ACC = '#2563eb';
$login = base_url('app/contractor/login.php');
$actions = [
  ['📝', is_hi()?'नया पंजीकरण':'New Registration',    $login],
  ['🔁', is_hi()?'पंजीकरण नवीनीकरण':'Renew Registration', $login],
  ['🔎', is_hi()?'आवेदन ट्रैक करें':'Track Application', $login],
  ['📄', is_hi()?'प्रमाणपत्र डाउनलोड':'Download Certificate', $login],
  ['✔',  is_hi()?'ठेकेदार सत्यापन':'Verify Contractor', base_url('app/contractor/verify.php')],
  ['₹',  is_hi()?'शुल्क भुगतान':'Pay Fees',           $login],
];
?>
<section class="text-white" style="background:radial-gradient(1100px 300px at 80% -10%, rgba(37,99,235,.35), transparent), linear-gradient(180deg,#0a2a44,#0c3350)">
  <div class="max-w-6xl mx-auto px-4 pt-14 pb-12">
    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 ring-1 ring-white/20 px-3 py-1 text-xs font-medium">⚒️ <?= is_hi()?'घटक-बी':'Component-B' ?></span>
    <h1 class="font-display font-semibold text-3xl sm:text-4xl lg:text-5xl leading-[1.1] mt-5 max-w-3xl"><?= is_hi()?'जल संसाधन विभाग':'Water Resources Department' ?></h1>
    <p class="text-cyan-100/90 text-lg mt-3 max-w-2xl"><?= is_hi()?'ठेकेदार पंजीकरण एवं सूचीयन पोर्टल':'Contractor Registration & Empanelment Portal' ?></p>
    <div class="flex flex-wrap gap-2 mt-7">
      <?php foreach (['GIGW 3.0','WCAG 2.1 AA','Aadhaar e-KYC','DigiLocker','हिंदी / English'] as $b): ?>
        <span class="text-[11px] bg-white/8 ring-1 ring-white/18 rounded-lg px-2.5 py-1.5 text-cyan-100/90">✓ <?= e($b) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 -mt-8">
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <?php foreach ($actions as $a): ?>
      <a href="<?= e($a[2]) ?>" class="card p-4 lift text-center group">
        <div class="w-11 h-11 mx-auto rounded-xl grid place-items-center text-xl mb-2" style="background:color-mix(in srgb,<?= $ACC ?> 12%,#fff);color:<?= $ACC ?>"><?= $a[0] ?></div>
        <div class="text-[13px] font-semibold text-ink leading-tight"><?= e($a[1]) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 py-12">
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach ([
      [number_format($stats['registered']), is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors'],
      [number_format($stats['active']),     is_hi()?'सक्रिय ठेकेदार':'Active Contractors'],
      [number_format($stats['apps_year']),  is_hi()?'इस वर्ष आवेदन':'Applications This Year'],
      [$stats['avg_days'].' '.(is_hi()?'दिन':'Days'), is_hi()?'औसत स्वीकृति समय':'Average Approval Time'],
    ] as $s): ?>
      <div class="card p-5 text-center">
        <div class="font-display text-3xl font-semibold" style="color:<?= $ACC ?>"><?= e($s[0]) ?></div>
        <div class="text-[12px] text-slate-500 font-medium mt-1"><?= e($s[1]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 3: Syntax-check and smoke-render**

Run: `php -l public/contractor.php`
Expected: `No syntax errors detected in public/contractor.php`.

Run: `php -r "\$_GET=[]; require 'public/contractor.php';" 2>&1 | head -20`
Expected: HTML output containing `Contractor Registration & Empanelment Portal` and a formatted figure near `12,5` (no PHP fatal/warning). (Requires XAMPP MySQL running.)

- [ ] **Step 4: Commit**

```bash
git add public/contractor.php includes/apps.php
git commit -m "feat(contractor): public GIGW landing page (Screen 1)"
```

---

### Task 4: 6-step registration wizard + DigiLocker auto-fill (Screens 2, 3) + extended insert

Replace the existing 4-step wizard modal in `index.php` with the storyboard's 6 steps (Company → Classification → Technical → Financial → Bank → Documents), add a DigiLocker connect-and-autofill action in Step 1, a live eligibility recommendation on the Financial step, and persist the new credential fields on submit.

**Files:**
- Modify: `app/contractor/index.php` (the registration POST handler ~lines 10–25, and the `<dialog id="wiz">` modal + its `<script>` ~lines 121–184)

**Interfaces:**
- Consumes: `contractor_fee()` (existing), the extended `contractors` columns (Task 2).
- Produces: a `contractors` row carrying `cin/address/contact/experience_yrs/completed_projects/turnover`, plus a `contractor_apps` row at stage `ASO` (unchanged chain).

- [ ] **Step 1: Extend the registration INSERT**

In `app/contractor/index.php`, replace the registration POST block (the `if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='register' ...)` body) with:

```php
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='register' && $role==='CONTRACTOR') {
  $class=in_array($_POST['class']??'',['I','II','III','IV'],true)?$_POST['class']:'IV';
  $cnt=(int)$pdo->query('SELECT COUNT(*) FROM contractors')->fetchColumn()+451;
  $reg=sprintf('WRD/REG/3/%04d',$cnt);
  $qr=bin2hex(random_bytes(6));
  $pdo->prepare("INSERT INTO contractors (reg_no,name,class,pan,gst,district,status,risk_score,valid_upto,registered_on,qr_token,login_user,cin,address,contact,experience_yrs,completed_projects,turnover) VALUES (?,?,?,?,?,?, 'Pending',?,?,CURDATE(),?,?,?,?,?,?,?,?)")
      ->execute([$reg,trim($_POST['name']),$class,strtoupper(trim($_POST['pan'])),strtoupper(trim($_POST['gst'])),trim($_POST['district']),rand(15,40),date('Y-m-d',strtotime('+3 years')),$qr,$u['username'],
                 strtoupper(trim($_POST['cin']??'')),trim($_POST['address']??''),trim($_POST['contact']??''),(int)($_POST['experience_yrs']??0),(int)($_POST['completed_projects']??0),(float)($_POST['turnover']??0)]);
  $cid=(int)$pdo->lastInsertId();
  $ackcnt=(int)$pdo->query('SELECT COUNT(*) FROM contractor_apps')->fetchColumn()+1001;
  $ack=sprintf('WRD/ACK/2526/%04d',$ackcnt);
  $pdo->prepare("INSERT INTO contractor_apps (ack_no,contractor_id,type,class,stage,status,fee,fee_paid,applied_on) VALUES (?,?, 'New',?,'ASO','Document Verification',?,1,CURDATE())")
      ->execute([$ack,$cid,$class,contractor_fee($class)]);
  add_audit($pdo,'contractor_app',(int)$pdo->lastInsertId(),'Application submitted (Aadhaar e-KYC + DigiLocker)','Citizen','ASO',$actor,'E-GRAS fee paid · Ack '.$ack);
  flash("Registration submitted. Acknowledgement $ack");
  header('Location: index.php'); exit;
}
```

- [ ] **Step 2: Replace the wizard modal markup**

In `app/contractor/index.php`, replace the entire `<dialog id="wiz">…</dialog>` element (through its closing `</dialog>`, before the `<script>`) with the 6-step version:

```php
<dialog id="wiz" class="rounded-2xl p-0 w-full max-w-xl backdrop:bg-black/50">
  <form method="post" class="p-6"><input type="hidden" name="action" value="register">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'ठेकेदार पंजीकरण':'Contractor Registration' ?></h2>
      <button type="button" onclick="document.getElementById('wiz').close()" class="text-slate-400 text-xl">✕</button>
    </div>
    <div class="flex items-center gap-1 mb-5" id="steps">
      <?php foreach(['Company','Class','Technical','Financial','Bank','Documents'] as $si=>$ss): ?>
        <div class="flex-1 text-center"><div class="step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold <?= $si===0?'text-white':'bg-slate-100 text-slate-400' ?>" <?= $si===0?'style="background:'.e($APP['accent']).'"':'' ?>><?= $si+1 ?></div><div class="text-[10px] text-slate-400 mt-1"><?= $ss ?></div></div>
      <?php endforeach; ?>
    </div>

    <!-- Step 1: Company Details + Aadhaar e-KYC + DigiLocker -->
    <div class="wiz-pane" data-pane="0">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <span class="bg-emerald-50 text-emerald-700 text-xs font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'आधार ई-केवाईसी सत्यापित':'Aadhaar e-KYC verified' ?></span>
        <button type="button" id="dlBtn" onclick="digilocker()" class="bg-[#06314a] text-white text-xs font-semibold px-3 py-1.5 rounded-lg">📤 <?= is_hi()?'डिजीलॉकर कनेक्ट करें':'Connect DigiLocker' ?></button>
      </div>
      <div id="dlStatus" class="hidden rounded-xl p-3 text-sm mb-3" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 10%,#fff)"></div>
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'फर्म का नाम':'Firm Name' ?></label>
      <input name="name" id="f_name" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">PAN</label><input name="pan" id="f_pan" required placeholder="AABCN1234K" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700">CIN</label><input name="cin" id="f_cin" placeholder="U45200JH2020PTC0001" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">GSTIN (JH only)</label><input name="gst" id="f_gst" required placeholder="20XXXXX1234X1Z5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'ज़िला':'District' ?></label><input name="district" required value="Ranchi" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <p id="gsterr" class="text-xs text-rose-600 mt-1 hidden"><?= is_hi()?'केवल झारखंड (20) जीएसटीआईएन मान्य।':'Only Jharkhand (code 20) GSTIN is valid.' ?></p>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'पता':'Address' ?></label><input name="address" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'संपर्क':'Contact' ?></label><input name="contact" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
    </div>

    <!-- Step 2: Classification -->
    <div class="wiz-pane hidden" data-pane="1">
      <p class="text-sm text-slate-500 mb-2"><?= is_hi()?'श्रेणी चुनें — पात्रता मानदंड:':'Choose a class — eligibility criteria:' ?></p>
      <div class="grid grid-cols-2 gap-2 text-xs mb-3">
        <?php foreach([['I','10+ yrs · 10+ proj · ₹5 Cr+'],['II','7+ yrs · 6+ proj · ₹3 Cr+'],['III','4+ yrs · 3+ proj · ₹1.5 Cr+'],['IV','Entry level']] as $cc): ?>
          <div class="border border-slate-200 rounded-lg px-3 py-2"><b>Class <?= $cc[0] ?></b><div class="text-slate-400"><?= $cc[1] ?></div></div>
        <?php endforeach; ?>
      </div>
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'आवेदित श्रेणी':'Applied Class' ?></label>
      <select name="class" id="f_class" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>I</option><option>II</option><option>III</option><option selected>IV</option></select>
    </div>

    <!-- Step 3: Technical Credentials -->
    <div class="wiz-pane hidden" data-pane="2">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'अनुभव (वर्ष)':'Experience (years)' ?></label><input type="number" name="experience_yrs" id="f_yrs" min="0" value="5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'पूर्ण परियोजनाएँ':'Completed Projects' ?></label><input type="number" name="completed_projects" id="f_proj" min="0" value="5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <p class="text-xs text-slate-400 mt-3"><?= is_hi()?'कार्य आदेश एवं पूर्णता प्रमाणपत्र दस्तावेज़ चरण में अपलोड करें।':'Upload work orders & completion certificates in the Documents step.' ?></p>
    </div>

    <!-- Step 4: Financial Credentials + live eligibility -->
    <div class="wiz-pane hidden" data-pane="3">
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'वार्षिक टर्नओवर (₹)':'Annual Turnover (₹)' ?></label>
      <input type="number" name="turnover" id="f_turn" min="0" step="100000" value="16000000" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <p class="text-xs text-slate-400 mt-1"><?= is_hi()?'आईटी रिटर्न, बैलेंस शीट, सीए प्रमाणपत्र दस्तावेज़ चरण में।':'IT returns, balance sheet & CA certificate in the Documents step.' ?></p>
      <div id="eligBox" class="mt-3 rounded-xl px-3 py-2.5 text-sm font-semibold" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 12%,#fff);color:<?= e($APP['accent']) ?>">🤖 <?= is_hi()?'अनुशंसित श्रेणी':'Recommended class' ?>: <span id="eligClass">—</span></div>
    </div>

    <!-- Step 5: Bank Details -->
    <div class="wiz-pane hidden" data-pane="4">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'खाता संख्या':'Account Number' ?></label><input class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700">IFSC</label><input placeholder="SBIN0001234" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <label class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 mt-3 text-sm"><span><?= is_hi()?'रद्द किया गया चेक':'Cancelled Cheque' ?></span><span class="text-emerald-600 text-xs font-semibold">✓ uploaded</span></label>
    </div>

    <!-- Step 6: Documents (drag & drop) + E-GRAS fee -->
    <div class="wiz-pane hidden" data-pane="5">
      <div class="border-2 border-dashed border-slate-300 rounded-xl p-5 text-center text-sm text-slate-500 mb-3">⬆ <?= is_hi()?'दस्तावेज़ यहाँ खींचें और छोड़ें (डेमो)':'Drag & drop documents here (demo)' ?></div>
      <?php foreach(['Photograph','Signature','PAN Card','Incorporation Certificate','GST Certificate','Balance Sheet','CA Certificate'] as $doc): ?>
        <label class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 mb-2 text-sm"><span><?= $doc ?></span><span class="text-emerald-600 text-xs font-semibold">✓ uploaded</span></label>
      <?php endforeach; ?>
      <div class="bg-paper rounded-xl p-3 text-sm mt-3 flex justify-between"><span class="text-slate-500"><?= is_hi()?'पंजीकरण शुल्क (ई-ग्रास)':'Registration Fee (E-GRAS)' ?></span><span class="font-semibold" id="feeAmt">₹10,000</span></div>
      <div class="mt-2 text-sm text-emerald-700 bg-emerald-50 rounded-xl px-3 py-2">✓ <?= is_hi()?'भुगतान सफल (डेमो)।':'Payment successful (demo).' ?></div>
    </div>

    <div class="flex gap-2 mt-5">
      <button type="button" id="prevBtn" onclick="wizStep(-1)" class="border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-600 hidden"><?= is_hi()?'पीछे':'Back' ?></button>
      <button type="button" id="nextBtn" onclick="wizStep(1)" class="flex-1 btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'आगे':'Next' ?> →</button>
      <button type="submit" id="subBtn" class="flex-1 bg-emerald-600 text-white rounded-xl py-2.5 font-semibold hidden"><?= is_hi()?'आवेदन जमा करें':'Submit Application' ?></button>
    </div>
  </form>
</dialog>
```

- [ ] **Step 3: Replace the wizard `<script>`**

Replace the existing wizard `<script>…</script>` block with the 6-step navigation, GST guard, fee sync, DigiLocker animation, and live eligibility:

```php
<script>
let cur=0; const panes=document.querySelectorAll('.wiz-pane'), dots=document.querySelectorAll('.step-dot');
const LAST=5, ACC='<?= e($APP['accent']) ?>';
function recomputeElig(){
  const y=+document.getElementById('f_yrs').value||0, p=+document.getElementById('f_proj').value||0, t=+document.getElementById('f_turn').value||0;
  let cls='IV';
  if(y>=10&&p>=10&&t>=50000000) cls='I'; else if(y>=7&&p>=6&&t>=30000000) cls='II'; else if(y>=4&&p>=3&&t>=15000000) cls='III';
  document.getElementById('eligClass').textContent='Class '+cls;
}
function wizStep(dir){
  if(dir>0 && cur===0){ const g=document.getElementById('f_gst').value.trim();
    if(g && !g.startsWith('20')){ document.getElementById('gsterr').classList.remove('hidden'); return; } }
  cur=Math.max(0,Math.min(LAST,cur+dir));
  panes.forEach((p,i)=>p.classList.toggle('hidden',i!==cur));
  dots.forEach((d,i)=>{ d.className='step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold '+(i<=cur?'text-white':'bg-slate-100 text-slate-400'); d.style.background=i<=cur?ACC:''; });
  document.getElementById('prevBtn').classList.toggle('hidden',cur===0);
  document.getElementById('nextBtn').classList.toggle('hidden',cur===LAST);
  document.getElementById('subBtn').classList.toggle('hidden',cur!==LAST);
  const fees={'I':'₹45,000','II':'₹30,000','III':'₹20,000','IV':'₹10,000'};
  document.getElementById('feeAmt').textContent=fees[document.getElementById('f_class').value]||'₹10,000';
  if(cur===3) recomputeElig();
}
function digilocker(){
  const box=document.getElementById('dlStatus'); box.classList.remove('hidden');
  box.innerHTML='⏳ Connecting to DigiLocker…';
  const items=['PAN','Aadhaar','GST','Company Documents']; let i=0;
  const tick=setInterval(()=>{ i++; box.innerHTML='Fetching: '+items.slice(0,i).map(x=>'✓ '+x).join('  ');
    if(i>=items.length){ clearInterval(tick);
      document.getElementById('f_name').value='M/s ABC Infra Pvt Ltd';
      document.getElementById('f_pan').value='AABCA9999K';
      document.getElementById('f_gst').value='20ABCIN9999A1Z5';
      document.getElementById('f_cin').value='U45200JH2021PTC009999';
      box.innerHTML='✅ <b>Verification Successful</b> — details auto-filled from DigiLocker.';
    } },500);
}
</script>
```

- [ ] **Step 4: Syntax-check**

Run: `php -l app/contractor/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add app/contractor/index.php
git commit -m "feat(contractor): 6-step wizard + DigiLocker auto-fill + eligibility (Screens 2,3)"
```

---

### Task 5: AI Document Verification panel (Screen 4)

Render real, deterministic verification results for the uploaded documents using `contractor_doc_verify()`, shown on the Documents step of the wizard.

**Files:**
- Modify: `app/contractor/index.php` (Step 6 `data-pane="5"` block — replace the static "✓ uploaded" rows)

**Interfaces:**
- Consumes: `contractor_doc_verify()` (Task 1).
- Produces: per-document Verified/Issue UI inside the wizard.

- [ ] **Step 1: Replace the document rows with verified results**

In `app/contractor/index.php`, inside `data-pane="5"`, replace the `foreach(['Photograph',...] as $doc)` loop with one that calls the verifier:

```php
      <?php foreach(['Photograph','Signature','PAN Card','Incorporation Certificate','GST Certificate','Balance Sheet','CA Certificate'] as $doc): $v=contractor_doc_verify($doc,0); ?>
        <label class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 mb-2 text-sm">
          <span><?= $doc ?></span>
          <?php if($v['status']==='Verified'): ?>
            <span class="text-emerald-600 text-xs font-semibold">🤖 ✓ <?= is_hi()?'सत्यापित':'Verified' ?></span>
          <?php else: ?>
            <span class="text-amber-600 text-xs font-semibold" title="<?= e($v['issue']) ?>">⚠ <?= e($v['issue']) ?></span>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
      <p class="text-[11px] text-slate-400 mb-2">🤖 <?= is_hi()?'एआई दस्तावेज़ जाँच — हस्ताक्षर, तिथि एवं गुणवत्ता।':'AI document check — signature, date & quality.' ?></p>
```

- [ ] **Step 2: Syntax-check**

Run: `php -l app/contractor/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/contractor/index.php
git commit -m "feat(contractor): AI document verification panel in wizard (Screen 4)"
```

---

### Task 6: Public verification portal (Screen 11)

Let any visitor verify a contractor by Registration Number (not just by QR token), with a search form when no parameter is supplied. Keep the existing QR-token path working.

**Files:**
- Modify: `app/contractor/verify.php`

**Interfaces:**
- Consumes: `contractors` table.
- Produces: public verification result keyed by `qr_token` OR `reg_no`.

- [ ] **Step 1: Broaden the lookup and add a search form**

In `app/contractor/verify.php`, replace the lookup block (lines ~5–7) with:

```php
$token=$_GET['token']??''; $reg=trim($_GET['reg']??'');
$c=null;
if($token){ $st=db()->prepare("SELECT * FROM contractors WHERE qr_token=?"); $st->execute([$token]); $c=$st->fetch(); }
elseif($reg){ $st=db()->prepare("SELECT * FROM contractors WHERE reg_no=?"); $st->execute([$reg]); $c=$st->fetch(); }
$searched = $token!=='' || $reg!=='';
```

Then, immediately after the opening `<div class="p-7 text-center">`, insert a search form shown when nothing was searched yet:

```php
    <?php if(!$searched): ?>
      <form method="get" class="text-left">
        <label class="text-sm font-medium text-slate-700"><?= 'Contractor Registration No' ?></label>
        <input name="reg" placeholder="WRD/REG/3/0451" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
        <button class="mt-3 w-full bg-[#06314a] text-white rounded-xl py-2.5 font-semibold">🔍 Verify Contractor</button>
      </form>
    <?php else: ?>
```

And add the matching `<?php endif; ?>` immediately before the closing `<p class="text-[11px] text-slate-400 mt-6">` line so the result block (the existing if/elseif/else for `$c`) is wrapped in the `else` branch.

- [ ] **Step 2: Syntax-check and smoke-render**

Run: `php -l app/contractor/verify.php`
Expected: `No syntax errors detected`.

Run: `php -r "\$_GET=['reg'=>'WRD/REG/3/0451']; require 'app/contractor/verify.php';" 2>&1 | grep -o 'Authentic & Valid\|Not Found'`
Expected: `Authentic & Valid` (requires XAMPP MySQL running and the DB seeded).

Run: `php -r "\$_GET=[]; require 'app/contractor/verify.php';" 2>&1 | grep -o 'Verify Contractor'`
Expected: `Verify Contractor` (the search form renders).

- [ ] **Step 3: Commit**

```bash
git add app/contractor/verify.php
git commit -m "feat(contractor): public verification by registration no + search form (Screen 11)"
```

---

## Phase-1 Checkpoint (manual, on XAMPP)

After Task 6, run the full suite and walk the demo path in a browser before declaring Phase 1 done:

- [ ] `php tests/run.php` → all green (`Failed: 0`).
- [ ] `php setup.php` once to apply schema + seed, then visit `http://localhost/WRD/public/contractor.php` — hero, six quick actions, ~12,5xx stats.
- [ ] Log in via `app/contractor/login.php` as the `contractor` user (password `demo123`), open **New Registration** → step through all 6 panes → Connect DigiLocker auto-fills Step 1 → Financial step shows recommended class → Documents step shows AI Verified/⚠ rows → Submit → acknowledgement flash.
- [ ] Visit `app/contractor/verify.php`, enter `WRD/REG/3/0451` → "Authentic & Valid"; enter the blacklisted `WRD/REG/3/0456` → "Blacklisted Contractor".
- [ ] Confirm Hindi toggle flips all new strings.

## Self-Review notes (coverage)

- Screen 1 (landing) → Task 3. Screen 2 (6-step wizard + dynamic eligibility) → Task 4. Screen 3 (DigiLocker) → Task 4. Screen 4 (AI doc verify) → Tasks 1 + 5. Screen 11 (public verify) → Task 6. Supporting pure logic + schema → Tasks 1 + 2.
- Screens 5–10, 12–15, the officer 6-stage chain, scoring engine, query management, renewal, revenue, leadership map, and the AI chat assistant are **Phase 2/3** — intentionally out of this plan.
- All AI/DigiLocker/E-GRAS remain simulated and offline-safe per the approved spec.
