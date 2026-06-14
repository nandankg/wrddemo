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
  $pid = (int)($_POST['project_id'] ?? 0);

  if ($act==='submit' && $role==='JE') {
    $phys=(int)$_POST['physical_pct']; $fin=(int)$_POST['financial_pct']; $note=trim($_POST['note'] ?? '');
    $dup=$pdo->prepare("SELECT id FROM progress_updates WHERE project_id=? AND status='Submitted'");
    $dup->execute([$pid]);
    if ($dup->fetch()) {
      flash('A progress update for this project already awaits verification.');
      header('Location: ?id='.$pid); exit;
    }
    if (ppms_valid_pct($phys) && ppms_valid_pct($fin)) {
      $st=$pdo->prepare('INSERT INTO progress_updates (project_id,physical_pct,financial_pct,note,status,submitted_by) VALUES (?,?,?,?,?,?)');
      $st->execute([$pid,$phys,$fin,$note,'Submitted',$u['id']]);
      add_audit($pdo,'project',$pid,'Progress submitted','JE','AE',$actor,"Physical $phys% · Financial $fin%".($note?" · $note":''));
      flash('Progress submitted for verification.');
    } else { flash('Percentages must be between 0 and 100.'); }
    header('Location: ?id='.$pid); exit;
  }

  $gid = (int)($_POST['update_id'] ?? 0);
  $g = $pdo->query("SELECT * FROM progress_updates WHERE id=$gid")->fetch();
  if ($g && $role==='AE' && $g['status']==='Submitted') {
    if ($act==='verify') {
      $pdo->prepare("UPDATE progress_updates SET status='Verified',verified_by=? WHERE id=?")->execute([$u['id'],$gid]);
      $pdo->prepare("UPDATE projects SET physical_pct=?,financial_pct=? WHERE id=?")->execute([(int)$g['physical_pct'],(int)$g['financial_pct'],$g['project_id']]);
      add_audit($pdo,'project',(int)$g['project_id'],'Progress verified','AE','AE',$actor,'Applied Physical '.$g['physical_pct'].'% · Financial '.$g['financial_pct'].'%');
      flash('Progress verified and applied.');
    } elseif ($act==='reject') {
      $pdo->prepare("UPDATE progress_updates SET status='Rejected',verified_by=? WHERE id=?")->execute([$u['id'],$gid]);
      add_audit($pdo,'project',(int)$g['project_id'],'Progress rejected','AE','JE',$actor,trim($_POST['remarks'] ?? '')?:'Returned for correction.');
      flash('Progress update rejected.');
    }
    header('Location: ?id='.$g['project_id']); exit;
  }
  header('Location: projects.php'); exit;
}

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='projects'; $PAGE_TITLE='Projects & Progress';
require __DIR__ . '/../../includes/header.php';

$viewId = (int)($_GET['id'] ?? 0);
$myDiv = (int)($u['division_id'] ?? 0);
$scopeDiv = in_array(ppms_role_view($role), ['field','division'], true) && $myDiv > 0;

// =================== DETAIL VIEW ===================
if ($viewId):
  $p = $pdo->query("SELECT p.*,s.name scheme,d.name divn FROM projects p JOIN schemes s ON s.id=p.scheme_id JOIN divisions d ON d.id=p.division_id WHERE p.id=$viewId")->fetch();
  if (!$p) { echo '<p class="text-slate-500">Project not found.</p>'; require __DIR__.'/../../includes/footer.php'; exit; }
  $updates = $pdo->query("SELECT * FROM progress_updates WHERE project_id=$viewId ORDER BY id DESC")->fetchAll();
  $pending = null; foreach ($updates as $up) if ($up['status']==='Submitted') { $pending=$up; break; }
