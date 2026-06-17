<?php
require_once __DIR__ . '/includes/header.php';   // $LAYOUT defaults to 'public'; no app context => default accent
require_once __DIR__ . '/includes/apps.php';
require_once __DIR__ . '/includes/leaders.php';
$apps = wrd_apps();
?>
<!-- ===== Suite hero ===== -->
<section class="text-white" style="background:
   radial-gradient(1200px 320px at 80% -10%, rgba(14,124,134,.30), transparent),
   linear-gradient(180deg,#0a2a44,#0c3350)">
  <div class="max-w-7xl mx-auto px-4 pt-14 pb-12">
    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 ring-1 ring-white/20 px-3 py-1 text-xs font-medium">
      ● <?= t('five_products') ?>
    </span>
    <h1 class="font-display font-semibold text-3xl sm:text-4xl lg:text-5xl leading-[1.1] mt-5 max-w-3xl"><?= t('suite_hero') ?></h1>
    <p class="text-cyan-100/90 text-base mt-4 max-w-2xl"><?= t('suite_sub') ?></p>
    <div class="flex flex-wrap gap-2 mt-7">
      <?php foreach (['GIGW 3.0','WCAG 2.1 AA','DPDP Act 2023','CERT-In','हिंदी / English'] as $b): ?>
        <span class="text-[11px] bg-white/8 ring-1 ring-white/18 rounded-lg px-2.5 py-1.5 text-cyan-100/90">✓ <?= e($b) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php render_leaders(); ?>

<!-- ===== Product cards ===== -->
<section class="max-w-7xl mx-auto px-4 py-12">
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($apps as $a): ?>
      <a href="<?= base_url($a['home']) ?>" class="card acc-card p-6 lift group" style="--acc:<?= e($a['accent']) ?>">
        <div class="w-12 h-12 rounded-xl grid place-items-center text-2xl mb-4 chip-acc"><?= $a['icon'] ?></div>
        <div class="font-display text-lg font-semibold text-ink group-hover:opacity-90"><?= is_hi()?e($a['name_hi']):e($a['name']) ?></div>
        <p class="text-sm text-slate-500 mt-1.5 leading-relaxed"><?= is_hi()?e($a['tagline_hi']):e($a['tagline']) ?></p>
        <div class="mt-4 text-sm font-semibold" style="color:<?= e($a['accent']) ?>"><?= t('open_demo') ?> →</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
