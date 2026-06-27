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
