<?php
/**
 * Water Availability Analytics & CEO Dashboard (RFP §8.2.9-8.2.10, storyboard Screens 7 & 10).
 * District/source availability, trend charts (Chart.js, vendored), and a colour-coded
 * Jharkhand district map (Leaflet + bundled GeoJSON) with click-to-drill-down.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();

set_app_context('allocation');
app_require_access('analytics');   // officers / leadership only

$pdo = db();
$sources = $pdo->query("SELECT * FROM water_sources")->fetchAll();
$allocs  = $pdo->query("SELECT app_no,applicant,source_name,quantity_mld,status,district FROM allocations")->fetchAll();

// ---- Aggregates ----
$totCap = 0; $totAlloc = 0;
foreach ($sources as $s) { $totCap += (float)$s['total_capacity_mld']; $totAlloc += (float)$s['allocated_mld']; }
$overallUtil = $totCap > 0 ? round($totAlloc / $totCap * 100, 1) : 0;
$licensed = 0; foreach ($allocs as $a) if ($a['status'] === 'Approved') $licensed++;

// District rollup (sources + allocations), keyed by district.
$dist = [];
foreach ($sources as $s) {
    $d = $s['district'] ?: 'Unspecified';
    $dist[$d] ??= ['total'=>0,'allocated'=>0,'sources'=>[],'allocations'=>[]];
    $dist[$d]['total'] += (float)$s['total_capacity_mld'];
    $dist[$d]['allocated'] += (float)$s['allocated_mld'];
    $dist[$d]['sources'][] = ['name'=>$s['name'],'type'=>$s['type'],'util'=>allocation_utilisation($s),'headroom'=>allocation_headroom($s)];
}
foreach ($allocs as $a) {
    $d = $a['district'] ?: 'Unspecified';
    $dist[$d] ??= ['total'=>0,'allocated'=>0,'sources'=>[],'allocations'=>[]];
    $dist[$d]['allocations'][] = ['app_no'=>$a['app_no'],'applicant'=>$a['applicant'],'qty'=>(float)$a['quantity_mld'],'status'=>$a['status']];
}
foreach ($dist as $d=>&$v) { $v['util'] = $v['total'] > 0 ? round($v['allocated']/$v['total']*100,1) : 0; }
unset($v);
uasort($dist, fn($a,$b)=>$b['util']<=>$a['util']);

// Source utilisation series (sorted desc) for the bar chart.
$srcSeries = [];
foreach ($sources as $s) $srcSeries[] = ['name'=>$s['name'],'util'=>allocation_utilisation($s),'color'=>allocation_util_tier(allocation_utilisation($s))['color']];
usort($srcSeries, fn($a,$b)=>$b['util']<=>$a['util']);

set_app_context('allocation');
$LAYOUT='app'; $ACTIVE='analytics'; $PAGE_TITLE='Water Analytics';
$EXTRA_HEAD = '<link rel="stylesheet" href="'.base_url('assets/vendor/leaflet/leaflet.css').'">'
            . '<script src="'.base_url('assets/vendor/leaflet/leaflet.js').'"></script>'
            . '<script src="'.base_url('assets/vendor/chartjs/chart.umd.js').'"></script>'
            . '<style>#distmap{height:460px;border-radius:1rem;background:#eef4f7;z-index:0}</style>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-5">
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'जल उपलब्धता विश्लेषिकी':'Water Availability Analytics' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'राज्यव्यापी स्रोत क्षमता, आवंटन भार एवं जिलावार उपयोग':'Statewide source capacity, allocation load & district-wise utilisation' ?></p>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
  <?php foreach([
    [is_hi()?'कुल क्षमता':'Total Capacity', number_format($totCap).' MLD','text-ink'],
    [is_hi()?'आवंटित':'Allocated', number_format($totAlloc).' MLD','text-cyan-700'],
    [is_hi()?'समग्र उपयोग':'Overall Utilisation', $overallUtil.'%', $overallUtil>90?'text-rose-700':($overallUtil>=70?'text-amber-700':'text-emerald-700')],
    [is_hi()?'जल स्रोत':'Water Sources', (string)count($sources),'text-ink'],
    [is_hi()?'जारी लाइसेंस':'Licences Issued', (string)$licensed,'text-emerald-700'],
  ] as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-2xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- District map + drilldown -->
  <div class="lg:col-span-2 card p-4">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिलावार आवंटन भार':'District-wise Allocation Load' ?></h2>
    <div id="distmap"></div>
    <div id="drill" class="mt-3 hidden">
      <div class="flex items-center justify-between">
        <h3 id="drillName" class="font-semibold text-ink"></h3>
        <span id="drillUtil" class="text-xs font-bold px-2 py-0.5 rounded-full"></span>
      </div>
      <div id="drillBody" class="grid sm:grid-cols-2 gap-3 mt-2 text-sm"></div>
    </div>
    <p id="drillHint" class="text-xs text-slate-400 mt-3 text-center"><?= is_hi()?'विवरण हेतु किसी जिले पर क्लिक करें।':'Click a district for drill-down.' ?></p>
  </div>

  <!-- District availability bars -->
  <div class="card p-4">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिलावार उपयोग':'District Utilisation' ?></h2>
    <div class="space-y-2.5 max-h-[460px] overflow-y-auto">
      <?php foreach($dist as $name=>$v): $tier=allocation_util_tier($v['util']); ?>
        <div>
          <div class="flex items-center justify-between text-xs mb-1">
            <span class="font-medium text-slate-700"><?= e($name) ?></span>
            <span class="font-semibold" style="color:<?= e($tier['color']) ?>"><?= rtrim(rtrim(number_format($v['util'],1),'0'),'.') ?>%</span>
          </div>
          <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full" style="width:<?= min(100,$v['util']) ?>%;background:<?= e($tier['color']) ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Charts -->
<div class="grid lg:grid-cols-2 gap-6 mt-6">
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'स्रोत उपयोग':'Source Utilisation' ?></h2>
    <canvas id="srcChart" height="220"></canvas>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'क्षमता बनाम आवंटन':'Capacity vs Allocated' ?></h2>
    <canvas id="capChart" height="220"></canvas>
  </div>
</div>

<script>
window.AN_SRC = <?= json_encode($srcSeries, JSON_UNESCAPED_UNICODE) ?>;
window.AN_DIST = <?= json_encode($dist, JSON_UNESCAPED_UNICODE) ?>;
window.AN_TOTALS = {cap:<?= $totCap ?>, alloc:<?= $totAlloc ?>};
window.AN_GEO = <?= json_encode(base_url('assets/geo/jharkhand-districts.geojson')) ?>;
(function(){
  function tierColor(u){ return u>90?'#dc2626':(u>=70?'#d97706':'#059669'); }

  // ---- District map ----
  var map = L.map('distmap',{scrollWheelZoom:false,attributionControl:false}).setView([23.6,85.4],7);
  var drill=document.getElementById('drill'), dHint=document.getElementById('drillHint'),
      dName=document.getElementById('drillName'), dUtil=document.getElementById('drillUtil'), dBody=document.getElementById('drillBody');
  function showDistrict(name){
    var v=window.AN_DIST[name]; if(!v) { return; }
    drill.classList.remove('hidden'); dHint.classList.add('hidden');
    dName.textContent=name+' ('+v.util+'% utilised)';
    var c=tierColor(v.util); dUtil.textContent=v.util+'%';
    dUtil.style.background=c+'22'; dUtil.style.color=c;
    var src=(v.sources||[]).map(function(s){return '<div class="rounded-lg border border-slate-100 p-2"><div class="font-medium text-slate-700">'+s.name+'</div><div class="text-[11px] text-slate-400">'+s.type+' · '+s.util+'% used · '+s.headroom+' MLD free</div></div>';}).join('')||'<div class="text-xs text-slate-400">No sources mapped.</div>';
    var al=(v.allocations||[]).map(function(a){return '<div class="rounded-lg border border-slate-100 p-2"><div class="font-medium text-slate-700">'+a.applicant+'</div><div class="text-[11px] text-slate-400 font-mono">'+a.app_no+' · '+a.qty+' MLD · '+a.status+'</div></div>';}).join('')||'<div class="text-xs text-slate-400">No applications.</div>';
    dBody.innerHTML='<div><div class="text-[11px] uppercase tracking-wide text-slate-400 mb-1 font-semibold">Sources</div><div class="space-y-1.5">'+src+'</div></div>'
                   +'<div><div class="text-[11px] uppercase tracking-wide text-slate-400 mb-1 font-semibold">Applications</div><div class="space-y-1.5">'+al+'</div></div>';
  }
  fetch(window.AN_GEO).then(function(r){return r.json();}).then(function(geo){
    L.geoJSON(geo,{
      style:function(f){
        var d=window.AN_DIST[f.properties.district];
        return d ? {color:'#fff',weight:1,fillColor:tierColor(d.util),fillOpacity:.65}
                 : {color:'#cbd5e1',weight:1,fillColor:'#e2e8f0',fillOpacity:.35};
      },
      onEachFeature:function(f,layer){
        var nm=f.properties.district;
        layer.bindTooltip(nm + (window.AN_DIST[nm] ? ' — '+window.AN_DIST[nm].util+'%' : ''));
        layer.on('click',function(){ if(window.AN_DIST[nm]) showDistrict(nm); });
      }
    }).addTo(map);
  }).catch(function(){});

  // ---- Charts ----
  new Chart(document.getElementById('srcChart'),{
    type:'bar',
    data:{ labels:window.AN_SRC.map(function(s){return s.name;}),
      datasets:[{ label:'Utilisation %', data:window.AN_SRC.map(function(s){return s.util;}),
        backgroundColor:window.AN_SRC.map(function(s){return s.color;}) }] },
    options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{max:100,ticks:{callback:function(v){return v+'%';}}}} }
  });
  new Chart(document.getElementById('capChart'),{
    type:'doughnut',
    data:{ labels:['Allocated','Available'],
      datasets:[{ data:[window.AN_TOTALS.alloc, Math.max(0,window.AN_TOTALS.cap-window.AN_TOTALS.alloc)],
        backgroundColor:['#0891b2','#cbd5e1'] }] },
    options:{ plugins:{legend:{position:'bottom'}}, cutout:'62%' }
  });
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
