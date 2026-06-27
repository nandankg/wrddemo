# Contractor Phase 2 — Scrutiny & Evaluation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the officer side of the contractor demo — a real per-application scrutiny page with document review, an objective four-part score, and a full officer⇄contractor query round-trip.

**Architecture:** Pure, unit-tested logic lives in `app/contractor/lib.php` (scoring, breakdown, forward-guard). DB access and rendering stay in page files, following the existing per-page pattern. A new `scrutiny.php` is the officer hub; the contractor responds to queries from their existing dashboard; a new `contractor_queries` table holds the round-trip.

**Tech Stack:** PHP 8 (procedural), MySQL via PDO (`db()`), server-rendered HTML with Tailwind utility classes, zero-dependency test harness (`tests/run.php`).

## Global Constraints

- No new Composer/JS dependencies — server-rendered PHP only, matching the module.
- All new pure functions live in `app/contractor/lib.php` and stay DB-free and render-free.
- Bilingual UI: every user-facing string uses `is_hi() ? 'हिन्दी' : 'English'`.
- Escape all dynamic output with `e()`; format money with `inr()`.
- Every workflow state change calls `add_audit($pdo, 'contractor_app', $appId, $action, $from, $to, $actor, $remarks)`.
- Tests run with `php tests/run.php` (auto-globs `tests/*_test.php`); the suite is green (71 passing) and must stay green.
- Workflow stages are `ASO → AE → EE → EIC` (`contractor_next_stage`). EIC approves; it does not forward.

---

### Task 1: Scoring, breakdown & forward-guard (pure logic)

**Files:**
- Modify: `app/contractor/lib.php` (append three functions; repoint one URL)
- Test: `tests/contractor_test.php` (add cases; update one existing assertion)

**Interfaces:**
- Consumes: `contractor_next_stage(string): ?string` (already in lib.php).
- Produces:
  - `contractor_score(array $c, array $docResults = []): array` → `['experience'=>int,'financial'=>int,'compliance'=>int,'overall'=>int,'band'=>string]`
  - `contractor_app_breakdown(array $apps): array` → `['new'=>int,'verifying'=>int,'approval_pending'=>int,'query'=>int,'approved'=>int,'rejected'=>int]`
  - `contractor_can_forward(array $app, int $openQueries): bool`

- [ ] **Step 1: Write the failing tests**

Append to `tests/contractor_test.php`:

```php
it('contractor_score is deterministic and weights experience/financial/compliance', function () {
    // Class I, meets threshold exactly, clean docs, low risk.
    $c = ['experience_yrs'=>10,'completed_projects'=>10,'turnover'=>50000000,
          'class'=>'I','status'=>'Active','risk_score'=>10];
    $s = contractor_score($c, []);
    assert_eq(100, $s['experience']);   // 10yr + 10proj caps the bar
    assert_eq(90,  $s['financial']);    // ratio 1.0 -> 40 + 50
    assert_eq(90,  $s['compliance']);   // 100 - risk 10 - 0 issues
    assert_eq(94,  $s['overall']);      // 35 + 27 + 31.5 -> 94
    assert_eq('A', $s['band']);

    // Doc issues lower compliance; experience & financial clamp at 100.
    $c3 = ['experience_yrs'=>14,'completed_projects'=>28,'turnover'=>85000000,
           'class'=>'I','status'=>'Active','risk_score'=>10];
    $docs = [['status'=>'Verified'],['status'=>'Issue'],['status'=>'Issue']];
    $s3 = contractor_score($c3, $docs);
    assert_eq(100, $s3['experience']);
    assert_eq(100, $s3['financial']);   // ratio 1.7 capped at 1.2 -> 40 + 60
    assert_eq(60,  $s3['compliance']);  // 100 - 10 - 15*2
    assert_eq(86,  $s3['overall']);
    assert_eq('A', $s3['band']);

    // Blacklisted zeroes compliance regardless of docs.
    $c2 = ['experience_yrs'=>0,'completed_projects'=>0,'turnover'=>0,
           'class'=>'IV','status'=>'Blacklisted','risk_score'=>40];
    $s2 = contractor_score($c2, []);
    assert_eq(0,   $s2['experience']);
    assert_eq(40,  $s2['financial']);   // ratio 0 -> 40
    assert_eq(0,   $s2['compliance']);  // blacklisted
    assert_eq(12,  $s2['overall']);     // 0 + 12 + 0
    assert_eq('C', $s2['band']);
});

it('contractor_app_breakdown buckets apps by review state', function () {
    $apps = [
      ['stage'=>'ASO','status'=>'Document Verification'],
      ['stage'=>'AE','status'=>'Under Process'],
      ['stage'=>'EE','status'=>'Under Process'],
      ['stage'=>'EIC','status'=>'Under Process'],
      ['stage'=>'AE','status'=>'Query Raised'],
      ['stage'=>'EIC','status'=>'Approved'],
      ['stage'=>'ASO','status'=>'Rejected'],
    ];
    $b = contractor_app_breakdown($apps);
    assert_eq(1, $b['new']);
    assert_eq(2, $b['verifying']);
    assert_eq(1, $b['approval_pending']);
    assert_eq(1, $b['query']);
    assert_eq(1, $b['approved']);
    assert_eq(1, $b['rejected']);
});

it('contractor_can_forward blocks on open queries and terminal states', function () {
    assert_true(contractor_can_forward(['stage'=>'ASO','status'=>'Under Process'], 0));
    assert_eq(false, contractor_can_forward(['stage'=>'ASO','status'=>'Under Process'], 1));
    assert_eq(false, contractor_can_forward(['stage'=>'ASO','status'=>'Query Raised'], 0));
    assert_eq(false, contractor_can_forward(['stage'=>'EIC','status'=>'Approved'], 0));
    assert_eq(false, contractor_can_forward(['stage'=>'ASO','status'=>'Rejected'], 0));
});
```

