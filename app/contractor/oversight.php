<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo = db();
$today = date('Y-m-d');
$contractors = $pdo->query("SELECT id,name,name_hi,class,district,status,valid_upto FROM contractors")->fetchAll();
$apps = $pdo->query("SELECT type,fee,fee_paid,applied_on,contractor_id,status FROM contractor_apps")->fetchAll();

$total = count($contractors);
$active = 0; $renewalsDue = 0;
foreach ($contractors as $c) {
    if (contractor_effective_status($c, $today) === 'Active') $active++;
    // due if valid within next 60 days and not already expired
    if (!empty($c['valid_upto']) && $c['valid_upto'] >= $today && $c['valid_upto'] <= date('Y-m-d', strtotime('+60 days'))) $renewalsDue++;
}
$pending = 0;
foreach ($apps as $a) if (!in_array($a['status'], ['Approved','Rejected'], true)) $pending++;
$k = contractor_revenue_kpis($apps);
$roll = contractor_district_rollup($contractors, $apps);

// Per-district drill payload: count, revenue, and the firm list.
$firmsByDist = [];
foreach ($contractors as $c) {
    $d = $c['district'] ?? ''; if ($d==='') continue;
    $firmsByDist[$d][] = ['name'=>(is_hi() ? ($c['name_hi'] ?: $c['name']) : $c['name']), 'class'=>$c['class'], 'status'=>contractor_effective_status($c, $today)];
}
$distPayload = [];
foreach ($roll as $d=>$v) $distPayload[$d] = ['count'=>$v['count'], 'revenue'=>round($v['revenue']), 'firms'=>array_slice($firmsByDist[$d] ?? [], 0, 12)];
$cr = fn(float $v): string => '₹' . number_format($v / 10000000, 2) . ' Cr';

set_app_context('contractor');
app_require_access('oversight');
$LAYOUT='app'; $ACTIVE='oversight'; $PAGE_TITLE='Command Centre';
$EXTRA_HEAD = '<link rel="stylesheet" href="' . base_url('assets/vendor/leaflet/leaflet.css') . '">'
            . '<script src="' . base_url('assets/vendor/leaflet/leaflet.js') . '"></script>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'कमांड सेंटर':'Command Centre' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'सचिव / प्रधान अभियंता — राज्यव्यापी ठेकेदार दृष्टि':'Secretary / Chief Engineer — statewide contractor view' ?></p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
  <?php foreach ([
      [is_hi()?'कुल ठेकेदार':'Total Contractors', (string)$total, 'text-ink'],
      [is_hi()?'सक्रिय':'Active', (string)$active, 'text-emerald-700'],
      [is_hi()?'लंबित अनुमोदन':'Pending Approvals', (string)$pending, 'text-amber-700'],
      [is_hi()?'राजस्व':'Revenue Collected', $cr($k['total']), 'text-sky-700'],
      [is_hi()?'नवीनीकरण देय':'Renewals Due', (string)$renewalsDue, 'text-rose-700'],
    ] as $kpi): ?>
    <div class="card p-5">
      <div class="text-2xl font-display font-bold <?= $kpi[2] ?>"><?= e($kpi[1]) ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= e($kpi[0]) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिलावार ठेकेदार वितरण':'District-wise Contractor Distribution' ?></h2>
    <div id="distmap" class="rounded-xl overflow-hidden" style="height:460px"></div>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिला विवरण':'District Detail' ?></h2>
    <p id="drillHint" class="text-sm text-slate-400"><?= is_hi()?'विवरण हेतु किसी जिले पर क्लिक करें।':'Click a district for its firms.' ?></p>
    <div id="drill" class="hidden">
      <div class="font-semibold text-ink mb-2" id="drillName"></div>
      <div id="drillBody" class="space-y-1.5"></div>
    </div>
  </div>
</div>

<script>
window.OV_DIST = <?= json_encode($distPayload, JSON_UNESCAPED_UNICODE) ?>;
window.OV_GEO  = <?= json_encode(base_url('assets/geo/jharkhand-districts.geojson')) ?>;
(function(){
  var counts = Object.keys(window.OV_DIST).map(function(d){return window.OV_DIST[d].count;});
  var maxC = counts.length ? Math.max.apply(null, counts) : 1;
  function shade(c){ if(!c) return '#e2e8f0'; var t=c/maxC; return t>0.66?'#06314a':(t>0.33?'#0E7C86':'#7dd3c0'); }
  var map = L.map('distmap',{scrollWheelZoom:false,attributionControl:false}).setView([23.6,85.4],7);
  var drill=document.getElementById('drill'), hint=document.getElementById('drillHint'),
      dName=document.getElementById('drillName'), dBody=document.getElementById('drillBody');
  function show(name){
    var v=window.OV_DIST[name]; if(!v) return;
    drill.classList.remove('hidden'); hint.classList.add('hidden');
    dName.textContent=name+' — '+v.count+' firms · ₹'+(v.revenue/100000).toFixed(1)+'L';
    dBody.innerHTML=(v.firms||[]).map(function(f){
      return '<div class="rounded-lg border border-slate-100 p-2"><div class="font-medium text-slate-700">'+f.name+'</div><div class="text-[11px] text-slate-400">Class '+f.class+' · '+f.status+'</div></div>';
    }).join('')||'<div class="text-xs text-slate-400">No firms.</div>';
  }
  fetch(window.OV_GEO).then(function(r){return r.json();}).then(function(geo){
    L.geoJSON(geo,{
      style:function(f){ var v=window.OV_DIST[f.properties.district];
        return {color:'#fff',weight:1,fillColor:shade(v?v.count:0),fillOpacity:.7}; },
      onEachFeature:function(f,layer){ var nm=f.properties.district;
        layer.bindTooltip(nm + (window.OV_DIST[nm] ? ' — '+window.OV_DIST[nm].count+' firms' : ' — 0'));
        layer.on('click',function(){ show(nm); });
      }
    }).addTo(map);
  }).catch(function(){});
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
