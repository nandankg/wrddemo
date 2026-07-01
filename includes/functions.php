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
        'Verified'=>'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        'Submitted'=>'bg-amber-100 text-amber-800 ring-amber-600/20',
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

/**
 * Inline line-icon set for public tile surfaces (launcher, services, quick-actions).
 * Uses stroke="currentColor" so each icon inherits its tile's accent/text colour.
 * Prefer these over emoji: they render identically on every OS (no tofu / colour-font gaps).
 */
function wrd_icon(string $name, string $cls = 'w-6 h-6'): string {
    $paths = [
        // bar chart — PPMS / live project status
        'chart' => '<path d="M4 4v16h16"/><rect x="6.5" y="12" width="2.6" height="5" rx=".4"/><rect x="11.2" y="8" width="2.6" height="9" rx=".4"/><rect x="15.9" y="10.5" width="2.6" height="6.5" rx=".4"/>',
        // briefcase — contractor / empanelment
        'briefcase' => '<rect x="3" y="7.5" width="18" height="12.5" rx="2"/><path d="M8 7.5V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v1.5"/><path d="M3 12.5h18"/>',
        // water drop — allocation / schemes
        'droplet' => '<path d="M12 3.2c0 0 6.2 6.4 6.2 10.8a6.2 6.2 0 0 1-12.4 0C5.8 9.6 12 3.2 12 3.2z"/>',
        // receipt — e-tariff / pay bill
        'receipt' => '<path d="M6 3h12v18l-2-1.4-2 1.4-2-1.4-2 1.4-2-1.4-2 1.4z"/><path d="M9 8h6"/><path d="M9 12h6"/>',
        // classical building — departmental website / CMS
        'building' => '<path d="M3 9.5 12 4l9 5.5"/><path d="M4.5 9.5V20"/><path d="M9.5 9.5V20"/><path d="M14.5 9.5V20"/><path d="M19.5 9.5V20"/><path d="M3 20h18"/>',
        // document — RTI
        'document' => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4"/><path d="M9 12h6"/><path d="M9 16h4"/>',
        // life-buoy — grievance / support
        'lifebuoy' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.6"/><path d="M6 6l3.4 3.4"/><path d="M18 6l-3.4 3.4"/><path d="M6 18l3.4-3.4"/><path d="M18 18l-3.4-3.4"/>',
        // document + plus — new registration
        'register' => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4"/><path d="M12 11v6"/><path d="M9 14h6"/>',
        // circular arrow — renew
        'renew' => '<path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v4h-4"/>',
        // magnifier — track / search
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M16.5 16.5 21 21"/>',
        // document + check — download certificate
        'certificate' => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4"/><path d="M9 13.5l2 2 4-4"/>',
        // shield + check — verify contractor
        'verify' => '<path d="M12 3l7 3v5.5c0 4.7-3 7.7-7 9.5-4-1.8-7-4.8-7-9.5V6z"/><path d="M9 11.5l2 2 4-4"/>',
        // banknote — pay fees / bill
        'banknote' => '<rect x="2.5" y="6.5" width="19" height="11" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5"/><path d="M18 9.5v5"/>',
    ];
    $body = $paths[$name] ?? $paths['document'];
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $body . '</svg>';
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
