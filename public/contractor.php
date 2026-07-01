<?php
require_once __DIR__ . '/../includes/header.php';   // $LAYOUT defaults to 'public'
require_once __DIR__ . '/../includes/leaders.php';
require_once __DIR__ . '/../app/contractor/lib.php';
$pdo = db();
$contractors = $pdo->query('SELECT status FROM contractors')->fetchAll();
$capps       = $pdo->query('SELECT applied_on FROM contractor_apps')->fetchAll();
$stats = contractor_public_stats($contractors, $capps);
$ACC = '#2563eb';
$login = base_url('app/contractor/login.php');
$actions = [
  ['register',    is_hi()?'नया पंजीकरण':'New Registration',    $login],
  ['renew',       is_hi()?'पंजीकरण नवीनीकरण':'Renew Registration', $login],
  ['search',      is_hi()?'आवेदन ट्रैक करें':'Track Application', $login],
  ['certificate', is_hi()?'प्रमाणपत्र डाउनलोड':'Download Certificate', $login],
  ['verify',      is_hi()?'ठेकेदार सत्यापन':'Verify Contractor', base_url('app/contractor/verify.php')],
  ['banknote',    is_hi()?'शुल्क भुगतान':'Pay Fees',           $login],
];
?>
<section class="text-white" style="background:radial-gradient(1100px 300px at 80% -10%, rgba(37,99,235,.35), transparent), linear-gradient(180deg,#0a2a44,#0c3350)">
  <div class="max-w-6xl mx-auto px-4 pt-14 pb-12 flex flex-col lg:flex-row lg:items-center gap-10">
    <div class="min-w-0 flex-1">
      <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 ring-1 ring-white/20 px-3 py-1 text-xs font-medium"><?= wrd_icon('briefcase','w-3.5 h-3.5') ?> <?= is_hi()?'घटक-बी':'Component-B' ?></span>
      <h1 class="font-display font-semibold text-3xl sm:text-4xl lg:text-5xl leading-[1.1] mt-5 max-w-3xl"><?= is_hi()?'जल संसाधन विभाग':'Water Resources Department' ?></h1>
      <p class="text-cyan-100/90 text-lg mt-3 max-w-2xl"><?= is_hi()?'ठेकेदार पंजीकरण एवं सूचीयन पोर्टल':'Contractor Registration & Empanelment Portal' ?></p>
      <div class="flex flex-wrap gap-2 mt-7">
        <?php foreach (['GIGW 3.0','WCAG 2.1 AA','Aadhaar e-KYC','DigiLocker','हिंदी / English'] as $b): ?>
          <span class="text-[11px] bg-white/8 ring-1 ring-white/18 rounded-lg px-2.5 py-1.5 text-cyan-100/90">✓ <?= e($b) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php render_hero_portraits(); ?>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 -mt-8">
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <?php foreach ($actions as $a): ?>
      <a href="<?= e($a[2]) ?>" class="card p-4 lift text-center group">
        <div class="w-11 h-11 mx-auto rounded-xl grid place-items-center mb-2" style="background:color-mix(in srgb,<?= $ACC ?> 12%,#fff);color:<?= $ACC ?>"><?= wrd_icon($a[0], 'w-5 h-5') ?></div>
        <div class="text-[13px] font-semibold text-ink leading-tight"><?= e($a[1]) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 py-12">
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach ([
      [number_format($stats['registered']), is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors'],
      [number_format($stats['active']),     is_hi()?'सक्रिय ठेकेदार':'Active Contractors'],
      [number_format($stats['apps_year']),  is_hi()?'इस वर्ष आवेदन':'Applications This Year'],
      [$stats['avg_days'].' '.(is_hi()?'दिन':'Days'), is_hi()?'औसत स्वीकृति समय':'Average Approval Time'],
    ] as $s): ?>
      <div class="card p-5 text-center">
        <div class="font-display text-3xl font-semibold" style="color:<?= $ACC ?>"><?= e($s[0]) ?></div>
        <div class="text-[12px] text-slate-500 font-medium mt-1"><?= e($s[1]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php render_secretaries(); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
