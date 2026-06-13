<?php
$ACTIVE='services'; $PAGE_TITLE='Citizen Services';
require_once __DIR__ . '/../includes/header.php';
$cards=[
  ['contractor_reg','⚒','Register, renew & download certificate', base_url('app/contractor/index.php'),'from-sky-500 to-blue-600'],
  ['apply_alloc','🜄','Apply, track, renew & pay for industrial water', base_url('app/allocation/index.php'),'from-cyan-500 to-teal-600'],
  ['pay_bill','◫','View & pay water bills; raise grievance', base_url('app/etariff/index.php'),'from-teal-500 to-emerald-600'],
  ['rti','📄','File RTI applications & track status', base_url('public/rti.php'),'from-amber-500 to-orange-600'],
  ['grievance','🛟','Submit & track grievances with SLA', base_url('public/grievance.php'),'from-rose-500 to-pink-600'],
  ['schemes','📊','View live project progress (PPMS)', base_url('public/schemes.php'),'from-indigo-500 to-blue-700'],
];
?>
<section class="water-hero text-white"><div class="max-w-7xl mx-auto px-4 py-12">
  <h1 class="font-display text-3xl sm:text-4xl font-semibold"><?= t('services') ?></h1>
  <p class="text-cyan-100/90 mt-2"><?= is_hi()?'एकल नागरिक-सेवा प्रवेश द्वार — सभी पाँच मंचों से जुड़ा':'Unified citizen-services gateway — connected across all five platforms' ?></p>
</div></section>
<section class="max-w-7xl mx-auto px-4 py-12">
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach($cards as $c): ?>
      <a href="<?= $c[3] ?>" class="card p-6 lift group">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $c[4] ?> text-white grid place-items-center text-xl mb-4"><?= $c[1] ?></div>
        <h3 class="font-display text-lg font-semibold text-ink group-hover:text-brand"><?= t($c[0]) ?></h3>
        <p class="text-sm text-slate-500 mt-1"><?= e($c[2]) ?></p>
        <span class="text-brand text-sm font-semibold mt-3 inline-block"><?= is_hi()?'सेवा प्रारंभ करें':'Access service' ?> →</span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="card p-6 mt-8 bg-ink text-white flex flex-wrap items-center justify-between gap-4">
    <div><h3 class="font-display text-xl font-semibold"><?= is_hi()?'एकीकृत लॉगिन (SSO)':'Single Sign-On (SSO)' ?></h3>
    <p class="text-sm text-cyan-100/80 mt-1"><?= is_hi()?'एक पहचान — सभी सेवाओं हेतु। झारखंड SSO एवं डिजिलॉकर से एकीकृत।':'One identity for every service. Integrated with Jharkhand SSO & DigiLocker.' ?></p></div>
    <a href="<?= base_url('auth/login.php') ?>" class="bg-white text-ink font-semibold px-5 py-2.5 rounded-xl"><?= t('login') ?> →</a>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
