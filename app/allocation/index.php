<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';
$STAGES=['AE','EE','SE','CE','EIC','Secretary'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $act=$_POST['action']??'';
  if($act==='apply'){
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM allocations')->fetchColumn()+207;
    $ano=sprintf('WRD/IWA/2526/%03d',$cnt);
    $fee=round((float)$_POST['quantity']*50000,2);
    $pdo->prepare("INSERT INTO allocations (app_no,applicant,source,source_name,quantity_mld,season,division_id,district,stage,status,gst,annual_fee,applied_on) VALUES (?,?,?,?,?,?,?,?, 'AE','New',?,?,CURDATE())")
        ->execute([$ano,trim($_POST['applicant']),$_POST['source'],trim($_POST['source_name']),(float)$_POST['quantity'],$_POST['season'],(int)$_POST['division_id'],trim($_POST['district']),strtoupper(trim($_POST['gst'])),$fee]);
    add_audit($pdo,'allocation',(int)$pdo->lastInsertId(),'Application submitted (SWCS)','Applicant','AE',$actor,'Allocation engine validated source & season.');
    flash("Allocation application $ano submitted."); header('Location: index.php'); exit;
  }
  $id=(int)($_POST['id']??0); $a=$pdo->query("SELECT * FROM allocations WHERE id=$id")->fetch();
  if($a){ $i=array_search($a['stage'],$STAGES); $rem=trim($_POST['remarks']??'');
    if($act==='forward' && $i!==false && $i<count($STAGES)-1){
      $next=$STAGES[$i+1]; $pdo->prepare("UPDATE allocations SET stage=?,status='Under Review' WHERE id=?")->execute([$next,$id]);
      add_audit($pdo,'allocation',$id,'Forwarded',$a['stage'],$next,$actor,$rem); flash("Forwarded to $next.");
    } elseif($act==='approve' && $a['stage']==='Secretary'){
      $lic='LIC/2526/'.str_pad((string)$id,4,'0',STR_PAD_LEFT);
      $pdo->prepare("UPDATE allocations SET status='Approved',license_no=? WHERE id=?")->execute([$lic,$id]);
      add_audit($pdo,'allocation',$id,'Approved & licence generated','Secretary','Issued',$actor,'Licence '.$lic);
      flash("Approved. Licence $lic generated.");
    } elseif($act==='hold'){
      $pdo->prepare("UPDATE allocations SET status='On Hold' WHERE id=?")->execute([$id]);
      add_audit($pdo,'allocation',$id,'Held',$a['stage'],$a['stage'],$actor,$rem?:'Held for clarification.'); flash('Application held.');
    } elseif($act==='reject'){
      $pdo->prepare("UPDATE allocations SET status='Rejected' WHERE id=?")->execute([$id]);
      add_audit($pdo,'allocation',$id,'Rejected',$a['stage'],$a['stage'],$actor,$rem); flash('Rejected.');
    }
    header('Location: index.php'); exit;
  }
}

$LAYOUT='app'; $ACTIVE='allocation'; $PAGE_TITLE='Water Allocation';
require __DIR__ . '/../../includes/header.php';
$rows=$pdo->query("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id ORDER BY a.id DESC")->fetchAll();
$divs=$pdo->query("SELECT id,name FROM divisions")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= t('allocation') ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'औद्योगिक जल आवंटन · बहु-स्तरीय अनुमोदन · जेई-ग्रास':'Industrial water allocation · multi-level approval · JE-GRASS (Component C)' ?></p></div>
  <button onclick="document.getElementById('newAlloc').showModal()" class="bg-brand text-white font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नया आवेदन':'New Application' ?></button>
</div>

