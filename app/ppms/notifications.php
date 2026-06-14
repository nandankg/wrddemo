<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
ppms_require_login();
$pdo = db();

// Opening the page clears the unread bell count.
$pdo->exec('UPDATE notifications SET is_read=1 WHERE is_read=0');
$rows = $pdo->query("SELECT * FROM notifications ORDER BY id DESC")->fetchAll();

set_app_context('ppms');
$LAYOUT='app'; $ACTIVE='notifications'; $PAGE_TITLE='Notifications';
require __DIR__ . '/../../includes/header.php';

$chip = ['SMS'=>'bg-sky-100 text-sky-800','OTP'=>'bg-violet-100 text-violet-800','EMAIL'=>'bg-emerald-100 text-emerald-800'];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= is_hi()?'सूचनाएँ':'Notifications' ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'एसएमएस / ओटीपी / ईमेल लॉग (सिम्युलेटेड)':'SMS / OTP / Email log (simulated gateway)' ?></p></div>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide">
      <tr><th class="text-left px-4 py-3"><?= is_hi()?'चैनल':'Channel' ?></th><th class="text-left px-4 py-3"><?= is_hi()?'प्राप्तकर्ता':'To' ?></th>
      <th class="text-left px-4 py-3"><?= is_hi()?'संदेश':'Message' ?></th><th class="text-left px-4 py-3 hidden md:table-cell"><?= is_hi()?'समय':'When' ?></th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($rows as $n): ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $chip[$n['channel']] ?? 'bg-slate-100 text-slate-700' ?>"><?= e($n['channel']) ?></span></td>
          <td class="px-4 py-3 text-slate-600"><?= e($n['to_label']) ?></td>
          <td class="px-4 py-3 text-slate-800"><?= e($n['message']) ?><?php if($n['entity']): ?><span class="text-xs text-slate-400"> · <?= e($n['entity']) ?></span><?php endif; ?></td>
          <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= date('d M Y, H:i',strtotime($n['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td class="px-4 py-6 text-center text-slate-400" colspan="4"><?= is_hi()?'कोई सूचना नहीं।':'No notifications yet.' ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