Then update the existing `contractor_pending_actions` assertions (currently expecting `applications.php?id=`) to the new scrutiny URL:

```php
    assert_eq('scrutiny.php?app_id=1', contractor_pending_actions('ASO', $apps)[0]['url']);
    assert_eq('scrutiny.php?app_id=2', contractor_pending_actions('EIC', $apps)[0]['url']);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php`
Expected: FAIL — `contractor_score`/`contractor_app_breakdown`/`contractor_can_forward` undefined, and the pending-actions assertion mismatch.

- [ ] **Step 3: Add the functions and repoint the URL**

Append to `app/contractor/lib.php` (before the closing of the file):

```php
/**
 * Objective four-part evaluation score for a contractor.
 * $c: a contractors row (experience_yrs, completed_projects, turnover, class, status, risk_score).
 * $docResults: rows from contractor_doc_verify(); each 'Issue' lowers compliance.
 * Returns experience|financial|compliance|overall (0..100) plus band A/B/C.
 */
function contractor_score(array $c, array $docResults = []): array {
    $years    = (int)($c['experience_yrs'] ?? 0);
    $projects = (int)($c['completed_projects'] ?? 0);
    $turnover = (float)($c['turnover'] ?? 0);
    $risk     = (int)($c['risk_score'] ?? 0);

    $experience = (int)round(min($years, 10) / 10 * 50 + min($projects, 10) / 10 * 50);

    $thresholds = ['I'=>50000000.0, 'II'=>30000000.0, 'III'=>15000000.0, 'IV'=>5000000.0];
    $threshold  = $thresholds[$c['class'] ?? 'IV'] ?? 5000000.0;
    $ratio      = $threshold > 0 ? $turnover / $threshold : 0.0;
    $financial  = max(0, min(100, (int)round(40 + min($ratio, 1.2) * 50)));

    if (($c['status'] ?? '') === 'Blacklisted') {
        $compliance = 0;
    } else {
        $issues = 0;
        foreach ($docResults as $d) if (($d['status'] ?? '') === 'Issue') $issues++;
        $compliance = max(0, min(100, 100 - $risk - 15 * $issues));
    }

    $overall = (int)round($experience * 0.35 + $financial * 0.30 + $compliance * 0.35);
    $band    = $overall >= 80 ? 'A' : ($overall >= 60 ? 'B' : 'C');

    return ['experience'=>$experience, 'financial'=>$financial,
            'compliance'=>$compliance, 'overall'=>$overall, 'band'=>$band];
}

/**
 * Count back-office applications by review bucket for the Screen-6 strip.
 * Terminal and query states take priority over the workflow stage.
 */
function contractor_app_breakdown(array $apps): array {
    $b = ['new'=>0,'verifying'=>0,'approval_pending'=>0,'query'=>0,'approved'=>0,'rejected'=>0];
    foreach ($apps as $a) {
        $st = $a['status'] ?? ''; $stage = $a['stage'] ?? '';
        if      ($st === 'Rejected')     $b['rejected']++;
        elseif  ($st === 'Approved')     $b['approved']++;
        elseif  ($st === 'Query Raised') $b['query']++;
        elseif  ($stage === 'EIC')       $b['approval_pending']++;
        elseif  ($stage === 'ASO')       $b['new']++;
        else                             $b['verifying']++;
    }
    return $b;
}

/** Can this application be forwarded to the next stage right now? */
function contractor_can_forward(array $app, int $openQueries): bool {
    if ($openQueries > 0) return false;
    if (in_array($app['status'] ?? '', ['Approved','Rejected','Query Raised'], true)) return false;
    return contractor_next_stage($app['stage'] ?? '') !== null;
}
```

