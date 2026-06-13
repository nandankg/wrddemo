<?php
$ACTIVE='tenders'; $PAGE_TITLE='Tenders & Notices';
require_once __DIR__ . '/../includes/header.php';
$pdo=db();
$type=$_GET['type']??'';
$sql="SELECT * FROM content WHERE status='Published'".($type?" AND type=?":'')." ORDER BY publish_date DESC";
$st=$pdo->prepare($sql); $st->execute($type?[$type]:[]); $rows=$st->fetchAll();
?>
<section class="water-hero text-white"><div class="max-w-7xl mx-auto px-4 py-12">
  <h1 class="font-display text-3xl sm:text-4xl font-semibold"><?= t('tenders') ?></h1>
  <p class="text-cyan-100/90 mt-2"><?= is_hi()?'निविदाएँ, सूचनाएँ, आदेश एवं समाचार':'Tenders, notices, orders & news — auto-published from CMS' ?></p>
</div></section>

<section class="max-w-7xl mx-auto px-4 py-10">
  <div class="flex flex-wrap gap-2 mb-6">
    <?php foreach(['','tender','notice','order','news','scheme'] as $t): ?>
      <a href="?<?= $t?'type='.$t:'' ?>" class="px-4 py-2 rounded-full text-sm font-medium <?= $type===$t?'bg-brand text-white':'bg-white border border-slate-200 text-slate-600 hover:border-brand' ?>"><?= $t?ucfirst($t):($GLOBALS['LANG']==='hi'?'सभी':'All') ?></a>
    <?php endforeach; ?>
  </div>
  <div class="grid md:grid-cols-2 gap-4">
    <?php foreach($rows as $r): ?>
      <div class="card p-5 lift">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold px-2 py-0.5 rounded bg-brandsoft text-branddeep uppercase"><?= e($r['type']) ?></span>
          <span class="text-xs text-slate-400"><?= date('d M Y',strtotime($r['publish_date'])) ?></span>
        </div>
        <h3 class="font-display text-lg font-semibold text-ink leading-snug"><?= bi($r['title'],$r['title_hi']) ?></h3>
        <p class="text-sm text-slate-600 mt-2"><?= bi($r['body'],$r['body_hi']) ?></p>
        <div class="flex items-center gap-3 mt-3 text-xs">
          <span class="text-slate-400"><?= e($r['category']) ?></span>
          <a href="https://jharkhandtenders.gov.in" target="_blank" class="text-brand font-semibold ml-auto"><?= is_hi()?'e-प्रोक्योरमेंट पर':'View on e-Procurement' ?> →</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
