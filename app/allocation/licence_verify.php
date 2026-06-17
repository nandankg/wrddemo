<?php
/**
 * Public licence verification (RFP §8.2.2). No login — reached by scanning the
 * QR on an issued Water Allocation Licence. Looks the licence up by qr_token.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';

$token = $_GET['token'] ?? '';
$a = null;
if ($token !== '') {
    $st = db()->prepare("SELECT a.*,d.name divn,d.circle FROM allocations a JOIN divisions d ON d.id=a.division_id WHERE a.qr_token=? AND a.status='Approved'");
    $st->execute([$token]); $a = $st->fetch();
}
$validUpto = $a && $a['valid_upto'] ? $a['valid_upto'] : ($a ? date('Y-m-d', strtotime($a['applied_on'].' +5 years')) : '');
$expired = $a ? allocation_days_to_expiry($validUpto, date('Y-m-d')) < 0 : false;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Licence Verification · WRD Jharkhand</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Mukta:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Mukta',sans-serif}.d{font-family:'Fraunces',serif}</style></head>
<body class="bg-slate-100 min-h-screen grid place-items-center p-6">
<div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
  <div class="bg-[#06314a] text-white px-6 py-5 flex items-center gap-3">
    <svg width="22" height="22" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg>
    <div><div class="d font-semibold">Water Allocation Licence Verification</div><div class="text-xs text-cyan-100">WRD, Government of Jharkhand</div></div>
  </div>
  <div class="p-7 text-center">
    <?php if($a && !$expired): ?>
      <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center text-3xl mx-auto">✓</div>
      <h1 class="d text-xl font-semibold text-[#06314a] mt-3">Authentic &amp; Valid</h1>
      <table class="w-full text-sm mt-5 text-left border border-slate-200 rounded-xl overflow-hidden">
        <tbody class="divide-y divide-slate-100">
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Licensee</td><td class="px-3 py-2 font-medium"><?= e($a['applicant']) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Licence No</td><td class="px-3 py-2 font-mono text-xs"><?= e($a['license_no']) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Source</td><td class="px-3 py-2"><?= e($a['source']) ?> — <?= e($a['source_name']) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Allocation</td><td class="px-3 py-2 font-semibold"><?= (float)$a['quantity_mld'] ?> MLD (<?= e($a['season']) ?>)</td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Division</td><td class="px-3 py-2"><?= e($a['divn']) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Valid Upto</td><td class="px-3 py-2 font-semibold text-emerald-700"><?= date('d M Y',strtotime($validUpto)) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Fee Status</td><td class="px-3 py-2"><?= allocation_fee_paid($a)?'<span class="text-emerald-700 font-semibold">Paid</span>':'<span class="text-amber-700 font-semibold">Due</span>' ?></td></tr>
        </tbody>
      </table>
      <p class="text-[11px] text-slate-400 mt-3 font-mono">Digital Sig ID <?= e(allocation_signature_id((string)$a['license_no'],(string)$a['qr_token'])) ?></p>
    <?php elseif($a && $expired): ?>
      <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-600 grid place-items-center text-3xl mx-auto">!</div>
      <h1 class="d text-xl font-semibold text-amber-700 mt-3">Licence Expired</h1>
      <p class="text-sm text-slate-600 mt-2"><?= e($a['applicant']) ?> (<?= e($a['license_no']) ?>) expired on <b><?= date('d M Y',strtotime($validUpto)) ?></b>. Renewal required.</p>
    <?php else: ?>
      <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 grid place-items-center text-3xl mx-auto">?</div>
      <h1 class="d text-xl font-semibold text-slate-700 mt-3">Not Found</h1>
      <p class="text-sm text-slate-500 mt-2">No licence matches this code. It may be invalid or revoked.</p>
    <?php endif; ?>
    <p class="text-[11px] text-slate-400 mt-6">Verified in real time against the WRD Integrated Digital Backbone.</p>
  </div>
</div>
</body></html>
