<?php
declare(strict_types=1);

/**
 * Leadership / dignitaries shown on public home pages.
 * Data is pure (wrd_leaders); the render_* helpers echo HTML.
 *
 *  - render_hero_portraits()  : CM + Minister, stacked, for the dark hero's right column.
 *  - render_secretaries()     : Secretariat band (Secretary, Addl. Secretary, Joint Secretary), below the hero.
 */

/** The WRD Jharkhand leadership, in protocol order. `tier` groups them for layout. */
function wrd_leaders(): array {
    return [
        ['slug'=>'hemant-soren',         'tier'=>'hero',      'name'=>'Shri Hemant Soren',   'name_hi'=>'श्री हेमंत सोरेन',
         'designation'=>"Hon'ble Chief Minister, Jharkhand",       'designation_hi'=>'माननीय मुख्यमंत्री, झारखंड'],
        ['slug'=>'hafizul-hassan',       'tier'=>'hero',      'name'=>'Shri Hafizul Hassan', 'name_hi'=>'श्री हफीजुल हसन',
         'designation'=>"Hon'ble Minister, Water Resources Dept.", 'designation_hi'=>'माननीय मंत्री, जल संसाधन विभाग'],
        ['slug'=>'prashant-kumar',       'tier'=>'secretariat','name'=>'Shri Prashant Kumar','name_hi'=>'श्री प्रशांत कुमार',
         'designation'=>'Secretary, WRD',                          'designation_hi'=>'सचिव, जल संसाधन विभाग'],
        ['slug'=>'additional-secretary', 'tier'=>'secretariat','name'=>'Additional Secretary','name_hi'=>'अपर सचिव',
         'designation'=>'Water Resources Department',              'designation_hi'=>'जल संसाधन विभाग'],
        ['slug'=>'bijay-kumar-bhagat',   'tier'=>'secretariat','name'=>'Shri Bijay Kumar Bhagat','name_hi'=>'श्री विजय कुमार भगत',
         'designation'=>'Joint Secretary (Engineering)',           'designation_hi'=>'संयुक्त सचिव (अभियांत्रिकी)'],
    ];
}

/** Leaders belonging to a given tier ('hero' | 'secretariat'), in protocol order. */
function wrd_leaders_in(string $tier): array {
    return array_values(array_filter(wrd_leaders(), fn($l) => $l['tier'] === $tier));
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

/**
 * Photo markup for one leader: the portrait if assets/img/leaders/<slug>.jpg exists,
 * otherwise a dignified initials placeholder. $shapeCls sizes/rounds both variants.
 */
function wrd_leader_photo(string $slug, string $name, string $shapeCls, string $textCls): string {
    $file = __DIR__ . '/../assets/img/leaders/' . $slug . '.jpg';
    if (is_file($file)) {
        return '<img src="' . e(base_url('assets/img/leaders/' . $slug . '.jpg')) . '" alt="' . e($name) . '"'
             . ' class="' . $shapeCls . ' object-cover" loading="lazy">';
    }
    return '<div class="' . $shapeCls . ' grid place-items-center font-display font-semibold ' . $textCls . '"'
         . ' style="background:linear-gradient(135deg,#06314a,#0E7C86)" role="img" aria-label="' . e($name) . '">'
         . e(wrd_leader_initials($name)) . '</div>';
}

/**
 * CM + Minister as stacked portrait cards, styled for the dark hero. Drop this inside
 * the hero's right column. Renders nothing if there are no hero-tier leaders.
 */
function render_hero_portraits(): void {
    $leaders = wrd_leaders_in('hero');
    if (!$leaders) return;
    ?>
    <div class="flex flex-col gap-3 w-full lg:w-80 shrink-0" aria-label="<?= is_hi()?'राजनीतिक नेतृत्व':'Political leadership' ?>">
      <?php foreach ($leaders as $l):
        $name = is_hi() ? $l['name_hi'] : $l['name'];
        $desg = is_hi() ? $l['designation_hi'] : $l['designation'];
      ?>
        <div class="flex items-center gap-3 rounded-2xl bg-white/10 ring-1 ring-white/15 p-3">
          <?= wrd_leader_photo($l['slug'], $name, 'w-16 h-16 rounded-xl ring-2 ring-white/25 shrink-0', 'text-white text-lg') ?>
          <div class="min-w-0">
            <div class="font-semibold text-white text-sm leading-tight"><?= e($name) ?></div>
            <div class="text-[11px] text-cyan-100/80 mt-0.5 leading-snug"><?= e($desg) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

/** Secretariat band (Secretary, Additional Secretary, Joint Secretary), shown below the hero. */
function render_secretaries(): void {
    $leaders = wrd_leaders_in('secretariat');
    if (!$leaders) return;
    ?>
    <section class="max-w-7xl mx-auto px-4 py-10" aria-label="<?= is_hi()?'विभागीय नेतृत्व':'Departmental leadership' ?>">
      <div class="card p-6">
        <div class="flex items-center gap-2 mb-6">
          <span class="h-5 w-1.5 rounded bg-brand" aria-hidden="true"></span>
          <h2 class="font-display text-xl font-semibold text-ink"><?= is_hi()?'विभागीय नेतृत्व':'Departmental Leadership' ?></h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 max-w-3xl mx-auto">
          <?php foreach ($leaders as $l):
            $name = is_hi() ? $l['name_hi'] : $l['name'];
            $desg = is_hi() ? $l['designation_hi'] : $l['designation'];
          ?>
            <div class="text-center">
              <?= wrd_leader_photo($l['slug'], $name, 'w-24 h-24 mx-auto rounded-full ring-2 ring-brandsoft shadow-sm', 'text-white text-2xl') ?>
              <div class="mt-3 font-semibold text-ink text-sm leading-tight"><?= e($name) ?></div>
              <div class="text-xs text-slate-500 mt-0.5 leading-tight"><?= e($desg) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}
