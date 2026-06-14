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
