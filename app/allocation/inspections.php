<?php
/**
 * Inspection & Enforcement (RFP §8.2.8). Officers log field inspections against a
 * licensed allocation, recording the finding and any enforcement action
 * (show-cause / penalty). The log is shown below the form.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

set_app_context('allocation');
app_require_access('inspections');

$FINDINGS=['Compliant','Minor Violation','Major Violation'];
$ACTIONS=['None','Show-Cause','Penalty'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add') {
  $aid=(int)($_POST['allocation_id']??0);
  $a=$pdo->prepare("SELECT app_no FROM allocations WHERE id=?"); $a->execute([$aid]); $a=$a->fetch();
  $finding=in_array($_POST['finding']??'',$FINDINGS,true)?$_POST['finding']:'Compliant';
  $enf=in_array($_POST['enforcement']??'',$ACTIONS,true)?$_POST['enforcement']:'None';
  if ($a) {
    $pdo->prepare("INSERT INTO inspections (allocation_id,app_no,inspector,finding,action,notes,inspected_on) VALUES (?,?,?,?,?,?,CURDATE())")
        ->execute([$aid,$a['app_no'],$actor,$finding,$enf,trim($_POST['notes']??'')]);
    add_audit($pdo,'allocation',$aid,'Field inspection: '.$finding.($enf!=='None'?' · '.$enf:''),null,null,$actor,trim($_POST['notes']??''));
    flash('Inspection recorded'.($enf!=='None'?' · '.$enf.' issued.':'.'));
  }
  header('Location: '.base_url('app/allocation/inspections.php')); exit;
}

$lics=$pdo->query("SELECT id,app_no,applicant,license_no FROM allocations WHERE status='Approved' ORDER BY id DESC")->fetchAll();
$logs=$pdo->query("SELECT i.*,a.applicant FROM inspections i LEFT JOIN allocations a ON a.id=i.allocation_id ORDER BY i.id DESC")->fetchAll();

$LAYOUT='app'; $ACTIVE='inspections'; $PAGE_TITLE='Inspections';
require __DIR__ . '/../../includes/header.php';
$fbadge=function($f){ $m=['Compliant'=>'bg-emerald-100 text-emerald-700','Minor Violation'=>'bg-amber-100 text-amber-700','Major Violation'=>'bg-rose-100 text-rose-700']; return '<span class="text-[11px] font-bold px-2 py-0.5 rounded-full '.($m[$f]??'bg-slate-100 text-slate-600').'">'.e($f).'</span>'; };
?>
<div class="mb-5">
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'निरीक्षण एवं प्रवर्तन':'Inspection & Enforcement' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'क्षेत्र निरीक्षण · उल्लंघन · कारण बताओ नोटिस':'Field inspection · violations · show-cause notices' ?></p>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Log form -->
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'निरीक्षण दर्ज करें':'Record Inspection' ?></h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="add">
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'लाइसेंस':'Licence' ?></label>
        <select name="allocation_id" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
          <?php foreach($lics as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['license_no']) ?> · <?= e($l['applicant']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'निष्कर्ष':'Finding' ?></label>
        <select name="finding" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($FINDINGS as $f): ?><option><?= $f ?></option><?php endforeach; ?></select></div>
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'प्रवर्तन कार्रवाई':'Enforcement Action' ?></label>
        <select name="enforcement" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($ACTIONS as $ac): ?><option><?= $ac ?></option><?php endforeach; ?></select></div>
      <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'टिप्पणी':'Notes' ?></label>
        <textarea name="notes" rows="2" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="<?= is_hi()?'फ्लो मीटर, आहरण, अनुपालन…':'Flow meter, drawal, compliance…' ?>"></textarea></div>
      <button class="w-full btn-acc rounded-xl py-2.5 font-semibold"><?= is_hi()?'निरीक्षण दर्ज करें':'Record Inspection' ?></button>
    </form>
  </div>

  <!-- Log list -->
  <div class="lg:col-span-2 card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'निरीक्षण लॉग':'Inspection Log' ?></h2>
    <div class="space-y-2.5">
      <?php foreach($logs as $g): ?>
        <div class="p-3 rounded-xl border border-slate-100">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="font-medium text-slate-800 text-sm"><?= e($g['applicant']??$g['app_no']) ?> <span class="text-xs text-slate-400 font-mono">· <?= e($g['app_no']) ?></span></div>
              <div class="text-[11px] text-slate-400"><?= e($g['inspector']) ?> · <?= date('d M Y',strtotime($g['inspected_on'])) ?></div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0"><?= $fbadge($g['finding']) ?><?php if($g['action']!=='None'): ?><span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-800 text-white"><?= e($g['action']) ?></span><?php endif; ?></div>
          </div>
          <?php if(trim((string)$g['notes'])!==''): ?><p class="text-xs text-slate-500 mt-1.5"><?= e($g['notes']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if(!$logs): ?><div class="text-center py-10 text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई निरीक्षण नहीं।':'No inspections recorded yet.' ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