In `contractor_pending_actions` change the URL it builds:

```php
        $out[] = ['label'=>$verb.' '.$a['ack_no'].' · '.($a['cname'] ?? 'New applicant'), 'meta'=>'', 'status'=>$a['status'], 'url'=>'scrutiny.php?app_id='.$a['id']];
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php`
Expected: PASS — `Passed: 74  Failed: 0` (71 prior + 3 new `it()` blocks).

- [ ] **Step 5: Commit**

```bash
git add app/contractor/lib.php tests/contractor_test.php
git commit -m "feat(contractor): scoring engine, app breakdown & forward-guard (pure logic)"
```

---

### Task 2: `contractor_queries` table + seed

**Files:**
- Modify: `setup.php` (drop-list line ~26; add CREATE TABLE after the `contractor_apps` block ~line 158)
- Modify: `sql/seed.php` (after the contractor_apps seeding, ~line 175)

**Interfaces:**
- Produces: table `contractor_queries(id, app_id, raised_by, raised_role, query_text, status, response_text, raised_on, responded_on, resolved_on)` and two seeded rows; seeded app id 3 set to status `Query Raised`.

- [ ] **Step 1: Add the table to the drop-list**

In `setup.php`, add `'contractor_queries'` to the table array that is dropped (the list beginning with `'contractor_apps','contractors',...`):

```php
              'contractor_queries','contractor_apps','contractors','fund_requisitions','projects','schemes',
```

- [ ] **Step 2: Add the CREATE TABLE**

In `setup.php`, immediately after the `CREATE TABLE contractor_apps (...) SQL);` block, add:

```php
    $pdo->exec(<<<SQL
    CREATE TABLE contractor_queries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_id INT,
        raised_by VARCHAR(120), raised_role VARCHAR(20),
        query_text TEXT,
        status VARCHAR(20) DEFAULT 'Open',
        response_text TEXT NULL,
        raised_on DATE, responded_on DATE NULL, resolved_on DATE NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
```

- [ ] **Step 3: Seed two illustrative queries**

In `sql/seed.php`, immediately after `foreach ($apps as $a) $ins->execute($a);` (the contractor_apps loop, ~line 175), add:

```php
    // ---- Contractor queries (officer<->contractor round-trip) ----
    $appIds = $pdo->query('SELECT id FROM contractor_apps ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    // App #3 (new applicant at ASO) is held by an open query.
    $pdo->prepare("UPDATE contractor_apps SET status='Query Raised' WHERE id=?")->execute([$appIds[2]]);
    $q = $pdo->prepare('INSERT INTO contractor_queries (app_id,raised_by,raised_role,query_text,status,response_text,raised_on,responded_on,resolved_on) VALUES (?,?,?,?,?,?,?,?,?)');
    $q->execute([$appIds[2], 'Sunita Kumari (ASO, Ranchi)', 'ASO',
        'Please upload an updated GST registration certificate — the copy on file has expired.',
        'Open', null, date('Y-m-d', strtotime('-2 days')), null, null]);
    $q->execute([$appIds[1], 'Ravi Sharma (EE, Ranchi)', 'EE',
        'Work-order value on completion certificate #3 does not match the audited financial statement.',
        'Resolved', 'Revised completion certificate uploaded with the corrected work-order value.',
        date('Y-m-d', strtotime('-9 days')), date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-6 days'))]);
```

- [ ] **Step 4: Rebuild and verify the schema + seed**

Run the installer (XAMPP MySQL must be running). In a browser open `http://localhost/WRD/setup.php`, or from CLI: `php setup.php`.
Expected: completes without error. Then verify:

Run: `php -r "require 'config/db.php'; \$p=db(); var_dump(\$p->query('SELECT app_id,status FROM contractor_queries ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));"`
Expected: two rows — one `Open`, one `Resolved`; and app #3's status is now `Query Raised` (`php -r "...SELECT id,status FROM contractor_apps..."`).

