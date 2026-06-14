<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$actor = $u['name'] . ' (' . $role . ')';

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';

  if ($act==='create') {
    $proj = (int)$_POST['project_id'];
    $row = $pdo->query("SELECT p.scheme_id,p.division_id,s.head_of_account FROM projects p JOIN schemes s ON s.id=p.scheme_id WHERE p.id=$proj")->fetch();
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM fund_requisitions')->fetchColumn() + 1;
    $reqno = sprintf('WRD/FR/2526/%04d',$cnt);
    $st=$pdo->prepare('INSERT INTO fund_requisitions (req_no,project_id,scheme_id,division_id,head_of_account,fy,amount_requested,justification,status,created_by,current_owner_role) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$reqno,$proj,$row['scheme_id'],$row['division_id'],$row['head_of_account'],'2025-26',(float)$_POST['amount'],trim($_POST['justification']),'Draft',$u['id'],'EE']);
    $id=(int)$pdo->lastInsertId();
    add_audit($pdo,'fund_requisition',$id,'Created','EE','EE',$actor,'Requisition drafted.');
    flash("Fund requisition $reqno created (Draft).");
    header('Location: ?id='.$id); exit;
  }

  $id = (int)($_POST['id'] ?? 0);
  $fr = $pdo->query("SELECT * FROM fund_requisitions WHERE id=$id")->fetch();
  if ($fr) {
    $remarks = trim($_POST['remarks'] ?? '');
    $s = $fr['status'];
    // Guard: only the right role can act, and only at the right stage.
    $permit = [
      'submit'     => $s==='Draft'                && $role==='EE',
      'accept'     => $s==='Pending Review'       && in_array($role,['SE','CE','EIC','EE'],true),
      'finance_ok' => $s==='Under Finance Review' && $role==='FINANCE',
      'release'    => $s==='Approved by Finance'  && in_array($role,['ADMIN','EIC'],true),
      'reject'     => in_array($s,['Pending Review','Under Finance Review'],true) && in_array($role,['SE','CE','EIC','EE','FINANCE'],true),
      'sendback'   => in_array($s,['Pending Review','Under Finance Review'],true) && in_array($role,['SE','CE','EIC','FINANCE'],true),
    ][$act] ?? false;
    if (!$permit) { flash('Action not permitted for your role at this stage.'); header('Location: ?id='.$id); exit; }
    switch ($act) {
      case 'submit': // Draft -> Pending Review
        $pdo->prepare("UPDATE fund_requisitions SET status='Pending Review',current_owner_role='SE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'fund_requisition',$id,'Submitted for review','EE','SE',$actor,$remarks?:'Forwarded for sanction.');
        flash('Submitted for review.'); break;
      case 'accept': // Pending Review -> Under Finance Review
        $pdo->prepare("UPDATE fund_requisitions SET status='Under Finance Review',current_owner_role='FINANCE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'fund_requisition',$id,'Recommended','SE','FINANCE',$actor,$remarks?:'Technically vetted; forwarded to Finance.');
        flash('Forwarded to Finance.'); break;
      case 'finance_ok': // -> Approved by Finance
        $alloc=(float)$_POST['allocated']; $fc=trim($_POST['fund_code']);
        $pdo->prepare("UPDATE fund_requisitions SET status='Approved by Finance',allocated_amount=?,fund_code=?,current_owner_role='ADMIN' WHERE id=?")->execute([$alloc,$fc,$id]);
        add_audit($pdo,'fund_requisition',$id,'Approved by Finance','FINANCE','ADMIN',$actor,'Allocated '.inr($alloc).' · '.$fc.($remarks?(' · '.$remarks):''));
        flash('Approved by Finance.'); break;
      case 'release': // -> Released
        $ref='FRC/2025/'.str_pad((string)$id,4,'0',STR_PAD_LEFT);
        $pdo->prepare("UPDATE fund_requisitions SET status='Released',release_ref=?,release_date=CURDATE(),current_owner_role='ADMIN' WHERE id=?")->execute([$ref,$id]);
        add_audit($pdo,'fund_requisition',$id,'Fund Released','ADMIN','ADMIN',$actor,'Released vide '.$ref);
        flash('Fund released. Certificate available.'); break;
      case 'reject':
        $pdo->prepare("UPDATE fund_requisitions SET status='Rejected' WHERE id=?")->execute([$id]);
        add_audit($pdo,'fund_requisition',$id,'Rejected',$role,$role,$actor,$remarks?:'Rejected.');
        flash('Requisition rejected.'); break;
      case 'sendback':
        $pdo->prepare("UPDATE fund_requisitions SET status='Draft',current_owner_role='EE' WHERE id=?")->execute([$id]);
        add_audit($pdo,'fund_requisition',$id,'Sent back for revision',$role,'EE',$actor,$remarks?:'Returned for revision.');
        flash('Sent back for revision.'); break;
    }
    header('Location: ?id='.$id); exit;
  }
}

set_app_context('ppms');
app_require_access('requisitions');
$LAYOUT='app'; $ACTIVE='requisitions'; $PAGE_TITLE='Fund Requisition';
require __DIR__ . '/../../includes/header.php';

$viewId = (int)($_GET['id'] ?? 0);

// =================== DETAIL VIEW ===================
if ($viewId):
  $fr = $pdo->query("SELECT fr.*,p.name proj,p.name_hi proj_hi,s.name scheme,d.name divn,d.bank_account
                     FROM fund_requisitions fr JOIN projects p ON p.id=fr.project_id
                     JOIN schemes s ON s.id=fr.scheme_id JOIN divisions d ON d.id=fr.division_id
                     WHERE fr.id=$viewId")->fetch();
  $logs = $pdo->query("SELECT * FROM workflow_log WHERE entity_type='fund_requisition' AND entity_id=$viewId ORDER BY id")->fetchAll();
  $s = $fr['status'];
  // which actions can THIS role take
  $canSubmit  = ($s==='Draft');
  $canReview  = ($s==='Pending Review' && in_array($role,['SE','CE','EIC','EE']));
  $canFinance = ($s==='Under Finance Review' && $role==='FINANCE');
  $canRelease = ($s==='Approved by Finance' && in_array($role,['ADMIN','EIC']));
?>
  <a href="requisitions.php" class="text-sm text-slate-500 hover:text-brand">← <?= t('fund_req') ?></a>
  <div class="grid lg:grid-cols-3 gap-6 mt-3">
    <div class="lg:col-span-2 space-y-6">
      <div class="card p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs text-slate-400 font-mono"><?= e($fr['req_no']) ?></div>
            <h1 class="font-display text-2xl font-semibold text-ink mt-1"><?= bi($fr['proj'],$fr['proj_hi']) ?></h1>
            <p class="text-sm text-slate-500 mt-0.5"><?= e($fr['scheme']) ?> · <?= e($fr['divn']) ?></p>
          </div>
          <?= badge($s) ?>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 mt-6">
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'माँगी गई राशि':'Amount Requested' ?></div><div class="font-display text-xl font-semibold text-ink mt-1"><?= inr((float)$fr['amount_requested']) ?></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'स्वीकृत राशि':'Allocated' ?></div><div class="font-display text-xl font-semibold text-emerald-700 mt-1"><?= $fr['allocated_amount']?inr((float)$fr['allocated_amount']):'—' ?></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'लेखा शीर्ष':'Head of Account' ?></div><div class="font-semibold text-ink mt-1"><?= e($fr['head_of_account']) ?></div></div>
        </div>
        <div class="mt-5"><div class="text-xs text-slate-400 mb-1"><?= is_hi()?'औचित्य':'Justification' ?></div><p class="text-sm text-slate-700"><?= e($fr['justification']) ?></p></div>
        <?php if ($s==='Released'): ?>
          <a href="<?= base_url('app/ppms/certificate.php') ?>?id=<?= $viewId ?>" target="_blank" class="inline-flex items-center gap-2 mt-5 bg-ink text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-ink2">📄 <?= is_hi()?'निधि निर्गत प्रमाणपत्र':'Fund Release Certificate' ?> (PDF)</a>
        <?php endif; ?>
      </div>

      <!-- Audit trail -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'फ़ाइल संचलन / ऑडिट ट्रेल':'File Movement · Audit Trail' ?></h2>
        <ol class="relative border-l-2 border-slate-200 ml-2 space-y-5">
          <?php foreach ($logs as $lg): ?>
            <li class="ml-5">
              <span class="absolute -left-[7px] w-3 h-3 rounded-full bg-brand ring-4 ring-brandsoft"></span>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-ink"><?= e($lg['action']) ?></span>
                <?php if($lg['from_role']): ?><span class="text-[11px] text-slate-400"><?= e($lg['from_role']) ?> → <?= e($lg['to_role']) ?></span><?php endif; ?>
              </div>
              <p class="text-xs text-slate-500 mt-0.5"><?= e($lg['actor']) ?> · <?= date('d M Y, H:i',strtotime($lg['created_at'])) ?></p>
              <?php if($lg['remarks']): ?><p class="text-sm text-slate-600 mt-1 bg-paper rounded-lg px-3 py-1.5"><?= e($lg['remarks']) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>

    <!-- Action panel -->
    <div>
      <div class="card p-6 sticky top-24">
        <h2 class="font-display text-lg font-semibold text-ink mb-1"><?= is_hi()?'कार्रवाई':'Take Action' ?></h2>
        <p class="text-xs text-slate-500 mb-4"><?= is_hi()?'वर्तमान भूमिका':'Acting as' ?>: <span class="font-semibold text-brand"><?= e($role) ?></span></p>

        <?php if ($canSubmit): ?>
          <form method="post" class="space-y-3">
            <input type="hidden" name="id" value="<?= $viewId ?>"><input type="hidden" name="action" value="submit">
            <textarea name="remarks" rows="2" placeholder="<?= is_hi()?'टिप्पणी (वैकल्पिक)':'Remarks (optional)' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
            <button class="w-full bg-brand hover:bg-branddeep text-white font-semibold py-2.5 rounded-xl"><?= is_hi()?'समीक्षा हेतु भेजें':'Submit for Review' ?> →</button>
          </form>
        <?php elseif ($canReview): ?>
          <form method="post" class="space-y-3">
            <input type="hidden" name="id" value="<?= $viewId ?>">
            <textarea name="remarks" rows="2" placeholder="<?= is_hi()?'टिप्पणी':'Remarks' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
            <button name="action" value="accept" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'अनुशंसा कर वित्त को भेजें':'Recommend → Finance' ?></button>
            <div class="flex gap-2">
              <button name="action" value="sendback" class="flex-1 bg-amber-100 text-amber-800 font-semibold py-2 rounded-xl text-sm">↩ <?= is_hi()?'वापस':'Send Back' ?></button>
              <button name="action" value="reject" class="flex-1 bg-rose-100 text-rose-700 font-semibold py-2 rounded-xl text-sm">✕ <?= is_hi()?'अस्वीकृत':'Reject' ?></button>
            </div>
          </form>
        <?php elseif ($canFinance): ?>
          <form method="post" class="space-y-3">
            <input type="hidden" name="id" value="<?= $viewId ?>">
            <label class="text-xs font-medium text-slate-600"><?= is_hi()?'स्वीकृत राशि (₹)':'Allocated Amount (₹)' ?></label>
            <input name="allocated" type="number" step="0.01" value="<?= (float)$fr['amount_requested'] ?>" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <label class="text-xs font-medium text-slate-600"><?= is_hi()?'निधि कोड':'Fund Code' ?></label>
            <input name="fund_code" value="FC-<?= e($fr['head_of_account']) ?>" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <textarea name="remarks" rows="2" placeholder="Remarks" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
            <button name="action" value="finance_ok" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'वित्त स्वीकृति':'Approve (Finance)' ?></button>
            <button name="action" value="reject" class="w-full bg-rose-100 text-rose-700 font-semibold py-2 rounded-xl text-sm">✕ <?= is_hi()?'अस्वीकृत':'Reject' ?></button>
          </form>
        <?php elseif ($canRelease): ?>
          <form method="post" class="space-y-3">
            <input type="hidden" name="id" value="<?= $viewId ?>">
            <div class="bg-emerald-50 ring-1 ring-emerald-200 rounded-xl p-3 text-sm text-emerald-800"><?= is_hi()?'वित्त द्वारा स्वीकृत':'Approved by Finance' ?>: <b><?= inr((float)$fr['allocated_amount']) ?></b></div>
            <button name="action" value="release" class="w-full bg-ink hover:bg-ink2 text-white font-semibold py-2.5 rounded-xl">🏦 <?= is_hi()?'निधि निर्गत करें':'Release Fund' ?></button>
          </form>
        <?php else: ?>
          <div class="text-center py-8 text-slate-400 text-sm">
            <div class="text-3xl mb-2">🔒</div>
            <?= is_hi()?'इस चरण पर आपकी भूमिका के लिए कोई कार्रवाई उपलब्ध नहीं।':'No action available for your role at this stage.' ?>
            <p class="text-xs mt-2"><?= is_hi()?'भूमिका बदलें (बाएँ नीचे) — जैसे':'Switch role (bottom-left) — e.g.' ?>
            <?php
              $need = ['Draft'=>'EE','Pending Review'=>'SE','Under Finance Review'=>'FINANCE','Approved by Finance'=>'ADMIN'][$s] ?? null;
              if($need) echo '<b class="text-brand">'.$need.'</b>';
            ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php
// =================== LIST VIEW ===================
else:
  $rows = $pdo->query("SELECT fr.*,p.name proj,p.name_hi proj_hi,d.name divn FROM fund_requisitions fr
                       JOIN projects p ON p.id=fr.project_id JOIN divisions d ON d.id=fr.division_id
                       ORDER BY fr.id DESC")->fetchAll();
  $projOpts = $pdo->query("SELECT id,name FROM projects ORDER BY name")->fetchAll();
?>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= t('fund_req') ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'परियोजना-वार निधि माँग जीवनचक्र':'Project-wise fund demand lifecycle (PPMS · Module A)' ?></p></div>
    <button onclick="document.getElementById('newFR').showModal()" class="bg-brand hover:bg-branddeep text-white font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नई माँग':'New Requisition' ?></button>
  </div>

  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
        <tr><th class="text-left px-4 py-3">Req No</th><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th>
        <th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'प्रमंडल':'Division' ?></th>
        <th class="text-right px-4 py-3"><?= is_hi()?'राशि':'Amount' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'स्थिति':'Status' ?></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($rows as $r): ?>
          <tr class="hover:bg-paper cursor-pointer" onclick="location.href='?id=<?= $r['id'] ?>'">
            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($r['req_no']) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['proj'],$r['proj_hi']) ?></td>
            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['divn']) ?></td>
            <td class="px-4 py-3 text-right font-semibold text-ink"><?= inr((float)$r['amount_requested']) ?></td>
            <td class="px-4 py-3"><?= badge($r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <dialog id="newFR" class="rounded-2xl p-0 w-full max-w-lg backdrop:bg-black/40">
    <form method="post" class="p-6">
      <input type="hidden" name="action" value="create">
      <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'नई निधि माँग':'New Fund Requisition' ?></h2>
      <div class="space-y-4">
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'परियोजना':'Project' ?></label>
          <select name="project_id" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5">
            <?php foreach($projOpts as $po): ?><option value="<?= $po['id'] ?>"><?= e($po['name']) ?></option><?php endforeach; ?>
          </select>
          <p class="text-[11px] text-slate-400 mt-1"><?= is_hi()?'योजना एवं लेखा-शीर्ष स्वतः भर जाएँगे।':'Scheme & head-of-account auto-populate from project metadata.' ?></p>
        </div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'माँगी गई राशि (₹)':'Amount Requested (₹)' ?></label>
          <input name="amount" type="number" step="0.01" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5" placeholder="e.g. 5000000"></div>
        <div><label class="text-sm font-medium text-slate-700"><?= is_hi()?'औचित्य':'Justification' ?></label>
          <textarea name="justification" rows="3" required class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></textarea></div>
      </div>
      <div class="flex gap-2 mt-5">
        <button type="button" onclick="document.getElementById('newFR').close()" class="flex-1 border border-slate-300 rounded-xl py-2.5 font-semibold text-slate-600"><?= is_hi()?'रद्द':'Cancel' ?></button>
        <button class="flex-1 bg-brand text-white rounded-xl py-2.5 font-semibold"><?= is_hi()?'सहेजें':'Create (Draft)' ?></button>
      </div>
    </form>
  </dialog>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
