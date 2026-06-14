<?php
declare(strict_types=1);
require_once __DIR__ . '/app_context.php';

/** Pure: nav items for the active product (testable without rendering). */
function app_sidebar_items(): array {
    return app_nav();
}

/** Render the themed, per-product sidebar. */
function render_app_sidebar(string $active): void {
    $ctx = app_ctx();
    if (!$ctx) return;
    $acc   = $ctx['accent'];
    $roles = $ctx['roles'];
    $cur   = function_exists('user_role') ? user_role() : null;
    $items = app_nav_visible(app_sidebar_items(), $cur);
    ?>
    <aside class="hidden lg:flex flex-col w-64 shrink-0 text-white px-3 py-5 gap-1"
           style="background:#0a263d">
      <div class="px-2 pb-3 mb-2 border-b border-white/10 flex items-center gap-2">
        <span class="w-8 h-8 rounded-lg grid place-items-center text-lg"
              style="background:<?= e($acc) ?>1f;color:<?= e($acc) ?>"><?= $ctx['icon'] ?></span>
        <div>
          <p class="text-sm font-semibold leading-tight"><?= e($ctx['short']) ?></p>
          <p class="text-[11px] text-slate-400"><?= is_hi() ? e($ctx['name_hi']) : e($ctx['name']) ?></p>
        </div>
      </div>
      <?php foreach ($items as $it): ?>
        <a href="<?= base_url($it['url']) ?>"
           class="nav-link <?= $active===$it['key']?'active':'' ?>"
           style="<?= $active===$it['key'] ? '--acc:'.e($acc).';' : '' ?>">
          <span class="w-5 text-center text-base"><?= $it['icon'] ?></span><span><?= e($it['label']) ?></span>
        </a>
      <?php endforeach; ?>

      <div class="mt-auto pt-4 border-t border-white/10">
        <label class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold px-2">Demo · Switch Role</label>
        <select onchange="if(this.value)location.href='<?= base_url('auth/role_switch.php') ?>?role='+this.value"
                class="mt-1.5 w-full bg-ink2 border border-white/15 text-slate-100 text-sm rounded-lg px-2 py-2 focus:outline-none">
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r) ?>" <?= $cur===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-slate-400 mt-2 px-2"><?= is_hi()?'इस उत्पाद की भूमिकाओं के बीच स्विच करें।':'Switch across this product\'s roles during the demo.' ?></p>
      </div>
    </aside>
    <?php
}
