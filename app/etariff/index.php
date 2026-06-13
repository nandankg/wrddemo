<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db(); $u=current_user(); $role=user_role(); $actor=$u['name'].' ('.$role.')';

function compute_bill(float $consumption, float $excess): array {
  $fixed=25000.0; $variable=round($consumption*0.85,2); $excessChg=round($excess*2.10,2);
  $penalty=0.0; $interest=0.0; $sub=$fixed+$variable+$excessChg+$penalty+$interest;
  $gst=round($sub*0.18,2); $total=round($sub+$gst,2);
  return compact('fixed','variable','excessChg','penalty','interest','gst','total');
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act=$_POST['action']??'';
  if ($act==='create') { // JE creates drawal + draft bill
    $cid=(int)$_POST['consumer_id']; $prev=(float)$_POST['prev']; $curr=(float)$_POST['curr'];
    $alloc=(float)$pdo->query("SELECT allocation_qty FROM consumers WHERE id=$cid")->fetchColumn();
    $consumption=max(0,$curr-$prev);
    $excess=max(0,$consumption-($alloc*1000)); // simple excess heuristic vs allocation
    $anomaly=$excess>0?1:0;
    $pdo->prepare("INSERT INTO drawal_entries (consumer_id,period,prev_reading,curr_reading,consumption,excess,anomaly,entered_by,entered_on) VALUES (?,?,?,?,?,?,?,?,CURDATE())")
        ->execute([$cid,$_POST['period'],$prev,$curr,$consumption,$excess,$anomaly,$u['id']]);
    $did=(int)$pdo->lastInsertId();
    $b=compute_bill($consumption,$excess);
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM bills')->fetchColumn()+1;
    $bno=sprintf('WRD/BILL/2526/%05d',$cnt);
    $pdo->prepare("INSERT INTO bills (bill_no,consumer_id,drawal_id,period,fixed_charge,variable_charge,excess_charge,penalty,interest,gst,total,status,stage,created_on) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'Draft','Draft',CURDATE())")
        ->execute([$bno,$cid,$did,$_POST['period'],$b['fixed'],$b['variable'],$b['excessChg'],$b['penalty'],$b['interest'],$b['gst'],$b['total']]);
    $bid=(int)$pdo->lastInsertId();
    add_audit($pdo,'bill',$bid,'Drawal entered & draft bill prepared','JE','JE',$actor,'Consumption '.number_format($consumption).' units'.($anomaly?' · ⚠ anomaly flagged':''));
    flash("Draft bill $bno prepared".($anomaly?' (anomaly flagged)':'').'.');
    header('Location: ?id='.$bid); exit;
  }
  $id=(int)($_POST['id']??0); $bill=$pdo->query("SELECT * FROM bills WHERE id=$id")->fetch();
  if($bill){ $rem=trim($_POST['remarks']??'');
    switch($act){
      case 'submit': $pdo->prepare("UPDATE bills SET status='Pending Verification',stage='AE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Submitted for verification','JE','AE',$actor,$rem); flash('Submitted to AE.'); break;
      case 'verify': $pdo->prepare("UPDATE bills SET status='Approved',stage='EE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Verified','AE','EE',$actor,$rem?:'Consumption & tariff verified.'); flash('Verified → EE.'); break;
      case 'raise': $pdo->prepare("UPDATE bills SET status='Demand Raised',stage='Consumer' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Demand approved & raised','EE','Consumer',$actor,$rem?:'Final demand approved.'); flash('Demand raised. Payment link active.'); break;
      case 'return': $pdo->prepare("UPDATE bills SET status='Draft',stage='JE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'bill',$id,'Returned for correction',$role,'JE',$actor,$rem?:'Returned.'); flash('Returned to JE.'); break;
    }
    header('Location: ?id='.$id); exit;
  }
}

$LAYOUT='app'; $ACTIVE='etariff'; $PAGE_TITLE='E-Tariff & Billing';
require __DIR__ . '/../../includes/header.php';
$viewId=(int)($_GET['id']??0);

if ($viewId):
  $b=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,c.consumer_id cno,c.category,d.name divn,d.bank_account,d.id div_id,
    de.consumption,de.excess,de.anomaly,de.prev_reading,de.curr_reading
    FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id
    LEFT JOIN drawal_entries de ON de.id=b.drawal_id WHERE b.id=$viewId")->fetch();
  $logs=$pdo->query("SELECT * FROM workflow_log WHERE entity_type='bill' AND entity_id=$viewId ORDER BY id")->fetchAll();
  $s=$b['status'];
?>
  <a href="index.php" class="text-sm text-slate-500 hover:text-brand">← <?= t('etariff') ?></a>
  <div class="grid lg:grid-cols-3 gap-6 mt-3">
    <div class="lg:col-span-2 space-y-6">
      <div class="card p-6">
        <div class="flex items-start justify-between gap-3">
          <div><div class="text-xs text-slate-400 font-mono"><?= e($b['bill_no']) ?> · <?= e($b['period']) ?></div>
          <h1 class="font-display text-2xl font-semibold text-ink mt-1"><?= bi($b['cname'],$b['cname_hi']) ?></h1>
          <p class="text-sm text-slate-500"><?= e($b['cno']) ?> · <?= e($b['category']) ?> · <?= e($b['divn']) ?></p></div>
          <?= badge($s) ?>
        </div>

        <?php if($b['anomaly']): ?>
          <div class="mt-4 bg-rose-50 ring-1 ring-rose-200 rounded-xl p-3 flex items-start gap-2 text-sm text-rose-700">
            ⚠ <div><b><?= is_hi()?'असामान्य खपत':'Anomaly detected' ?></b> — <?= is_hi()?'अत्यधिक जल आहरण चिह्नित। समीक्षा आवश्यक।':'Excess drawal flagged for review.' ?> (<?= number_format((float)$b['excess']) ?> units excess)</div>
          </div>
        <?php endif; ?>

        <!-- meter -->
        <div class="grid grid-cols-3 gap-3 mt-5 text-center">
          <div class="bg-paper rounded-xl p-3"><div class="text-xs text-slate-400"><?= is_hi()?'पिछली रीडिंग':'Previous' ?></div><div class="font-semibold text-ink mt-0.5"><?= number_format((float)$b['prev_reading']) ?></div></div>
          <div class="bg-paper rounded-xl p-3"><div class="text-xs text-slate-400"><?= is_hi()?'वर्तमान रीडिंग':'Current' ?></div><div class="font-semibold text-ink mt-0.5"><?= number_format((float)$b['curr_reading']) ?></div></div>
          <div class="bg-paper rounded-xl p-3"><div class="text-xs text-slate-400"><?= is_hi()?'कुल खपत':'Consumption' ?></div><div class="font-semibold text-brand mt-0.5"><?= number_format((float)$b['consumption']) ?></div></div>
        </div>

        <!-- tariff breakdown -->
        <table class="w-full text-sm mt-6">
          <tbody class="divide-y divide-slate-100">
            <?php foreach([
              [is_hi()?'स्थिर प्रभार':'Fixed charge',$b['fixed_charge']],
              [is_hi()?'परिवर्तनीय प्रभार':'Variable charge',$b['variable_charge']],
              [is_hi()?'अधिक उपयोग प्रभार':'Excess usage charge',$b['excess_charge']],
              [is_hi()?'विलंब शुल्क':'Penalty',$b['penalty']],
              [is_hi()?'जीएसटी (18%)':'GST (18%)',$b['gst']],
            ] as $tr): ?>
              <tr><td class="py-2 text-slate-600"><?= $tr[0] ?></td><td class="py-2 text-right font-medium"><?= inr_full((float)$tr[1]) ?></td></tr>
            <?php endforeach; ?>
            <tr class="border-t-2 border-slate-200"><td class="py-3 font-semibold text-ink"><?= is_hi()?'कुल देय':'Total Payable' ?></td><td class="py-3 text-right font-display text-xl font-semibold text-ink"><?= inr_full((float)$b['total']) ?></td></tr>
          </tbody>
        </table>

        <?php if($s==='Demand Raised' && in_array($role,['CONSUMER','EE','ADMIN'])): ?>
          <a href="<?= base_url('app/etariff/pay.php') ?>?id=<?= $viewId ?>" class="inline-flex items-center gap-2 mt-5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-3 rounded-xl">💳 <?= is_hi()?'अभी भुगतान करें':'Pay Now' ?> (JE-GRAS / UPI)</a>
        <?php elseif($s==='Paid'): ?>
          <div class="mt-5 bg-emerald-50 ring-1 ring-emerald-200 rounded-xl p-4 text-emerald-800 text-sm">✓ <?= is_hi()?'भुगतान प्राप्त — प्रमंडल खाते में जमा':'Paid — credited to division account' ?> <b><?= e($b['bank_account']) ?></b></div>
        <?php endif; ?>
      </div>

      <!-- audit -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'अनुमोदन कार्यप्रवाह':'Approval Workflow' ?> (JE → AE → EE → <?= is_hi()?'उपभोक्ता':'Consumer' ?>)</h2>
        <ol class="relative border-l-2 border-slate-200 ml-2 space-y-5">
          <?php foreach($logs as $lg): ?>
            <li class="ml-5"><span class="absolute -left-[7px] w-3 h-3 rounded-full bg-brand ring-4 ring-brandsoft"></span>
              <div class="text-sm font-semibold text-ink"><?= e($lg['action']) ?> <?php if($lg['from_role']):?><span class="text-[11px] font-normal text-slate-400"><?= e($lg['from_role']) ?> → <?= e($lg['to_role']) ?></span><?php endif;?></div>
              <p class="text-xs text-slate-500"><?= e($lg['actor']) ?> · <?= date('d M Y, H:i',strtotime($lg['created_at'])) ?></p>
              <?php if($lg['remarks']):?><p class="text-sm text-slate-600 mt-1 bg-paper rounded-lg px-3 py-1.5"><?= e($lg['remarks']) ?></p><?php endif;?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>

    <!-- action -->
    <div><div class="card p-6 sticky top-24">
      <h2 class="font-display text-lg font-semibold text-ink mb-1"><?= is_hi()?'कार्रवाई':'Take Action' ?></h2>
      <p class="text-xs text-slate-500 mb-4"><?= is_hi()?'भूमिका':'Acting as' ?>: <span class="font-semibold text-brand"><?= e($role) ?></span></p>
      <?php
        $a=null;
        if($s==='Draft'&&$role==='JE') $a='submit';
        elseif($s==='Pending Verification'&&$role==='AE') $a='verify';
        elseif($s==='Approved'&&$role==='EE') $a='raise';
        if($a): ?>
        <form method="post" class="space-y-3"><input type="hidden" name="id" value="<?= $viewId ?>">
          <textarea name="remarks" rows="2" placeholder="<?= is_hi()?'टिप्पणी':'Remarks' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
          <?php if($a==='submit'):?><button name="action" value="submit" class="w-full bg-brand hover:bg-branddeep text-white font-semibold py-2.5 rounded-xl"><?= is_hi()?'AE को सत्यापन हेतु भेजें':'Submit to AE' ?> →</button>
          <?php elseif($a==='verify'):?><button name="action" value="verify" class="w-full bg-emerald-600 text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'सत्यापित कर EE को भेजें':'Verify → EE' ?></button>
            <button name="action" value="return" class="w-full bg-amber-100 text-amber-800 font-semibold py-2 rounded-xl text-sm">↩ <?= is_hi()?'JE को वापस':'Return to JE' ?></button>
          <?php elseif($a==='raise'):?><button name="action" value="raise" class="w-full bg-ink text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'मांग स्वीकृत करें':'Approve & Raise Demand' ?></button>
            <button name="action" value="return" class="w-full bg-amber-100 text-amber-800 font-semibold py-2 rounded-xl text-sm">↩ <?= is_hi()?'वापस':'Return' ?></button><?php endif;?>
        </form>
      <?php else: ?>
        <div class="text-center py-8 text-slate-400 text-sm"><div class="text-3xl mb-2">🔒</div>
          <?= is_hi()?'इस चरण हेतु कोई कार्रवाई नहीं।':'No action at this stage for your role.' ?>
          <?php $need=['Draft'=>'JE','Pending Verification'=>'AE','Approved'=>'EE','Demand Raised'=>'CONSUMER'][$s]??null;
          if($need) echo '<p class="text-xs mt-2">'.(is_hi()?'भूमिका बदलें':'Switch to').' <b class="text-brand">'.$need.'</b></p>';?>
        </div>
      <?php endif; ?>
    </div></div>
  </div>

<?php else:
  $bills=$pdo->query("SELECT b.*,c.name cname,c.name_hi cname_hi,d.name divn FROM bills b JOIN consumers c ON c.id=b.consumer_id JOIN divisions d ON d.id=c.division_id ORDER BY b.id DESC")->fetchAll();
  $cons=$pdo->query("SELECT id,name,consumer_id,allocation_qty FROM consumers ORDER BY name")->fetchAll();
?>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= t('etariff') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'जल आहरण · टैरिफ · प्रमंडल-वार राजस्व जमा':'Drawal · tariff · division-wise revenue credit (Component D)' ?></p></div>
    <?php if(in_array($role,['JE','ADMIN'])): ?><button onclick="document.getElementById('newBill').showModal()" class="bg-brand text-white font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'जल आहरण प्रविष्टि':'New Drawal Entry' ?></button><?php endif; ?>
  </div>

  <!-- division revenue strip -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php foreach($pdo->query("SELECT d.name,d.bank_account,COALESCE(SUM(p.amount),0) amt FROM divisions d LEFT JOIN payments p ON p.division_id=d.id AND p.status='Success' GROUP BY d.id ORDER BY amt DESC") as $dr): ?>
      <div class="card p-3.5"><div class="text-[11px] text-slate-400 truncate"><?= e(preg_replace('/ (Division|Irrigation).*/','',$dr['name'])) ?></div>
        <div class="font-display text-lg font-semibold text-emerald-700 mt-0.5"><?= inr((float)$dr['amt']) ?></div>
        <div class="text-[10px] text-slate-400 font-mono"><?= e($dr['bank_account']) ?></div></div>
    <?php endforeach; ?>
  </div>

  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
        <th class="text-left px-4 py-3">Bill No</th><th class="text-left px-4 py-3"><?= is_hi()?'उपभोक्ता':'Consumer' ?></th>
        <th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'अवधि':'Period' ?></th>
        <th class="text-right px-4 py-3"><?= is_hi()?'राशि':'Amount' ?></th><th class="text-left px-4 py-3">Status</th></tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach($bills as $r): ?>
          <tr class="hover:bg-paper cursor-pointer" onclick="location.href='?id=<?= $r['id'] ?>'">
            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($r['bill_no']) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['cname'],$r['cname_hi']) ?><div class="text-xs text-slate-400"><?= e($r['divn']) ?></div></td>
            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['period']) ?></td>
            <td class="px-4 py-3 text-right font-semibold text-ink"><?= inr((float)$r['total']) ?></td>
            <td class="px-4 py-3"><?= badge($r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <dialog id="newBill" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/40">
    <form method="post" class="p-6"><input type="hidden" name="action" value="create">
      <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'जल आहरण प्रविष्टि (JE)':'Water Drawal Entry (JE)' ?></h2>
      <div class="space-y-4">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'उपभोक्ता':'Consumer' ?></label>
          <select name="consumer_id" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><?php foreach($cons as $c):?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['consumer_id']) ?>)</option><?php endforeach;?></select></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'अवधि':'Billing Period' ?></label>
          <input name="period" required value="<?= date('M Y') ?>" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'पिछली रीडिंग':'Previous Reading' ?></label><input name="prev" type="number" step="0.01" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
          <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'वर्तमान रीडिंग':'Current Reading' ?></label><input name="curr" type="number" step="0.01" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
        </div>
        <p class="text-[11px] text-slate-400"><?= is_hi()?'टैरिफ स्वतः गणना होगी; अत्यधिक खपत स्वतः चिह्नित।':'Tariff auto-calculated; excess drawal auto-flagged as anomaly.' ?></p>
      </div>
      <div class="flex gap-2 mt-5">
        <button type="button" onclick="document.getElementById('newBill').close()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600">Cancel</button>
        <button class="flex-1 bg-brand text-white rounded-xl py-2.5 font-semibold"><?= is_hi()?'बिल तैयार करें':'Prepare Bill' ?></button>
      </div>
    </form>
  </dialog>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
