<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db(); $u=current_user();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $act=$_POST['action']??'';
  if($act==='save'){
    $pdo->prepare("INSERT INTO content (type,title,title_hi,body,body_hi,category,status,publish_date,author,is_new) VALUES (?,?,?,?,?,?,?,CURDATE(),?,1)")
        ->execute([$_POST['type'],trim($_POST['title']),trim($_POST['title_hi']),trim($_POST['body']),trim($_POST['body_hi']),trim($_POST['category']),$_POST['status'],$u['name']]);
    flash('Content saved'.($_POST['status']==='Published'?' & published.':' as draft.'));
  } elseif($act==='toggle'){
    $id=(int)$_POST['id']; $cur=$pdo->query("SELECT status FROM content WHERE id=$id")->fetchColumn();
    $new=$cur==='Published'?'Draft':'Published';
    $pdo->prepare("UPDATE content SET status=? WHERE id=?")->execute([$new,$id]);
    flash("Content $new.");
  } elseif($act==='delete'){
    $pdo->prepare("DELETE FROM content WHERE id=?")->execute([(int)$_POST['id']]); flash('Content deleted.');
  }
  header('Location: index.php'); exit;
}
$LAYOUT='app'; $ACTIVE='cms'; $PAGE_TITLE='Website CMS';
require __DIR__ . '/../../includes/header.php';
$rows=$pdo->query("SELECT * FROM content ORDER BY publish_date DESC, id DESC")->fetchAll();
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div><h1 class="font-display text-2xl sm:text-3xl font-semibold text-ink"><?= t('cms') ?></h1>
  <p class="text-sm text-slate-500"><?= is_hi()?'द्विभाषी प्रकाशन · कार्यप्रवाह: लेखक → समीक्षक → अनुमोदक → प्रकाशक':'Bilingual publishing · Author → Reviewer → Approver → Publisher' ?></p></div>
  <button onclick="document.getElementById('newC').showModal()" class="bg-brand text-white font-semibold px-4 py-2.5 rounded-xl">+ <?= is_hi()?'नई सामग्री':'New Content' ?></button>
</div>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-paper text-slate-500 text-xs uppercase tracking-wide"><tr>
      <th class="text-left px-4 py-3"><?= is_hi()?'शीर्षक (EN / हिं)':'Title (EN / HI)' ?></th><th class="text-left px-4 py-3">Type</th>
      <th class="text-left px-4 py-3 hidden md:table-cell">Date</th><th class="text-left px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach($rows as $r): ?>
        <tr class="hover:bg-paper">
          <td class="px-4 py-3"><div class="font-medium text-slate-800"><?= e($r['title']) ?></div><div class="text-xs text-slate-500"><?= e($r['title_hi']) ?></div></td>
          <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-brandsoft text-branddeep"><?= e($r['type']) ?></span></td>
          <td class="px-4 py-3 text-slate-500 hidden md:table-cell"><?= date('d M Y',strtotime($r['publish_date'])) ?></td>
          <td class="px-4 py-3"><?= badge($r['status']==='Published'?'Approved':'Draft') ?> <span class="sr-only"><?= e($r['status']) ?></span></td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <form method="post" class="inline"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button name="action" value="toggle" class="text-xs font-semibold text-brand"><?= $r['status']==='Published'?'Unpublish':'Publish' ?></button></form>
            <form method="post" class="inline ml-2" onsubmit="return confirm('Delete?')"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button name="action" value="delete" class="text-xs font-semibold text-rose-500">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="text-xs text-slate-400 mt-3"><?= is_hi()?'प्रकाशित सामग्री तुरंत सार्वजनिक वेबसाइट पर दिखती है।':'Published content appears instantly on the public website (Tenders/Notices).' ?> <a href="<?= base_url('public/tenders.php') ?>" target="_blank" class="text-brand font-semibold"><?= is_hi()?'सार्वजनिक दृश्य':'View public site' ?> →</a></p>

<dialog id="newC" class="rounded-2xl p-0 w-full max-w-2xl backdrop:bg-black/40">
  <form method="post" class="p-6"><input type="hidden" name="action" value="save">
    <h2 class="font-display text-xl font-semibold text-ink mb-4"><?= is_hi()?'नई सामग्री (द्विभाषी)':'New Content (bilingual)' ?></h2>
    <div class="grid grid-cols-2 gap-3 mb-3">
      <div><label class="text-sm font-medium text-slate-700">Type</label>
        <select name="type" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"><option>notice</option><option>tender</option><option>news</option><option>scheme</option><option>order</option></select></div>
      <div><label class="text-sm font-medium text-slate-700">Category</label><input name="category" value="General" class="mt-1 w-full border border-slate-300 rounded-xl px-3 py-2.5"></div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
      <div class="space-y-2"><div class="text-xs font-bold uppercase text-slate-400">English</div>
        <input name="title" required placeholder="Title" class="w-full border border-slate-300 rounded-xl px-3 py-2.5">
        <textarea name="body" rows="4" placeholder="Body" class="w-full border border-slate-300 rounded-xl px-3 py-2.5"></textarea></div>
      <div class="space-y-2"><div class="text-xs font-bold uppercase text-slate-400">हिन्दी</div>
        <input name="title_hi" placeholder="शीर्षक" class="w-full border border-slate-300 rounded-xl px-3 py-2.5">
        <textarea name="body_hi" rows="4" placeholder="विवरण" class="w-full border border-slate-300 rounded-xl px-3 py-2.5"></textarea></div>
    </div>
    <div class="flex items-center gap-2 mt-4">
      <label class="text-sm font-medium text-slate-700">Status</label>
      <select name="status" class="border border-slate-300 rounded-xl px-3 py-2"><option>Published</option><option>Draft</option></select>
      <div class="flex-1"></div>
      <button type="button" onclick="document.getElementById('newC').close()" class="border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-600">Cancel</button>
      <button class="bg-brand text-white rounded-xl px-5 py-2.5 font-semibold"><?= is_hi()?'सहेजें':'Save' ?></button>
    </div>
  </form>
</dialog>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
