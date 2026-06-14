<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act=$_POST['action']??'';
  $id=(int)($_POST['id']??0);
  $a=$pdo->query("SELECT * FROM allocations WHERE id=$id")->fetch();
  if ($a) {
    $stage=$a['stage']; $rem=trim($_POST['remarks']??'');
    $active = !in_array($a['status'],['Approved','Rejected'],true);
    $permit = [
      'forward' => allocation_next_stage($stage)!==null && $role===$stage && $active,
      'approve' => $stage==='SECRETARY' && $role==='SECRETARY' && $a['status']!=='Approved',
      'hold'    => $role===$stage && $active,
      'reject'  => $role===$stage && $active,
    ][$act] ?? false;
    if (!$permit) { flash('Action not permitted for your role at this stage.'); header('Location: applications.php'); exit; }
    if ($act==='forward') {
      $next=allocation_next_stage($stage);
      $pdo->prepare("UPDATE allocations SET stage=?,status='Under Review' WHERE id=?")->execute([$next,$id]);
      add_audit($pdo,'allocation',$id,'Forwarded',$stage,$next,$actor,$rem);
      flash("Forwarded to $next.");
    } elseif ($act==='approve') {
      $lic='LIC/2526/'.str_pad((string)$id,4,'0',STR_PAD_LEFT);
      $pdo->prepare("UPDATE allocations SET status='Approved',license_no=? WHERE id=?")->execute([$lic,$id]);
      add_audit($pdo,'allocation',$id,'Approved & licence generated','SECRETARY','Issued',$actor,'Licence '.$lic);
      flash("Approved. Licence $lic generated.");
    } elseif ($act==='hold') {
      $pdo->prepare("UPDATE allocations SET status='On Hold' WHERE id=?")->execute([$id]);
      add_audit($pdo,'allocation',$id,'Held',$stage,$stage,$actor,$rem?:'Held for clarification.'); flash('Application held.');
    } elseif ($act==='reject') {
      $pdo->prepare("UPDATE allocations SET status='Rejected' WHERE id=?")->execute([$id]);
      add_audit($pdo,'allocation',$id,'Rejected',$stage,$stage,$actor,$rem?:'Rejected.'); flash('Rejected.');
    }
    header('Location: applications.php'); exit;
  }
}

set_app_context('allocation');
app_require_access('applications');
$LAYOUT='app'; $ACTIVE='applications'; $PAGE_TITLE='Applications';
require __DIR__ . '/../../includes/header.php';

$STAGES=['AE','EE','SE','CE','EIC','SECRETARY'];
$rows=$pdo->query("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id ORDER BY a.id DESC")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'आवेदन':'Applications' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'बहु-स्तरीय अनुमोदन':'Multi-level approval' ?> · AE → EE → SE → CE → EIC → SECRETARY</p></div>
</div>

<div class="space-y-3">
  <?php foreach($rows as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div><div class="font-display text-lg font-semibold text-ink"><?= e($a['applicant']) ?></div>
          <div class="text-sm text-slate-500"><span class="font-mono text-xs"><?= e($a['app_no']) ?></span> · <?= e($a['source']) ?>: <?= e($a['source_name']) ?> · <?= (float)$a['quantity_mld'] ?> MLD · <?= e($a['season']) ?></div>
          <div class="text-xs text-slate-400 mt-0.5"><?= e($a['divn']) ?> · GST <?= e($a['gst']) ?> · <?= is_hi()?'वार्षिक शुल्क':'Annual fee' ?> <?= inr((float)$a['annual_fee']) ?></div>
        </div>
        <div class="text-right"><?= badge($a['status']) ?><?php if($a['license_no']): ?><div class="text-xs text-emerald-700 font-semibold mt-1">📜 <a href="<?= base_url('app/allocation/licence.php') ?>?id=<?= $a['id'] ?>" target="_blank" class="hover:underline"><?= e($a['license_no']) ?></a></div><?php endif; ?></div>
      </div>

      <!-- hierarchy tracker -->
      <div class="flex items-center gap-1 mt-4">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="px-2 h-6 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i&&$a['status']!=='Rejected'?'text-white':'bg-slate-100 text-slate-400') ?>" <?= ($si===$i&&$a['status']!=='Rejected'&&$a['status']!=='Approved')?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if(!in_array($a['status'],['Approved','Rejected'],true) && $role===$a['stage']): ?>
        <form method="post" class="flex flex-wrap gap-2 mt-4 items-center">
          <input type="hidden" name="id" value="<?= $a['id'] ?>">
          <input name="remarks" placeholder="<?= is_hi()?'टिप्पणी / आपत्ति':'Remarks / objection' ?>" class="flex-1 min-w-[160px] border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
          <?php if($a['stage']==='SECRETARY'): ?>
            <button name="action" value="approve" class="bg-emerald-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'स्वीकृत + लाइसेंस':'Approve + Licence' ?></button>
          <?php else: ?>
            <button name="action" value="forward" class="btn-acc text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'अग्रेषित':'Forward' ?> →</button>
          <?php endif; ?>
          <button name="action" value="hold" class="bg-amber-100 text-amber-800 text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'रोकें':'Hold' ?></button>
          <button name="action" value="reject" class="bg-rose-100 text-rose-700 text-sm font-semibold px-3 py-1.5 rounded-lg">✕</button>
        </form>
      <?php elseif(!in_array($a['status'],['Approved','Rejected'],true)): ?>
        <p class="text-xs text-slate-400 mt-3"><?= is_hi()?'वर्तमान चरण':'Currently at stage' ?> <b><?= e($a['stage']) ?></b> — <?= is_hi()?'उस भूमिका में बदलें (बाएँ नीचे) कार्रवाई हेतु।':'switch to that role (bottom-left) to act.' ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
