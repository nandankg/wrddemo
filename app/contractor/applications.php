<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act=$_POST['action']??'';
  $aid=(int)($_POST['app_id']??0);
  $app=$pdo->query("SELECT * FROM contractor_apps WHERE id=$aid")->fetch();
  if ($app) {
    $stage=$app['stage']; $rem=trim($_POST['remarks']??'');
    $permit = [
      'forward' => contractor_next_stage($stage)!==null && $role===$stage && !in_array($app['status'],['Approved','Rejected','Query Raised'],true)
                   && (int)$pdo->query("SELECT COUNT(*) FROM contractor_queries WHERE app_id=$aid AND status<>'Resolved'")->fetchColumn()===0,
      'approve' => $stage==='EIC' && $role==='EIC' && !in_array($app['status'],['Approved','Rejected','Query Raised'],true)
                   && (int)$pdo->query("SELECT COUNT(*) FROM contractor_queries WHERE app_id=$aid AND status<>'Resolved'")->fetchColumn()===0,
      'reject'  => in_array($role,['ASO','AE','EE','EIC'],true) && $role===$stage && !in_array($app['status'],['Approved','Rejected'],true),
    ][$act] ?? false;
    if (!$permit) { flash('Action not permitted for your role at this stage.'); header('Location: applications.php'); exit; }
    if ($act==='forward') {
      $next=contractor_next_stage($stage);
      $pdo->prepare("UPDATE contractor_apps SET stage=?,status='Under Process' WHERE id=?")->execute([$next,$aid]);
      add_audit($pdo,'contractor_app',$aid,'Forwarded',$stage,$next,$actor,$rem);
      flash("Forwarded to $next.");
    } elseif ($act==='approve') {
      $pdo->prepare("UPDATE contractor_apps SET status='Approved' WHERE id=?")->execute([$aid]);
      if ($app['contractor_id']) $pdo->prepare("UPDATE contractors SET status='Active' WHERE id=?")->execute([$app['contractor_id']]);
      add_audit($pdo,'contractor_app',$aid,'Approved & certificate issued','EIC','Issued',$actor,'Certificate generated with QR.');
      flash('Approved. Digital certificate issued.');
    } elseif ($act==='reject') {
      $pdo->prepare("UPDATE contractor_apps SET status='Rejected' WHERE id=?")->execute([$aid]);
      add_audit($pdo,'contractor_app',$aid,'Rejected',$stage,$stage,$actor,$rem?:'Rejected.');
      flash('Application rejected.');
    }
    header('Location: applications.php'); exit;
  }
}

set_app_context('contractor');
app_require_access('applications');
$LAYOUT='app'; $ACTIVE='applications'; $PAGE_TITLE='Applications';
require __DIR__ . '/../../includes/header.php';

$STAGES=['ASO','AE','EE','EIC'];
$isContractor = contractor_role_view($role)==='contractor';
if ($isContractor) {
  $st=$pdo->prepare("SELECT a.*,c.name cname FROM contractor_apps a JOIN contractors c ON c.id=a.contractor_id WHERE c.login_user=? ORDER BY a.id DESC");
  $st->execute([$u['username']]); $apps=$st->fetchAll();
} else {
  $apps=$pdo->query("SELECT a.*,c.name cname FROM contractor_apps a LEFT JOIN contractors c ON c.id=a.contractor_id ORDER BY a.id DESC")->fetchAll();
  $openByApp=[];
  foreach ($pdo->query("SELECT app_id, COUNT(*) n FROM contractor_queries WHERE status<>'Resolved' GROUP BY app_id") as $r) $openByApp[(int)$r['app_id']]=(int)$r['n'];
}
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'आवेदन':'Applications' ?></h1>
  <p class="text-sm text-slate-500"><?= $isContractor?(is_hi()?'आपके आवेदन':'Your applications'):(is_hi()?'प्रसंस्करण इनबॉक्स':'Processing inbox') ?> · ASO → AE → EE → EIC</p></div>
</div>

