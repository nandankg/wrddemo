<?php
/**
 * Renewal & Annual Demand (RFP §8.2.3). Lists approved licences with validity,
 * flags those within the 90-day renewal window, and lets the applicant file a
 * renewal — which creates a fresh allocation that re-enters the AE..Secretary
 * pipeline (renewed_from points back to the parent licence).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';
$view=allocation_role_view($role);
$today=date('Y-m-d');

// File a renewal: clone an approved parent into a new pending application.
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='renew') {
  $pid=(int)($_POST['id']??0);
  $sql="SELECT * FROM allocations WHERE id=? AND status='Approved'".($view==='applicant'?" AND login_user=?":"");
  $st=$pdo->prepare($sql); $st->execute($view==='applicant'?[$pid,$u['username']]:[$pid]);
  if ($p=$st->fetch()) {
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM allocations')->fetchColumn()+207;
    $ano=sprintf('WRD/IWA/2526/%03d',$cnt);
    $pdo->prepare("INSERT INTO allocations (app_no,applicant,source,source_name,quantity_mld,season,division_id,district,stage,status,gst,annual_fee,applied_on,login_user,fee_status,renewed_from) VALUES (?,?,?,?,?,?,?,?, 'AE','New',?,?,CURDATE(),?, 'Unpaid',?)")
        ->execute([$ano,$p['applicant'],$p['source'],$p['source_name'],$p['quantity_mld'],$p['season'],$p['division_id'],$p['district'],$p['gst'],$p['annual_fee'],$p['login_user'],$pid]);
    add_audit($pdo,'allocation',(int)$pdo->lastInsertId(),'Renewal filed (from '.$p['license_no'].')','Applicant','AE',$actor,'Annual renewal of licence '.$p['license_no']);
    flash("Renewal $ano filed — routed to AE for scrutiny.");
  }
  header('Location: '.base_url('app/allocation/renewals.php')); exit;
}

$sql="SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.status='Approved'".($view==='applicant'?" AND a.login_user=?":"")." ORDER BY a.valid_upto IS NULL, a.valid_upto ASC";
$st=$pdo->prepare($sql); $st->execute($view==='applicant'?[$u['username']]:[]); $rows=$st->fetchAll();

set_app_context('allocation');
$LAYOUT='app'; $ACTIVE='renewals'; $PAGE_TITLE='Renewals';
require __DIR__ . '/../../includes/header.php';
?>
<div class="mb-5">
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'नवीनीकरण एवं वार्षिक मांग':'Renewal & Annual Demand' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'समाप्ति-निकट लाइसेंस · 90 दिन की नवीनीकरण विंडो':'Licences nearing expiry · 90-day renewal window' ?></p>
</div>

<div class="space-y-3">
  <?php foreach($rows as $a):
    $valid=$a['valid_upto']?:date('Y-m-d',strtotime($a['applied_on'].' +5 years'));
    $days=allocation_days_to_expiry($valid,$today);
    $due=allocation_is_due_renewal($valid,$today);
    $expired=$days<0;
  ?>
    <div class="card p-5 <?= $due?'ring-1 ring-amber-200':'' ?>">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="font-display text-lg font-semibold text-ink"><?= e($a['applicant']) ?></div>
          <div class="text-sm text-slate-500"><span class="font-mono text-xs"><?= e($a['license_no']) ?></span> · <?= e($a['source']) ?>: <?= e($a['source_name']) ?> · <?= (float)$a['quantity_mld'] ?> MLD</div>
          <div class="text-xs text-slate-400 mt-0.5"><?= e($a['divn']) ?> · <?= is_hi()?'वैध तक':'Valid upto' ?> <span class="font-semibold text-slate-600"><?= date('d M Y',strtotime($valid)) ?></span></div>
        </div>
        <div class="text-right">
          <?php if($expired): ?>
            <span class="text-[11px] font-bold uppercase bg-rose-100 text-rose-700 px-2 py-1 rounded-full"><?= is_hi()?'समाप्त':'Expired' ?></span>
          <?php elseif($due): ?>
            <span class="text-[11px] font-bold uppercase bg-amber-100 text-amber-700 px-2 py-1 rounded-full"><?= $days ?> <?= is_hi()?'दिन शेष':'days left' ?></span>
          <?php else: ?>
            <span class="text-[11px] font-bold uppercase bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full"><?= is_hi()?'सक्रिय':'Active' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php if($due): ?>
      <form method="post" class="mt-4 flex items-center gap-3">
        <input type="hidden" name="id" value="<?= $a['id'] ?>">
        <button name="action" value="renew" class="btn-acc rounded-lg px-4 py-2 text-sm font-semibold">🔁 <?= is_hi()?'नवीनीकरण आवेदन करें':'File Renewal' ?></button>
        <span class="text-xs text-slate-400"><?= is_hi()?'नई फाइल AE → सचिव से होकर जाएगी।':'New file routes through AE → Secretary.' ?></span>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$rows): ?>
    <div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'कोई स्वीकृत लाइसेंस नहीं।':'No approved licences yet.' ?></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
