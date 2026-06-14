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
