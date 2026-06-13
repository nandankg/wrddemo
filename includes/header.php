<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$LAYOUT      = $LAYOUT      ?? 'public';
$PAGE_TITLE  = $PAGE_TITLE  ?? '';
$ACTIVE      = $ACTIVE      ?? '';
$u = current_user();

$nav = [
    'home'      => ['label'=>t('home'),      'url'=>base_url('index.php')],
    'about'     => ['label'=>t('about'),     'url'=>base_url('public/about.php')],
    'schemes'   => ['label'=>t('schemes'),   'url'=>base_url('public/schemes.php')],
    'tenders'   => ['label'=>t('tenders'),   'url'=>base_url('public/tenders.php')],
    'services'  => ['label'=>t('services'),  'url'=>base_url('public/services.php')],
    'rti'       => ['label'=>t('rti'),       'url'=>base_url('public/rti.php')],
    'grievance' => ['label'=>t('grievance'), 'url'=>base_url('public/grievance.php')],
];
?><!doctype html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $PAGE_TITLE ? e($PAGE_TITLE).' · ' : '' ?>WRD Jharkhand</title>
<meta name="description" content="Water Resources Department, Government of Jharkhand — Integrated Digital Backbone.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Mukta:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    colors: {
      ink:'#06314a', ink2:'#0a4763', brand:'#0E7C86', branddeep:'#0a5d65',
      brandsoft:'#e6f4f4', gold:'#B45309', goldsoft:'#fdf3e7',
    },
    fontFamily: { display:['Fraunces','serif'], sans:['Mukta','system-ui','sans-serif'] },
  }}
}
</script>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<script defer src="<?= base_url('assets/js/app.js') ?>"></script>
<?php if (!empty($EXTRA_HEAD)) echo $EXTRA_HEAD; ?>
</head>
<body class="min-h-screen flex flex-col">
<a href="#main" class="skip"><?= t('skip_content') ?></a>

<!-- ===== Government utility strip ===== -->
<div class="bg-ink text-slate-200 text-xs">
  <div class="max-w-7xl mx-auto px-4 h-9 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="hidden sm:inline">🇮🇳 <?= t('govt') ?></span>
      <span class="hidden md:inline text-slate-400">|</span>
      <span class="hidden md:inline text-slate-400">GIGW 3.0 · WCAG 2.1 AA</span>
    </div>
    <div class="flex items-center gap-1">
      <!-- accessibility -->
      <button data-acc-toggle class="px-2 py-1 rounded hover:bg-white/10" aria-haspopup="true" aria-expanded="false" title="<?= t('accessibility') ?>">♿ <span class="hidden sm:inline"><?= t('accessibility') ?></span></button>
      <div id="acc-panel" class="hidden absolute right-3 mt-28 z-50 card p-3 text-slate-700 shadow-xl w-56" role="menu">
        <p class="text-[11px] font-semibold text-slate-500 mb-2"><?= t('accessibility') ?></p>
        <div class="flex gap-2 mb-2">
          <button onclick="WRD.fontSmaller()" class="flex-1 border rounded-lg py-1.5 hover:bg-slate-50 text-sm">A-</button>
          <button onclick="WRD.fontReset()" class="flex-1 border rounded-lg py-1.5 hover:bg-slate-50 text-sm">A</button>
          <button onclick="WRD.fontLarger()" class="flex-1 border rounded-lg py-1.5 hover:bg-slate-50 text-sm font-bold">A+</button>
        </div>
        <button onclick="WRD.toggleContrast()" class="w-full border rounded-lg py-1.5 hover:bg-slate-50 text-sm">◐ High Contrast</button>
      </div>
      <!-- language toggle -->
      <a href="?lang=en" class="px-2 py-1 rounded hover:bg-white/10 <?= lang()==='en'?'bg-white/15 font-semibold':'' ?>">EN</a>
      <a href="?lang=hi" class="px-2 py-1 rounded hover:bg-white/10 <?= lang()==='hi'?'bg-white/15 font-semibold':'' ?>">हिं</a>
    </div>
  </div>
