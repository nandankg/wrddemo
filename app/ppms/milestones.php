<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();
$u = current_user(); $role = user_role();
$actor = $u['name'] . ' (' . $role . ')';
$today = date('Y-m-d');

// ---------- Action: JE/AE update a milestone ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($role,['JE','AE'],true)) {
    $mid = (int)($_POST['milestone_id'] ?? 0);
    $new = $_POST['status'] ?? '';
    if (in_array($new,['In-Progress','Done'],true) && $mid) {
        $m = $pdo->query("SELECT * FROM milestones WHERE id=$mid")->fetch();
        if ($m) {
            $actual = $new==='Done' ? $today : null;
            $pdo->prepare('UPDATE milestones SET status=?,actual_date=? WHERE id=?')->execute([$new,$actual,$mid]);
            add_audit($pdo,'project',(int)$m['project_id'],'Milestone '.$new,$role,$role,$actor,$m['name']);
            if ($new==='Done') {
                $proj = $pdo->query("SELECT name FROM projects WHERE id=".(int)$m['project_id'])->fetch();
                ppms_notify($pdo,'SMS','EE · +91-9430xx521','Milestone "'.$m['name'].'" marked Done for '.($proj['name']??'project').'.','project #'.(int)$m['project_id']);
            }
            flash('Milestone updated.');
        }
    }
    header('Location: ?project='.(int)($_POST['project_id'] ?? 0)); exit;
}

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='milestones'; $PAGE_TITLE='Milestones';
require __DIR__ . '/../../includes/header.php';

$viewId = (int)($_GET['project'] ?? 0);
$myDiv = (int)($u['division_id'] ?? 0);
$scopeDiv = in_array(ppms_role_view($role), ['field','division'], true) && $myDiv > 0;

// =================== DETAIL VIEW ===================
if ($viewId):
  $p = $pdo->query("SELECT p.*,d.name divn FROM projects p JOIN divisions d ON d.id=p.division_id WHERE p.id=$viewId")->fetch();
  if (!$p) { echo '<p class="text-slate-500">Project not found.</p>'; require __DIR__.'/../../includes/footer.php'; exit; }
  $rows = $pdo->query("SELECT * FROM milestones WHERE project_id=$viewId ORDER BY planned_date")->fetchAll();
  $prog = ppms_milestone_progress($rows);
?>
  <a href="milestones.php" class="text-sm text-slate-500 hover:underline">← <?= is_hi()?'सभी परियोजनाएँ':'All projects' ?></a>
  <div class="card p-6 mt-3">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="font-display text-2xl font-semibold text-ink"><?= bi($p['name'],$p['name_hi']) ?></h1>
        <p class="text-sm text-slate-500 mt-0.5"><?= e($p['divn']) ?> · <?= is_hi()?'मील-पत्थर ट्रैकिंग':'Milestone tracking' ?></p>
      </div>
      <div class="text-right">
        <div class="text-xs text-slate-400"><?= is_hi()?'मील-पत्थर पूर्णता':'Milestone completion' ?></div>
        <div class="font-display text-2xl font-semibold" style="color:<?= e($APP['accent']) ?>"><?= $prog ?>%</div>
      </div>
    </div>
    <div class="mt-3 h-2.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full" style="width:<?= $prog ?>%;background:<?= e($APP['accent']) ?>"></div></div>
  </div>

  <div class="card p-6 mt-6">
    <h2 class="font-display text-lg font-semibold text-ink mb-4"><?= is_hi()?'मील-पत्थर':'Milestones' ?></h2>
    <ol class="relative border-l-2 border-slate-200 ml-2 space-y-5">
      <?php foreach ($rows as $m):
        $eff = ppms_milestone_status($m['status'],$m['actual_date'],$m['planned_date'],$today); ?>
        <li class="ml-5">
          <span class="absolute -left-[7px] w-3 h-3 rounded-full ring-4 ring-brandsoft" style="background:<?= $eff==='Done'?'#10b981':($eff==='Delayed'?'#ef4444':'#94a3b8') ?>"></span>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-semibold text-ink"><?= bi($m['name'],$m['name_hi']) ?></span>
            <?= badge($eff) ?>
            <span class="text-[11px] text-slate-400"><?= is_hi()?'भार':'weight' ?> <?= (int)$m['weight'] ?></span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5"><?= is_hi()?'नियोजित':'Planned' ?>: <?= date('d M Y',strtotime($m['planned_date'])) ?><?= $m['actual_date']?' · '.(is_hi()?'वास्तविक':'Actual').': '.date('d M Y',strtotime($m['actual_date'])):'' ?></p>
          <?php if (in_array($role,['JE','AE'],true) && $eff!=='Done'): ?>
            <form method="post" class="mt-2 flex gap-2">
              <input type="hidden" name="milestone_id" value="<?= (int)$m['id'] ?>">
              <input type="hidden" name="project_id" value="<?= $viewId ?>">
              <button name="status" value="In-Progress" class="text-xs bg-sky-100 text-sky-800 font-semibold px-3 py-1.5 rounded-lg">▶ <?= is_hi()?'प्रगति पर':'Mark In-Progress' ?></button>
              <button name="status" value="Done" class="text-xs bg-emerald-600 text-white font-semibold px-3 py-1.5 rounded-lg">✓ <?= is_hi()?'पूर्ण':'Mark Done' ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if(!$rows): ?><li class="ml-5 text-sm text-slate-400"><?= is_hi()?'कोई मील-पत्थर नहीं।':'No milestones defined.' ?></li><?php endif; ?>
    </ol>
  </div>

<?php
// =================== LIST VIEW ===================
else:
  $sql = "SELECT p.id,p.name,p.name_hi,d.name divn,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id=p.id) total,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id=p.id AND m.status='Done') done,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id=p.id AND m.status<>'Done' AND m.planned_date < '$today') delayed
          FROM projects p JOIN divisions d ON d.id=p.division_id";
  if ($scopeDiv) $sql .= " WHERE p.division_id=".$myDiv;
  $sql .= " ORDER BY delayed DESC, p.name";
  $rows = $pdo->query($sql)->fetchAll();
?>
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'मील-पत्थर ट्रैकिंग':'Milestone Tracking' ?></h1>
    <p class="text-sm text-slate-500"><?= is_hi()?'परियोजना-वार मील-पत्थर एवं विलंब':'Per-project milestones & delays' ?> · PPMS</p></div>
  </div>
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
        <tr><th class="text-left px-4 py-3"><?= is_hi()?'परियोजना':'Project' ?></th><th class="text-left px-4 py-3 hidden md:table-cell">Division</th>
        <th class="text-left px-4 py-3"><?= is_hi()?'मील-पत्थर':'Milestones' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'विलंबित':'Delayed' ?></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($rows as $r): ?>
          <tr class="hover:bg-paper cursor-pointer" onclick="location.href='?project=<?= $r['id'] ?>'">
            <td class="px-4 py-3 font-medium text-slate-800"><?= bi($r['name'],$r['name_hi']) ?></td>
            <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= e($r['divn']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= (int)$r['done'] ?>/<?= (int)$r['total'] ?> <?= is_hi()?'पूर्ण':'done' ?></td>
            <td class="px-4 py-3"><?= (int)$r['delayed']>0 ? '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset bg-rose-100 text-rose-800 ring-rose-600/20">'.(int)$r['delayed'].'</span>' : '<span class="text-xs text-slate-400">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif;
require __DIR__ . '/../../includes/footer.php'; ?>
