<?php
declare(strict_types=1);

function render_sidebar(string $active): void {
    $items = [
        ['dashboard',   t('command_centre'), base_url('app/dashboard.php'),        '▤'],
        ['ppms_req',    t('fund_req'),       base_url('app/ppms/requisitions.php'), '₹'],
        ['ppms_reports',t('reports'),        base_url('app/ppms/reports.php'),      '▦'],
        ['contractor',  t('contractor_reg'), base_url('app/contractor/index.php'),  '⚒'],
        ['allocation',  t('allocation'),     base_url('app/allocation/index.php'),  '🜄'],
        ['etariff',     t('etariff'),        base_url('app/etariff/index.php'),     '◫'],
        ['cms',         t('cms'),            base_url('app/cms/index.php'),         '✎'],
    ];
    $roles = ['SECRETARY','EIC','CE','SE','EE','AE','JE','FINANCE','ADMIN','CONSUMER','CONTRACTOR'];
    $cur = user_role();
    ?>
    <aside class="hidden lg:flex flex-col w-64 shrink-0 bg-ink text-white px-3 py-5 gap-1">
      <div class="px-2 pb-3 mb-2 border-b border-white/10">
        <p class="text-[11px] uppercase tracking-wider text-cyan-300/80 font-semibold">Integrated Suite</p>
        <p class="text-sm text-slate-300 mt-0.5">5 Components · One Backbone</p>
      </div>
      <?php foreach ($items as [$key,$label,$url,$icon]): ?>
        <a href="<?= $url ?>" class="nav-link <?= $active===$key?'active':'' ?>">
          <span class="w-5 text-center text-base"><?= $icon ?></span><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>

      <!-- Demo role switcher -->
      <div class="mt-auto pt-4 border-t border-white/10">
        <label class="text-[11px] uppercase tracking-wider text-cyan-300/80 font-semibold px-2">Demo · Switch Role</label>
        <select onchange="if(this.value)location.href='<?= base_url('auth/role_switch.php') ?>?role='+this.value"
                class="mt-1.5 w-full bg-ink2 border border-white/15 text-slate-100 text-sm rounded-lg px-2 py-2 focus:outline-none">
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r ?>" <?= $cur===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-slate-400 mt-2 px-2">Jump across the approval hierarchy instantly during the presentation.</p>
      </div>
    </aside>
    <?php
}
