<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

// Contractor self-registration (creates contractor + application at ASO).
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='register' && $role==='CONTRACTOR') {
  $class=in_array($_POST['class']??'',['I','II','III','IV'],true)?$_POST['class']:'IV';
  $cnt=(int)$pdo->query('SELECT COUNT(*) FROM contractors')->fetchColumn()+451;
  $reg=sprintf('WRD/REG/3/%04d',$cnt);
  $qr=bin2hex(random_bytes(6));
  $pdo->prepare("INSERT INTO contractors (reg_no,name,class,pan,gst,district,status,risk_score,valid_upto,registered_on,qr_token,login_user) VALUES (?,?,?,?,?,?, 'Pending',?,?,CURDATE(),?,?)")
      ->execute([$reg,trim($_POST['name']),$class,strtoupper(trim($_POST['pan'])),strtoupper(trim($_POST['gst'])),trim($_POST['district']),rand(15,40),date('Y-m-d',strtotime('+3 years')),$qr,$u['username']]);
  $cid=(int)$pdo->lastInsertId();
  $ackcnt=(int)$pdo->query('SELECT COUNT(*) FROM contractor_apps')->fetchColumn()+1001;
  $ack=sprintf('WRD/ACK/2526/%04d',$ackcnt);
  $pdo->prepare("INSERT INTO contractor_apps (ack_no,contractor_id,type,class,stage,status,fee,fee_paid,applied_on) VALUES (?,?, 'New',?,'ASO','Document Verification',?,1,CURDATE())")
      ->execute([$ack,$cid,$class,contractor_fee($class)]);
  add_audit($pdo,'contractor_app',(int)$pdo->lastInsertId(),'Application submitted (Aadhaar e-KYC)','Citizen','ASO',$actor,'E-GRAS fee paid · Ack '.$ack);
  flash("Registration submitted. Acknowledgement $ack");
  header('Location: index.php'); exit;
}

$view = contractor_role_view($role);
if ($view==='contractor') {
  $st=$pdo->prepare("SELECT a.*,c.name cname,c.name_hi cname_hi,c.id cid,c.status cstatus FROM contractor_apps a JOIN contractors c ON c.id=a.contractor_id WHERE c.login_user=? ORDER BY a.id DESC");
  $st->execute([$u['username']]); $apps=$st->fetchAll();
  $contractors=[];
} else {
  $apps=$pdo->query("SELECT a.*,c.name cname FROM contractor_apps a LEFT JOIN contractors c ON c.id=a.contractor_id ORDER BY a.id DESC")->fetchAll();
  $contractors=$pdo->query("SELECT * FROM contractors")->fetchAll();
}
$k=contractor_kpis($apps,$contractors);
$tasks=contractor_pending_actions($role,$apps);
$STAGES=['ASO','AE','EE','EIC'];

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Registry Desk';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= $view==='contractor'?(is_hi()?'मेरा पंजीकरण':'My Registration'):(is_hi()?'पंजीयन कार्यालय':'Registering Authority Desk') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <?php if($view==='contractor'): ?>
    <button onclick="document.getElementById('wiz').showModal()" class="btn-acc font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नया पंजीकरण':'New Registration' ?></button>
  <?php endif; ?>
</div>

