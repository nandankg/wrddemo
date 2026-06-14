<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
contractor_require_login();
$pdo=db(); $id=(int)($_GET['id']??0);
$c=$pdo->query("SELECT * FROM contractors WHERE id=$id")->fetch();
if(!$c){ http_response_code(404); exit('Not found.'); }
$verifyUrl=base_url('app/contractor/verify.php').'?token='.$c['qr_token'];
$pushed=isset($_GET['digilocker']);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Registration Certificate · <?= e($c['reg_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Mukta:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Mukta',sans-serif}.d{font-family:'Fraunces',serif}@media print{.noprint{display:none}}</style></head>
<body class="bg-slate-100 py-8">
<div class="max-w-3xl mx-auto mb-4 noprint flex flex-wrap justify-between gap-2">
  <a href="<?= base_url('app/contractor/index.php') ?>" class="text-sm text-slate-600">← Back</a>
  <div class="flex gap-2">
    <?php if(!$pushed): ?><a href="?id=<?= $id ?>&digilocker=1" class="bg-[#06314a] text-white px-4 py-2 rounded-lg font-semibold text-sm">📤 Push to DigiLocker</a><?php endif; ?>
    <a href="<?= e($verifyUrl) ?>" target="_blank" class="border border-slate-300 bg-white px-4 py-2 rounded-lg font-semibold text-sm">🔍 Verify (public)</a>
    <button onclick="print()" class="bg-[#0E7C86] text-white px-4 py-2 rounded-lg font-semibold text-sm">🖨 Print</button>
  </div>
</div>

<?php if($pushed): ?>
<div class="max-w-3xl mx-auto mb-4 noprint bg-emerald-50 ring-1 ring-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">✓ Certificate pushed to DigiLocker for <b><?= e($c['name']) ?></b>. Available under "Issued Documents".</div>
<?php endif; ?>

<div class="max-w-3xl mx-auto bg-white shadow-xl p-12 border-t-4 border-[#0E7C86] relative">
  <div class="flex items-center justify-between border-b border-slate-200 pb-5">
    <div class="flex items-center gap-3">
      <span class="grid place-items-center w-12 h-12 rounded-xl bg-[#06314a] text-white"><svg width="24" height="24" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg></span>
      <div><div class="d font-semibold text-[#06314a] text-lg">Water Resources Department</div><div class="text-xs text-slate-500">Government of Jharkhand</div></div>
    </div>
    <div class="text-right text-xs text-slate-500">Reg No: <span class="font-mono"><?= e($c['reg_no']) ?></span></div>
  </div>
  <h1 class="d text-2xl font-semibold text-center text-[#06314a] mt-8">Contractor Registration Certificate</h1>
  <p class="text-center text-sm text-slate-500">ठेकेदार पंजीकरण प्रमाणपत्र</p>
  <p class="text-sm text-slate-700 mt-8 leading-relaxed text-center">This is to certify that <b><?= e($c['name']) ?></b>, <?= e($c['district']) ?>, is a registered and empanelled contractor with the Water Resources Department, Government of Jharkhand.</p>
  <table class="w-full text-sm mt-6 border border-slate-200 rounded-lg overflow-hidden">
    <tbody class="divide-y divide-slate-200">
      <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600 w-1/2">Registration Class</td><td class="px-4 py-2.5">Class <?= e($c['class']) ?></td></tr>
      <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">PAN</td><td class="px-4 py-2.5"><?= e($c['pan']) ?></td></tr>
      <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">GSTIN</td><td class="px-4 py-2.5"><?= e($c['gst']) ?></td></tr>
      <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Valid Upto</td><td class="px-4 py-2.5 font-semibold text-emerald-700"><?= date('d M Y',strtotime($c['valid_upto'])) ?></td></tr>
    </tbody>
  </table>
  <div class="flex items-end justify-between mt-12">
    <div class="text-center">
      <div class="w-28 h-28 bg-white border border-slate-300 grid place-items-center">
        <svg width="92" height="92" viewBox="0 0 29 29" shape-rendering="crispEdges"><?php
          mt_srand(crc32($c['qr_token'])); echo '<rect width="29" height="29" fill="#fff"/>';
          for($y=0;$y<29;$y++)for($x=0;$x<29;$x++){ if(mt_rand(0,2)===0) echo '<rect x="'.$x.'" y="'.$y.'" width="1" height="1" fill="#06314a"/>'; }
          foreach([[0,0],[22,0],[0,22]] as $cc){ echo '<rect x="'.$cc[0].'" y="'.$cc[1].'" width="7" height="7" fill="none" stroke="#06314a"/><rect x="'.($cc[0]+2).'" y="'.($cc[1]+2).'" width="3" height="3" fill="#06314a"/>'; }
        ?></svg>
      </div>
      <p class="text-[10px] text-slate-400 mt-1">Scan to verify authenticity</p>
    </div>
    <div class="text-center"><div class="h-12"></div><div class="border-t border-slate-400 pt-1 px-6 text-sm font-semibold text-[#06314a]">Engineer-in-Chief</div><div class="text-xs text-slate-500">WRD, Jharkhand</div></div>
  </div>
</div>
</body></html>
