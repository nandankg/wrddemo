<?php
declare(strict_types=1);

/**
 * PPMS pure logic — no DB, no rendering. Callers pass already-fetched rows.
 * Keeps the dashboards/pages thin and these rules unit-testable.
 */

/** Aggregate project KPIs. $projects: rows with status, sanctioned_amount, spent_amount, physical_pct. */
function ppms_kpis(array $projects): array {
    $sanctioned = 0.0; $spent = 0.0; $physSum = 0; $atRisk = 0; $byStatus = [];
    foreach ($projects as $p) {
        $sanctioned += (float)$p['sanctioned_amount'];
        $spent      += (float)$p['spent_amount'];
        $physSum    += (int)$p['physical_pct'];
        $st = $p['status'];
        $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        if ($st === 'Delayed' || $st === 'Critical') $atRisk++;
    }
    $n = count($projects);
    return [
        'sanctioned'   => $sanctioned,
        'spent'        => $spent,
        'utilisation'  => $sanctioned > 0 ? (int)round($spent / $sanctioned * 100) : 0,
        'count'        => $n,
        'at_risk'      => $atRisk,
        'avg_physical' => $n > 0 ? (int)round($physSum / $n) : 0,
        'by_status'    => $byStatus,
    ];
}

/** Fund-requisition KPIs. $reqs: rows with status, amount_requested, allocated_amount. */
function ppms_fund_kpis(array $reqs): array {
    $pendingRelease = 0; $underFinance = 0; $released = 0.0;
    foreach ($reqs as $r) {
        if ($r['status'] === 'Approved by Finance')  $pendingRelease++;
        if ($r['status'] === 'Under Finance Review') $underFinance++;
        if ($r['status'] === 'Released')             $released += (float)$r['allocated_amount'];
    }
    return [
        'pending_release' => $pendingRelease,
        'under_finance'   => $underFinance,
        'released_amount' => $released,
        'count'           => count($reqs),
    ];
}

/** Map a role to its dashboard archetype. */
function ppms_role_view(string $role): string {
    switch ($role) {
        case 'JE': case 'AE':      return 'field';
        case 'EE':                 return 'division';
        case 'FINANCE':            return 'finance';
        case 'SE': case 'EIC': case 'SECRETARY': default: return 'oversight';
    }
}

/** Validate a progress percentage. */
function ppms_valid_pct(int $v): bool { return $v >= 0 && $v <= 100; }

/**
 * Pending actions for a role.
 * $reqs: fund requisitions (id, req_no, status, amount_requested).
 * $progress: progress updates (id, project_name, status).
 * Returns rows: ['label','meta','status','url'].
 */
function ppms_pending_actions(string $role, array $reqs, array $progress): array {
    $out = [];
    if (in_array($role, ['EE','SE','EIC'], true)) {
        foreach ($reqs as $r) if ($r['status'] === 'Pending Review')
            $out[] = ['label'=>'Review requisition '.$r['req_no'], 'meta'=>(string)$r['amount_requested'], 'status'=>$r['status'], 'url'=>'requisitions.php?id='.$r['id']];
    }
    if ($role === 'EIC') {
        foreach ($reqs as $r) if ($r['status'] === 'Approved by Finance')
            $out[] = ['label'=>'Release fund '.$r['req_no'], 'meta'=>(string)$r['amount_requested'], 'status'=>$r['status'], 'url'=>'requisitions.php?id='.$r['id']];
    }
    if ($role === 'FINANCE') {
        foreach ($reqs as $r) if ($r['status'] === 'Under Finance Review')
            $out[] = ['label'=>'Finance review '.$r['req_no'], 'meta'=>(string)$r['amount_requested'], 'status'=>$r['status'], 'url'=>'requisitions.php?id='.$r['id']];
    }
    if ($role === 'AE') {
        foreach ($progress as $g) if ($g['status'] === 'Submitted')
            $out[] = ['label'=>'Verify progress · '.$g['project_name'], 'meta'=>'', 'status'=>$g['status'], 'url'=>'projects.php?id='.$g['id']];
    }
    if ($role === 'JE') {
        foreach ($progress as $g) if ($g['status'] === 'Rejected')
            $out[] = ['label'=>'Resubmit progress · '.$g['project_name'], 'meta'=>'', 'status'=>$g['status'], 'url'=>'projects.php?id='.$g['id']];
    }
    return $out;
}

/** Require a logged-in user; bounce to the PPMS login (not the generic one) if not. */
function ppms_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/ppms/login.php')); exit;
    }
}
