<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db();

// ---- CSV export ----
if (($_GET['export']??'')==='csv') {
  header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="WRD_PPMS_MIS_'.date('Ymd').'.csv"');
  $out=fopen('php://output','w');
  fputcsv($out,['Project','Scheme','Division','Status','Physical %','Financial %','Sanctioned','Spent']);
  foreach($pdo->query("SELECT p.name,s.name sc,d.name dv,p.status,p.physical_pct,p.financial_pct,p.sanctioned_amount,p.spent_amount
    FROM projects p JOIN schemes s ON s.id=p.scheme_id JOIN divisions d ON d.id=p.division_id ORDER BY p.name") as $r)
    fputcsv($out,[$r['name'],$r['sc'],$r['dv'],$r['status'],$r['physical_pct'],$r['financial_pct'],$r['sanctioned_amount'],$r['spent_amount']]);
  fclose($out); exit;
}

$fStatus=$_GET['status']??''; $fDiv=(int)($_GET['div']??0);
$where=[]; $p=[];
if($fStatus){ $where[]='p.status=?'; $p[]=$fStatus; }
if($fDiv){ $where[]='p.division_id=?'; $p[]=$fDiv; }
$wsql=$where?('WHERE '.implode(' AND ',$where)):'';
$st=$pdo->prepare("SELECT p.*,s.name scheme,d.name divn FROM projects p JOIN schemes s ON s.id=p.scheme_id JOIN divisions d ON d.id=p.division_id $wsql ORDER BY p.physical_pct DESC");
$st->execute($p); $rows=$st->fetchAll();
$divs=$pdo->query("SELECT id,name FROM divisions")->fetchAll();

$LAYOUT='app'; $ACTIVE='ppms_reports'; $PAGE_TITLE='Reports & MIS';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= t('reports') ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'भौतिक एवं वित्तीय प्रगति · स्वतः एमआईएस':'Physical & financial progress · auto-generated MIS' ?></p></div>
  <div class="flex gap-2">
    <a href="?export=csv<?= $fStatus?'&status='.urlencode($fStatus):'' ?><?= $fDiv?'&div='.$fDiv:'' ?>" class="border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">⬇ CSV</a>
    <button onclick="print()" class="border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">🖨 PDF</button>
  </div>
</div>

<form class="card p-4 mb-5 flex flex-wrap gap-3 items-end">
  <div><label class="text-xs font-medium text-slate-500"><?= is_hi()?'स्थिति':'Status' ?></label>
    <select name="status" class="mt-1 border border-slate-300 rounded-lg px-3 py-2 text-sm"><option value="">All</option>
      <?php foreach(['On Track','Delayed','Critical'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <div><label class="text-xs font-medium text-slate-500"><?= is_hi()?'प्रमंडल':'Division' ?></label>
    <select name="div" class="mt-1 border border-slate-300 rounded-lg px-3 py-2 text-sm"><option value="0">All</option>
      <?php foreach($divs as $d): ?><option value="<?= $d['id'] ?>" <?= $fDiv===(int)$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <button class="bg-brand text-white rounded-lg px-4 py-2 text-sm font-semibold"><?= t('search') ?></button>
  <a href="reports.php" class="text-sm text-slate-500 px-2 py-2">Reset</a>
</form>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
      <tr><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th><th class="text-left px-4 py-3 hidden md:table-cell">Division</th>
      <th class="text-left px-4 py-3"><?= is_hi()?'भौतिक':'Physical' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'वित्तीय':'Financial' ?></th>
      <th class="text-left px-4 py-3">Status</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($rows as $r): ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><div class="font-medium text-slate-800"><?= bi($r['name'],$r['name_hi']) ?></div><div class="text-xs text-slate-400"><?= e($r['scheme']) ?></div></td>
          <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['divn']) ?></td>
          <td class="px-4 py-3 w-40"><div class="flex items-center gap-2"><div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-brand" style="width:<?= (int)$r['physical_pct'] ?>%"></div></div><span class="text-xs font-semibold text-slate-600"><?= (int)$r['physical_pct'] ?>%</span></div></td>
          <td class="px-4 py-3 w-40"><div class="flex items-center gap-2"><div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500" style="width:<?= (int)$r['financial_pct'] ?>%"></div></div><span class="text-xs font-semibold text-slate-600"><?= (int)$r['financial_pct'] ?>%</span></div></td>
          <td class="px-4 py-3"><?= badge($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3"><?= is_hi()?'रिपोर्ट प्रारूप: PDF · Word · Excel · CSV · स्वचालित मासिक एमआईएस।':'Report formats: PDF · Word · Excel · CSV · auto-scheduled monthly MIS.' ?></p>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