<?php if($view==='registry'): ?>
<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach([
    [is_hi()?'प्रक्रियाधीन आवेदन':'Applications In Process', (string)$k['in_process'], 'text-amber-700'],
    [is_hi()?'सक्रिय ठेकेदार':'Active Contractors', (string)$k['active'], 'text-emerald-700'],
    [is_hi()?'ब्लैकलिस्टेड':'Blacklisted', (string)$k['blacklisted'], 'text-rose-700'],
    [is_hi()?'कुल आवेदन':'Total Applications', (string)$k['total_apps'], 'text-ink'],
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
      <a href="<?= base_url('app/contractor/applications.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
    </div>
    <?php if($tasks): ?>
      <div class="space-y-2">
        <?php foreach($tasks as $tk): ?>
          <a href="<?= base_url('app/contractor/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
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
    <a href="<?= base_url('app/contractor/applications.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📋 <?= is_hi()?'आवेदन इनबॉक्स':'Applications Inbox' ?></span></a>
    <a href="<?= base_url('app/contractor/registry.php') ?>" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper mb-2"><span class="font-medium text-slate-700">📒 <?= is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors' ?></span></a>
    <a href="<?= base_url('app/contractor/verify.php') ?>" target="_blank" class="block p-3 rounded-lg border border-slate-100 hover:bg-paper"><span class="font-medium text-slate-700">✔ <?= is_hi()?'प्रमाणपत्र सत्यापन':'Verify Certificate' ?></span></a>
  </div>
</div>

<?php else: // ===== contractor portal ===== ?>
<div class="space-y-3">
  <?php foreach($apps as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div><div class="font-medium text-slate-800"><?= bi($a['cname'],$a['cname_hi']) ?> <span class="text-xs text-slate-400 font-mono">· <?= e($a['ack_no']) ?></span></div>
        <div class="text-xs text-slate-500"><?= e($a['type']) ?> · Class <?= e($a['class']) ?> · Fee <?= inr((float)$a['fee']) ?></div></div>
        <?= badge($a['status']) ?>
      </div>
      <div class="flex items-center gap-1 mt-3">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="w-7 h-7 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i?'text-white':'bg-slate-100 text-slate-400') ?>" <?= $si===$i?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if($a['status']==='Approved'): ?>
        <a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= (int)$a['cid'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">📄 <?= is_hi()?'प्रमाणपत्र डाउनलोड करें':'Download Certificate' ?> →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$apps): ?><div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई आवेदन नहीं। "नया पंजीकरण" से आरंभ करें।':'No applications yet. Start with "New Registration".' ?></div><?php endif; ?>
</div>

<!-- Registration wizard -->
<dialog id="wiz" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/50">
  <form method="post" class="p-6"><input type="hidden" name="action" value="register">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'ठेकेदार पंजीकरण':'Contractor Registration' ?></h2>
      <button type="button" onclick="document.getElementById('wiz').close()" class="text-slate-400 text-xl">✕</button>
    </div>
    <div class="flex items-center gap-1 mb-5" id="steps">
      <?php foreach(['Aadhaar e-KYC','Details','Documents','Payment'] as $si=>$ss): ?>
        <div class="flex-1 text-center"><div class="step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold <?= $si===0?'text-white':'bg-slate-100 text-slate-400' ?>" <?= $si===0?'style="background:'.e($APP['accent']).'"':'' ?> data-step="<?= $si ?>"><?= $si+1 ?></div><div class="text-[10px] text-slate-400 mt-1"><?= $ss ?></div></div>
      <?php endforeach; ?>
    </div>
    <div class="wiz-pane" data-pane="0">
      <div class="rounded-xl p-4 text-sm" style="background:color-mix(in srgb,<?= e($APP['accent']) ?> 12%,#fff);color:<?= e($APP['accent']) ?>">📱 <?= is_hi()?'आधार सत्यापन':'Aadhaar verification' ?></div>
      <input placeholder="XXXX XXXX 1234" class="mt-3 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="mt-2 flex gap-2"><input placeholder="OTP (any 6 digits)" maxlength="6" class="flex-1 border border-slate-300 rounded-xl px-3 py-2.5"><span class="bg-emerald-50 text-emerald-700 text-sm font-semibold px-3 py-2.5 rounded-xl">✓ Verified</span></div>
    </div>
    <div class="wiz-pane hidden" data-pane="1">
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'फर्म का नाम':'Firm Name' ?></label>
      <input name="name" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">PAN</label><input name="pan" required placeholder="AABCN1234K" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700">Class</label><select name="class" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>I</option><option>II</option><option>III</option><option selected>IV</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">GSTIN (JH only)</label><input name="gst" required placeholder="20XXXXX1234X1Z5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'ज़िला':'District' ?></label><input name="district" required value="Ranchi" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <p id="gsterr" class="text-xs text-rose-600 mt-1 hidden"><?= is_hi()?'केवल झारखंड (20) जीएसटीआईएन मान्य।':'Only Jharkhand (code 20) GSTIN is valid.' ?></p>
    </div>
    <div class="wiz-pane hidden" data-pane="2">
      <p class="text-sm text-slate-500 mb-3"><?= is_hi()?'दस्तावेज़ अपलोड (डेमो):':'Upload documents (demo):' ?></p>
      <?php foreach(['Photograph','Signature','PAN Card','Incorporation Certificate','GST Certificate'] as $doc): ?>
        <label class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 mb-2 text-sm"><span><?= $doc ?></span><span class="text-emerald-600 text-xs font-semibold">✓ uploaded</span></label>
      <?php endforeach; ?>
    </div>
    <div class="wiz-pane hidden" data-pane="3">
      <div class="bg-paper rounded-xl p-4 text-sm"><div class="flex justify-between"><span class="text-slate-500"><?= is_hi()?'पंजीकरण शुल्क':'Registration Fee' ?></span><span class="font-semibold" id="feeAmt">₹10,000</span></div>
      <div class="text-xs text-slate-400 mt-1">via E-GRAS · Net Banking / UPI / Card</div></div>
      <div class="mt-3 text-sm text-emerald-700 bg-emerald-50 rounded-xl px-3 py-2.5">✓ <?= is_hi()?'भुगतान सफल (डेमो)। आवेदन जमा करने हेतु तैयार।':'Payment successful (demo). Ready to submit.' ?></div>
    </div>
    <div class="flex gap-2 mt-5">
      <button type="button" id="prevBtn" onclick="wizStep(-1)" class="border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-600 hidden"><?= is_hi()?'पीछे':'Back' ?></button>
      <button type="button" id="nextBtn" onclick="wizStep(1)" class="flex-1 btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'आगे':'Next' ?> →</button>
      <button type="submit" id="subBtn" class="flex-1 bg-emerald-600 text-white rounded-xl py-2.5 font-semibold hidden"><?= is_hi()?'आवेदन जमा करें':'Submit Application' ?></button>
    </div>
  </form>
</dialog>
<script>
let cur=0; const panes=document.querySelectorAll('.wiz-pane'), dots=document.querySelectorAll('.step-dot');
const ACC='<?= e($APP['accent']) ?>';
function wizStep(dir){
  if(dir>0 && cur===1){ const g=document.querySelector('[name=gst]').value.trim();
    if(g && !g.startsWith('20')){ document.getElementById('gsterr').classList.remove('hidden'); return; } }
  cur=Math.max(0,Math.min(3,cur+dir));
  panes.forEach((p,i)=>p.classList.toggle('hidden',i!==cur));
  dots.forEach((d,i)=>{ d.className='step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold '+(i<=cur?'text-white':'bg-slate-100 text-slate-400'); d.style.background=i<=cur?ACC:''; });
  document.getElementById('prevBtn').classList.toggle('hidden',cur===0);
  document.getElementById('nextBtn').classList.toggle('hidden',cur===3);
  document.getElementById('subBtn').classList.toggle('hidden',cur!==3);
  const fees={'I':'₹45,000','II':'₹30,000','III':'₹20,000','IV':'₹10,000'};
  document.getElementById('feeAmt').textContent=fees[document.querySelector('[name=class]').value]||'₹10,000';
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
