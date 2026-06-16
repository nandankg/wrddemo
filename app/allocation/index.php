<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

// Applicant self-application (creates an allocation at stage AE).
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='apply' && $role==='CONSUMER') {
  $cnt=(int)$pdo->query('SELECT COUNT(*) FROM allocations')->fetchColumn()+207;
  $ano=sprintf('WRD/IWA/2526/%03d',$cnt);
  $fee=allocation_annual_fee((float)$_POST['quantity']);
  // Source comes from a water_sources pick; derive type + name from it.
  $src=in_array($_POST['source']??'',['River','Canal','Reservoir','Dam'],true)?$_POST['source']:'River';
  $srcName=trim($_POST['source_name']??'');
  if (!empty($_POST['source_id'])) {
    $ws=$pdo->prepare('SELECT type,name FROM water_sources WHERE id=?'); $ws->execute([(int)$_POST['source_id']]);
    if ($row=$ws->fetch()) { $src=$row['type']; $srcName=$row['name']; }
  }
  $season=in_array($_POST['season']??'',['Perennial','Seasonal'],true)?$_POST['season']:'Perennial';
  $pdo->prepare("INSERT INTO allocations (app_no,applicant,source,source_name,quantity_mld,season,division_id,district,stage,status,gst,annual_fee,applied_on,login_user) VALUES (?,?,?,?,?,?,?,?, 'AE','New',?,?,CURDATE(),?)")
      ->execute([$ano,trim($_POST['applicant']),$src,$srcName,(float)$_POST['quantity'],$season,(int)$_POST['division_id'],trim($_POST['district']),strtoupper(trim($_POST['gst'])),$fee,$u['username']]);
  add_audit($pdo,'allocation',(int)$pdo->lastInsertId(),'Application submitted (SWCS)','Applicant','AE',$actor,'Allocation engine validated source & season.');
  flash("Allocation application $ano submitted."); header('Location: index.php'); exit;
}

$view = allocation_role_view($role);
if ($view==='applicant') {
  $st=$pdo->prepare("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.login_user=? ORDER BY a.id DESC");
  $st->execute([$u['username']]); $rows=$st->fetchAll();
} else {
  $rows=$pdo->query("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id ORDER BY a.id DESC")->fetchAll();
}
$k=allocation_kpis($rows);
$tasks=allocation_pending_actions($role,$rows);
$STAGES=['AE','EE','SE','CE','EIC','SECRETARY'];

set_app_context('allocation');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Allocation Desk';
require __DIR__ . '/../../includes/header.php';
$divs=$pdo->query("SELECT id,name FROM divisions")->fetchAll();
// Water sources for the application picker (with derived utilisation).
$wsRows=$pdo->query("SELECT * FROM water_sources ORDER BY name")->fetchAll();
$preSourceId=(int)($_GET['source_id'] ?? 0);   // arriving from the GIS picker
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= $view==='applicant'?(is_hi()?'मेरा जल आवंटन':'My Water Allocation'):(is_hi()?'आवंटन कार्यालय':'Allocation Desk') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <?php if($view==='applicant'): ?>
    <button onclick="document.getElementById('newAlloc').showModal()" class="btn-acc font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नया आवेदन':'New Application' ?></button>
  <?php endif; ?>
</div>