> If `config/db.php` is not the bootstrap path, use the same include the other pages use (`includes/auth.php` pulls it in). Confirm the include path before running.

- [ ] **Step 5: Commit**

```bash
git add setup.php sql/seed.php
git commit -m "feat(contractor): contractor_queries table + seeded round-trip"
```

---

### Task 3: Officer scrutiny page (`scrutiny.php`) — review, score & raise/resolve queries

**Files:**
- Create: `app/contractor/scrutiny.php`
- Modify: `app/contractor/applications.php` (add an "Open scrutiny →" link per row)

**Interfaces:**
- Consumes: `contractor_score`, `contractor_doc_verify`, `contractor_role_view`, `add_audit`, `set_app_context`, `e`, `inr`, `is_hi`, `base_url`, `badge`.
- Produces: route `scrutiny.php?app_id=<id>` (officer-only) handling `action=raise_query` and `action=resolve_query`.

- [ ] **Step 1: Create the scrutiny page**

Create `app/contractor/scrutiny.php`:

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

// Officers only — contractors never see the scrutiny desk.
if (contractor_role_view($role) !== 'registry') { header('Location: '.base_url('app/contractor/index.php')); exit; }

// ---- Query actions (officer raises / resolves) ----
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act=$_POST['action']??''; $pid=(int)($_POST['app_id']??0);
  $app=$pdo->query("SELECT * FROM contractor_apps WHERE id=$pid")->fetch();
  if ($app) {
    if ($act==='raise_query') {
      $qt=trim($_POST['query_text']??'');
      if ($qt!=='') {
        $pdo->prepare("INSERT INTO contractor_queries (app_id,raised_by,raised_role,query_text,status,raised_on) VALUES (?,?,?,?, 'Open', CURDATE())")
            ->execute([$pid,$actor,$role,$qt]);
        $pdo->prepare("UPDATE contractor_apps SET status='Query Raised' WHERE id=?")->execute([$pid]);
        add_audit($pdo,'contractor_app',$pid,'Query raised: '.$qt,$role,'Contractor',$actor,'Awaiting contractor response.');
        flash('Query raised. Contractor notified.');
      }
    } elseif ($act==='resolve_query') {
      $qid=(int)($_POST['query_id']??0);
      $pdo->prepare("UPDATE contractor_queries SET status='Resolved', resolved_on=CURDATE() WHERE id=? AND app_id=?")->execute([$qid,$pid]);
      $pending=(int)$pdo->query("SELECT COUNT(*) FROM contractor_queries WHERE app_id=$pid AND status<>'Resolved'")->fetchColumn();
      if ($pending===0) $pdo->prepare("UPDATE contractor_apps SET status='Under Process' WHERE id=? AND status='Query Raised'")->execute([$pid]);
      add_audit($pdo,'contractor_app',$pid,'Query resolved',$role,$role,$actor,'');
      flash('Query resolved.');
    }
    header('Location: scrutiny.php?app_id='.$pid); exit;
  }
}

