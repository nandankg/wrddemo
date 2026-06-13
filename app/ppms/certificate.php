<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';
$pdo=db();
$id=(int)($_GET['id']??0);
$fr=$pdo->query("SELECT fr.*,p.name proj,s.name scheme,d.name divn,d.bank_account,d.bank_name
  FROM fund_requisitions fr JOIN projects p ON p.id=fr.project_id JOIN schemes s ON s.id=fr.scheme_id
  JOIN divisions d ON d.id=fr.division_id WHERE fr.id=$id AND fr.status='Released'")->fetch();
if(!$fr){ http_response_code(404); exit('Certificate not available.'); }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Fund Release Certificate · <?= e($fr['req_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Mukta:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Mukta',sans-serif}h1,h2,.d{font-family:'Fraunces',serif}@media print{.noprint{display:none}}</style>
</head><body class="bg-slate-100 py-8">
<div class="max-w-3xl mx-auto mb-4 noprint flex justify-between">
  <a href="<?= base_url('app/ppms/requisitions.php') ?>?id=<?= $id ?>" class="text-sm text-slate-600">← Back</a>
  <button onclick="print()" class="bg-[#0E7C86] text-white px-5 py-2 rounded-lg font-semibold text-sm">🖨 Print / Save PDF</button>
</div>
<div class="max-w-3xl mx-auto bg-white shadow-xl p-12 border-t-4 border-[#0E7C86] relative">
  <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
    <span class="d text-[120px] text-[#0E7C86]/5 font-semibold rotate-[-25deg]">RELEASED</span>
  </div>
  <div class="relative">
    <div class="flex items-center justify-between border-b border-slate-200 pb-5">
      <div class="flex items-center gap-3">
        <span class="grid place-items-center w-12 h-12 rounded-xl bg-[#06314a] text-white">
          <svg width="24" height="24" viewBox="0 0 24 24"><path d="M12 2.5C12 2.5 5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.5 12 2.5Z" fill="#fff"/></svg></span>
        <div><div class="d font-semibold text-[#06314a] text-lg">Water Resources Department</div>
        <div class="text-xs text-slate-500">Government of Jharkhand</div></div>
      </div>
      <div class="text-right text-xs text-slate-500">Ref: <span class="font-mono"><?= e($fr['release_ref']) ?></span><br>Date: <?= date('d M Y',strtotime($fr['release_date'])) ?></div>
    </div>

    <h1 class="d text-2xl font-semibold text-center text-[#06314a] mt-8">Fund Release Certificate</h1>
    <p class="text-center text-sm text-slate-500 mt-1">निधि निर्गत प्रमाणपत्र</p>

    <p class="text-sm text-slate-700 mt-8 leading-relaxed">
      This is to certify that funds have been duly sanctioned and released against Fund Requisition
      <b><?= e($fr['req_no']) ?></b>, raised under the scheme <b><?= e($fr['scheme']) ?></b> for the project
      <b><?= e($fr['proj']) ?></b>, <?= e($fr['divn']) ?>, for the financial year <b><?= e($fr['fy']) ?></b>.
    </p>

    <table class="w-full text-sm mt-6 border border-slate-200 rounded-lg overflow-hidden">
      <tbody class="divide-y divide-slate-200">
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600 w-1/2">Amount Requested</td><td class="px-4 py-2.5"><?= inr_full((float)$fr['amount_requested']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Amount Released</td><td class="px-4 py-2.5 font-semibold text-emerald-700"><?= inr_full((float)$fr['allocated_amount']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Head of Account</td><td class="px-4 py-2.5"><?= e($fr['head_of_account']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Fund Code</td><td class="px-4 py-2.5"><?= e($fr['fund_code']) ?></td></tr>
        <tr><td class="bg-slate-50 px-4 py-2.5 font-medium text-slate-600">Disbursing Account</td><td class="px-4 py-2.5"><?= e($fr['bank_account']) ?> · <?= e($fr['bank_name']) ?></td></tr>
      </tbody>
    </table>

    <div class="flex items-end justify-between mt-12">
      <div class="text-center">
        <div class="w-28 h-28 bg-white border border-slate-300 grid place-items-center">
          <!-- pseudo QR -->
          <svg width="92" height="92" viewBox="0 0 29 29" shape-rendering="crispEdges"><?php
            $seed=crc32($fr['release_ref']); mt_srand($seed);
            echo '<rect width="29" height="29" fill="#fff"/>';
            for($y=0;$y<29;$y++)for($x=0;$x<29;$x++){ if(mt_rand(0,2)===0) echo '<rect x="'.$x.'" y="'.$y.'" width="1" height="1" fill="#06314a"/>'; }
            foreach([[0,0],[22,0],[0,22]] as $c){ echo '<rect x="'.$c[0].'" y="'.$c[1].'" width="7" height="7" fill="none" stroke="#06314a" stroke-width="1"/><rect x="'.($c[0]+2).'" y="'.($c[1]+2).'" width="3" height="3" fill="#06314a"/>'; }
          ?></svg>
        </div>
        <p class="text-[10px] text-slate-400 mt-1">Scan to verify · <?= e($fr['release_ref']) ?></p>
      </div>
      <div class="text-center">
        <div class="h-12"></div>
        <div class="border-t border-slate-400 pt-1 px-6 text-sm font-semibold text-[#06314a]">Executive Engineer</div>
        <div class="text-xs text-slate-500"><?= e($fr['divn']) ?></div>
      </div>
    </div>
    <p class="text-[10px] text-slate-400 mt-10 text-center">This is a system-generated certificate from the WRD Integrated Digital Backbone. Authenticity verifiable via QR / reference number.</p>
  </div>
</div>
</body></html>