?>
  <a href="projects.php" class="text-sm text-slate-500 hover:underline">← <?= is_hi()?'सभी परियोजनाएँ':'All projects' ?></a>
  <div class="grid lg:grid-cols-3 gap-6 mt-3">
    <div class="lg:col-span-2 space-y-6">
      <div class="card p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h1 class="font-display text-2xl font-semibold text-ink"><?= bi($p['name'],$p['name_hi']) ?></h1>
            <p class="text-sm text-slate-500 mt-0.5"><?= e($p['scheme']) ?> · <?= e($p['divn']) ?></p>
          </div>
          <?= badge($p['status']) ?>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 mt-6">
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'भौतिक प्रगति':'Physical Progress' ?></div>
            <div class="flex items-center gap-2 mt-2"><div class="flex-1 h-2.5 bg-slate-200 rounded-full overflow-hidden"><div class="h-full" style="width:<?= (int)$p['physical_pct'] ?>%;background:<?= e($APP['accent']) ?>"></div></div><span class="font-semibold text-ink"><?= (int)$p['physical_pct'] ?>%</span></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'वित्तीय प्रगति':'Financial Progress' ?></div>
            <div class="flex items-center gap-2 mt-2"><div class="flex-1 h-2.5 bg-slate-200 rounded-full overflow-hidden"><div class="h-full bg-emerald-500" style="width:<?= (int)$p['financial_pct'] ?>%"></div></div><span class="font-semibold text-ink"><?= (int)$p['financial_pct'] ?>%</span></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'स्वीकृत राशि':'Sanctioned' ?></div><div class="font-display text-lg font-semibold text-ink mt-1"><?= inr((float)$p['sanctioned_amount']) ?></div></div>
          <div class="bg-paper rounded-xl p-4"><div class="text-xs text-slate-400"><?= is_hi()?'व्यय':'Spent' ?></div><div class="font-display text-lg font-semibold text-ink mt-1"><?= inr((float)$p['spent_amount']) ?></div></div>
        </div>
      </div>

      <!-- Progress history + audit -->
      <div class="card p-6">
        <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'प्रगति इतिहास':'Progress History' ?></h2>
        <ol class="relative border-l-2 border-slate-200 ml-2 space-y-5">
          <?php foreach ($updates as $up): ?>
            <li class="ml-5">
              <span class="absolute -left-[7px] w-3 h-3 rounded-full ring-4 ring-brandsoft" style="background:<?= e($APP['accent']) ?>"></span>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-ink"><?= is_hi()?'भौतिक':'Physical' ?> <?= (int)$up['physical_pct'] ?>% · <?= is_hi()?'वित्तीय':'Financial' ?> <?= (int)$up['financial_pct'] ?>%</span>
                <?= badge($up['status']) ?>
              </div>
              <p class="text-xs text-slate-500 mt-0.5"><?= date('d M Y, H:i',strtotime($up['created_at'])) ?></p>
              <?php if($up['note']): ?><p class="text-sm text-slate-600 mt-1 bg-paper rounded-lg px-3 py-1.5"><?= e($up['note']) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
          <?php if(!$updates): ?><li class="ml-5 text-sm text-slate-400"><?= is_hi()?'अभी तक कोई प्रगति अद्यतन नहीं।':'No progress updates yet.' ?></li><?php endif; ?>
        </ol>
      </div>
    </div>

    <!-- Action panel -->
    <div>
      <div class="card p-6 sticky top-24">
        <h2 class="font-display text-lg font-semibold text-ink mb-1"><?= is_hi()?'कार्रवाई':'Take Action' ?></h2>
        <p class="text-xs text-slate-500 mb-4"><?= is_hi()?'वर्तमान भूमिका':'Acting as' ?>: <span class="font-semibold" style="color:<?= e($APP['accent']) ?>"><?= e($role) ?></span></p>

        <?php if ($role==='JE'): ?>
          <form method="post" class="space-y-3">
            <input type="hidden" name="project_id" value="<?= $viewId ?>"><input type="hidden" name="action" value="submit">
            <label class="text-xs font-medium text-slate-600"><?= is_hi()?'भौतिक प्रगति (%)':'Physical Progress (%)' ?></label>
            <input name="physical_pct" type="number" min="0" max="100" value="<?= (int)$p['physical_pct'] ?>" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <label class="text-xs font-medium text-slate-600"><?= is_hi()?'वित्तीय प्रगति (%)':'Financial Progress (%)' ?></label>
            <input name="financial_pct" type="number" min="0" max="100" value="<?= (int)$p['financial_pct'] ?>" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <textarea name="note" rows="2" placeholder="<?= is_hi()?'टिप्पणी':'Site note' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
            <button class="w-full btn-acc font-semibold py-2.5 rounded-xl"><?= is_hi()?'सत्यापन हेतु भेजें':'Submit for Verification' ?> →</button>
          </form>
        <?php elseif ($role==='AE' && $pending): ?>
          <div class="bg-amber-50 ring-1 ring-amber-200 rounded-xl p-3 text-sm text-amber-800 mb-3">
            <?= is_hi()?'जेई द्वारा प्रस्तुत':'Submitted by JE' ?>: <b><?= (int)$pending['physical_pct'] ?>% / <?= (int)$pending['financial_pct'] ?>%</b>
          </div>
          <form method="post" class="space-y-3">
            <input type="hidden" name="update_id" value="<?= (int)$pending['id'] ?>">
            <button name="action" value="verify" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl">✓ <?= is_hi()?'सत्यापित कर लागू करें':'Verify & Apply' ?></button>
            <textarea name="remarks" rows="2" placeholder="<?= is_hi()?'अस्वीकृति कारण':'Reason (if rejecting)' ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
            <button name="action" value="reject" class="w-full bg-rose-100 text-rose-700 font-semibold py-2 rounded-xl text-sm">✕ <?= is_hi()?'अस्वीकृत':'Reject' ?></button>
          </form>
        <?php else: ?>
          <div class="text-center py-8 text-slate-400 text-sm">
            <div class="text-3xl mb-2">🔒</div>
            <?= is_hi()?'इस चरण पर आपकी भूमिका हेतु कोई कार्रवाई नहीं।':'No action for your role here.' ?>
            <p class="text-xs mt-2"><?= is_hi()?'जेई प्रगति प्रस्तुत करता है; एई सत्यापित करता है।':'JE submits progress; AE verifies.' ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php