$aid=(int)($_GET['app_id']??0);
$app=$pdo->query("SELECT a.id app_id, a.ack_no, a.type, a.class, a.stage, a.status app_status, a.fee, a.fee_paid,
                         c.id cid, c.name cname, c.name_hi cname_hi, c.district, c.status cstatus,
                         c.risk_score, c.experience_yrs, c.completed_projects, c.turnover
                  FROM contractor_apps a LEFT JOIN contractors c ON c.id=a.contractor_id
                  WHERE a.id=$aid")->fetch();

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='applications'; $PAGE_TITLE='Scrutiny';
require __DIR__ . '/../../includes/header.php';

if (!$app) { echo '<div class="card p-10 text-center text-slate-400">'.(is_hi()?'आवेदन नहीं मिला।':'Application not found.').'</div>'; require __DIR__ . '/../../includes/footer.php'; exit; }

$hasFirm = $app['cid'] !== null;
$DOCS = ['PAN Card','GST Certificate','Balance Sheet','CA Certificate','Work Order','Completion Certificate','Cancelled Cheque'];
$docResults = [];
foreach ($DOCS as $d) $docResults[$d] = contractor_doc_verify($d, (int)$app['app_id']);

$score = $hasFirm ? contractor_score([
  'experience_yrs'=>$app['experience_yrs'],'completed_projects'=>$app['completed_projects'],
  'turnover'=>$app['turnover'],'class'=>$app['class'],'status'=>$app['cstatus'],'risk_score'=>$app['risk_score'],
], array_values($docResults)) : null;

$queries = $pdo->query("SELECT * FROM contractor_queries WHERE app_id=$aid ORDER BY id DESC")->fetchAll();
$name = $hasFirm ? (is_hi() ? ($app['cname_hi'] ?: $app['cname']) : $app['cname']) : (is_hi()?'नया आवेदक':'New Applicant');
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <a href="<?= base_url('app/contractor/applications.php') ?>" class="text-sm text-slate-400">← <?= is_hi()?'आवेदन':'Applications' ?></a>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= e($name) ?></h1>
    <p class="text-sm text-slate-500"><?= e($app['ack_no']) ?> · <?= is_hi()?'श्रेणी':'Class' ?> <?= e($app['class']) ?><?= $hasFirm?' · '.e($app['district']):'' ?> · <?= is_hi()?'चरण':'stage' ?> <b><?= e($app['stage']) ?></b></p>
  </div>
  <?= badge($app['app_status']) ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Documents + AI verification -->
  <div class="lg:col-span-2 space-y-6">
    <div class="card p-6">
      <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'दस्तावेज़ सत्यापन':'Document Verification' ?> <span class="text-xs font-normal text-slate-400">· AI</span></h2>
      <div class="space-y-2">
        <?php foreach ($DOCS as $d): $r=$docResults[$d]; $ok=$r['status']==='Verified'; ?>
          <div class="flex items-center justify-between border border-slate-100 rounded-xl px-4 py-2.5">
            <span class="text-sm text-slate-700"><?= e($d) ?></span>
            <?php if ($ok): ?>
              <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-full px-2.5 py-1">✓ <?= is_hi()?'सत्यापित':'Verified' ?></span>
            <?php else: ?>
              <span class="text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-2.5 py-1" title="<?= e($r['issue']) ?>">⚠ <?= e($r['issue']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Query thread -->
    <div class="card p-6">
      <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'प्रश्न प्रबंधन':'Query Management' ?></h2>
      <div class="space-y-3 mb-5">
        <?php foreach ($queries as $q): ?>
          <div class="border border-slate-100 rounded-xl p-3">
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs text-slate-400"><?= e($q['raised_by']) ?> · <?= e($q['raised_on']) ?></span>
              <span class="text-xs font-semibold rounded-full px-2 py-0.5 <?= $q['status']==='Resolved'?'bg-emerald-50 text-emerald-700':($q['status']==='Responded'?'bg-sky-50 text-sky-700':'bg-amber-50 text-amber-700') ?>"><?= e($q['status']) ?></span>
            </div>
            <p class="text-sm text-slate-700 mt-1"><?= e($q['query_text']) ?></p>
            <?php if (!empty($q['response_text'])): ?>
              <p class="text-sm text-slate-600 mt-2 pl-3 border-l-2 border-sky-200"><b><?= is_hi()?'उत्तर':'Reply' ?>:</b> <?= e($q['response_text']) ?></p>
            <?php endif; ?>
            <?php if ($q['status']!=='Resolved'): ?>
              <form method="post" class="mt-2">
                <input type="hidden" name="app_id" value="<?= $app['app_id'] ?>">
                <input type="hidden" name="query_id" value="<?= $q['id'] ?>">
                <button name="action" value="resolve_query" class="text-xs font-semibold text-emerald-700">✓ <?= is_hi()?'समाधान करें':'Mark resolved' ?></button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$queries): ?><p class="text-sm text-slate-400"><?= is_hi()?'कोई प्रश्न नहीं।':'No queries raised.' ?></p><?php endif; ?>
      </div>
      <form method="post" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="app_id" value="<?= $app['app_id'] ?>">
        <input name="query_text" required placeholder="<?= is_hi()?'ठेकेदार से प्रश्न पूछें…':'Ask the contractor for clarification…' ?>" class="flex-1 min-w-[200px] border border-slate-300 rounded-xl px-3 py-2.5 text-sm">
        <button name="action" value="raise_query" class="btn-acc font-semibold px-4 py-2.5 rounded-xl text-sm"><?= is_hi()?'प्रश्न भेजें':'Raise Query' ?></button>
      </form>
    </div>
  </div>

  <!-- Scoring panel -->
  <div class="space-y-6">
    <div class="card p-6">
      <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'मूल्यांकन स्कोर':'Evaluation Score' ?></h2>
      <?php if ($score): ?>
        <div class="text-center mb-5">
          <div class="text-5xl font-display font-bold" style="color:<?= e($APP['accent']) ?>"><?= $score['overall'] ?></div>
          <div class="text-xs text-slate-400 mt-1"><?= is_hi()?'समग्र · बैंड':'Overall · Band' ?> <b><?= e($score['band']) ?></b></div>
        </div>
        <?php foreach ([
            [is_hi()?'अनुभव':'Experience', $score['experience']],
            [is_hi()?'वित्तीय':'Financial', $score['financial']],
            [is_hi()?'अनुपालन':'Compliance', $score['compliance']],
          ] as $row): ?>
          <div class="mb-3">
            <div class="flex justify-between text-xs text-slate-500 mb-1"><span><?= e($row[0]) ?></span><span class="font-semibold text-slate-700"><?= $row[1] ?></span></div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width:<?= (int)$row[1] ?>%;background:<?= e($APP['accent']) ?>"></div></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-sm text-slate-400"><?= is_hi()?'कंपनी ई-केवाईसी के बाद स्कोरिंग उपलब्ध।':'Scoring available after company e-KYC is on file.' ?></p>
      <?php endif; ?>
    </div>
    <a href="<?= base_url('app/contractor/applications.php') ?>" class="block text-center btn-acc font-semibold px-4 py-2.5 rounded-xl"><?= is_hi()?'निर्णय हेतु इनबॉक्स':'Go to inbox to decide' ?> →</a>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

- [ ] **Step 2: Link to scrutiny from the applications inbox**

In `app/contractor/applications.php`, inside the officer branch where a row currently renders actions, add an "Open scrutiny →" link. Place it just after the opening of the `<div class="card p-4">` row's status line — concretely, after the line rendering `<?= badge($a['status']) ?>` add:

```php
        <?php if(!$isContractor): ?>
          <a href="<?= base_url('app/contractor/scrutiny.php') ?>?app_id=<?= $a['id'] ?>" class="text-xs font-semibold" style="color:<?= e($APP['accent']) ?>"><?= is_hi()?'जांच खोलें':'Open scrutiny' ?> →</a>
        <?php endif; ?>
```

- [ ] **Step 3: Verify the pure suite still passes**

Run: `php tests/run.php`
Expected: PASS — `Passed: 74  Failed: 0` (no test change; this confirms no syntax error in lib consumers).

- [ ] **Step 4: Manual verification (browser)**

Rebuild via `http://localhost/WRD/setup.php`, then log into the contractor portal as an officer (ASO). From the dashboard's pending actions or **Applications → Open scrutiny**, open `scrutiny.php?app_id=3`. Confirm: document checklist with at least one AI "⚠" issue; the scoring panel shows Overall + Experience/Financial/Compliance bars; the seeded **Open** query appears; raising a new query shows a flash and the app badge becomes "Query Raised"; **Mark resolved** on the last open query flips the app back to "Under Process".

- [ ] **Step 5: Commit**

```bash
git add app/contractor/scrutiny.php app/contractor/applications.php
git commit -m "feat(contractor): officer scrutiny page — docs, scoring gauges, query raise/resolve"
```

---

### Task 4: Contractor query response + forward guard

**Files:**
- Modify: `app/contractor/index.php` (contractor view: list open queries + respond form; POST handler)
- Modify: `app/contractor/applications.php` (block Forward while a query is open)

**Interfaces:**
- Consumes: `contractor_can_forward`, `add_audit`, the `contractor_queries` table.
- Produces: `action=respond_query` POST on `index.php` (contractor-only).

- [ ] **Step 1: Handle the contractor's response (POST) in index.php**

In `app/contractor/index.php`, add a second POST handler next to the existing `action==='register'` block (after it, before `$view = contractor_role_view($role);`):

```php
// Contractor answers an officer query: records the response and resumes the workflow.
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='respond_query' && $role==='CONTRACTOR') {
  $qid=(int)($_POST['query_id']??0); $resp=trim($_POST['response_text']??'');
  $q=$pdo->query("SELECT q.*, a.contractor_id FROM contractor_queries q JOIN contractor_apps a ON a.id=q.app_id WHERE q.id=$qid")->fetch();
  // Only the owning contractor may respond, and only to a still-open query.
  if ($q && $resp!=='' && $q['status']==='Open') {
    $own=$pdo->query("SELECT 1 FROM contractors WHERE id=".(int)$q['contractor_id']." AND login_user=".$pdo->quote($u['username']))->fetchColumn();
    if ($own) {
      $pdo->prepare("UPDATE contractor_queries SET status='Responded', response_text=?, responded_on=CURDATE() WHERE id=?")->execute([$resp,$qid]);
      $pdo->prepare("UPDATE contractor_apps SET status='Under Process' WHERE id=? AND status='Query Raised'")->execute([$q['app_id']]);
      add_audit($pdo,'contractor_app',(int)$q['app_id'],'Query response submitted','Contractor',$q['raised_role'],$actor,$resp);
      flash('Response submitted to the registering authority.');
    }
  }
  header('Location: index.php'); exit;
}
```

- [ ] **Step 2: Show open queries to the contractor**

In `app/contractor/index.php`, in the `$view==='contractor'` branch where the contractor's applications are loaded (after `$apps=$st->fetchAll();`), load their open queries:

```php
  $myq=$pdo->query("SELECT q.* FROM contractor_queries q JOIN contractor_apps a ON a.id=q.app_id JOIN contractors c ON c.id=a.contractor_id WHERE c.login_user=".$pdo->quote($u['username'])." AND q.status='Open' ORDER BY q.id DESC")->fetchAll();
```

Then in the contractor view's HTML (inside `<?php if($view==='contractor'): ?>` … add near the top of that block, above the applications list):

```php
  <?php if (!empty($myq)): ?>
    <div class="card p-6 mb-6 border-l-4 border-amber-400">
      <h2 class="font-display text-lg font-semibold text-ink mb-3">⚠ <?= is_hi()?'विभाग से प्रश्न':'Queries from the Department' ?></h2>
      <?php foreach ($myq as $q): ?>
        <div class="border border-slate-100 rounded-xl p-4 mb-3">
          <p class="text-sm text-slate-700"><?= e($q['query_text']) ?></p>
          <form method="post" class="flex flex-wrap gap-2 mt-3 items-end">
            <input type="hidden" name="query_id" value="<?= $q['id'] ?>">
            <input name="response_text" required placeholder="<?= is_hi()?'अपना उत्तर लिखें…':'Type your response…' ?>" class="flex-1 min-w-[200px] border border-slate-300 rounded-xl px-3 py-2.5 text-sm">
            <button name="action" value="respond_query" class="btn-acc font-semibold px-4 py-2.5 rounded-xl text-sm"><?= is_hi()?'उत्तर भेजें':'Submit Response' ?></button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
```

- [ ] **Step 3: Guard Forward in the applications inbox**

In `app/contractor/applications.php`, where the officer rows are built, compute open-query counts once after `$apps` is loaded (officer branch):

```php
  $openByApp=[];
  foreach ($pdo->query("SELECT app_id, COUNT(*) n FROM contractor_queries WHERE status<>'Resolved' GROUP BY app_id") as $r) $openByApp[(int)$r['app_id']]=(int)$r['n'];
```

Then, in the row form, replace the existing Forward button block so it only shows when forwarding is allowed; otherwise show a held note. Change the `<?php if($a['stage']==='EIC'): ?> … <?php else: ?> <button name="action" value="forward" …>Forward</button> <?php endif; ?>` so the `else` branch reads:

```php
          <?php else: ?>
            <?php if (contractor_can_forward($a, $openByApp[$a['id']] ?? 0)): ?>
              <button name="action" value="forward" class="btn-acc text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'अग्रेषित':'Forward' ?> →</button>
            <?php else: ?>
              <span class="text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-3 py-1.5"><?= is_hi()?'प्रश्न लंबित — अग्रेषण रोका':'Query pending — forwarding held' ?></span>
            <?php endif; ?>
          <?php endif; ?>
```

Also harden the server-side `forward` permission in the same file's POST handler so the guard cannot be bypassed. In the `$permit` array, change the `'forward'` line to also require no open query:

```php
      'forward' => contractor_next_stage($stage)!==null && $role===$stage && !in_array($app['status'],['Approved','Rejected','Query Raised'],true)
                   && (int)$pdo->query("SELECT COUNT(*) FROM contractor_queries WHERE app_id=$aid AND status<>'Resolved'")->fetchColumn()===0,
```

- [ ] **Step 4: Verify the pure suite still passes**

Run: `php tests/run.php`
Expected: PASS — `Passed: 74  Failed: 0`.

- [ ] **Step 5: Manual verification (browser)**

Rebuild via `setup.php`. (1) As an **officer**, open `scrutiny.php?app_id=3` and confirm the app is held (Query Raised) with one open query; in **Applications**, app #3 shows "Query pending — forwarding held". (2) Log in as the **contractor** (`contractor`) for firm WRD/REG/3/0451 — note the seeded open query is on app #3 (a *new* applicant, no contractor link), so to see the contractor flow, raise a fresh query against the contractor's own app from the officer side first, then respond as the contractor and confirm the app returns to "Under Process" and the officer can now Forward.

- [ ] **Step 6: Commit**

```bash
git add app/contractor/index.php app/contractor/applications.php
git commit -m "feat(contractor): contractor query response + forward guard on open queries"
```

---

### Task 5: Screen-6 status breakdown strip on the registry dashboard

**Files:**
- Modify: `app/contractor/index.php` (registry view: add a breakdown strip using `contractor_app_breakdown`)

**Interfaces:**
- Consumes: `contractor_app_breakdown($apps)` (Task 1), the registry `$apps` already loaded in `index.php`.

- [ ] **Step 1: Compute the breakdown**

In `app/contractor/index.php`, after `$k=contractor_kpis($apps,$contractors);` add:

```php
$bd = contractor_app_breakdown($apps);
```

- [ ] **Step 2: Render the strip in the registry view**

In `app/contractor/index.php`, inside `<?php if($view==='registry'): ?>`, immediately after the existing KPI grid `</div>`, add:

```php
<!-- Screen 6: scrutiny pipeline breakdown -->
<div class="card p-5 mb-6">
  <div class="flex items-center gap-2 mb-4">
    <span class="h-5 w-1.5 rounded bg-brand" aria-hidden="true"></span>
    <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'जांच पाइपलाइन':'Scrutiny Pipeline' ?></h2>
  </div>
  <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 text-center">
    <?php foreach ([
        [is_hi()?'नए':'New', $bd['new'], 'text-sky-700'],
        [is_hi()?'सत्यापन':'Verifying', $bd['verifying'], 'text-indigo-700'],
        [is_hi()?'अनुमोदन हेतु':'Approval Pending', $bd['approval_pending'], 'text-violet-700'],
        [is_hi()?'प्रश्न':'Query Raised', $bd['query'], 'text-amber-700'],
        [is_hi()?'स्वीकृत':'Approved', $bd['approved'], 'text-emerald-700'],
        [is_hi()?'अस्वीकृत':'Rejected', $bd['rejected'], 'text-rose-700'],
      ] as $cell): ?>
      <div class="rounded-xl bg-slate-50 py-3">
        <div class="text-2xl font-display font-bold <?= $cell[2] ?>"><?= (int)$cell[1] ?></div>
        <div class="text-[11px] text-slate-500 mt-0.5"><?= e($cell[0]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
```

- [ ] **Step 3: Verify the pure suite still passes**

Run: `php tests/run.php`
Expected: PASS — `Passed: 74  Failed: 0`.

- [ ] **Step 4: Manual verification (browser)**

Rebuild via `setup.php`, log in as an officer, and on the registry dashboard confirm the six-cell strip shows counts consistent with the seed (with app #3 held: New 0, Verifying 2, Approval Pending 1, Query Raised 1, Approved 0, Rejected 0).

- [ ] **Step 5: Commit**

```bash
git add app/contractor/index.php
git commit -m "feat(contractor): Screen-6 scrutiny pipeline breakdown strip"
```

---

## Self-Review

**Spec coverage:**
- Screen 8 Scoring Engine → Task 1 (`contractor_score`) + Task 3 (gauges). ✓
- Screen 6 finish (breakdown + per-app review with docs) → Task 5 (strip) + Task 3 (scrutiny page). ✓
- Screen 7 Query Management full round-trip → Task 2 (table/seed) + Task 3 (raise/resolve) + Task 4 (contractor respond, forward guard, audit). ✓
- Query lifecycle (Open→Responded→Resolved; app held at Query Raised) → Tasks 3 & 4. ✓
- Audit trail on every transition → `add_audit` calls in Tasks 3 & 4. ✓
- Tests for score/breakdown/can_forward → Task 1. ✓
- Out-of-scope screens (9/12/13/14/15) → not included. ✓

**Placeholder scan:** No TBD/TODO; every code step shows complete code; manual-verification steps name exact routes and expected counts.

**Type consistency:** `contractor_score` keys (`experience/financial/compliance/overall/band`) are produced in Task 1 and consumed verbatim in Task 3. `contractor_app_breakdown` keys (`new/verifying/approval_pending/query/approved/rejected`) match between Task 1 and Task 5. `contractor_can_forward($app, int $openQueries)` signature matches its Task 4 call sites. Query statuses (`Open/Responded/Resolved`) and app status `Query Raised` are used consistently across Tasks 2–4.
