<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();
$u = current_user();

// ---- KPIs ----
$sanctioned = (float)$pdo->query('SELECT COALESCE(SUM(sanctioned_amount),0) FROM projects')->fetchColumn();
$spent      = (float)$pdo->query('SELECT COALESCE(SUM(spent_amount),0) FROM projects')->fetchColumn();
$revenue    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Success'")->fetchColumn();
$pendingFR  = (int)$pdo->query("SELECT COUNT(*) FROM fund_requisitions WHERE status IN ('Pending Review','Under Finance Review','Approved by Finance')")->fetchColumn();
$pendingAlloc=(int)$pdo->query("SELECT COUNT(*) FROM allocations WHERE status NOT IN ('Approved')")->fetchColumn();
$openGrv    = (int)$pdo->query("SELECT COUNT(*) FROM grievances WHERE status NOT IN ('Resolved')")->fetchColumn();
$statusCounts = $pdo->query("SELECT status, COUNT(*) c FROM projects GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// ---- Map data ----
$projects = $pdo->query("SELECT p.name,p.name_hi,p.lat,p.lng,p.status,p.physical_pct,p.financial_pct,p.sanctioned_amount,d.name div_name
                         FROM projects p JOIN divisions d ON d.id=p.division_id")->fetchAll();
$mapData = array_map(fn($p)=>[
  'name'=>is_hi()?($p['name_hi']?:$p['name']):$p['name'],'lat'=>(float)$p['lat'],'lng'=>(float)$p['lng'],
  'status'=>$p['status'],'phys'=>(int)$p['physical_pct'],'fin'=>(int)$p['financial_pct'],
  'amt'=>inr((float)$p['sanctioned_amount']),'div'=>$p['div_name']
], $projects);

// ---- Revenue by division ----
$revDiv = $pdo->query("SELECT d.name, COALESCE(SUM(p.amount),0) amt
  FROM divisions d LEFT JOIN payments p ON p.division_id=d.id AND p.status='Success'
  GROUP BY d.id ORDER BY amt DESC")->fetchAll();

// ---- Fund requisition status ----
$frStatus = $pdo->query("SELECT status, COUNT(*) c FROM fund_requisitions GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// ---- Monthly collection (last 6 mo) ----
$monthly = $pdo->query("SELECT DATE_FORMAT(paid_on,'%b %Y') m, SUM(amount) amt FROM payments WHERE status='Success' GROUP BY DATE_FORMAT(paid_on,'%Y-%m') ORDER BY MIN(paid_on)")->fetchAll();

// ---- Pending actions for current role ----
$myTasks = [];
$role = user_role();
if (in_array($role,['EE','SE','CE','EIC'])) {
  foreach ($pdo->query("SELECT req_no,amount_requested,status FROM fund_requisitions WHERE status='Pending Review' LIMIT 5") as $r)
    $myTasks[] = ['Fund requisition '.$r['req_no'], inr((float)$r['amount_requested']), $r['status'], base_url('app/ppms/requisitions.php')];
}
if ($role==='FINANCE') {
  foreach ($pdo->query("SELECT req_no,amount_requested,status FROM fund_requisitions WHERE status='Under Finance Review' LIMIT 5") as $r)
    $myTasks[] = ['Finance review '.$r['req_no'], inr((float)$r['amount_requested']), $r['status'], base_url('app/ppms/requisitions.php')];
}
if (in_array($role,['AE','EE'])) {
  foreach ($pdo->query("SELECT bill_no,total,status FROM bills WHERE status IN ('Pending Verification','Approved') LIMIT 5") as $r)
    $myTasks[] = ['Bill '.$r['bill_no'], inr((float)$r['total']), $r['status'], base_url('app/etariff/index.php')];
}
if ($role==='JE') {
  foreach ($pdo->query("SELECT bill_no,total,status FROM bills WHERE status='Draft' LIMIT 5") as $r)
    $myTasks[] = ['Draft bill '.$r['bill_no'], inr((float)$r['total']), $r['status'], base_url('app/etariff/index.php')];
}

$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Command Centre';
$EXTRA_HEAD = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= t('command_centre') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <span class="text-xs text-slate-500 bg-white border border-slate-200 rounded-full px-3 py-1.5">● <?= is_hi()?'लाइव डेटा':'Live data' ?> · <?= date('d M Y, H:i') ?></span>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $kpis = [
    [is_hi()?'स्वीकृत परिव्यय':'Sanctioned Outlay', inr($sanctioned), 'text-ink', round($spent/$sanctioned*100).'% '.(is_hi()?'उपयोगित':'utilised')],
    [is_hi()?'राजस्व संग्रह':'Revenue Collected', inr($revenue), 'text-emerald-700', is_hi()?'प्रमंडल-वार जमा':'Division-wise credited'],
    [is_hi()?'लंबित स्वीकृतियाँ':'Pending Approvals', (string)($pendingFR+$pendingAlloc), 'text-amber-700', is_hi()?'निधि + आवंटन':'Fund + allocation'],
    [is_hi()?'खुली शिकायतें':'Open Grievances', (string)$openGrv, 'text-rose-700', is_hi()?'एसएलए ट्रैक':'SLA tracked'],
  ];
  foreach ($kpis as $k): ?>
    <div class="card kpi-accent p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $k[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $k[2] ?>"><?= $k[1] ?></div>
      <div class="text-[11px] text-slate-400 mt-1"><?= $k[3] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- GIS map -->
  <div class="lg:col-span-2 card p-5">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'परियोजना भू-मानचित्र (जीआईएस)':'Project Geo-Monitoring (GIS)' ?></h2>
      <div class="flex gap-3 text-[11px]">
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>On Track</span>
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Delayed</span>
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>Critical</span>
      </div>
    </div>
    <div id="map" class="h-[380px] rounded-xl overflow-hidden ring-1 ring-slate-200 z-0"></div>
  </div>

  <!-- Fund requisition doughnut -->
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'निधि माँग स्थिति':'Fund Requisition Status' ?></h2>
    <canvas id="frChart" height="220"></canvas>
  </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 mt-6">
  <!-- Revenue by division -->
  <div class="lg:col-span-2 card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'प्रमंडल-वार राजस्व (जेई-ग्रास)':'Division-wise Revenue (JE-GRAS)' ?></h2>
    <canvas id="revChart" height="120"></canvas>
  </div>

  <!-- My pending actions -->
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'आपकी लंबित कार्रवाई':'Your Pending Actions' ?></h2>
    <?php if ($myTasks): ?>
      <div class="space-y-2">
        <?php foreach ($myTasks as $tk): ?>
          <a href="<?= $tk[3] ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:border-brand hover:bg-brandsoft">
            <div class="min-w-0"><p class="text-sm font-medium text-slate-700 truncate"><?= e($tk[0]) ?></p><p class="text-xs text-slate-400"><?= $tk[1] ?></p></div>
            <?= badge($tk[2]) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm">
        <div class="text-4xl mb-2">✓</div>
        <?= is_hi()?'इस भूमिका हेतु कोई लंबित कार्य नहीं।':'No pending tasks for this role.' ?><br>
        <span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे) और कार्यप्रवाह देखें।':'Switch role (bottom-left) to see workflow tasks.' ?></span>
      </div>
    <?php endif; ?>
    <a href="<?= base_url('app/ppms/reports.php') ?>" class="block text-center mt-4 text-brand text-sm font-semibold hover:underline"><?= t('reports') ?> →</a>
  </div>
</div>

<script>
const PROJECTS = <?= json_encode($mapData, JSON_UNESCAPED_UNICODE) ?>;
const REVDIV   = <?= json_encode($revDiv, JSON_UNESCAPED_UNICODE) ?>;
const FRSTATUS = <?= json_encode($frStatus, JSON_UNESCAPED_UNICODE) ?>;
const MONTHLY  = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;

// ---- Leaflet GIS map ----
const map = L.map('map', {scrollWheelZoom:false}).setView([23.6, 85.3], 7);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18, attribution:'© OpenStreetMap'}).addTo(map);
const colour = {'On Track':'#10b981','Delayed':'#f59e0b','Critical':'#ef4444'};
PROJECTS.forEach(p=>{
  L.circleMarker([p.lat,p.lng],{radius:9,color:'#fff',weight:2,fillColor:colour[p.status]||'#64748b',fillOpacity:.95})
   .addTo(map)
   .bindPopup(`<b>${p.name}</b><br>${p.div}<br>Status: <b style="color:${colour[p.status]}">${p.status}</b><br>Physical: ${p.phys}% · Financial: ${p.fin}%<br>Outlay: ${p.amt}`);
});

const teal='#0E7C86', ink='#06314a';
// ---- Fund requisition doughnut ----
new Chart(document.getElementById('frChart'),{
  type:'doughnut',
  data:{labels:Object.keys(FRSTATUS),datasets:[{data:Object.values(FRSTATUS),
    backgroundColor:['#10b981','#f59e0b','#0ea5e9','#14b8a6','#ef4444','#94a3b8','#6366f1']}]},
  options:{plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}},cutout:'62%'}
});
// ---- Revenue by division bar ----
new Chart(document.getElementById('revChart'),{
  type:'bar',
  data:{labels:REVDIV.map(r=>r.name.replace(/ (Division|Irrigation|Reservoir|Water Ways|Canal).*/,'')),
    datasets:[{label:'Revenue (₹)',data:REVDIV.map(r=>+r.amt),backgroundColor:teal,borderRadius:6}]},
  options:{plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'₹'+(v/100000).toFixed(1)+'L'}}}}
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