// =================== LIST VIEW ===================
else:
  if ($scopeDiv) {
    $st=$pdo->prepare("SELECT p.*,d.name divn,(SELECT status FROM progress_updates pu WHERE pu.project_id=p.id ORDER BY pu.id DESC LIMIT 1) last_status FROM projects p JOIN divisions d ON d.id=p.division_id WHERE p.division_id=? ORDER BY p.physical_pct DESC");
    $st->execute([$myDiv]); $rows=$st->fetchAll();
  } else {
    $rows=$pdo->query("SELECT p.*,d.name divn,(SELECT status FROM progress_updates pu WHERE pu.project_id=p.id ORDER BY pu.id DESC LIMIT 1) last_status FROM projects p JOIN divisions d ON d.id=p.division_id ORDER BY p.physical_pct DESC")->fetchAll();
  }
?>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'परियोजनाएँ एवं प्रगति':'Projects & Progress' ?></h1>
    <p class="text-sm text-slate-500"><?= $scopeDiv?(is_hi()?'मेरे प्रमंडल की परियोजनाएँ':'Projects in my division'):(is_hi()?'सभी परियोजनाएँ':'All projects') ?> · PPMS</p></div>
  </div>
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
        <tr><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th><th class="text-left px-4 py-3 hidden md:table-cell">Division</th>
        <th class="text-left px-4 py-3"><?= is_hi()?'भौतिक':'Physical' ?></th><th class="text-left px-4 py-3">Status</th><th class="text-left px-4 py-3"><?= is_hi()?'अद्यतन':'Update' ?></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($rows as $r): ?>
          <tr class="hover:bg-paper cursor-pointer" onclick="location.href='?id=<?= $r['id'] ?>'">
            <td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['name'],$r['name_hi']) ?></td>
            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['divn']) ?></td>
            <td class="px-4 py-3 w-40"><div class="flex items-center gap-2"><div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full" style="width:<?= (int)$r['physical_pct'] ?>%;background:<?= e($APP['accent']) ?>"></div></div><span class="text-xs font-semibold text-slate-600"><?= (int)$r['physical_pct'] ?>%</span></div></td>
            <td class="px-4 py-3"><?= badge($r['status']) ?></td>
            <td class="px-4 py-3"><?= $r['last_status']?badge($r['last_status']):'<span class="text-xs text-slate-400">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
