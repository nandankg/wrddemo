<?php
declare(strict_types=1);

/**
 * Industrial Water Allocation pure logic — no DB, no rendering.
 * Approval chain per RFP §8.2.6: AE -> EE -> SE -> CE -> EIC -> SECRETARY (terminal, approves).
 */

/** Map a role to its dashboard archetype. */
function allocation_role_view(string $role): string {
    return $role === 'CONSUMER' ? 'applicant' : 'officer';
}

/** Next approval stage after $stage, or null at the terminal (SECRETARY). */
function allocation_next_stage(string $stage): ?string {
    return ['AE'=>'EE', 'EE'=>'SE', 'SE'=>'CE', 'CE'=>'EIC', 'EIC'=>'SECRETARY'][$stage] ?? null;
}

/** Annual allocation fee (demo rate: MLD x 50,000). */
function allocation_annual_fee(float $mld): float {
    return round(max(0.0, $mld) * 50000, 2);
}

/** KPIs. $rows: allocations with status. */
function allocation_kpis(array $rows): array {
    $inProcess = 0; $licensed = 0; $onHold = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'Approved') { $licensed++; continue; }
        if ($r['status'] === 'Rejected') continue;
        $inProcess++;
        if ($r['status'] === 'On Hold') $onHold++;
    }
    return ['in_process'=>$inProcess, 'licensed'=>$licensed, 'on_hold'=>$onHold, 'total'=>count($rows)];
}

/**
 * Pending actions for an officer role: applications at this role's stage that are
 * not yet Approved/Rejected. $rows: id, app_no, applicant, stage, status.
 */
function allocation_pending_actions(string $role, array $rows): array {
    if (!in_array($role, ['AE','EE','SE','CE','EIC','SECRETARY'], true)) return [];
    $verb = $role === 'SECRETARY' ? 'Approve & licence' : 'Scrutinise';
    $out = [];
    foreach ($rows as $r) {
        if ($r['stage'] !== $role) continue;
        if (in_array($r['status'], ['Approved','Rejected'], true)) continue;
        $out[] = ['label'=>$verb.' '.$r['app_no'].' · '.$r['applicant'], 'meta'=>'', 'status'=>$r['status'], 'url'=>'applications.php?id='.$r['id']];
    }
    return $out;
}

/** Require a logged-in user; bounce to the allocation login if not. */
function allocation_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/allocation/login.php')); exit;
    }
}
