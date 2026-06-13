<?php
declare(strict_types=1);

/** Format INR in Indian crore/lakh notation. */
function inr(float $n): string {
    if ($n >= 10000000)  return '₹' . rtrim(rtrim(number_format($n/10000000, 2), '0'), '.') . ' Cr';
    if ($n >= 100000)    return '₹' . rtrim(rtrim(number_format($n/100000, 2), '0'), '.') . ' L';
    return '₹' . number_format($n, 0);
}
function inr_full(float $n): string { return '₹' . number_format($n, 2); }

/** Status → tailwind badge classes. */
function badge(string $status): string {
    $map = [
        'Released'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'Paid'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'Approved'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'Approved by Finance'=>'bg-teal-100 text-teal-800 ring-teal-600/20',
        'Active'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'On Track'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'Resolved'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'Replied'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'Pending Review'=>'bg-amber-100 text-amber-800 ring-amber-600/20',
        'Under Finance Review'=>'bg-amber-100 text-amber-800 ring-amber-600/20',
        'Under Review'=>'bg-amber-100 text-amber-800 ring-amber-600/20',
        'Under Process'=>'bg-amber-100 text-amber-800 ring-amber-600/20',
        'Pending Verification'=>'bg-amber-100 text-amber-800 ring-amber-600/20',
        'Pending Approval'=>'bg-amber-100 text-amber-800 ring-amber-600/20',
        'Demand Raised'=>'bg-sky-100 text-sky-800 ring-sky-600/20',
        'In Progress'=>'bg-sky-100 text-sky-800 ring-sky-600/20',
        'Document Verification'=>'bg-sky-100 text-sky-800 ring-sky-600/20',
        'Renewal Due'=>'bg-orange-100 text-orange-800 ring-orange-600/20',
        'Delayed'=>'bg-orange-100 text-orange-800 ring-orange-600/20',
        'Escalated'=>'bg-orange-100 text-orange-800 ring-orange-600/20',
        'Draft'=>'bg-slate-100 text-slate-700 ring-slate-500/20',
        'New'=>'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
        'Rejected'=>'bg-rose-100 text-rose-800 ring-rose-600/20',
        'Blacklisted'=>'bg-rose-100 text-rose-800 ring-rose-600/20',
        'Critical'=>'bg-rose-100 text-rose-800 ring-rose-600/20',
    ];
    $cls = $map[$status] ?? 'bg-slate-100 text-slate-700 ring-slate-500/20';
    return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '
         . $cls . '">' . htmlspecialchars($status) . '</span>';
}

/** Risk band from score. */
function risk_band(int $score): array {
    if ($score >= 70) return ['High', 'bg-rose-100 text-rose-800'];
    if ($score >= 40) return ['Medium', 'bg-amber-100 text-amber-800'];
    return ['Low', 'bg-emerald-100 text-emerald-800'];
}

function add_audit(PDO $pdo, string $type, int $id, string $action, ?string $from, ?string $to, string $actor, string $remarks=''): void {
    $pdo->prepare('INSERT INTO workflow_log (entity_type,entity_id,action,from_role,to_role,actor,remarks) VALUES (?,?,?,?,?,?,?)')
        ->execute([$type,$id,$action,$from,$to,$actor,$remarks]);
}

function flash(string $msg=null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES); }
