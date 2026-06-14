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
