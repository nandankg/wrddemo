<?php
$ACTIVE='grievance'; $PAGE_TITLE='Grievance Redressal';
require_once __DIR__ . '/../includes/header.php';
$pdo=db(); $ref=null; $tracked=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['do']??'')==='file'){
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM grievances')->fetchColumn()+5;
    $ref=sprintf('WRD/GRV/2526/%04d',$cnt);
    $pdo->prepare("INSERT INTO grievances (ref_no,name,phone,category,division_id,description,status,sla_due,created_on) VALUES (?,?,?,?,?,?, 'New',?,CURDATE())")
        ->execute([$ref,trim($_POST['name']),trim($_POST['phone']),$_POST['category'],(int)$_POST['division_id'],trim($_POST['description']),date('Y-m-d',strtotime('+7 days'))]);
  } elseif(($_POST['do']??'')==='track'){
    $st=$pdo->prepare("SELECT g.*,d.name divn FROM grievances g LEFT JOIN divisions d ON d.id=g.division_id WHERE g.ref_no=?");
    $st->execute([trim($_POST['ref'])]); $tracked=$st->fetch();
  }
}
$divs=$pdo->query("SELECT id,name FROM divisions")->fetchAll();
$stats=$pdo->query("SELECT status,COUNT(*) c FROM grievances GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<section class="water-hero text-white"><div class="max-w-7xl mx-auto px-4 py-12">
  <h1 class="font-display text-3xl sm:text-4xl font-semibold"><?= t('grievance') ?></h1>
  <p class="text-cyan-100/90 mt-2"><?= is_hi()?'ओटीपी-आधारित · स्वतः-रूटिंग · एसएलए एस्केलेशन':'OTP-based · auto-routing · SLA escalation' ?></p>
</div></section>

<section class="max-w-7xl mx-auto px-4 py-10 grid lg:grid-cols-3 gap-8">
  <div class="lg:col-span-2">
    <?php if($ref): ?>
      <div class="card p-6 bg-emerald-50 ring-1 ring-emerald-200 mb-6">
        <div class="text-emerald-700 font-semibold">✓ <?= is_hi()?'शिकायत दर्ज':'Grievance registered' ?></div>
        <p class="text-sm text-slate-600 mt-1"><?= is_hi()?'संदर्भ संख्या':'Reference number' ?>: <b class="font-mono"><?= e($ref) ?></b> · <?= is_hi()?'एसएलए 7 दिन':'SLA 7 days' ?></p>
      </div>
    <?php endif; ?>
    <div class="card p-6">
      <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'नई शिकायत दर्ज करें':'Submit a Grievance' ?></h2>
      <form method="post" class="space-y-4"><input type="hidden" name="do" value="file">
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'नाम':'Name' ?></label><input name="name" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'मोबाइल':'Mobile' ?></label><input name="phone" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><span class="text-[11px] text-emerald-600">✓ OTP verified (demo)</span></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'श्रेणी':'Category' ?></label>
            <select name="category" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>Water Supply</option><option>Billing</option><option>Project</option><option>Registration</option><option>Other</option></select></div>
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'प्रमंडल':'Division' ?></label>
            <select name="division_id" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($divs as $d):?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach;?></select></div>
        </div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'विवरण':'Description' ?></label><textarea name="description" rows="3" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></textarea></div>
        <button class="bg-brand hover:bg-branddeep text-white font-semibold px-5 py-2.5 rounded-xl"><?= is_hi()?'शिकायत जमा करें':'Submit Grievance' ?></button>
      </form>
    </div>
  </div>

  <div>
    <div class="card p-6 mb-6">
      <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'शिकायत ट्रैक करें':'Track Grievance' ?></h2>
      <form method="post" class="flex gap-2"><input type="hidden" name="do" value="track">
        <input name="ref" placeholder="WRD/GRV/2526/0001" class="flex-1 border border-slate-300 rounded-xl px-3 py-2.5 text-sm">
        <button class="bg-ink text-white px-4 rounded-xl font-semibold text-sm"><?= t('search') ?></button>
      </form>
      <?php if($tracked): ?>
        <div class="mt-4 border-t border-slate-100 pt-4 text-sm">
          <div class="flex justify-between"><span class="text-slate-500"><?= is_hi()?'स्थिति':'Status' ?></span><?= badge($tracked['status']) ?></div>
          <div class="mt-2 text-slate-600"><?= e($tracked['category']) ?> · <?= e($tracked['divn']) ?></div>
          <div class="text-xs text-slate-400 mt-1"><?= e($tracked['description']) ?></div>
        </div>
      <?php elseif($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do']??'')==='track'): ?>
        <p class="text-sm text-rose-600 mt-3"><?= is_hi()?'कोई शिकायत नहीं मिली।':'No grievance found.' ?></p>
      <?php endif; ?>
    </div>
    <div class="card p-6">
      <h2 class="font-display text-lg font-semibold text-ink mb-3"><?= is_hi()?'निपटान आँकड़े':'Disposal Statistics' ?></h2>
      <?php foreach(['Resolved'=>'emerald','In Progress'=>'sky','Escalated'=>'orange','New'=>'indigo'] as $k=>$col): ?>
        <div class="flex items-center justify-between py-1.5"><span class="text-sm text-slate-600"><?= e($k) ?></span><span class="font-semibold text-<?= $col ?>-600"><?= (int)($stats[$k]??0) ?></span></div>
      <?php endforeach; ?>
      <p class="text-[11px] text-slate-400 mt-3"><?= is_hi()?'CPGRAMS / झारसेवा से एकीकृत।':'Integrated with CPGRAMS / JharSeva.' ?></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
