<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';
$STAGES=['ASO','SO','US','DS','JS','EIC'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $act=$_POST['action']??'';
  if($act==='register'){
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM contractors')->fetchColumn()+451;
    $reg=sprintf('WRD/REG/3/%04d',$cnt);
    $qr=bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO contractors (reg_no,name,class,pan,gst,district,status,risk_score,valid_upto,registered_on,qr_token) VALUES (?,?,?,?,?,?,?,?,?,CURDATE(),?)")
        ->execute([$reg,trim($_POST['name']),$_POST['class'],strtoupper(trim($_POST['pan'])),strtoupper(trim($_POST['gst'])),trim($_POST['district']),'Pending',rand(15,40),date('Y-m-d',strtotime('+3 years')),$qr]);
    $cid=(int)$pdo->lastInsertId();
    $ackcnt=(int)$pdo->query('SELECT COUNT(*) FROM contractor_apps')->fetchColumn()+1001;
    $ack=sprintf('WRD/ACK/2526/%04d',$ackcnt);
    $pdo->prepare("INSERT INTO contractor_apps (ack_no,contractor_id,type,class,stage,status,fee,fee_paid,applied_on) VALUES (?,?, 'New',?,'ASO','Document Verification',?,1,CURDATE())")
        ->execute([$ack,$cid,$_POST['class'],['I'=>45000,'II'=>30000,'III'=>20000,'IV'=>10000][$_POST['class']]??10000]);
    add_audit($pdo,'contractor_app',(int)$pdo->lastInsertId(),'Application submitted (Aadhaar e-KYC)','Citizen','ASO',$actor,'E-GRAS fee paid · Ack '.$ack);
    flash("Registration submitted. Acknowledgement $ack");
    header('Location: index.php#apps'); exit;
  }
  $aid=(int)($_POST['app_id']??0); $app=$pdo->query("SELECT * FROM contractor_apps WHERE id=$aid")->fetch();
  if($app){
    $i=array_search($app['stage'],$STAGES);
    if($act==='forward' && $i!==false && $i<count($STAGES)-1){
      $next=$STAGES[$i+1];
      $pdo->prepare("UPDATE contractor_apps SET stage=?,status='Under Process' WHERE id=?")->execute([$next,$aid]);
      add_audit($pdo,'contractor_app',$aid,'Forwarded',$app['stage'],$next,$actor,trim($_POST['remarks']??''));
      flash("Forwarded to $next.");
    } elseif($act==='approve' && $app['stage']==='EIC'){
      $pdo->prepare("UPDATE contractor_apps SET status='Approved' WHERE id=?")->execute([$aid]);
      if($app['contractor_id']) $pdo->prepare("UPDATE contractors SET status='Active' WHERE id=?")->execute([$app['contractor_id']]);
      add_audit($pdo,'contractor_app',$aid,'Approved & certificate issued','EIC','Issued',$actor,'Certificate generated with QR.');
      flash('Approved. Digital certificate issued.');
    } elseif($act==='reject'){
      $pdo->prepare("UPDATE contractor_apps SET status='Rejected' WHERE id=?")->execute([$aid]);
      add_audit($pdo,'contractor_app',$aid,'Rejected',$app['stage'],$app['stage'],$actor,trim($_POST['remarks']??''));
      flash('Application rejected.');
    }
    header('Location: index.php#apps'); exit;
  }
}

$LAYOUT='app'; $ACTIVE='contractor'; $PAGE_TITLE='Contractor Registration';
require __DIR__ . '/../../includes/header.php';
$contractors=$pdo->query("SELECT * FROM contractors ORDER BY status='Blacklisted' DESC, name")->fetchAll();
$apps=$pdo->query("SELECT a.*,c.name cname FROM contractor_apps a LEFT JOIN contractors c ON c.id=a.contractor_id ORDER BY a.id DESC")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= t('contractor_reg') ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'पंजीकरण · नवीनीकरण · प्रमाणपत्र · डिजिलॉकर':'Registration · renewal · certificate · DigiLocker (Component B)' ?></p></div>
  <button onclick="document.getElementById('wiz').showModal()" class="bg-brand text-white font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नया पंजीकरण':'New Registration' ?></button>
</div>

