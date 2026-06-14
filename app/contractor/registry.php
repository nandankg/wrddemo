<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
// Back-office only: the register (with PAN/GST) is not exposed to contractor logins.
if (contractor_role_view(user_role()) === 'contractor') { header('Location: ' . base_url('app/contractor/index.php')); exit; }
$pdo=db();
$contractors=$pdo->query("SELECT * FROM contractors ORDER BY status='Blacklisted' DESC, name")->fetchAll();

set_app_context('contractor');
$LAYOUT='app'; $ACTIVE='registry'; $PAGE_TITLE='Registered Contractors';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'श्रेणी · जोखिम स्कोरिंग · ब्लैकलिस्ट':'Class · risk scoring · blacklist' ?></p></div>
</div>
<div class="card overflow-hidden">
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
          <td class="px-4 py-3 text-right"><?php if($c['status']==='Active'): ?><a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= $c['id'] ?>" target="_blank" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">Cert →</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3">🔴 <?= is_hi()?'ब्लैकलिस्ट सार्वजनिक रूप से प्रदर्शित (आरएफपी पारदर्शिता)।':'Blacklisted contractors shown publicly per RFP transparency requirement.' ?></p>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