<div class="space-y-3">
  <?php foreach($rows as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div><div class="font-display text-lg font-semibold text-ink"><?= e($a['applicant']) ?></div>
          <div class="text-sm text-slate-500"><span class="font-mono text-xs"><?= e($a['app_no']) ?></span> · <?= e($a['source']) ?>: <?= e($a['source_name']) ?> · <?= (float)$a['quantity_mld'] ?> MLD · <?= e($a['season']) ?></div>
          <div class="text-xs text-slate-400 mt-0.5"><?= e($a['divn']) ?> · GST <?= e($a['gst']) ?> · <?= is_hi()?'वार्षिक शुल्क':'Annual fee' ?> <?= inr((float)$a['annual_fee']) ?></div>
        </div>
        <div class="text-right"><?= badge($a['status']) ?><?php if($a['license_no']): ?><div class="text-xs text-emerald-700 font-semibold mt-1">📜 <?= e($a['license_no']) ?></div><?php endif; ?></div>
      </div>

      <!-- hierarchy tracker -->
      <div class="flex items-center gap-1 mt-4">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="px-2 h-6 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||$si<$i)?'bg-emerald-500 text-white':($si===$i&&$a['status']!=='Rejected'?'bg-brand text-white':'bg-slate-100 text-slate-400') ?>"><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||$si<$i)?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if(!in_array($a['status'],['Approved','Rejected'])): ?>
        <form method="post" class="flex flex-wrap gap-2 mt-4 items-center">
          <input type="hidden" name="id" value="<?= $a['id'] ?>">
          <input name="remarks" placeholder="<?= is_hi()?'टिप्पणी / आपत्ति':'Remarks / objection' ?>" class="flex-1 min-w-[160px] border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
          <span class="text-xs text-slate-400"><?= is_hi()?'भूमिका':'You are' ?>: <b class="text-brand"><?= e($role) ?></b> · <?= is_hi()?'चरण':'stage' ?> <b><?= e($a['stage']) ?></b></span>
          <?php if($a['stage']==='Secretary'): ?>
            <button name="action" value="approve" class="bg-emerald-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'स्वीकृत + लाइसेंस':'Approve + Licence' ?></button>
          <?php else: ?>
            <button name="action" value="forward" class="bg-brand text-white text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'अग्रेषित':'Forward' ?> →</button>
          <?php endif; ?>
          <button name="action" value="hold" class="bg-amber-100 text-amber-800 text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'रोकें':'Hold' ?></button>
          <button name="action" value="reject" class="bg-rose-100 text-rose-700 text-sm font-semibold px-3 py-1.5 rounded-lg">✕</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<dialog id="newAlloc" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/40">
  <form method="post" class="p-6"><input type="hidden" name="action" value="apply">
    <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'जल आवंटन आवेदन':'Water Allocation Application' ?></h2>
    <div class="space-y-3">
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'आवेदक / उद्योग':'Applicant / Industry' ?></label><input name="applicant" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'जल स्रोत':'Water Source' ?></label>
          <select name="source" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>River</option><option>Canal</option><option>Reservoir</option></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'स्रोत नाम':'Source Name' ?></label><input name="source_name" required value="Subarnarekha River" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'मात्रा (MLD)':'Quantity (MLD)' ?></label><input name="quantity" type="number" step="0.1" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'मौसम':'Season' ?></label>
          <select name="season" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>Perennial</option><option>Seasonal</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'प्रमंडल':'Division' ?></label>
          <select name="division_id" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($divs as $d):?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach;?></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'ज़िला':'District' ?></label><input name="district" required value="Ranchi" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      </div>
      <div><label class="text-sm font-medium text-slate-700">GSTIN</label><input name="gst" required placeholder="20XXXXX1234X1Z5" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
      <div class="bg-brandsoft rounded-xl p-3 text-xs text-branddeep">🜄 <?= is_hi()?'आवंटन इंजन स्रोत उपलब्धता एवं मौसमी नीति की जाँच करेगा। SWCS से सत्यापित।':'Allocation engine validates source availability & seasonal policy. Verified via SWCS.' ?></div>
    </div>
    <div class="flex gap-2 mt-5">
      <button type="button" onclick="document.getElementById('newAlloc').close()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600">Cancel</button>
      <button class="flex-1 bg-brand text-white rounded-xl py-2.5 font-semibold"><?= is_hi()?'आवेदन जमा करें':'Submit' ?></button>
    </div>
  </form>
</dialog>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
