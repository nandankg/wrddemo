<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/functions.php';
$token=$_GET['token']??'';
$c=null;
if($token){ $st=db()->prepare("SELECT * FROM contractors WHERE qr_token=?"); $st->execute([$token]); $c=$st->fetch(); }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Certificate Verification · WRD Jharkhand</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Mukta:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><style>body{font-family:'Mukta',sans-serif}.d{font-family:'Fraunces',serif}</style></head>
<body class="bg-slate-100 min-h-screen grid place-items-center p-6">
<div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
  <div class="bg-[#06314a] text-white px-6 py-5 flex items-center gap-3">
    <svg width="22" height="22" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg>
    <div><div class="d font-semibold">Certificate Verification</div><div class="text-xs text-cyan-100">WRD, Government of Jharkhand</div></div>
  </div>
  <div class="p-7 text-center">
    <?php if($c && $c['status']!=='Blacklisted'): ?>
      <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center text-3xl mx-auto">✓</div>
      <h1 class="d text-xl font-semibold text-[#06314a] mt-3">Authentic & Valid</h1>
      <table class="w-full text-sm mt-5 text-left border border-slate-200 rounded-xl overflow-hidden">
        <tbody class="divide-y divide-slate-100">
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Contractor</td><td class="px-3 py-2 font-medium"><?= e($c['name']) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Reg No</td><td class="px-3 py-2 font-mono text-xs"><?= e($c['reg_no']) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Class</td><td class="px-3 py-2">Class <?= e($c['class']) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Valid Upto</td><td class="px-3 py-2 font-semibold text-emerald-700"><?= date('d M Y',strtotime($c['valid_upto'])) ?></td></tr>
          <tr><td class="bg-slate-50 px-3 py-2 text-slate-500">Status</td><td class="px-3 py-2"><?= badge($c['status']) ?></td></tr>
        </tbody>
      </table>
    <?php elseif($c && $c['status']==='Blacklisted'): ?>
      <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-600 grid place-items-center text-3xl mx-auto">⚠</div>
      <h1 class="d text-xl font-semibold text-rose-700 mt-3">Blacklisted Contractor</h1>
      <p class="text-sm text-slate-600 mt-2"><?= e($c['name']) ?> (<?= e($c['reg_no']) ?>) is currently <b>blacklisted</b>. Do not engage.</p>
    <?php else: ?>
      <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 grid place-items-center text-3xl mx-auto">?</div>
      <h1 class="d text-xl font-semibold text-slate-700 mt-3">Not Found</h1>
      <p class="text-sm text-slate-500 mt-2">No certificate matches this code. It may be invalid or revoked.</p>
    <?php endif; ?>
    <p class="text-[11px] text-slate-400 mt-6">Verified in real time against the WRD Integrated Digital Backbone.</p>
  </div>
</div>
</body></html>
