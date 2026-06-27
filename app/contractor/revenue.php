<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo = db();
$apps = $pdo->query("SELECT type,fee,fee_paid,applied_on,contractor_id FROM contractor_apps")->fetchAll();
$contractors = $pdo->query("SELECT id,district FROM contractors")->fetchAll();

$k   = contractor_revenue_kpis($apps);
$mc  = contractor_monthly_collection($apps);
$roll = contractor_district_rollup($contractors, $apps);
// Top districts by revenue for the bar chart.
arsort($roll);
$topDist = array_slice($roll, 0, 10, true);

$cr = fn(float $v): string => '₹' . number_format($v / 10000000, 2) . ' Cr';

set_app_context('contractor');
app_require_access('revenue');
$LAYOUT='app'; $ACTIVE='revenue'; $PAGE_TITLE='Revenue MIS';
$EXTRA_HEAD = '<script src="' . base_url('assets/vendor/chartjs/chart.umd.js') . '"></script>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'राजस्व एमआईएस':'Revenue MIS' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'पंजीकरण एवं नवीनीकरण शुल्क संग्रह':'Registration & renewal fee collection' ?></p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach ([
      [is_hi()?'कुल संग्रह':'Total Collected', $cr($k['total']), 'text-emerald-700'],
      [is_hi()?'नवीनीकरण राजस्व':'Renewal Revenue', $cr($k['renewal']), 'text-sky-700'],
      [is_hi()?'नया पंजीकरण':'New Registration', $cr($k['new']), 'text-violet-700'],
      [is_hi()?'चालू वित्त वर्ष':'This FY', $cr($k['fy']), 'text-amber-700'],
    ] as $kpi): ?>
    <div class="card p-5">
      <div class="text-2xl font-display font-bold <?= $kpi[2] ?>"><?= e($kpi[1]) ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= e($kpi[0]) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card p-5 mb-6">
  <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'मासिक संग्रह (12 माह)':'Monthly Collection (12 months)' ?></h2>
  <canvas id="monthChart" height="90"></canvas>
</div>

<div class="grid lg:grid-cols-2 gap-6">
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'नया बनाम नवीनीकरण':'New vs Renewal' ?></h2>
    <canvas id="splitChart" height="200"></canvas>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जिलावार राजस्व (शीर्ष 10)':'District-wise Revenue (Top 10)' ?></h2>
    <canvas id="distChart" height="200"></canvas>
  </div>
</div>

<script>
window.RV_MONTHS = <?= json_encode(array_keys($mc)) ?>;
window.RV_MVALS  = <?= json_encode(array_map(fn($v)=>round($v), array_values($mc))) ?>;
window.RV_SPLIT  = <?= json_encode([round($k['new']), round($k['renewal'])]) ?>;
window.RV_DIST   = <?= json_encode(array_map(fn($d)=>round($d['revenue']), $topDist), JSON_UNESCAPED_UNICODE) ?>;
window.RV_DLBL   = <?= json_encode(array_keys($topDist), JSON_UNESCAPED_UNICODE) ?>;
(function(){
  var acc = <?= json_encode($APP['accent']) ?>;
  new Chart(document.getElementById('monthChart'), {
    type:'bar',
    data:{ labels:window.RV_MONTHS, datasets:[{ label:'₹', data:window.RV_MVALS, backgroundColor:acc }] },
    options:{ plugins:{legend:{display:false}}, scales:{y:{ticks:{callback:function(v){return '₹'+(v/100000).toFixed(0)+'L';}}}} }
  });
  new Chart(document.getElementById('splitChart'), {
    type:'doughnut',
    data:{ labels:['New','Renewal'], datasets:[{ data:window.RV_SPLIT, backgroundColor:[acc,'#0ea5e9'] }] }
  });
  new Chart(document.getElementById('distChart'), {
    type:'bar',
    data:{ labels:window.RV_DLBL, datasets:[{ label:'₹', data:window.RV_DIST, backgroundColor:acc }] },
    options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{ticks:{callback:function(v){return '₹'+(v/100000).toFixed(0)+'L';}}}} }
  });
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
