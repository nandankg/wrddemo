<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db();
$all = $pdo->query("SELECT * FROM contractors ORDER BY status='Blacklisted' DESC, name")->fetchAll();
$today = date('Y-m-d');
$f = [
  'district' => trim((string)($_GET['district'] ?? '')),
  'class'    => trim((string)($_GET['class'] ?? '')),
  'category' => trim((string)($_GET['category'] ?? '')),
  'status'   => trim((string)($_GET['status'] ?? '')),
];
$contractors = contractor_filter($all, $f, $today);
$matrix = contractor_empanelment_matrix($all, $today);
$districts = array_values(array_unique(array_filter(array_map(fn($c)=>$c['district'] ?? '', $all))));
sort($districts);
$categories = ['Civil','Mechanical','Electrical','Irrigation'];
$statuses = ['Active','Suspended','Expired','Blacklisted'];

set_app_context('contractor');
app_require_access('registry');
$LAYOUT='app'; $ACTIVE='registry'; $PAGE_TITLE='Registered Contractors';
require __DIR__ . '/../../includes/header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'पंजीकृत ठेकेदार':'Registered Contractors' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'श्रेणी · जोखिम स्कोरिंग · ब्लैकलिस्ट':'Class · risk scoring · blacklist' ?></p></div>
</div>
<!-- Empanelment by class -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach (['I','II','III','IV'] as $cls): $row=$matrix[$cls]; ?>
    <div class="card p-4">
      <div class="flex items-center justify-between mb-2">
        <span class="font-display font-semibold text-ink"><?= is_hi()?'श्रेणी':'Class' ?>-<?= e($cls) ?></span>
        <span class="text-xs text-slate-400"><?= $row['active']+$row['suspended']+$row['expired'] ?></span>
      </div>
      <div class="flex gap-3 text-xs">
        <span class="text-emerald-700 font-semibold"><?= (int)$row['active'] ?> <?= is_hi()?'सक्रिय':'Active' ?></span>
        <span class="text-amber-700 font-semibold"><?= (int)$row['suspended'] ?> <?= is_hi()?'निलंबित':'Susp.' ?></span>
        <span class="text-rose-700 font-semibold"><?= (int)$row['expired'] ?> <?= is_hi()?'समाप्त':'Exp.' ?></span>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="get" class="card p-4 mb-6 flex flex-wrap items-end gap-3">
  <?php
    $sel = function(string $name, array $opts, string $cur, string $anyLabel) {
      echo '<div><label class="block text-xs text-slate-500 mb-1">'.e($name).'</label><select name="'.e(strtolower($name)).'" class="border border-slate-300 rounded-xl px-3 py-2 text-sm">';
      echo '<option value="">'.e($anyLabel).'</option>';
      foreach ($opts as $o) echo '<option value="'.e($o).'"'.($o===$cur?' selected':'').'>'.e($o).'</option>';
      echo '</select></div>';
    };
    $sel(is_hi()?'District':'District', $districts, $f['district'], is_hi()?'सभी जिले':'All districts');
    $sel(is_hi()?'Class':'Class', ['I','II','III','IV'], $f['class'], is_hi()?'सभी':'All');
    $sel('Category', $categories, $f['category'], is_hi()?'सभी श्रेणियाँ':'All categories');
    $sel('Status', $statuses, $f['status'], is_hi()?'सभी':'All');
  ?>
  <button class="btn-acc font-semibold px-4 py-2 rounded-xl text-sm"><?= is_hi()?'फ़िल्टर':'Filter' ?></button>
  <a href="<?= base_url('app/contractor/registry.php') ?>" class="text-sm text-slate-500 px-2 py-2"><?= is_hi()?'रीसेट':'Reset' ?></a>
  <span class="text-xs text-slate-400 ml-auto"><?= count($contractors) ?> <?= is_hi()?'परिणाम':'results' ?></span>
</form>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
      <th class="text-left px-4 py-3"><?= is_hi()?'ठेकेदार':'Contractor' ?></th><th class="text-left px-4 py-3">Class</th>
      <th class="text-left px-4 py-3 hidden md:table-cell">GST</th><th class="text-left px-4 py-3"><?= is_hi()?'जोखिम':'Risk' ?></th>
      <th class="text-left px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($contractors as $c): [$rb,$rc]=risk_band((int)$c['risk_score']); ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><div class="font-medium text-slate-800"><?= bi($c['name'],$c['name_hi']) ?></div><div class="text-xs text-slate-400 font-mono"><?= e($c['reg_no']) ?> · <?= e($c['district']) ?></div></td>
          <td class="px-4 py-3"><span class="inline-grid place-items-center w-7 h-7 rounded-lg bg-ink text-white text-xs font-bold"><?= e($c['class']) ?></span></td>
          <td class="px-4 py-3 text-xs font-mono text-slate-500 hidden md:table-cell"><?= e($c['gst']) ?></td>
          <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-1 rounded-full <?= $rc ?>"><?= $rb ?> · <?= (int)$c['risk_score'] ?></span></td>
          <td class="px-4 py-3"><?= badge(contractor_effective_status($c, $today)) ?></td>
          <td class="px-4 py-3 text-right"><?php if($c['status']==='Active'): ?><a href="<?= base_url('app/contractor/certificate.php') ?>?id=<?= $c['id'] ?>" target="_blank" class="text-sm font-semibold" style="color:<?= e($APP['accent']) ?>">Cert →</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3">🔴 <?= is_hi()?'ब्लैकलिस्ट सार्वजनिक रूप से प्रदर्शित (आरएफपी पारदर्शिता)।':'Blacklisted contractors shown publicly per RFP transparency requirement.' ?></p>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
