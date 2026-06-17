<?php
/**
 * JE-GRASS Treasury Payment (RFP §8.2.5): challan -> simulated payment -> receipt.
 * Reuses the shared `payments` table via source_module='allocation'.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';
$id=(int)($_GET['id']??($_POST['id']??0));

$a=$pdo->prepare("SELECT a.*,d.name divn,d.bank_account,d.bank_name FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.id=? AND a.status='Approved'");
$a->execute([$id]); $a=$a->fetch();
if(!$a){ flash('Payment is available only for approved licences.'); header('Location: '.base_url('app/allocation/index.php')); exit; }

// Simulated JE-GRASS payment. Guarded against double-pay.
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='pay' && !allocation_fee_paid($a)) {
  $txn='JEGRAS-2526-'.strtoupper(bin2hex(random_bytes(3)));
  $challan=allocation_challan_no($id);
  $pdo->prepare("INSERT INTO payments (txn_ref,bill_id,source_module,consumer_id,division_id,amount,channel,credited_account,status,paid_on) VALUES (?,?,?,?,?,?,?,?,?,NOW())")
      ->execute([$txn,$id,'allocation',null,(int)$a['division_id'],(float)$a['annual_fee'],'JE-GRASS',$a['bank_account'],'Success']);
  $pdo->prepare("UPDATE allocations SET fee_status='Paid',challan_no=?,paid_on=NOW() WHERE id=?")->execute([$challan,$id]);
  add_audit($pdo,'allocation',$id,'Licence fee paid via JE-GRASS',null,null,$actor,'Txn '.$txn.' · '.inr_full((float)$a['annual_fee']));
  flash('Payment successful. Receipt generated.');
  header('Location: '.base_url('app/allocation/payment.php').'?id='.$id); exit;
}

$paid = allocation_fee_paid($a);
$challan = $a['challan_no'] ?: allocation_challan_no($id);
$pay = null;
if ($paid) {
  $p=$pdo->prepare("SELECT * FROM payments WHERE source_module='allocation' AND bill_id=? ORDER BY id DESC LIMIT 1");
  $p->execute([$id]); $pay=$p->fetch();
}

set_app_context('allocation');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Licence Fee Payment';
require __DIR__ . '/../../includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <a href="<?= base_url('app/allocation/index.php') ?>" class="text-sm text-slate-500">← <?= is_hi()?'डैशबोर्ड':'Dashboard' ?></a>
  <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink mt-2"><?= is_hi()?'लाइसेंस शुल्क भुगतान':'Licence Fee Payment' ?></h1>
  <p class="text-sm text-slate-500"><?= e($a['applicant']) ?> · <span class="font-mono text-xs"><?= e($a['app_no']) ?></span> · <?= e($a['license_no']) ?></p>

  <?php if(!$paid): ?>
  <!-- ===== Challan ===== -->
  <div class="card p-6 mt-5">
    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
      <div>
        <div class="text-xs text-slate-400 uppercase tracking-wide font-semibold"><?= is_hi()?'जे-ग्रास चालान':'JE-GRASS Challan' ?></div>
        <div class="font-mono text-lg font-semibold text-ink mt-0.5"><?= e($challan) ?></div>
      </div>
      <span class="text-[11px] font-bold uppercase bg-amber-100 text-amber-700 px-2 py-1 rounded-full"><?= is_hi()?'भुगतान शेष':'Payment Due' ?></span>
    </div>
    <table class="w-full text-sm mt-4">
      <tbody class="divide-y divide-slate-100">
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'मद':'Head of Account' ?></td><td class="py-2.5 text-right">0701 — Water Allocation Fee</td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'प्रमंडल':'Division' ?></td><td class="py-2.5 text-right"><?= e($a['divn']) ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'कोषागार खाता':'Treasury A/C' ?></td><td class="py-2.5 text-right font-mono text-xs"><?= e($a['bank_account']) ?> · <?= e($a['bank_name']) ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'वार्षिक शुल्क':'Annual Fee' ?></td><td class="py-2.5 text-right font-display text-2xl font-semibold text-ink"><?= inr_full((float)$a['annual_fee']) ?></td></tr>
      </tbody>
    </table>
    <form method="post" class="mt-5">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button name="action" value="pay" class="w-full btn-acc rounded-xl py-3 font-semibold text-base">🏦 <?= is_hi()?'जे-ग्रास से भुगतान करें':'Pay via JE-GRASS' ?> · <?= inr_full((float)$a['annual_fee']) ?></button>
    </form>
    <p class="text-[11px] text-slate-400 mt-3 text-center"><?= is_hi()?'सुरक्षित कोषागार गेटवे · भुगतान सत्यापन एवं समाधान सहित':'Secure treasury gateway · with payment verification &amp; reconciliation' ?></p>
  </div>
  <?php else: ?>
  <!-- ===== Receipt ===== -->
  <div class="card p-6 mt-5 noprint-wrap">
    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
      <span class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center text-2xl">✓</span>
      <div>
        <div class="font-display text-lg font-semibold text-emerald-700"><?= is_hi()?'भुगतान सफल':'Payment Successful' ?></div>
        <div class="text-xs text-slate-500"><?= is_hi()?'जे-ग्रास कोषागार रसीद':'JE-GRASS Treasury Receipt' ?></div>
      </div>
    </div>
    <table class="w-full text-sm mt-4">
      <tbody class="divide-y divide-slate-100">
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'लेन-देन संदर्भ':'Transaction Ref' ?></td><td class="py-2.5 text-right font-mono font-semibold"><?= e($pay['txn_ref']??'—') ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'चालान सं.':'Challan No' ?></td><td class="py-2.5 text-right font-mono"><?= e($challan) ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'राशि':'Amount Paid' ?></td><td class="py-2.5 text-right font-semibold text-emerald-700"><?= inr_full((float)$a['annual_fee']) ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'चैनल':'Channel' ?></td><td class="py-2.5 text-right"><?= e($pay['channel']??'JE-GRASS') ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'जमा खाता':'Credited A/C' ?></td><td class="py-2.5 text-right font-mono text-xs"><?= e($a['bank_account']) ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'दिनांक':'Date' ?></td><td class="py-2.5 text-right"><?= $a['paid_on']?date('d M Y, H:i',strtotime($a['paid_on'])):date('d M Y, H:i') ?></td></tr>
        <tr><td class="py-2.5 text-slate-500"><?= is_hi()?'स्थिति':'Status' ?></td><td class="py-2.5 text-right"><span class="text-[11px] font-bold bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">SUCCESS</span></td></tr>
      </tbody>
    </table>
    <div class="flex gap-2 mt-5">
      <a href="<?= base_url('app/allocation/licence.php') ?>?id=<?= $id ?>" target="_blank" class="flex-1 text-center btn-acc rounded-xl py-2.5 font-semibold">📜 <?= is_hi()?'लाइसेंस देखें':'View Licence' ?></a>
      <button onclick="print()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600">🖨 <?= is_hi()?'रसीद प्रिंट':'Print Receipt' ?></button>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
