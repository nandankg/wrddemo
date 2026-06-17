<?php
declare(strict_types=1);

/**
 * Leadership / dignitaries band shown on public home pages.
 * Data is pure (wrd_leaders); render_leaders() echoes the band.
 */

/** The WRD Jharkhand leadership, in protocol order. */
function wrd_leaders(): array {
    return [
        ['slug'=>'hemant-soren',    'name'=>'Shri Hemant Soren',    'name_hi'=>'श्री हेमंत सोरेन',
         'designation'=>"Hon'ble Chief Minister, Jharkhand",        'designation_hi'=>'माननीय मुख्यमंत्री, झारखंड'],
        ['slug'=>'hafizul-hassan',  'name'=>'Shri Hafizul Hassan',  'name_hi'=>'श्री हफीजुल हसन',
         'designation'=>"Hon'ble Minister, Water Resources Dept.",  'designation_hi'=>'माननीय मंत्री, जल संसाधन विभाग'],
        ['slug'=>'prashant-kumar',  'name'=>'Shri Prashant Kumar',  'name_hi'=>'श्री प्रशांत कुमार',
         'designation'=>'Secretary, WRD',                           'designation_hi'=>'सचिव, जल संसाधन विभाग'],
        ['slug'=>'joint-secretary', 'name'=>'Joint Secretary',      'name_hi'=>'संयुक्त सचिव',
         'designation'=>'Water Resources Department',               'designation_hi'=>'जल संसाधन विभाग'],
    ];
}

/** Initials from an English name, honorifics stripped (e.g. "Shri Hemant Soren" -> "HS"). */
function wrd_leader_initials(string $name): string {
    $skip = ['shri','smt','smt.','dr','dr.','mr','mr.','ms','ms.','mrs','mrs.','sri'];
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $words = array_values(array_filter($words, fn($w) => !in_array(mb_strtolower($w), $skip, true)));
    $out = '';
    foreach (array_slice($words, 0, 2) as $w) $out .= mb_strtoupper(mb_substr($w, 0, 1));
    return $out !== '' ? $out : '–';
}

/** Echo the "Our Leadership / नेतृत्व" band. Photos auto-load from assets/img/leaders/<slug>.jpg if present. */
function render_leaders(): void {
    $leaders = wrd_leaders();
    ?>
    <section class="max-w-7xl mx-auto px-4 py-10" aria-label="<?= is_hi()?'विभागीय नेतृत्व':'Departmental leadership' ?>">
      <div class="card p-6">
        <div class="flex items-center gap-2 mb-6">
          <span class="h-5 w-1.5 rounded bg-brand" aria-hidden="true"></span>
          <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'नेतृत्व':'Our Leadership' ?></h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
          <?php foreach ($leaders as $l):
            $name = is_hi() ? $l['name_hi'] : $l['name'];
            $desg = is_hi() ? $l['designation_hi'] : $l['designation'];
            $file = __DIR__ . '/../assets/img/leaders/' . $l['slug'] . '.jpg';
            $hasPhoto = is_file($file);
          ?>
            <div class="text-center">
              <?php if ($hasPhoto): ?>
                <img src="<?= base_url('assets/img/leaders/' . $l['slug'] . '.jpg') ?>" alt="<?= e($name) ?>"
                     class="w-24 h-24 mx-auto rounded-full object-cover ring-2 ring-brandsoft shadow-sm" width="96" height="96">
              <?php else: ?>
                <div class="w-24 h-24 mx-auto rounded-full grid place-items-center text-white text-2xl font-display font-semibold ring-2 ring-brandsoft shadow-sm"
                     style="background:linear-gradient(135deg,#06314a,#0E7C86)" role="img" aria-label="<?= e($name) ?>"><?= e(wrd_leader_initials($l['name'])) ?></div>
              <?php endif; ?>
              <div class="mt-3 font-semibold text-ink text-sm leading-tight"><?= e($name) ?></div>
              <div class="text-xs text-slate-500 mt-0.5 leading-tight"><?= e($desg) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}
