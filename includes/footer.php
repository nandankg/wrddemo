<?php
$LAYOUT = $LAYOUT ?? 'public';
if ($LAYOUT === 'app'): ?>
  </main>
</div>
<?php else: ?>
  </main>
  <footer class="bg-ink text-slate-300 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-4 gap-8">
      <div>
        <div class="flex items-center gap-3">
          <img src="<?= base_url('assets/img/jlogo.png') ?>" alt="<?= e(t('govt')) ?>"
               class="w-12 h-12 object-contain bg-white rounded-lg p-1 shrink-0" width="48" height="48">
          <div class="font-display font-semibold text-white text-lg leading-tight"><?= t('portal_name') ?></div>
        </div>
        <p class="text-sm text-slate-400 mt-3"><?= t('govt') ?></p>
        <p class="text-sm text-slate-400 mt-3">Jal Bhawan, Doranda,<br>Ranchi – 834002, Jharkhand</p>
      </div>
      <div>
        <p class="font-semibold text-white mb-3"><?= t('services') ?></p>
        <ul class="space-y-2 text-sm">
          <li><a class="hover:text-white" href="<?= base_url('public/services.php') ?>"><?= t('apply_alloc') ?></a></li>
          <li><a class="hover:text-white" href="<?= base_url('public/services.php') ?>"><?= t('pay_bill') ?></a></li>
          <li><a class="hover:text-white" href="<?= base_url('public/services.php') ?>"><?= t('contractor_reg') ?></a></li>
          <li><a class="hover:text-white" href="<?= base_url('public/rti.php') ?>"><?= t('rti') ?></a></li>
        </ul>
      </div>
      <div>
        <p class="font-semibold text-white mb-3">Policies (GIGW)</p>
        <ul class="space-y-2 text-sm text-slate-400">
          <li>Privacy Policy · Terms of Use</li>
          <li>Copyright · Hyperlinking Policy</li>
          <li>Accessibility Statement</li>
          <li>Web Information Manager</li>
        </ul>
      </div>
      <div>
        <p class="font-semibold text-white mb-3">Compliance</p>
        <div class="flex flex-wrap gap-2 text-[11px]">
          <span class="bg-white/10 px-2 py-1 rounded">GIGW 3.0</span>
          <span class="bg-white/10 px-2 py-1 rounded">WCAG 2.1 AA</span>
          <span class="bg-white/10 px-2 py-1 rounded">CERT-In VAPT</span>
          <span class="bg-white/10 px-2 py-1 rounded">DPDP 2023</span>
          <span class="bg-white/10 px-2 py-1 rounded">Bilingual</span>
        </div>
        <div class="mt-4 text-xs text-slate-400">
          <span id="visitor-count">Visitors: 4,82,317</span><br>Last Updated: <?= date('d M Y') ?>
        </div>
      </div>
    </div>
    <div class="border-t border-white/10">
      <div class="max-w-7xl mx-auto px-4 py-4 text-xs text-slate-400 flex flex-col sm:flex-row justify-between gap-2">
        <span>© <?= date('Y') ?> Water Resources Department, Government of Jharkhand. All rights reserved.</span>
        <span class="text-slate-500">Demonstration build · Production on React 18 + PostgreSQL 16 per RFP</span>
      </div>
    </div>
  </footer>
<?php endif; ?>
</body>
</html>
