<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$view = ppms_role_view($role);
$myDiv = (int)($u['division_id'] ?? 0);

// Division roles see only their division's projects; oversight/finance see all.
$scopeDiv = in_array($view, ['field','division'], true) && $myDiv > 0;
if ($scopeDiv) {
    $st = $pdo->prepare("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id WHERE p.division_id=? ORDER BY p.physical_pct DESC");
    $st->execute([$myDiv]); $projects = $st->fetchAll();
} else {
    $projects = $pdo->query("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id ORDER BY p.physical_pct DESC")->fetchAll();
}
$reqs = $pdo->query("SELECT id,req_no,status,amount_requested,allocated_amount FROM fund_requisitions ORDER BY id DESC")->fetchAll();
$progress = $pdo->query("SELECT pu.id,pu.status,p.name project_name FROM progress_updates pu JOIN projects p ON p.id=pu.project_id ORDER BY pu.id DESC")->fetchAll();

$k = ppms_kpis($projects);
$f = ppms_fund_kpis($reqs);
$tasks = ppms_pending_actions($role, $reqs, $progress);

// Map data for GIS (oversight only)
$mapData = array_map(fn($p)=>[
  'name'=>is_hi()?($p['name_hi']?:$p['name']):$p['name'],'lat'=>(float)$p['lat'],'lng'=>(float)$p['lng'],
  'status'=>$p['status'],'phys'=>(int)$p['physical_pct'],'fin'=>(int)$p['financial_pct'],
  'amt'=>inr((float)$p['sanctioned_amount']),'div'=>$p['divn']
], $projects);

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Command Centre';
if ($view === 'oversight') {
  $EXTRA_HEAD = '
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
}
require __DIR__ . '/../../includes/header.php';

$viewLabel = [
  'oversight'=>is_hi()?'राज्य कमांड सेंटर':'State Command Centre',
  'division' =>is_hi()?'प्रमंडल डैशबोर्ड':'Division Dashboard',
  'field'    =>is_hi()?'क्षेत्र प्रगति डैशबोर्ड':'Field Progress Dashboard',
  'finance'  =>is_hi()?'निधि निर्गत डैशबोर्ड':'Fund Release Dashboard',
][$view];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= e($viewLabel) ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?><?= $scopeDiv?' · '.e($projects[0]['divn'] ?? ''):'' ?></p>
  </div>
  <span class="text-xs text-slate-500 bg-white border border-slate-200 rounded-full px-3 py-1.5">● <?= is_hi()?'लाइव डेटा':'Live data' ?> · <?= date('d M Y, H:i') ?></span>
</div>

