<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
$pdo=db(); $id=(int)($_GET['id']??0);
$a=$pdo->query("SELECT a.*,d.name divn,d.circle FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.id=$id AND a.status='Approved'")->fetch();
if(!$a){ http_response_code(404); exit('Licence not available.'); }
// Ensure a public verification token exists (legacy approvals may predate Phase 2).
if(empty($a['qr_token'])){ $a['qr_token']=bin2hex(random_bytes(8)); $pdo->prepare("UPDATE allocations SET qr_token=? WHERE id=?")->execute([$a['qr_token'],$id]); }
$verifyUrl=allocation_abs_url('app/allocation/licence_verify.php?token='.$a['qr_token']);
$sigId=allocation_signature_id((string)$a['license_no'],(string)$a['qr_token']);
$validUpto=$a['valid_upto']?:date('Y-m-d',strtotime($a['applied_on'].' +5 years'));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Water Allocation Licence · <?= e($a['license_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Mukta:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="<?= base_url('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
<style>body{font-family:'Mukta',sans-serif}.d{font-family:'Fraunces',serif}@media print{.noprint{display:none}}</style></head>
<body class="bg-slate-100 py-8">
<div class="max-w-3xl mx-auto mb-4 noprint flex justify-between">
  <a href="<?= base_url('app/allocation/index.php') ?>" class="text-sm text-slate-600">← Back</a>
  <button onclick="print()" class="bg-[#0891b2] text-white px-5 py-2 rounded-lg font-semibold text-sm">🖨 Print / Save PDF</button>
</div>
<div class="max-w-3xl mx-auto bg-white shadow-xl p-12 border-t-4 border-[#0891b2] relative">
  <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
    <span class="d text-[120px] text-[#0891b2]/5 font-semibold rotate-[-25deg]">LICENSED</span>
  </div>
  <div class="relative">
    <div class="flex items-center justify-between border-b border-slate-200 pb-5">
      <div class="flex items-center gap-3">
        <span class="grid place-items-center w-12 h-12 rounded-xl bg-[#06314a] text-white">
          <svg width="24" height="24" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg></span>
        <div><div class="d font-semibold text-[#06314a] text-lg">Water Resources Department</div><div class="text-xs text-slate-500">Government of Jharkhand</div></div>
      </div>
      <div class="text-right text-xs text-slate-500">Licence: <span class="font-mono"><?= e($a['license_no']) ?></span><br>Date: <?= date('d M Y',strtotime($a['applied_on'])) ?></div>
    </div>
    <h1 class="d text-2xl font-semibold text-center text-[#06314a] mt-8">Industrial Water Allocation Licence</h1>
    <p class="text-center text-sm text-slate-500">औद्योगिक जल आवंटन लाइसेंस</p>
    <p class="text-sm text-slate-700 mt-8 leading-relaxed text-center">This licence authorises <b><?= e($a['applicant']) ?></b> to draw water as allocated below, subject to the terms and seasonal policy of the Water Resources Department, Government of Jharkhand.</p>
    <table class="w-full text-sm mt-6 border border-slate-200 rounded-lg overflow-hidden">
      <tbody class="divide-y divide-slate-200">
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600 w-1/2">Source</td><td class="px-4 py-2.5"><?= e($a['source']) ?> — <?= e($a['source_name']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Allocated Quantity</td><td class="px-4 py-2.5 font-semibold"><?= (float)$a['quantity_mld'] ?> MLD (<?= e($a['season']) ?>)</td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Division / Circle</td><td class="px-4 py-2.5"><?= e($a['divn']) ?> · <?= e($a['circle']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Validity</td><td class="px-4 py-2.5"><?= date('d M Y',strtotime($a['applied_on'])) ?> — <span class="font-semibold"><?= date('d M Y',strtotime($validUpto)) ?></span> (5 years)</td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Annual Fee</td><td class="px-4 py-2.5 font-semibold text-emerald-700"><?= inr_full((float)$a['annual_fee']) ?>
          <?php if(allocation_fee_paid($a)): ?><span class="ml-2 text-[10px] font-bold uppercase tracking-wide bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">Fee Paid</span><?php else: ?><span class="ml-2 text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">Fee Due</span><?php endif; ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">GSTIN</td><td class="px-4 py-2.5"><?= e($a['gst']) ?></td></tr>
      </tbody>
    </table>
    <div class="flex items-end justify-between mt-12">
      <div class="text-center">
        <div id="qrcode" class="w-28 h-28 bg-white border border-slate-300 grid place-items-center p-1.5"></div>
        <p class="text-[10px] text-slate-400 mt-1">📱 Scan to verify · <?= e($a['license_no']) ?></p>
      </div>
      <div class="text-center">
        <div class="inline-block text-left bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
          <div class="flex items-center gap-1.5 text-emerald-700 font-semibold text-sm">🔒 Digitally Signed</div>
          <div class="text-xs text-slate-600 mt-0.5">Anjali Verma, IAS — Secretary, WRD</div>
          <div class="text-[10px] text-slate-400 mt-1 font-mono">Sig ID <?= e($sigId) ?></div>
          <div class="text-[10px] text-slate-400 font-mono"><?= date('d M Y H:i') ?> IST</div>
        </div>
        <div class="border-t border-slate-400 pt-1 mt-2 px-6 text-sm font-semibold text-[#06314a]">Secretary</div>
        <div class="text-xs text-slate-500">WRD, Jharkhand</div>
      </div>
    </div>
  </div>
</div>
<script>
  new QRCode(document.getElementById("qrcode"), {
    text: <?= json_encode($verifyUrl) ?>, width: 104, height: 104,
    colorDark: "#06314a", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.M
  });
</script>
</body></html>
