<?php
$ACTIVE='schemes'; $PAGE_TITLE='Schemes & Projects';
require_once __DIR__ . '/../includes/header.php';
$pdo=db();
$projects=$pdo->query("SELECT p.*,s.name scheme,s.type,d.name divn FROM projects p JOIN schemes s ON s.id=p.scheme_id JOIN divisions d ON d.id=p.division_id ORDER BY p.physical_pct DESC")->fetchAll();
$schemes=$pdo->query("SELECT * FROM schemes")->fetchAll();
?>
<section class="water-hero text-white"><div class="max-w-7xl mx-auto px-4 py-12">
  <h1 class="font-display text-3xl sm:text-4xl font-semibold"><?= t('schemes') ?></h1>
  <p class="text-cyan-100/90 mt-2"><?= is_hi()?'विभागीय योजनाएँ एवं लाइव परियोजना स्थिति (पीपीएमएस से)':'Departmental schemes & live project status (sourced from PPMS)' ?></p>
</div></section>

<section class="max-w-7xl mx-auto px-4 py-10">
  <h2 class="font-display text-2xl font-semibold text-ink mb-4"><?= is_hi()?'योजनाएँ':'Schemes' ?></h2>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
    <?php foreach($schemes as $s): ?>
      <div class="card p-5 lift"><div class="w-10 h-10 rounded-xl bg-brandsoft text-branddeep grid place-items-center mb-3"><?= wrd_icon('droplet','w-5 h-5') ?></div>
        <h3 class="font-semibold text-ink"><?= bi($s['name'],$s['name_hi']) ?></h3>
        <p class="text-xs text-slate-500 mt-1"><?= e($s['type']) ?> · <?= is_hi()?'पात्रता एवं फॉर्म ऑनलाइन':'Eligibility & forms online' ?></p></div>
    <?php endforeach; ?>
  </div>

  <div class="flex items-center justify-between mb-4">
    <h2 class="font-display text-2xl font-semibold text-ink"><?= is_hi()?'लाइव परियोजना स्थिति':'Live Project Status' ?></h2>
    <span class="text-xs text-emerald-600 font-semibold">● <?= is_hi()?'पीपीएमएस लाइव एपीआई':'PPMS live API' ?></span>
  </div>
  <div class="grid md:grid-cols-2 gap-4">
    <?php foreach($projects as $p): ?>
      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div><h3 class="font-display text-lg font-semibold text-ink"><?= bi($p['name'],$p['name_hi']) ?></h3>
          <p class="text-xs text-slate-500"><?= e($p['scheme']) ?> · <?= e($p['divn']) ?></p></div>
          <?= badge($p['status']) ?>
        </div>
        <div class="mt-4 space-y-2">
          <div><div class="flex justify-between text-xs text-slate-500 mb-1"><span><?= is_hi()?'भौतिक':'Physical' ?></span><span class="font-semibold"><?= (int)$p['physical_pct'] ?>%</span></div><div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-brand" style="width:<?= (int)$p['physical_pct'] ?>%"></div></div></div>
          <div><div class="flex justify-between text-xs text-slate-500 mb-1"><span><?= is_hi()?'वित्तीय':'Financial' ?></span><span class="font-semibold"><?= (int)$p['financial_pct'] ?>%</span></div><div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500" style="width:<?= (int)$p['financial_pct'] ?>%"></div></div></div>
        </div>
        <div class="text-xs text-slate-400 mt-3"><?= is_hi()?'स्वीकृत':'Sanctioned' ?>: <?= inr((float)$p['sanctioned_amount']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
