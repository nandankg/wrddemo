<?php
$ACTIVE='about'; $PAGE_TITLE='About the Department';
require_once __DIR__ . '/../includes/header.php';
$pdo=db();
$officers=[
  ['Hon\'ble Minister','माननीय मंत्री','Water Resources Department'],
  ['Secretary','सचिव','Anjali Verma, IAS'],
  ['Engineer-in-Chief','प्रधान अभियंता','R. K. Mahto'],
  ['Chief Engineer','मुख्य अभियंता','S. P. Singh'],
];
?>
<section class="water-hero text-white"><div class="max-w-7xl mx-auto px-4 py-12">
  <h1 class="font-display text-3xl sm:text-4xl font-semibold"><?= t('about') ?></h1>
  <p class="text-cyan-100/90 mt-2"><?= is_hi()?'दृष्टि, मिशन एवं संगठनात्मक संरचना':'Vision, mission & organisational structure' ?></p>
</div></section>
<section class="max-w-7xl mx-auto px-4 py-10 grid lg:grid-cols-3 gap-8">
  <div class="lg:col-span-2 space-y-6">
    <div class="card p-6"><h2 class="font-display text-xl font-semibold text-ink mb-2"><?= is_hi()?'दृष्टि':'Vision' ?></h2>
      <p class="text-slate-600"><?= is_hi()?'झारखंड के जल संसाधनों का न्यायसंगत, सतत एवं पारदर्शी प्रबंधन — सिंचाई, उद्योग एवं नागरिक कल्याण हेतु।':'Equitable, sustainable and transparent management of Jharkhand’s water resources for irrigation, industry and citizen welfare.' ?></p></div>
    <div class="card p-6"><h2 class="font-display text-xl font-semibold text-ink mb-2"><?= is_hi()?'मिशन':'Mission' ?></h2>
      <ul class="space-y-2 text-slate-600 text-sm">
        <li>• <?= is_hi()?'सिंचाई क्षमता का सृजन एवं विस्तार।':'Create and expand irrigation potential across the State.' ?></li>
        <li>• <?= is_hi()?'पारदर्शी जल आवंटन एवं राजस्व प्रबंधन।':'Transparent water allocation and revenue management.' ?></li>
        <li>• <?= is_hi()?'प्रौद्योगिकी-सक्षम, नागरिक-केंद्रित शासन।':'Technology-enabled, citizen-centric governance.' ?></li>
      </ul></div>
    <div class="card p-6"><h2 class="font-display text-xl font-semibold text-ink mb-2"><?= is_hi()?'नागरिक चार्टर':"Citizens' Charter" ?></h2>
      <p class="text-slate-600 text-sm"><?= is_hi()?'समयबद्ध सेवा वितरण की प्रतिबद्धता — पंजीकरण, आवंटन, बिलिंग एवं शिकायत निवारण।':'A commitment to time-bound service delivery — registration, allocation, billing and grievance redressal.' ?></p>
      <a href="#" class="inline-block mt-3 text-brand font-semibold text-sm">⬇ <?= is_hi()?'डाउनलोड (हिंदी / English) PDF':'Download (Hindi / English) PDF' ?></a></div>
  </div>
  <div>
    <div class="card p-6"><h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'नेतृत्व':'Leadership' ?></h2>
      <div class="space-y-4">
        <?php foreach($officers as $o): ?>
          <div class="flex items-center gap-3"><div class="w-11 h-11 rounded-full bg-brandsoft text-branddeep grid place-items-center font-display font-semibold"><?= mb_substr($o[2],0,1) ?></div>
            <div><div class="font-semibold text-sm text-ink"><?= is_hi()?e($o[1]):e($o[0]) ?></div><div class="text-xs text-slate-500"><?= e($o[2]) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card p-6 mt-6"><h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'संपर्क':'Contact' ?></h2>
      <p class="text-sm text-slate-600">Jal Bhawan, Doranda<br>Ranchi – 834002, Jharkhand</p>
      <p class="text-sm text-slate-500 mt-2">📧 wrd@jharkhand.gov.in</p></div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