</div>

<!-- ===== Masthead ===== -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 h-[72px] flex items-center justify-between gap-4">
    <a href="<?= base_url('index.php') ?>" class="flex items-center gap-3">
      <!-- water-drop crest -->
      <span class="grid place-items-center w-11 h-11 rounded-xl water-hero text-white shrink-0" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff" opacity=".95"/><path d="M9 14.5a3 3 0 0 0 3 3" stroke="#0E7C86" stroke-width="1.6" stroke-linecap="round"/></svg>
      </span>
      <div class="leading-tight">
        <div class="font-display font-semibold text-ink text-[15px] sm:text-[17px]"><?= t('portal_name') ?></div>
        <div class="text-[11px] sm:text-xs text-slate-500"><?= t('govt') ?> · <?= t('tagline') ?></div>
      </div>
    </a>

    <?php if ($LAYOUT === 'public'): ?>
    <nav class="hidden lg:flex items-center gap-1" aria-label="Primary">
      <?php foreach ($nav as $k=>$item): ?>
        <a href="<?= $item['url'] ?>" class="px-3 py-2 rounded-lg text-[14px] font-medium <?= $ACTIVE===$k?'text-brand bg-brandsoft':'text-slate-700 hover:bg-slate-100' ?>"><?= $item['label'] ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="flex items-center gap-2">
      <?php if ($u): ?>
        <a href="<?= base_url('app/dashboard.php') ?>" class="hidden sm:inline-flex items-center gap-1.5 bg-brand hover:bg-branddeep text-white text-sm font-semibold px-3.5 py-2 rounded-lg"><?= t('dashboard') ?></a>
        <div class="hidden md:flex flex-col items-end leading-tight">
          <span class="text-[13px] font-semibold text-ink"><?= e($u['name']) ?></span>
          <span class="text-[11px] text-slate-500"><?= e(role_label()) ?></span>
        </div>
        <a href="<?= base_url('auth/logout.php') ?>" class="text-sm text-slate-500 hover:text-rose-600 px-2 py-2" title="<?= t('logout') ?>">⎋</a>
      <?php else: ?>
        <a href="<?= base_url('auth/login.php') ?>" class="inline-flex items-center gap-1.5 bg-ink hover:bg-ink2 text-white text-sm font-semibold px-4 py-2 rounded-lg"><?= t('login') ?></a>
      <?php endif; ?>
      <?php if ($LAYOUT==='public'): ?>
      <button onclick="WRD.toggleMenu('mobile-nav')" class="lg:hidden p-2 text-ink" aria-label="Menu">☰</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($LAYOUT==='public'): ?>
  <div id="mobile-nav" class="hidden lg:hidden border-t border-slate-200 px-4 py-2 bg-white">
    <?php foreach ($nav as $k=>$item): ?>
      <a href="<?= $item['url'] ?>" class="block px-3 py-2 rounded-lg text-sm <?= $ACTIVE===$k?'text-brand bg-brandsoft':'text-slate-700' ?>"><?= $item['label'] ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</header>

<?php if ($f = flash()): ?>
  <div class="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-sm">
    <div class="max-w-7xl mx-auto px-4 py-2.5 flex items-center gap-2">✅ <?= e($f) ?></div>
  </div>
<?php endif; ?>

<?php if ($LAYOUT === 'app'):
    require __DIR__ . '/sidebar.php'; ?>
<div class="flex-1 flex max-w-[1500px] w-full mx-auto">
  <?php render_sidebar($ACTIVE); ?>
  <main id="main" class="flex-1 min-w-0 px-4 sm:px-6 py-6 bg-paper">
<?php else: ?>
  <main id="main" class="flex-1">
<?php endif; ?>
