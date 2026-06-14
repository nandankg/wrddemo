<?php
declare(strict_types=1);

/**
 * Contractor Registration pure logic — no DB, no rendering.
 */

/** Map a role to its dashboard archetype. */
function contractor_role_view(string $role): string {
    return $role === 'CONTRACTOR' ? 'contractor' : 'registry';
}

/** Next empanelment stage after $stage, or null at the terminal (EIC). */
function contractor_next_stage(string $stage): ?string {
    return ['ASO'=>'AE', 'AE'=>'EE', 'EE'=>'EIC'][$stage] ?? null;
}

/** Registration fee by class. */
function contractor_fee(string $class): float {
    return ['I'=>45000.0, 'II'=>30000.0, 'III'=>20000.0, 'IV'=>10000.0][$class] ?? 10000.0;
}

/** KPIs. $apps: rows with status. $contractors: rows with status. */
function contractor_kpis(array $apps, array $contractors): array {
    $inProcess = 0; $approved = 0;
    foreach ($apps as $a) {
        if ($a['status'] === 'Approved') { $approved++; continue; }
        if ($a['status'] === 'Rejected') continue;
        $inProcess++;
    }
    $active = 0; $blacklisted = 0;
    foreach ($contractors as $c) {
        if ($c['status'] === 'Active') $active++;
        if ($c['status'] === 'Blacklisted') $blacklisted++;
    }
    return [
        'in_process'  => $inProcess,
        'approved'    => $approved,
        'active'      => $active,
        'blacklisted' => $blacklisted,
        'total_apps'  => count($apps),
    ];
}

/**
 * Pending actions for a back-office role: applications sitting at this role's stage
 * that are not yet Approved/Rejected. $apps rows: id, ack_no, stage, status, cname.
 */
function contractor_pending_actions(string $role, array $apps): array {
    if (!in_array($role, ['ASO','AE','EE','EIC'], true)) return [];
    $verb = ['ASO'=>'Scrutinise', 'AE'=>'Verify (technical)', 'EE'=>'Recommend', 'EIC'=>'Approve & issue'][$role];
    $out = [];
    foreach ($apps as $a) {
        if ($a['stage'] !== $role) continue;
        if (in_array($a['status'], ['Approved','Rejected'], true)) continue;
        $out[] = ['label'=>$verb.' '.$a['ack_no'].' · '.($a['cname'] ?? 'New applicant'), 'meta'=>'', 'status'=>$a['status'], 'url'=>'applications.php?id='.$a['id']];
    }
    return $out;
}

/** Require a logged-in user; bounce to the contractor login if not. */
function contractor_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/contractor/login.php')); exit;
    }
}