<!-- Processing inbox -->
<div id="apps" class="card p-5 mb-6">
  <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'प्रसंस्करण इनबॉक्स':'Processing Inbox' ?> (ASO → SO → US → DS → JS → EIC)</h2>
  <div class="space-y-2">
    <?php foreach($apps as $a): $i=array_search($a['stage'],$STAGES); ?>
      <div class="border border-slate-100 rounded-xl p-3.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div><div class="font-medium text-slate-800"><?= e($a['cname']??'New Applicant') ?> <span class="text-xs text-slate-400 font-mono">· <?= e($a['ack_no']) ?></span></div>
          <div class="text-xs text-slate-500"><?= e($a['type']) ?> · Class <?= e($a['class']) ?> · Fee <?= inr((float)$a['fee']) ?> <?= $a['fee_paid']?'<span class="text-emerald-600">✓ paid</span>':'' ?></div></div>
          <?= badge($a['status']) ?>
        </div>
        <!-- stage tracker -->
        <div class="flex items-center gap-1 mt-3">
          <?php foreach($STAGES as $si=>$s): ?>
            <div class="flex-1 flex items-center">
              <span class="w-6 h-6 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||$si<$i)?'bg-emerald-500 text-white':($si===$i?'bg-brand text-white':'bg-slate-100 text-slate-400') ?>"><?= $s ?></span>
              <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||$si<$i)?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if($a['status']!=='Approved' && $a['status']!=='Rejected'): ?>
          <form method="post" class="flex flex-wrap gap-2 mt-3 items-center">
            <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
            <input name="remarks" placeholder="<?= is_hi()?'टिप्पणी':'Remarks' ?>" class="flex-1 min-w-[160px] border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
            <span class="text-xs text-slate-400"><?= is_hi()?'भूमिका':'You are' ?>: <b class="text-brand"><?= e($role) ?></b> · <?= is_hi()?'चरण':'stage' ?> <b><?= e($a['stage']) ?></b></span>
            <?php if($a['stage']==='EIC'): ?>
              <button name="action" value="approve" class="bg-emerald-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'स्वीकृत + प्रमाणपत्र':'Approve + Issue' ?></button>
            <?php else: ?>
              <button name="action" value="forward" class="bg-brand text-white text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'अग्रेषित':'Forward' ?> →</button>
            <?php endif; ?>
            <button name="action" value="reject" class="bg-rose-100 text-rose-700 text-sm font-semibold px-3 py-1.5 rounded-lg">✕</button>
          </form>
        <?php elseif($a['status']==='Approved' && $a['contractor_id']): ?>
          <a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= $a['contractor_id'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold text-brand">📄 <?= is_hi()?'प्रमाणपत्र देखें':'View Certificate' ?> →</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Contractors register -->
<div class="card overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
    <h2 class="font-display text-lg font-semibold text-ink"><?= is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors' ?></h2>
    <span class="text-xs text-slate-400"><?= is_hi()?'जोखिम स्कोरिंग एवं ब्लैकलिस्ट सहित':'with smart risk scoring & blacklist' ?></span>
  </div>
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
      <th class="text-left px-4 py-3"><?= is_hi()?'ठेकेदार':'Contractor' ?></th><th class="text-left px-4 py-3">Class</th>
      <th class="text-left px-4 py-3 hidden md:table-cell">GST</th><th class="text-left px-4 py-3"><?= is_hi()?'जोखिम':'Risk' ?></th>
      <th class="text-left px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($contractors as $c): [$rb,$rc]=risk_band((int)$c['risk_score']); ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><div class="font-medium text-slate-800"><?= bi($c['name'],$c['name_hi']) ?></div><div class="text-xs text-slate-400 font-mono"><?= e($c['reg_no']) ?> · <?= e($c['district']) ?></div></td>
          <td class="px-4 py-3"><span class="inline-grid place-items-center w-7 h-7 rounded-lg bg-ink text-white text-xs font-bold"><?= e($c['class']) ?></span></td>
          <td class="px-4 py-3 text-xs font-mono text-slate-500 hidden md:table-cell"><?= e($c['gst']) ?></td>
          <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-1 rounded-full <?= $rc ?>"><?= $rb ?> · <?= (int)$c['risk_score'] ?></span></td>
          <td class="px-4 py-3"><?= badge($c['status']) ?></td>
          <td class="px-4 py-3 text-right"><?php if($c['status']==='Active'): ?><a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= $c['id'] ?>" target="_blank" class="text-brand text-sm font-semibold">Cert →</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3">🔴 <?= is_hi()?'ब्लैकलिस्ट सार्वजनिक रूप से प्रदर्शित।':'Blacklisted contractors shown publicly per RFP transparency requirement.' ?></p>

<!-- Registration wizard -->
<dialog id="wiz" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/50">
  <form method="post" class="p-6"><input type="hidden" name="action" value="register">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'ठेकेदार पंजीकरण':'Contractor Registration' ?></h2>
      <button type="button" onclick="document.getElementById('wiz').close()" class="text-slate-400 text-xl">✕</button>
    </div>
    <!-- steps indicator -->
    <div class="flex items-center gap-1 mb-5" id="steps">
      <?php foreach(['Aadhaar e-KYC','Details','Documents','Payment'] as $si=>$ss): ?>
        <div class="flex-1 text-center"><div class="step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold <?= $si===0?'bg-brand text-white':'bg-slate-100 text-slate-400' ?>" data-step="<?= $si ?>"><?= $si+1 ?></div><div class="text-[10px] text-slate-400 mt-1"><?= $ss ?></div></div>
      <?php endforeach; ?>
    </div>

    <div class="wiz-pane" data-pane="0">
      <div class="bg-brandsoft rounded-xl p-4 text-sm text-branddeep">📱 <?= is_hi()?'आधार सत्यापन':'Aadhaar verification' ?></div>
      <input id="aadhaar" placeholder="XXXX XXXX 1234" class="mt-3 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="mt-2 flex gap-2"><input placeholder="OTP (any 6 digits)" maxlength="6" class="flex-1 border border-slate-300 rounded-xl px-3 py-2.5"><span class="bg-emerald-50 text-emerald-700 text-sm font-semibold px-3 py-2.5 rounded-xl">✓ Verified</span></div>
    </div>
    <div class="wiz-pane hidden" data-pane="1">
      <label class="text-sm font-medium text-slate-700"><?= is_hi()?'फर्म का नाम':'Firm Name' ?></label>
      <input name="name" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div><label class="text-sm font-medium text-slate-700">PAN</label><input name="pan" required placeholder="AABCN1234K" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700">Class</label><select name="class" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>I</option><option>II</option><option>III</option><option>IV</option></select></div>
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
      <div class="bg-paper rounded-xl p-4 text-sm"><div class="flex justify-between"><span class="text-slate-500"><?= is_hi()?'पंजीकरण शुल्क':'Registration Fee' ?></span><span class="font-semibold" id="feeAmt">₹45,000</span></div>
      <div class="text-xs text-slate-400 mt-1">via E-GRAS · Net Banking / UPI / Card</div></div>
      <div class="mt-3 text-sm text-emerald-700 bg-emerald-50 rounded-xl px-3 py-2.5">✓ <?= is_hi()?'भुगतान सफल (डेमो)। आवेदन जमा करने हेतु तैयार।':'Payment successful (demo). Ready to submit.' ?></div>
    </div>

    <div class="flex gap-2 mt-5">
      <button type="button" id="prevBtn" onclick="wizStep(-1)" class="border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-600 hidden"><?= is_hi()?'पीछे':'Back' ?></button>
      <button type="button" id="nextBtn" onclick="wizStep(1)" class="flex-1 bg-brand text-white rounded-xl py-2.5 font-semibold"><?= is_hi()?'आगे':'Next' ?> →</button>
      <button type="submit" id="subBtn" class="flex-1 bg-emerald-600 text-white rounded-xl py-2.5 font-semibold hidden"><?= is_hi()?'आवेदन जमा करें':'Submit Application' ?></button>
    </div>
  </form>
</dialog>

<script>
let cur=0; const panes=document.querySelectorAll('.wiz-pane'), dots=document.querySelectorAll('.step-dot');
function wizStep(dir){
  if(dir>0 && cur===1){ const g=document.querySelector('[name=gst]').value.trim();
    if(g && !g.startsWith('20')){ document.getElementById('gsterr').classList.remove('hidden'); return; } }
  cur=Math.max(0,Math.min(3,cur+dir));
  panes.forEach((p,i)=>p.classList.toggle('hidden',i!==cur));
  dots.forEach((d,i)=>{ d.className='step-dot w-7 h-7 mx-auto rounded-full grid place-items-center text-xs font-bold '+(i<=cur?'bg-brand text-white':'bg-slate-100 text-slate-400'); });
  document.getElementById('prevBtn').classList.toggle('hidden',cur===0);
  document.getElementById('nextBtn').classList.toggle('hidden',cur===3);
  document.getElementById('subBtn').classList.toggle('hidden',cur!==3);
  const fees={'I':'₹45,000','II':'₹30,000','III':'₹20,000','IV':'₹10,000'};
  document.getElementById('feeAmt').textContent=fees[document.querySelector('[name=class]').value]||'₹10,000';
}
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
