<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
etariff_require_login();
$pdo=db(); $u=current_user(); $role=user_role();
$view=etariff_role_view($role);

// Consumer scoping
$isConsumer = $view==='consumer';
$myIds = [];
if ($isConsumer) {
  $st=$pdo->prepare("SELECT id FROM consumers WHERE login_user=?"); $st->execute([$u['username']]);
  $myIds = array_map('intval', array_column($st->fetchAll(),'id'));
}
if ($isConsumer && $myIds) {
  $in=implode(',',$myIds);
  $bills=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,d.name divn FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id WHERE b.consumer_id IN ($in) ORDER BY b.id DESC")->fetchAll();
} elseif ($isConsumer) {
  $bills=[];
} else {
  $bills=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,d.name divn FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id ORDER BY b.id DESC")->fetchAll();
}
$k=etariff_bill_kpis($bills);
$tasks=etariff_pending_actions($role,$bills);

// Revenue MIS data (revenue view only) — E-Tariff payments only
$revDiv=[]; $monthly=[];
if ($view==='revenue') {
  $revDiv=$pdo->query("SELECT d.name, COALESCE(SUM(p.amount),0) amt FROM divisions d
    LEFT JOIN payments p ON p.division_id=d.id AND p.status='Success' AND p.source_module='etariff'
    GROUP BY d.id ORDER BY amt DESC")->fetchAll();
  $monthly=$pdo->query("SELECT DATE_FORMAT(paid_on,'%b %Y') m, SUM(amount) amt FROM payments
    WHERE status='Success' AND source_module='etariff' GROUP BY DATE_FORMAT(paid_on,'%Y-%m') ORDER BY MIN(paid_on)")->fetchAll();
}

set_app_context('etariff');
$LAYOUT='app'; $ACTIVE='dashboard'; $PAGE_TITLE='Revenue & Billing';
if ($view==='revenue') {
  $EXTRA_HEAD = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
}
require __DIR__ . '/../../includes/header.php';

$viewLabel=[
  'consumer'=>is_hi()?'मेरे जल बिल':'My Water Bills',
  'billing' =>is_hi()?'बिलिंग डेस्क':'Billing Desk',
  'revenue' =>is_hi()?'राजस्व एवं संग्रह केंद्र':'Revenue & Collection Centre',
][$view];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= e($viewLabel) ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'नमस्ते':'Welcome' ?>, <span class="font-medium text-slate-700"><?= e($u['name']) ?></span> · <?= e(role_label()) ?></p>
  </div>
  <span class="text-xs text-slate-500 bg-white border border-slate-200 rounded-full px-3 py-1.5">● <?= is_hi()?'लाइव डेटा':'Live data' ?> · <?= date('d M Y, H:i') ?></span>
</div>

<!-- KPI row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $kpis = $view==='revenue' ? [
    [is_hi()?'संग्रहित राजस्व':'Revenue Collected', inr($k['collected']), 'text-emerald-700'],
    [is_hi()?'बकाया मांग':'Outstanding Demand', inr($k['outstanding']), 'text-rose-700'],
    [is_hi()?'भुगतान किए बिल':'Bills Paid', (string)$k['paid'], 'text-ink'],
    [is_hi()?'मांग जारी':'Demands Raised', (string)$k['demand_raised'], 'text-amber-700'],
  ] : ($view==='consumer' ? [
    [is_hi()?'देय राशि':'Amount Due', inr($k['outstanding']), 'text-rose-700'],
    [is_hi()?'भुगतान किए बिल':'Bills Paid', (string)$k['paid'], 'text-emerald-700'],
    [is_hi()?'कुल बिल':'Total Bills', (string)count($bills), 'text-ink'],
    [is_hi()?'मांग जारी':'Awaiting Payment', (string)$k['demand_raised'], 'text-amber-700'],
  ] : [
    [is_hi()?'ड्राफ्ट (JE)':'Drafts (JE)', (string)$k['draft'], 'text-slate-700'],
    [is_hi()?'सत्यापन हेतु (AE)':'To Verify (AE)', (string)$k['pending'], 'text-amber-700'],
    [is_hi()?'मांग हेतु (EE)':'To Raise (EE)', (string)$k['approved'], 'text-sky-700'],
    [is_hi()?'बकाया मांग':'Outstanding', inr($k['outstanding']), 'text-rose-700'],
  ]);
  foreach ($kpis as $kp): ?>
    <div class="card acc-kpi p-5 lift">
      <div class="text-[12px] text-slate-500 font-medium"><?= $kp[0] ?></div>
      <div class="font-display text-3xl font-semibold mt-1 <?= $kp[2] ?>"><?= $kp[1] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <?php if ($view==='revenue'): ?>
      <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'प्रमंडल-वार राजस्व (जेई-ग्रास)':'Division-wise Revenue (JE-GRAS)' ?></h2>
        <canvas id="revChart" height="130"></canvas>
      </div>
      <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'मासिक संग्रह':'Monthly Collection' ?></h2>
        <canvas id="moChart" height="110"></canvas>
      </div>
    <?php else: ?>
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-display text-lg font-semibold text-ink"><?= $isConsumer?(is_hi()?'मेरे बिल':'My Bills'):(is_hi()?'हाल के बिल':'Recent Bills') ?></h2>
          <a href="<?= base_url('app/etariff/bills.php') ?>" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>"><?= t('view_all') ?> →</a>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
            <tr><th class="text-left px-4 py-3">Bill No</th><?php if(!$isConsumer):?><th class="text-left px-4 py-3"><?= is_hi()?'उपभोक्ता':'Consumer' ?></th><?php endif;?><th class="text-right px-4 py-3"><?= is_hi()?'राशि':'Amount' ?></th><th class="text-left px-4 py-3">Status</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach (array_slice($bills,0,8) as $r): ?>
              <tr class="hover:bg-paper cursor-pointer" onclick="location.href='<?= base_url('app/etariff/bills.php') ?>?id=<?= $r['id'] ?>'">
                <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($r['bill_no']) ?></td>
                <?php if(!$isConsumer):?><td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['cname'],$r['cname_hi']) ?></td><?php endif;?>
                <td class="px-4 py-3 text-right font-semibold text-ink"><?= inr((float)$r['total']) ?></td>
                <td class="px-4 py-3"><?= badge($r['status']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(!$bills): ?><tr><td colspan="4" class="px-4 py-10 text-center text-slate-400 text-sm"><?= is_hi()?'कोई बिल नहीं।':'No bills.' ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Pending actions -->
  <div class="card p-5">
    <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'आपकी लंबित कार्रवाई':'Your Pending Actions' ?></h2>
    <?php if ($tasks): ?>
      <div class="space-y-2">
        <?php foreach ($tasks as $tk): ?>
          <a href="<?= base_url('app/etariff/'.$tk['url']) ?>" class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-slate-100 hover:bg-paper">
            <div class="min-w-0"><p class="text-sm font-medium text-slate-700 truncate"><?= e($tk['label']) ?></p><p class="text-xs text-slate-400"><?= inr((float)$tk['meta']) ?></p></div>
            <?= badge($tk['status']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-10 text-slate-400 text-sm">
        <div class="text-4xl mb-2">✓</div>
        <?= is_hi()?'कोई लंबित कार्य नहीं।':'No pending tasks.' ?><br>
        <span class="text-xs"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे)।':'Switch role (bottom-left) to see other workflows.' ?></span>
      </div>
    <?php endif; ?>
    <a href="<?= base_url('app/etariff/bills.php') ?>" class="block text-center mt-4 text-sm font-semibold hover:underline" style="color:<?= e($APP['accent']) ?>"><?= is_hi()?'सभी बिल':'All bills' ?> →</a>
  </div>
</div>

<?php if ($view==='revenue'): ?>
<script>
const REVDIV = <?= json_encode($revDiv, JSON_UNESCAPED_UNICODE) ?>;
const MONTHLY = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const acc = '<?= e($APP['accent']) ?>';
new Chart(document.getElementById('revChart'),{
  type:'bar',
  data:{labels:REVDIV.map(r=>r.name.replace(/ (Division|Irrigation|Reservoir|Water Ways|Canal).*/,'')),
    datasets:[{label:'Revenue (₹)',data:REVDIV.map(r=>+r.amt),backgroundColor:acc,borderRadius:6}]},
  options:{plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'₹'+(v/100000).toFixed(1)+'L'}}}}
});
new Chart(document.getElementById('moChart'),{
  type:'line',
  data:{labels:MONTHLY.map(m=>m.m),datasets:[{label:'Collection (₹)',data:MONTHLY.map(m=>+m.amt),borderColor:acc,backgroundColor:acc+'22',fill:true,tension:.35}]},
  options:{plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'₹'+(v/100000).toFixed(1)+'L'}}}}
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