<div class="space-y-3">
  <?php foreach($apps as $a): $i=array_search($a['stage'],$STAGES); ?>
    <div class="card p-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div><div class="font-medium text-slate-800"><?= e($a['cname']??'New Applicant') ?> <span class="text-xs text-slate-400 font-mono">· <?= e($a['ack_no']) ?></span></div>
        <div class="text-xs text-slate-500"><?= e($a['type']) ?> · Class <?= e($a['class']) ?> · Fee <?= inr((float)$a['fee']) ?> <?= $a['fee_paid']?'<span class="text-emerald-600">✓ paid</span>':'<span class="text-rose-600">unpaid</span>' ?></div></div>
        <?= badge($a['status']) ?>
        <?php if(!$isContractor): ?>
          <a href="<?= base_url('app/contractor/scrutiny.php') ?>?app_id=<?= e($a['id']) ?>" class="text-xs font-semibold" style="color:<?= e($APP['accent']) ?>"><?= is_hi()?'जांच खोलें':'Open scrutiny' ?> →</a>
        <?php endif; ?>
      </div>
      <!-- stage tracker -->
      <div class="flex items-center gap-1 mt-3">
        <?php foreach($STAGES as $si=>$s): ?>
          <div class="flex-1 flex items-center">
            <span class="w-7 h-7 rounded-full grid place-items-center text-[10px] font-bold <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-500 text-white':($si===$i?'text-white':'bg-slate-100 text-slate-400') ?>" <?= $si===$i?'style="background:'.e($APP['accent']).'"':'' ?>><?= $s ?></span>
            <?php if($si<count($STAGES)-1): ?><span class="flex-1 h-0.5 <?= ($a['status']==='Approved'||($i!==false&&$si<$i))?'bg-emerald-400':'bg-slate-100' ?>"></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if(!$isContractor && !in_array($a['status'],['Approved','Rejected'],true)): ?>
        <form method="post" class="flex flex-wrap gap-2 mt-3 items-center">
          <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
          <input name="remarks" placeholder="<?= is_hi()?'टिप्पणी':'Remarks' ?>" class="flex-1 min-w-[160px] border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
          <span class="text-xs text-slate-400"><?= is_hi()?'भूमिका':'You are' ?>: <b style="color:<?= e($APP['accent']) ?>"><?= e($role) ?></b> · <?= is_hi()?'चरण':'stage' ?> <b><?= e($a['stage']) ?></b></span>
          <?php if($a['stage']==='EIC'): ?>
            <?php if (($openByApp[$a['id']] ?? 0) === 0): ?>
              <button name="action" value="approve" class="bg-emerald-600 text-white text-sm font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'स्वीकृत + प्रमाणपत्र':'Approve + Issue' ?></button>
            <?php else: ?>
              <span class="text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-3 py-1.5"><?= is_hi()?'प्रश्न लंबित — अनुमोदन रोका':'Query pending — approval held' ?></span>
            <?php endif; ?>
          <?php else: ?>
            <?php if (contractor_can_forward($a, $openByApp[$a['id']] ?? 0)): ?>
              <button name="action" value="forward" class="btn-acc text-sm font-semibold px-3 py-1.5 rounded-lg"><?= is_hi()?'अग्रेषित':'Forward' ?> →</button>
            <?php else: ?>
              <span class="text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-3 py-1.5"><?= is_hi()?'प्रश्न लंबित — अग्रेषण रोका':'Query pending — forwarding held' ?></span>
            <?php endif; ?>
          <?php endif; ?>
          <button name="action" value="reject" class="bg-rose-100 text-rose-700 text-sm font-semibold px-3 py-1.5 rounded-lg">✕</button>
        </form>
      <?php elseif($a['status']==='Approved' && $a['contractor_id']): ?>
        <a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= $a['contractor_id'] ?>" target="_blank" class="inline-block mt-3 text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">📄 <?= is_hi()?'प्रमाणपत्र देखें':'View Certificate' ?> →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!$apps): ?><div class="card p-10 text-center text-slate-400 text-sm"><?= is_hi()?'कोई आवेदन नहीं।':'No applications.' ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
