<?php
require_once __DIR__ . '/includes/header.php';
$pdo = db();

$stat = [
  'projects'   => (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
  'sanctioned' => (float)$pdo->query('SELECT COALESCE(SUM(sanctioned_amount),0) FROM projects')->fetchColumn(),
  'revenue'    => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Success'")->fetchColumn(),
  'contractors'=> (int)$pdo->query("SELECT COUNT(*) FROM contractors WHERE status='Active'")->fetchColumn(),
  'consumers'  => (int)$pdo->query('SELECT COUNT(*) FROM consumers')->fetchColumn(),
  'divisions'  => (int)$pdo->query('SELECT COUNT(*) FROM divisions')->fetchColumn(),
];
$notices = $pdo->query("SELECT * FROM content WHERE status='Published' ORDER BY publish_date DESC LIMIT 6")->fetchAll();
$tickers = $pdo->query("SELECT title,title_hi FROM content WHERE status='Published' ORDER BY publish_date DESC LIMIT 5")->fetchAll();

$services = [
  ['apply_alloc',  '🜄', base_url('app/allocation/index.php'),  'from-cyan-500 to-teal-600'],
  ['pay_bill',     '◫',  base_url('app/etariff/index.php'),     'from-teal-500 to-emerald-600'],
  ['contractor_reg','⚒', base_url('app/contractor/index.php'),  'from-sky-500 to-blue-600'],
  ['tenders',      '📋', base_url('public/tenders.php'),        'from-indigo-500 to-blue-700'],
  ['rti',          '📄', base_url('public/rti.php'),            'from-amber-500 to-orange-600'],
  ['grievance',    '🛟', base_url('public/grievance.php'),      'from-rose-500 to-pink-600'],
];
?>

<!-- ===== HERO ===== -->
<section class="water-hero grain text-white">
  <div class="relative max-w-7xl mx-auto px-4 pt-16 pb-20 z-10">
    <div class="max-w-3xl">
      <span class="inline-flex items-center gap-2 rounded-full bg-white/10 ring-1 ring-white/20 px-3 py-1 text-xs font-medium rise">
        ● <?= is_hi() ? 'पाँच मंच · एक एकीकृत डिजिटल रीढ़' : 'Five Platforms · One Integrated Backbone' ?>
      </span>
      <h1 class="font-display font-semibold text-4xl sm:text-5xl lg:text-[3.4rem] leading-[1.05] mt-5 rise d1">
        <?= is_hi()
            ? 'हर बूँद जल, हर रुपया — पारदर्शी, सुरक्षित, उत्तरदायी।'
            : 'Every drop of water, every rupee — transparent, secure, accountable.' ?>
      </h1>
      <p class="text-cyan-100/90 text-lg mt-5 max-w-2xl rise d2">
        <?= is_hi()
            ? 'जल संसाधन विभाग, झारखंड का एकीकृत डिजिटल पारितंत्र — परियोजना निगरानी, जल आवंटन, ठेकेदार पंजीकरण, ई-टैरिफ बिलिंग एवं नागरिक सेवाएँ, एक ही सुरक्षित मंच पर।'
            : 'The unified digital ecosystem of the Water Resources Department, Jharkhand — project monitoring, water allocation, contractor registration, e-tariff billing and citizen services on one secure platform.' ?>
      </p>
      <div class="flex flex-wrap gap-3 mt-8 rise d3">
        <a href="<?= base_url('public/services.php') ?>" class="bg-white text-ink font-semibold px-6 py-3 rounded-xl hover:bg-cyan-50 lift"><?= t('quick_services') ?> →</a>
        <a href="<?= base_url('auth/login.php') ?>" class="bg-white/10 ring-1 ring-white/30 text-white font-semibold px-6 py-3 rounded-xl hover:bg-white/20"><?= t('command_centre') ?></a>
      </div>
    </div>

    <!-- live stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mt-12">
      <?php
      $cards = [
        [is_hi()?'सक्रिय परियोजनाएँ':'Active Projects', $stat['projects'], ''],
        [is_hi()?'स्वीकृत राशि':'Sanctioned Outlay', inr($stat['sanctioned']), ''],
        [is_hi()?'राजस्व (संग्रहित)':'Revenue Collected', inr($stat['revenue']), ''],
        [is_hi()?'पंजीकृत ठेकेदार':'Active Contractors', $stat['contractors'], ''],
        [is_hi()?'जल उपभोक्ता':'Water Consumers', $stat['consumers'], ''],
        [is_hi()?'प्रमंडल':'Divisions', $stat['divisions'], ''],
      ]; $i=1;
      foreach ($cards as $c): ?>
        <div class="bg-white/10 ring-1 ring-white/15 rounded-2xl p-4 backdrop-blur rise d<?= $i++ ?>">
          <div class="font-display text-2xl font-semibold"><?= $c[1] ?></div>
          <div class="text-[12px] text-cyan-100/80 mt-1"><?= $c[0] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="text-[11px] text-cyan-200/70 mt-3">↻ <?= is_hi()?'पीपीएमएस से सीधे अद्यतन (लाइव एपीआई)':'Auto-updated from PPMS via secure live API' ?></p>
  </div>
</section>

<!-- ===== Ticker ===== -->
<div class="bg-goldsoft border-y border-amber-200 overflow-hidden">
  <div class="max-w-7xl mx-auto px-4 h-11 flex items-center gap-4">
    <span class="shrink-0 bg-gold text-white text-xs font-bold px-2.5 py-1 rounded"><?= t('whats_new') ?></span>
    <div class="ticker-wrap overflow-hidden flex-1">
      <div class="ticker text-sm text-amber-900">
        <?php for($k=0;$k<2;$k++) foreach ($tickers as $tk): ?>
          <span>◆ <?= bi($tk['title'],$tk['title_hi']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ===== Quick services ===== -->
<section class="max-w-7xl mx-auto px-4 py-14">
  <div class="flex items-end justify-between mb-7">
    <h2 class="font-display text-3xl font-semibold text-ink"><?= t('quick_services') ?></h2>
    <a href="<?= base_url('public/services.php') ?>" class="text-brand font-semibold text-sm hover:underline"><?= t('view_all') ?> →</a>
  </div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($services as $s): ?>
      <a href="<?= $s[2] ?>" class="card p-6 lift group">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $s[3] ?> text-white grid place-items-center text-xl mb-4"><?= $s[1] ?></div>
        <div class="font-display text-lg font-semibold text-ink group-hover:text-brand"><?= t($s[0]) ?></div>
        <div class="text-sm text-slate-500 mt-1"><?= is_hi()?'ऑनलाइन आवेदन एवं ट्रैकिंग':'Apply, track & pay online' ?> →</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===== Latest + Integrations ===== -->
<section class="max-w-7xl mx-auto px-4 pb-16 grid lg:grid-cols-3 gap-8">
  <div class="lg:col-span-2">
    <h2 class="font-display text-2xl font-semibold text-ink mb-5"><?= t('latest') ?></h2>
    <div class="card divide-y divide-slate-100">
      <?php foreach ($notices as $nz): ?>
        <div class="p-4 flex items-start gap-4 hover:bg-slate-50">
          <span class="shrink-0 text-[11px] font-semibold uppercase tracking-wide px-2 py-1 rounded bg-brandsoft text-branddeep"><?= e($nz['type']) ?></span>
          <div class="min-w-0">
            <p class="font-medium text-slate-800 leading-snug"><?= bi($nz['title'],$nz['title_hi']) ?></p>
            <p class="text-xs text-slate-500 mt-1"><?= date('d M Y', strtotime($nz['publish_date'])) ?> · <?= e($nz['category']) ?></p>
          </div>
          <?php if ($nz['is_new']): ?><span class="ml-auto shrink-0 text-[10px] font-bold text-rose-600 animate-pulse">NEW</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div>
    <h2 class="font-display text-2xl font-semibold text-ink mb-5"><?= is_hi()?'एकीकरण':'Integrated With' ?></h2>
    <div class="card p-6 space-y-3">
      <?php foreach ([
        ['JE-GRAS / Treasury','Division-wise revenue credit'],
        ['DigiLocker','Certificate push & verification'],
        ['Single Window (SWCS)','Inter-departmental clearance'],
        ['SMS Gateway (DLT)','Citizen alerts & OTP'],
        ['DPDP Act 2023','Personal data protection'],
        ['CERT-In VAPT','Security-audited hosting'],
      ] as $ig): ?>
        <div class="flex items-center gap-3">
          <span class="w-9 h-9 rounded-lg bg-brandsoft text-branddeep grid place-items-center font-bold">✓</span>
          <div><div class="font-semibold text-sm text-ink"><?= e($ig[0]) ?></div><div class="text-xs text-slate-500"><?= e($ig[1]) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card p-6 mt-5 kpi-accent">
      <p class="font-display text-lg font-semibold text-ink"><?= is_hi()?'मंत्री / सचिव संदेश':"Minister & Secretary" ?></p>
      <p class="text-sm text-slate-600 mt-2 italic">“<?= is_hi()?'जल ही जीवन है — हमारा संकल्प पारदर्शी एवं तकनीक-सक्षम शासन।':'Water is life — our commitment is transparent, technology-enabled governance.' ?>”</p>
      <p class="text-xs text-slate-500 mt-2">— <?= is_hi()?'सचिव, जल संसाधन विभाग':'Secretary, Water Resources Department' ?></p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
