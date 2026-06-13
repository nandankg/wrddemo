<?php
$ACTIVE='rti'; $PAGE_TITLE='RTI Online';
require_once __DIR__ . '/../includes/header.php';
$pdo=db(); $ref=null; $tracked=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['do']??'')==='file'){
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM rti_applications')->fetchColumn()+4;
    $ref=sprintf('WRD/RTI/2526/%04d',$cnt);
    $pdo->prepare("INSERT INTO rti_applications (ref_no,applicant,subject,status,filed_on,fee_paid) VALUES (?,?,?, 'New',CURDATE(),1)")
        ->execute([$ref,trim($_POST['applicant']),trim($_POST['subject'])]);
  } elseif(($_POST['do']??'')==='track'){
    $st=$pdo->prepare("SELECT * FROM rti_applications WHERE ref_no=?"); $st->execute([trim($_POST['ref'])]); $tracked=$st->fetch();
  }
}
$disclosures=['Particulars of organisation, functions & duties','Powers & duties of officers','Procedure followed in decision-making','Norms for discharge of functions','Rules, regulations & manuals','Directory of officers & employees','Budget allocated to each agency'];
?>
<section class="water-hero text-white"><div class="max-w-7xl mx-auto px-4 py-12">
  <h1 class="font-display text-3xl sm:text-4xl font-semibold"><?= t('rti') ?></h1>
  <p class="text-cyan-100/90 mt-2"><?= is_hi()?'सूचना का अधिकार अधिनियम, 2005 के अंतर्गत':'Under the Right to Information Act, 2005' ?></p>
</div></section>

<section class="max-w-7xl mx-auto px-4 py-10 grid lg:grid-cols-3 gap-8">
  <div class="lg:col-span-2">
    <?php if($ref): ?>
      <div class="card p-6 bg-emerald-50 ring-1 ring-emerald-200 mb-6"><div class="text-emerald-700 font-semibold">✓ <?= is_hi()?'आरटीआई आवेदन दर्ज':'RTI application filed' ?></div>
      <p class="text-sm text-slate-600 mt-1"><?= is_hi()?'संदर्भ':'Reference' ?>: <b class="font-mono"><?= e($ref) ?></b> · <?= is_hi()?'शुल्क भुगतान (JE-GRAS)':'Fee paid via JE-GRAS' ?> ✓</p></div>
    <?php endif; ?>
    <div class="card p-6">
      <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'आरटीआई आवेदन दाखिल करें':'File an RTI Application' ?></h2>
      <form method="post" class="space-y-4"><input type="hidden" name="do" value="file">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'आवेदक का नाम':'Applicant Name' ?></label><input name="applicant" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'विषय / जानकारी मांगी गई':'Subject / Information sought' ?></label><textarea name="subject" rows="3" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></textarea></div>
        <div class="bg-paper rounded-xl p-3 text-sm flex items-center justify-between"><span class="text-slate-600"><?= is_hi()?'आरटीआई शुल्क':'RTI Fee' ?></span><span class="font-semibold">₹10 · <span class="text-emerald-600">JE-GRAS ✓</span></span></div>
        <button class="bg-brand hover:bg-branddeep text-white font-semibold px-5 py-2.5 rounded-xl"><?= is_hi()?'आवेदन जमा करें':'Submit Application' ?></button>
      </form>
    </div>
  </div>
  <div>
    <div class="card p-6 mb-6">
      <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'आरटीआई ट्रैक करें':'Track RTI' ?></h2>
      <form method="post" class="flex gap-2"><input type="hidden" name="do" value="track">
        <input name="ref" placeholder="WRD/RTI/2526/0001" class="flex-1 border border-slate-300 rounded-xl px-3 py-2.5 text-sm">
        <button class="bg-ink text-white px-4 rounded-xl font-semibold text-sm"><?= t('search') ?></button></form>
      <?php if($tracked): ?><div class="mt-4 border-t border-slate-100 pt-4 text-sm"><div class="flex justify-between"><span class="text-slate-500">Status</span><?= badge($tracked['status']) ?></div><p class="text-slate-600 mt-2"><?= e($tracked['subject']) ?></p></div>
      <?php elseif($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['do']??'')==='track'): ?><p class="text-sm text-rose-600 mt-3"><?= is_hi()?'नहीं मिला।':'Not found.' ?></p><?php endif; ?>
    </div>
    <div class="card p-6">
      <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'स्वतः प्रकटीकरण [धारा 4(1)(b)]':'Suo-Motu Disclosure [Sec 4(1)(b)]' ?></h2>
      <ul class="space-y-2 text-sm">
        <?php foreach($disclosures as $d): ?><li class="flex items-start gap-2 text-slate-600"><span class="text-brand">📄</span><?= e($d) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
