<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db(); $u=current_user();
$id=(int)($_GET['id']??0);
$b=$pdo->query("SELECT b.*,c.name cname,c.consumer_id cno,d.id div_id,d.name divn,d.bank_account,d.bank_name
  FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id
  WHERE b.id=$id")->fetch();
if(!$b){ http_response_code(404); exit('Bill not found.'); }

$done=false; $txn='';
if($_SERVER['REQUEST_METHOD']==='POST' && $b['status']==='Demand Raised'){
  $channel=$_POST['channel']??'JE-GRAS';
  $txn='GRAS'.strtoupper(bin2hex(random_bytes(4)));
  $pdo->prepare("INSERT INTO payments (txn_ref,bill_id,source_module,consumer_id,division_id,amount,channel,credited_account,status) VALUES (?,?, 'etariff',?,?,?,?,?, 'Success')")
      ->execute([$txn,$id,$b['consumer_id'],$b['div_id'],$b['total'],$channel,$b['bank_account']]);
  $pdo->prepare("UPDATE bills SET status='Paid',stage='Consumer' WHERE id=?")->execute([$id]);
  add_audit($pdo,'bill',$id,'Payment received','Consumer','Consumer',$u['name'],'₹'.number_format((float)$b['total'],2).' via '.$channel.' → '.$b['bank_account']);
  $done=true;
}

$LAYOUT='app'; $ACTIVE='etariff'; $PAGE_TITLE='Payment';
require __DIR__ . '/../../includes/header.php';
?>
<a href="index.php?id=<?= $id ?>" class="text-sm text-slate-500 hover:text-brand">← <?= is_hi()?'बिल':'Bill' ?> <?= e($b['bill_no']) ?></a>

<?php if($done): // ===== RECEIPT ===== ?>
  <div class="max-w-xl mx-auto mt-6">
    <div class="card p-8 text-center">
      <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center text-3xl mx-auto">✓</div>
      <h1 class="font-display text-2xl font-semibold text-ink mt-4"><?= is_hi()?'भुगतान सफल':'Payment Successful' ?></h1>
      <p class="text-sm text-slate-500 mt-1"><?= is_hi()?'राशि संबंधित प्रमंडल खाते में जमा कर दी गई है।':'Amount credited directly to the mapped division account.' ?></p>

      <!-- routing confirmation -->
      <div class="flex items-center justify-center gap-2 mt-6 text-xs">
        <span class="bg-paper rounded-lg px-3 py-2 font-medium"><?= is_hi()?'उपभोक्ता':'Consumer' ?></span>
        <span class="text-brand">──▶</span>
        <span class="bg-paper rounded-lg px-3 py-2 font-medium">JE-GRAS</span>
        <span class="text-brand">──▶</span>
        <span class="bg-emerald-50 ring-1 ring-emerald-200 rounded-lg px-3 py-2 font-semibold text-emerald-700"><?= e(preg_replace('/ (Division|Irrigation).*/','',$b['divn'])) ?></span>
      </div>

      <table class="w-full text-sm mt-6 text-left border border-slate-200 rounded-xl overflow-hidden">
        <tbody class="divide-y divide-slate-100">
          <tr><td class="bg-slate-50 px-4 py-2.5 text-slate-500 w-1/2">Transaction Ref</td><td class="px-4 py-2.5 font-mono"><?= e($txn) ?></td></tr>
          <tr><td class="bg-slate-50 px-4 py-2.5 text-slate-500">Bill No</td><td class="px-4 py-2.5"><?= e($b['bill_no']) ?></td></tr>
          <tr><td class="bg-slate-50 px-4 py-2.5 text-slate-500">Consumer</td><td class="px-4 py-2.5"><?= e($b['cname']) ?> (<?= e($b['cno']) ?>)</td></tr>
          <tr><td class="bg-slate-50 px-4 py-2.5 text-slate-500">Amount Paid</td><td class="px-4 py-2.5 font-semibold text-emerald-700"><?= inr_full((float)$b['total']) ?></td></tr>
          <tr><td class="bg-slate-50 px-4 py-2.5 text-slate-500"><?= is_hi()?'जमा खाता (प्रमंडल)':'Credited Account (Division)' ?></td><td class="px-4 py-2.5 font-mono"><?= e($b['bank_account']) ?><div class="text-xs text-slate-400 font-sans"><?= e($b['bank_name']) ?></div></td></tr>
        </tbody>
      </table>
      <div class="flex gap-2 mt-6">
        <button onclick="print()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-700">🖨 <?= is_hi()?'रसीद':'Receipt' ?></button>
        <a href="<?= base_url('index.php') ?>" class="flex-1 bg-brand text-white rounded-xl py-2.5 font-semibold text-center"><?= t('dashboard') ?></a>
      </div>
    </div>
  </div>

<?php else: // ===== PAY PAGE ===== ?>
  <div class="max-w-2xl mx-auto mt-6">
    <h1 class="font-display text-2xl font-semibold text-ink"><?= is_hi()?'एकल-विंडो भुगतान':'Single-Window Payment' ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'प्रत्येक भुगतान सीधे संबंधित प्रमंडल के निर्दिष्ट बैंक खाते में जाता है।':'Every payment is auto-routed to the consumer’s mapped division account.' ?></p>

    <!-- routing diagram -->
    <div class="card p-6 mt-5">
      <div class="relative flex items-center justify-between">
        <div class="text-center z-10"><div class="w-14 h-14 rounded-2xl bg-ink text-white grid place-items-center text-xl mx-auto">🏭</div><div class="text-xs font-medium mt-1.5"><?= is_hi()?'उपभोक्ता':'Consumer' ?></div><div class="text-[10px] text-slate-400"><?= e($b['cno']) ?></div></div>
        <div class="text-center z-10"><div class="w-14 h-14 rounded-2xl bg-brand text-white grid place-items-center text-xl mx-auto">🏦</div><div class="text-xs font-medium mt-1.5">JE-GRAS</div><div class="text-[10px] text-slate-400"><?= is_hi()?'कोषागार':'Treasury' ?></div></div>
        <div class="text-center z-10"><div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white grid place-items-center text-xl mx-auto">🏛</div><div class="text-xs font-medium mt-1.5"><?= e(preg_replace('/ (Division|Irrigation).*/','',$b['divn'])) ?></div><div class="text-[10px] text-slate-400 font-mono"><?= e($b['bank_account']) ?></div></div>
        <!-- track -->
        <div id="track" class="absolute left-7 right-7 top-7 h-0.5 bg-slate-200"></div>
      </div>
    </div>

    <form method="post" class="card p-6 mt-5">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100">
        <span class="text-slate-600"><?= is_hi()?'कुल देय':'Total Payable' ?></span>
        <span class="font-display text-2xl font-semibold text-ink"><?= inr_full((float)$b['total']) ?></span>
      </div>
      <label class="text-sm font-medium text-slate-700 mt-4 block"><?= is_hi()?'भुगतान माध्यम':'Payment Channel' ?></label>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-2">
        <?php foreach(['JE-GRAS','UPI','Net Banking','Debit Card','Credit Card','NEFT/RTGS'] as $i=>$ch): ?>
          <label class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm cursor-pointer hover:border-brand has-[:checked]:border-brand has-[:checked]:bg-brandsoft flex items-center gap-2">
            <input type="radio" name="channel" value="<?= $ch ?>" <?= $i===0?'checked':'' ?> class="accent-brand"><?= $ch ?>
          </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" onclick="return startRoute(event)" class="w-full mt-5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl">
        <?= is_hi()?'भुगतान करें एवं प्रमंडल खाते में रूट करें':'Pay & Route to Division Account' ?> →
      </button>
      <p class="text-[11px] text-slate-400 mt-3 text-center"><?= is_hi()?'सुरक्षित · TLS 1.3 · स्वतः मिलान (reconciliation)':'Secure · TLS 1.3 · auto-reconciliation' ?></p>
    </form>
  </div>

  <script>
  function startRoute(e){
    // play the routing animation, then submit
    e.preventDefault();
    const track=document.getElementById('track');
    const dot=document.createElement('span'); dot.className='flow-dot'; track.appendChild(dot);
    const btn=e.target; btn.disabled=true; btn.textContent='<?= is_hi()?"राशि रूट हो रही है…":"Routing payment…" ?>';
    setTimeout(()=>{ e.target.closest('form').submit(); }, 1700);
    return false;
  }
  </script>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