<?php if($view==='officer'): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach([
    [is_hi()?'प्रक्रियाधीन':'In Process', (string)$k['in_process'], 'text-amber-700'],
    [is_hi()?'जारी लाइसेंस':'Licences Issued', (string)$k['licensed'], 'text-emerald-700'],
    [is_hi()?'रोके गए':'On Hold', (string)$k['on_hold'], 'text-rose-700'],
    [is_hi()?'कुल आवेदन':'Total Applications', (string)$k['total'], 'text-ink'],
  ] as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-5">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'मेरे चरण की लंबित फाइलें':'Files Pending at My Stage' ?></h2>
      <a href="<?= base_url('app/allocation/applications.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
    </div>
    <?php if($tasks): ?>
      <div class="space-y-2">
        <?php foreach($tasks as $tk): ?>
          <a href="<?= base_url('app/allocation/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
            <p class="text-sm font-medium text-slate-700 truncate"><?= e($tk['label']) ?></p><?= badge($tk['status']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm"><div class="text-4xl mb-2">✓</div><?= is_hi()?'आपके चरण पर कोई लंबित फाइल नहीं।':'No files pending at your stage.' ?><br><span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे)।':'Switch role (bottom-left).' ?></span></div>
    <?php endif; ?>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'त्वरित लिंक':'Quick Links' ?></h2>
    <a href="<?= base_url('app/allocation/applications.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📋 <?= is_hi()?'आवेदन इनबॉक्स':'Applications Inbox' ?></span></a>
    <a href="<?= base_url('app/allocation/licences.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper"><span class="font-medium text-slate-700">📜 <?= is_hi()?'जारी लाइसेंस':'Issued Licences' ?></span></a>
  </div>
</div>

<?php else: // ===== applicant portal ===== ?>
<div class="space-y-3">
  <?php foreach($rows as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div><div class="font-display text-lg font-semibold text-ink"><?= e($a['applicant']) ?></div>
          <div class="text-sm text-slate-500"><span class="font-mono text-xs"><?= e($a['app_no']) ?></span> · <?= e($a['source']) ?>: <?= e($a['source_name']) ?> · <?= (float)$a['quantity_mld'] ?> MLD · <?= e($a['season']) ?></div>
          <div class="text-xs text-slate-400 mt-0.5"><?= e($a['divn']) ?> · <?= is_hi()?'वार्षिक शुल्क':'Annual fee' ?> <?= inr((float)$a['annual_fee']) ?></div>
        </div>
        <div class="text-right"><?= badge($a['status']) ?></div>
      </div>
      <div class="flex items-center gap-1 mt-4">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="px-2 h-6 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i&&$a['status']!=='Rejected'?'text-white':'bg-slate-100 text-slate-400') ?>" <?= ($si===$i&&!in_array($a['status'],['Rejected','Approved'],true))?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if($a['status']==='Approved' && $a['license_no']): ?>
        <a href="<?= base_url('app/allocation/licence.php') ?>?id=<?= $a['id'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">📜 <?= is_hi()?'लाइसेंस डाउनलोड करें':'Download Licence' ?> (<?= e($a['license_no']) ?>) →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$rows): ?><div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई आवेदन नहीं। "नया आवेदन" से आरंभ करें।':'No applications yet. Start with "New Application".' ?></div><?php endif; ?>
</div>

<dialog id="newAlloc" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/40">
  <form method="post" class="p-6"><input type="hidden" name="action" value="apply">
    <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'जल आवंटन आवेदन':'Water Allocation Application' ?></h2>
    <div class="space-y-3">
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'आवेदक / उद्योग':'Applicant / Industry' ?></label><input id="aiApplicant" name="applicant" required value="<?= e($u['name']) ?>" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      <div>
        <div class="flex items-center justify-between">
          <label class="text-sm font-medium text-slate-700"><?= is_hi()?'जल स्रोत':'Water Source' ?></label>
          <a href="<?= base_url('app/allocation/map.php') ?>?pick=1" class="text-xs font-semibold" style="color:<?= e($APP['accent']) ?>">🗺️ <?= is_hi()?'मानचित्र पर चुनें':'Pick on map' ?> →</a>
        </div>
        <select id="aiSource" name="source_id" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
          <option value=""><?= is_hi()?'— स्रोत चुनें —':'— Select source —' ?></option>
          <?php foreach($wsRows as $w): $u2=allocation_utilisation($w); ?>
            <option value="<?= $w['id'] ?>" <?= $preSourceId===(int)$w['id']?'selected':'' ?>><?= e($w['name']) ?> (<?= e($w['type']) ?> · <?= rtrim(rtrim(number_format($u2,1),'0'),'.') ?>% <?= is_hi()?'उपयोग':'used' ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'मात्रा (MLD)':'Quantity (MLD)' ?></label><input id="aiQuantity" name="quantity" type="number" step="0.1" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'मौसम':'Season' ?></label>
          <select id="aiSeason" name="season" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>Perennial</option><option>Seasonal</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'प्रमंडल':'Division' ?></label>
          <select id="aiDivision" name="division_id" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($divs as $d):?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach;?></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'ज़िला':'District' ?></label><input id="aiDistrict" name="district" required value="Ranchi" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <div><label class="text-sm font-medium text-slate-700">GSTIN</label><input name="gst" required placeholder="20XXXXX1234X1Z5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>

      <!-- ===== AI assist panel (recommendation · risk · executive summary) ===== -->
      <div id="aiPanel" class="rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-3 py-2 text-xs font-semibold flex items-center gap-2 text-white" style="background:<?= e($APP['accent']) ?>">
          <span>🤖 <?= is_hi()?'एआई सहायक':'AI Assist' ?></span>
          <span class="text-[10px] font-normal opacity-80"><?= is_hi()?'स्रोत/मात्रा भरते ही अपडेट':'updates as you fill source / quantity' ?></span>
        </div>
        <div class="p-3 space-y-2.5 text-sm">
          <div id="aiHint" class="text-slate-400 text-xs py-2 text-center"><?= is_hi()?'मात्रा एवं स्रोत दर्ज करें — एआई अनुशंसा देगा।':'Enter quantity & source — the engine will recommend.' ?></div>
          <div id="aiBody" class="hidden space-y-2.5">
            <div class="flex items-start gap-2">
              <span class="text-xs text-slate-500 mt-0.5 shrink-0"><?= is_hi()?'अनुशंसित':'Suggested' ?>:</span>
              <span id="aiRec" class="text-sm font-semibold text-slate-800"></span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500"><?= is_hi()?'जोखिम':'Risk' ?>:</span>
              <span id="aiRisk" class="text-[11px] font-bold px-2 py-0.5 rounded-full"></span>
              <span id="aiRiskWhy" class="text-[11px] text-slate-400 truncate"></span>
            </div>
            <button type="button" id="aiSummaryBtn" class="text-xs font-semibold px-3 py-1.5 rounded-lg border" style="border-color:<?= e($APP['accent']) ?>;color:<?= e($APP['accent']) ?>"><?= is_hi()?'कार्यकारी सारांश बनाएँ':'Generate Executive Summary' ?></button>
            <p id="aiSummary" class="hidden text-xs text-slate-600 leading-relaxed bg-paper rounded-lg p-2.5 border border-slate-100"></p>
          </div>
        </div>
      </div>
      <div class="rounded-xl p-3 text-xs" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 12%,#fff);color:<?= e($APP['accent']) ?>">🜄 <?= is_hi()?'आवंटन इंजन स्रोत उपलब्धता एवं मौसमी नीति की जाँच करेगा। SWCS से सत्यापित।':'Allocation engine validates source availability & seasonal policy. Verified via SWCS.' ?></div>
    </div>
    <div class="flex gap-2 mt-5">
      <button type="button" onclick="document.getElementById('newAlloc').close()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600">Cancel</button>
      <button class="flex-1 btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'आवेदन जमा करें':'Submit' ?></button>
    </div>
  </form>
</dialog>

<script>
(function(){
  var dlg = document.getElementById('newAlloc');
  var aiURL = <?= json_encode(base_url('app/allocation/ai.php')) ?>;
  var els = {
    source:document.getElementById('aiSource'), qty:document.getElementById('aiQuantity'),
    season:document.getElementById('aiSeason'), div:document.getElementById('aiDivision'),
    dist:document.getElementById('aiDistrict'), app:document.getElementById('aiApplicant')
  };
  var hint=document.getElementById('aiHint'), body=document.getElementById('aiBody'),
      recEl=document.getElementById('aiRec'), riskEl=document.getElementById('aiRisk'),
      riskWhy=document.getElementById('aiRiskWhy'), sumBtn=document.getElementById('aiSummaryBtn'),
      sumEl=document.getElementById('aiSummary');
  var RISK={LOW:['bg-emerald-100','text-emerald-700'],MEDIUM:['bg-amber-100','text-amber-700'],HIGH:['bg-rose-100','text-rose-700']};
  var timer=null, latestSummary='';

  function refresh(){
    var qty=parseFloat(els.qty.value||'0');
    if(!qty || qty<=0){ hint.classList.remove('hidden'); body.classList.add('hidden'); return; }
    var p=new URLSearchParams({quantity:qty, source_id:els.source.value||'', division_id:els.div.value||'',
      district:els.dist.value||'', season:els.season.value||'Perennial', applicant:els.app.value||''});
    fetch(aiURL+'?'+p.toString()).then(function(r){return r.json();}).then(function(d){
      hint.classList.add('hidden'); body.classList.remove('hidden');
      if(d.recommendation){
        recEl.textContent=d.recommendation.name+' — '+d.recommendation.reason;
      } else { recEl.textContent='No active source can meet this demand.'; }
      var rk=d.risk.level; riskEl.textContent=rk;
      riskEl.className='text-[11px] font-bold px-2 py-0.5 rounded-full '+(RISK[rk]||RISK.MEDIUM).join(' ');
      riskWhy.textContent=(d.risk.reasons&&d.risk.reasons[0])||'';
      latestSummary=d.summary||''; sumEl.classList.add('hidden');
    }).catch(function(){ /* keep silent in demo */ });
  }
  function debounced(){ clearTimeout(timer); timer=setTimeout(refresh,200); }
  Object.values(els).forEach(function(el){ if(el){ el.addEventListener('change',debounced); el.addEventListener('input',debounced);} });
  sumBtn && sumBtn.addEventListener('click',function(){
    sumEl.textContent=latestSummary||'Enter quantity and source first.'; sumEl.classList.toggle('hidden');
  });

  // Auto-open the application with a source pre-picked from the GIS map.
  <?php if($preSourceId): ?>
  if(dlg && dlg.showModal){ dlg.showModal(); refresh(); }
  <?php endif; ?>
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
