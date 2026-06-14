<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db();
set_app_context('allocation');
app_require_access('licences');
$LAYOUT='app'; $ACTIVE='licences'; $PAGE_TITLE='Licences';
require __DIR__ . '/../../includes/header.php';
$rows=$pdo->query("SELECT a.*,d.name divn FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.status='Approved' AND a.license_no IS NOT NULL ORDER BY a.id DESC")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'जारी लाइसेंस':'Issued Licences' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'अनुमोदित जल आवंटन लाइसेंस':'Approved water allocation licences' ?></p></div>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
      <th class="text-left px-4 py-3"><?= is_hi()?'लाइसेंस':'Licence' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'उद्योग':'Industry' ?></th>
      <th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'स्रोत':'Source' ?></th><th class="text-right px-4 py-3">MLD</th>
      <th class="text-right px-4 py-3"><?= is_hi()?'वार्षिक शुल्क':'Annual Fee' ?></th><th class="px-4 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($rows as $a): ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= e($a['license_no']) ?></td>
          <td class="px-4 py-3 font-medium text-slate-800"><?= e($a['applicant']) ?><div class="text-xs text-slate-400"><?= e($a['divn']) ?></div></td>
          <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($a['source']) ?>: <?= e($a['source_name']) ?></td>
          <td class="px-4 py-3 text-right font-semibold text-ink"><?= (float)$a['quantity_mld'] ?></td>
          <td class="px-4 py-3 text-right"><?= inr((float)$a['annual_fee']) ?></td>
          <td class="px-4 py-3 text-right"><a href="<?= base_url('app/allocation/licence.php') ?>?id=<?= $a['id'] ?>" target="_blank" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">Licence →</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="6" class="px-4 py-10 text-center text-slate-400 text-sm"><?= is_hi()?'अभी तक कोई लाइसेंस जारी नहीं।':'No licences issued yet.' ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