<!-- KPI row (PPMS-scoped) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $kpis = $view==='finance' ? [
    [is_hi()?'निर्गत राशि':'Funds Released', inr($f['released_amount']), 'text-emerald-700'],
    [is_hi()?'वित्त समीक्षा हेतु':'Awaiting Finance', (string)$f['under_finance'], 'text-amber-700'],
    [is_hi()?'निर्गत हेतु तैयार':'Ready to Release', (string)$f['pending_release'], 'text-sky-700'],
    [is_hi()?'कुल माँगें':'Total Requisitions', (string)$f['count'], 'text-ink'],
  ] : [
    [is_hi()?'स्वीकृत परिव्यय':'Sanctioned Outlay', inr($k['sanctioned']), 'text-ink'],
    [is_hi()?'उपयोगिता':'Utilisation', $k['utilisation'].'%', 'text-emerald-700'],
    [is_hi()?'औसत भौतिक प्रगति':'Avg Physical Progress', $k['avg_physical'].'%', 'text-sky-700'],
    [is_hi()?'जोखिम पर परियोजनाएँ':'Projects at Risk', (string)$k['at_risk'], 'text-rose-700'],
  ];
  foreach ($kpis as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Primary panel -->
  <div class="lg:col-span-2 space-y-6">
    <?php if ($view==='oversight'): ?>
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'परियोजना भू-मानचित्र (जीआईएस)':'Project Geo-Monitoring (GIS)' ?></h2>
          <div class="flex gap-3 text-[11px]">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>On Track</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Delayed</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>Critical</span>
          </div>
        </div>
        <div id="map" class="h-[360px] rounded-xl overflow-hidden ring-1 ring-slate-200 z-0"></div>
      </div>
      <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'स्थिति-वार परियोजनाएँ':'Projects by Status' ?></h2>
        <canvas id="statusChart" height="110"></canvas>
      </div>
    <?php else: ?>
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-display text-lg font-semibold text-ink"><?= $scopeDiv?(is_hi()?'मेरे प्रमंडल की परियोजनाएँ':'Projects in My Division'):(is_hi()?'परियोजनाएँ':'Projects') ?></h2>
          <a href="<?= base_url('app/ppms/projects.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
            <tr><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'भौतिक':'Physical' ?></th><th class="text-left px-4 py-3">Status</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach (array_slice($projects,0,8) as $p): ?>
              <tr class="hover:bg-paper cursor-pointer" onclick="location.href='<?= base_url('app/ppms/projects.php') ?>?id=<?= $p['id'] ?>'">
                <td class="px-4 py-3 font-medium text-slate-800"><?= bi($p['name'],$p['name_hi']) ?></td>
                <td class="px-4 py-3 w-40"><div class="flex items-center gap-2"><div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full" style="width:<?= (int)$p['physical_pct'] ?>%;background:<?= e($APP['accent']) ?>"></div></div><span class="text-xs font-semibold text-slate-600"><?= (int)$p['physical_pct'] ?>%</span></div></td>
                <td class="px-4 py-3"><?= badge($p['status']) ?></td>
              </tr>
            <?php endforeach; ?>
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
          <a href="<?= base_url('app/ppms/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
            <div class="min-w-0"><p class="text-sm font-medium text-slate-700 truncate"><?= e($tk['label']) ?></p><?php if($tk['meta']): ?><p class="text-xs text-slate-400"><?= inr((float)$tk['meta']) ?></p><?php endif; ?></div>
            <?= badge($tk['status']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm">
        <div class="text-4xl mb-2">✓</div>
        <?= is_hi()?'इस भूमिका हेतु कोई लंबित कार्य नहीं।':'No pending tasks for this role.' ?><br>
        <span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे)।':'Switch role (bottom-left) to see other workflows.' ?></span>
      </div>
    <?php endif; ?>
    <a href="<?= base_url('app/ppms/reports.php') ?>" class="block text-center mt-4 text-sm font-semibold hover:underline" style="color:<?= e($APP['accent']) ?>"><?= t('reports') ?> →</a>
  </div>
</div>

<?php if ($view==='oversight'): ?>
<script>
const PROJECTS = <?= json_encode($mapData, JSON_UNESCAPED_UNICODE) ?>;
const BYSTATUS = <?= json_encode($k['by_status'], JSON_UNESCAPED_UNICODE) ?>;
const map = L.map('map', {scrollWheelZoom:false}).setView([23.6, 85.3], 7);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18, attribution:'© OpenStreetMap'}).addTo(map);
const colour = {'On Track':'#10b981','Delayed':'#f59e0b','Critical':'#ef4444'};
PROJECTS.forEach(p=>{
  L.circleMarker([p.lat,p.lng],{radius:9,color:'#fff',weight:2,fillColor:colour[p.status]||'#64748b',fillOpacity:.95})
   .addTo(map)
   .bindPopup(`<b>${p.name}</b><br>${p.div}<br>Status: <b style="color:${colour[p.status]}">${p.status}</b><br>Physical: ${p.phys}% · Financial: ${p.fin}%<br>Outlay: ${p.amt}`);
});
new Chart(document.getElementById('statusChart'),{
  type:'bar',
  data:{labels:Object.keys(BYSTATUS),datasets:[{label:'Projects',data:Object.values(BYSTATUS),
    backgroundColor:['#10b981','#f59e0b','#ef4444','#0ea5e9','#6366f1']}]},
  options:{plugins:{legend:{display:false}},scales:{y:{ticks:{stepSize:1}}}}
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
