<?php
/**
 * GIS Source Selection (RFP §8.3, storyboard Screen 3).
 * Self-hosted Leaflet + bundled Jharkhand district GeoJSON, no online tiles.
 * Browse mode: explore sources. Pick mode (?pick=1): choose a source to apply against.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();

$pdo = db();
$pick = (int)($_GET['pick'] ?? 0) === 1;
$q    = (float)($_GET['q'] ?? 50);
$district = trim((string)($_GET['district'] ?? ''));

$sources = $pdo->query("SELECT * FROM water_sources ORDER BY name")->fetchAll();
$rec = allocation_recommend_source($district, $q, $sources);
$recId = $rec['id'] ?? 0;

// Shape for JS (adds derived tier/util/headroom).
$js = [];
foreach ($sources as $s) {
    $util = allocation_utilisation($s);
    $tier = allocation_util_tier($util);
    $js[] = [
        'id'=>(int)$s['id'], 'name'=>$s['name'], 'name_hi'=>$s['name_hi'], 'type'=>$s['type'],
        'district'=>$s['district'], 'lat'=>(float)$s['lat'], 'lng'=>(float)$s['lng'],
        'total'=>(float)$s['total_capacity_mld'], 'allocated'=>(float)$s['allocated_mld'],
        'headroom'=>allocation_headroom($s), 'util'=>$util, 'color'=>$tier['color'],
        'tier'=>$tier['label'], 'season'=>$s['season'], 'status'=>$s['status'],
        'recommended'=>((int)$s['id'] === (int)$recId),
    ];
}

set_app_context('allocation');
$LAYOUT='app'; $ACTIVE='map'; $PAGE_TITLE = $pick ? 'Select Water Source' : 'Source Map (GIS)';
$EXTRA_HEAD = '<link rel="stylesheet" href="'.base_url('assets/vendor/leaflet/leaflet.css').'">'
            . '<script src="'.base_url('assets/vendor/leaflet/leaflet.js').'"></script>'
            . '<style>#allocmap{height:560px;border-radius:1rem;background:#eef4f7;z-index:0}'
            . '.src-card.active{box-shadow:0 0 0 2px var(--acc)}</style>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'जल स्रोत मानचित्र':'Water Source Map' ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'जिलावार जल स्रोत · उपलब्ध क्षमता · आवंटन':'District-wise sources · available capacity · allocations' ?> · <span class="text-slate-400">GeoJSON · <?= count($sources) ?> sources</span></p>
  </div>
  <?php if($pick): ?>
    <a href="<?= base_url('app/allocation/index.php') ?>" class="text-sm font-semibold text-slate-500">← <?= is_hi()?'आवेदन पर लौटें':'Back to application' ?></a>
  <?php endif; ?>
</div>

<div class="grid lg:grid-cols-3 gap-5">
  <!-- Map -->
  <div class="lg:col-span-2 card p-3">
    <div id="allocmap"></div>
    <div class="flex flex-wrap items-center gap-4 mt-3 px-1 text-xs text-slate-500">
      <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#059669"></span><?= is_hi()?'उपलब्ध (<70%)':'Available (<70%)' ?></span>
      <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#d97706"></span><?= is_hi()?'मध्यम (70–90%)':'Moderate (70–90%)' ?></span>
      <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#dc2626"></span><?= is_hi()?'गंभीर (>90%)':'Critical (>90%)' ?></span>
    </div>
  </div>

  <!-- Source list -->
  <div class="card p-4 max-h-[600px] overflow-y-auto">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'जल स्रोत':'Water Sources' ?></h2>
    <div class="space-y-2.5">
      <?php foreach ($js as $s): $tier = allocation_util_tier($s['util']); ?>
        <div class="src-card p-3 rounded-xl border border-slate-100 hover:bg-paper cursor-pointer transition" data-src="<?= $s['id'] ?>">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="font-semibold text-slate-800 text-sm truncate"><?= is_hi()?e($s['name_hi']):e($s['name']) ?>
                <?php if($s['recommended']): ?><span class="ml-1 align-middle text-[10px] font-bold text-white px-1.5 py-0.5 rounded-full" style="background:<?= e($APP['accent']) ?>">★ AI</span><?php endif; ?>
              </div>
              <div class="text-[11px] text-slate-400"><?= e($s['type']) ?> · <?= e($s['district']) ?> · <?= e($s['season']) ?></div>
            </div>
            <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full text-white" style="background:<?= e($s['color']) ?>"><?= e($s['tier']) ?></span>
          </div>
          <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full" style="width:<?= min(100,$s['util']) ?>%;background:<?= e($s['color']) ?>"></div>
          </div>
          <div class="flex items-center justify-between mt-1.5 text-[11px] text-slate-500">
            <span><?= rtrim(rtrim(number_format($s['util'],1),'0'),'.') ?>% <?= is_hi()?'उपयोग':'used' ?></span>
            <span class="font-medium text-emerald-700"><?= rtrim(rtrim(number_format($s['headroom'],1),'0'),'.') ?> MLD <?= is_hi()?'शेष':'free' ?></span>
          </div>
          <?php if($pick): ?>
            <a href="<?= base_url('app/allocation/index.php') ?>?source_id=<?= $s['id'] ?>" class="mt-2 block text-center btn-acc rounded-lg py-1.5 text-xs font-semibold"><?= is_hi()?'इस स्रोत हेतु आवेदन':'Apply for this source' ?> →</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
window.ALLOC_SOURCES = <?= json_encode($js, JSON_UNESCAPED_UNICODE) ?>;
window.ALLOC_GEO_URL = <?= json_encode(base_url('assets/geo/jharkhand-districts.geojson')) ?>;
window.ALLOC_PICK = <?= $pick ? 'true' : 'false' ?>;
window.ALLOC_ACCENT = <?= json_encode($APP['accent']) ?>;
(function(){
  var map = L.map('allocmap', { scrollWheelZoom:false, attributionControl:false }).setView([23.6,85.4], 7);
  // District basemap (no online tiles) — bundled GeoJSON only.
  fetch(window.ALLOC_GEO_URL).then(function(r){return r.json();}).then(function(geo){
    L.geoJSON(geo, { style:function(){return {color:'#94a3b8',weight:1,fillColor:'#dbeafe',fillOpacity:.35};} }).addTo(map);
  }).catch(function(){ /* map still works with markers only */ });

  var markers = {};
  window.ALLOC_SOURCES.forEach(function(s){
    var m = L.circleMarker([s.lat, s.lng], {
      radius: s.recommended ? 11 : 8, color: s.recommended ? window.ALLOC_ACCENT : '#fff',
      weight: s.recommended ? 3 : 2, fillColor: s.color, fillOpacity: .95
    }).addTo(map);
    var html = '<div style="min-width:180px">'
      + '<div style="font-weight:700;color:#06314a">'+s.name+ (s.recommended?' <span style="color:'+window.ALLOC_ACCENT+'">★ AI pick</span>':'') +'</div>'
      + '<div style="font-size:11px;color:#64748b">'+s.type+' · '+s.district+' · '+s.season+'</div>'
      + '<div style="margin-top:6px;font-size:12px">Utilisation: <b style="color:'+s.color+'">'+s.util+'%</b></div>'
      + '<div style="font-size:12px">Headroom: <b style="color:#047857">'+s.headroom+' MLD</b></div>'
      + '<div style="font-size:12px;color:#64748b">Allocated '+s.allocated+' / '+s.total+' MLD</div>'
      + (window.ALLOC_PICK ? '<a href="'+<?= json_encode(base_url('app/allocation/index.php')) ?>+'?source_id='+s.id+'" style="display:block;text-align:center;margin-top:8px;background:'+window.ALLOC_ACCENT+';color:#fff;border-radius:8px;padding:6px;font-size:12px;font-weight:600;text-decoration:none">Apply for this source →</a>' : '')
      + '</div>';
    m.bindPopup(html);
    markers[s.id] = m;
    if (s.recommended) m.openPopup();
  });

  // List card ↔ marker sync.
  document.querySelectorAll('.src-card').forEach(function(card){
    card.addEventListener('click', function(e){
      if (e.target.tagName === 'A') return;
      var id = card.getAttribute('data-src'); var m = markers[id];
      document.querySelectorAll('.src-card').forEach(function(c){c.classList.remove('active');});
      card.classList.add('active');
      if (m){ map.setView(m.getLatLng(), 9); m.openPopup(); }
    });
  });
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
