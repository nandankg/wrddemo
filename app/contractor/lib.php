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
        $out[] = ['label'=>$verb.' '.$a['ack_no'].' · '.($a['cname'] ?? 'New applicant'), 'meta'=>'', 'status'=>$a['status'], 'url'=>'scrutiny.php?app_id='.$a['id']];
    }
    return $out;
}

/** Require a logged-in user; bounce to the contractor login if not. */
function contractor_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/contractor/login.php')); exit;
    }
}

/* ===========================================================================
 * Phase 1 — eligibility, AI document verification, public statistics.
 * All deterministic and offline-safe (no network, no DB).
 * =========================================================================== */

/**
 * Recommend the highest contractor class the credentials qualify for.
 * Thresholds (turnover in rupees): I = 10yr/10proj/5Cr, II = 7yr/6proj/3Cr,
 * III = 4yr/3proj/1.5Cr, else IV (entry level).
 */
function contractor_eligibility(int $years, int $projects, float $turnover): array {
    $tiers = [
        ['class'=>'I',   'yr'=>10, 'proj'=>10, 'turn'=>50000000.0],
        ['class'=>'II',  'yr'=>7,  'proj'=>6,  'turn'=>30000000.0],
        ['class'=>'III', 'yr'=>4,  'proj'=>3,  'turn'=>15000000.0],
    ];
    foreach ($tiers as $t) {
        if ($years >= $t['yr'] && $projects >= $t['proj'] && $turnover >= $t['turn']) {
            $cr = rtrim(rtrim(number_format($t['turn']/10000000, 1), '0'), '.');
            return ['class'=>$t['class'],
                    'reason'=>"Meets Class {$t['class']} bar: {$t['yr']}+ yrs, {$t['proj']}+ projects, ₹{$cr} Cr+ turnover."];
        }
    }
    return ['class'=>'IV', 'reason'=>'Entry-level eligibility — Class IV registration.'];
}

/**
 * Simulated AI document verification. Deterministic per ($doc,$seed): most
 * documents read Verified; a stable minority surface a realistic issue.
 */
function contractor_doc_verify(string $doc, int $seed = 0): array {
    $issues = ['Signature not detected', 'Date mismatch with PAN record', 'Low-resolution scan'];
    $h = crc32(mb_strtolower(trim($doc)) . '|' . $seed);
    if ($h % 6 === 0) {
        return ['status'=>'Issue', 'issue'=>$issues[$h % count($issues)]];
    }
    return ['status'=>'Verified', 'issue'=>null];
}

/**
 * Public landing statistics: a production-scale baseline plus live seed counts,
 * so the figures look real and still move with demo data.
 * $apps rows carry 'applied_on' (Y-m-d); $today defaults to the system date.
 */
function contractor_public_stats(array $contractors, array $apps, ?string $today = null,
        array $base = ['registered'=>12532, 'active'=>9840, 'apps_year'=>2140]): array {
    $year = (int)date('Y', $today ? (strtotime($today) ?: time()) : time());
    $active = 0;
    foreach ($contractors as $c) if (($c['status'] ?? '') === 'Active') $active++;
    $appsYear = 0;
    foreach ($apps as $a) if ((int)date('Y', strtotime((string)($a['applied_on'] ?? '1970-01-01'))) === $year) $appsYear++;
    return [
        'registered' => $base['registered'] + count($contractors),
        'active'     => $base['active'] + $active,
        'apps_year'  => $base['apps_year'] + $appsYear,
        'avg_days'   => 7,
    ];
}

/* ===========================================================================
 * Phase 2 — scoring engine, application breakdown & forward-guard.
 * =========================================================================== */

/**
 * Objective four-part evaluation score for a contractor.
 * $c: a contractors row (experience_yrs, completed_projects, turnover, class, status, risk_score).
 * $docResults: rows from contractor_doc_verify(); each 'Issue' lowers compliance.
 * Returns experience|financial|compliance|overall (0..100) plus band A/B/C.
 */
function contractor_score(array $c, array $docResults = []): array {
    $years    = (int)($c['experience_yrs'] ?? 0);
    $projects = (int)($c['completed_projects'] ?? 0);
    $turnover = (float)($c['turnover'] ?? 0);
    $risk     = (int)($c['risk_score'] ?? 0);

    $experience = (int)round(min($years, 10) / 10 * 50 + min($projects, 10) / 10 * 50);

    $thresholds = ['I'=>50000000.0, 'II'=>30000000.0, 'III'=>15000000.0, 'IV'=>5000000.0];
    $threshold  = $thresholds[$c['class'] ?? 'IV'] ?? 5000000.0;
    $ratio      = $threshold > 0 ? $turnover / $threshold : 0.0;
    $financial  = max(0, min(100, (int)round(40 + min($ratio, 1.2) * 50)));

    if (($c['status'] ?? '') === 'Blacklisted') {
        $compliance = 0;
    } else {
        $issues = 0;
        foreach ($docResults as $d) if (($d['status'] ?? '') === 'Issue') $issues++;
        $compliance = max(0, min(100, 100 - $risk - 15 * $issues));
    }

    $overall = (int)round($experience * 0.35 + $financial * 0.30 + $compliance * 0.35);
    $band    = $overall >= 80 ? 'A' : ($overall >= 60 ? 'B' : 'C');

    return ['experience'=>$experience, 'financial'=>$financial,
            'compliance'=>$compliance, 'overall'=>$overall, 'band'=>$band];
}

/**
 * Count back-office applications by review bucket for the Screen-6 strip.
 * Terminal and query states take priority over the workflow stage.
 */
function contractor_app_breakdown(array $apps): array {
    $b = ['new'=>0,'verifying'=>0,'approval_pending'=>0,'query'=>0,'approved'=>0,'rejected'=>0];
    foreach ($apps as $a) {
        $st = $a['status'] ?? ''; $stage = $a['stage'] ?? '';
        if      ($st === 'Rejected')     $b['rejected']++;
        elseif  ($st === 'Approved')     $b['approved']++;
        elseif  ($st === 'Query Raised') $b['query']++;
        elseif  ($stage === 'EIC')       $b['approval_pending']++;
        elseif  ($stage === 'ASO')       $b['new']++;
        else                             $b['verifying']++;
    }
    return $b;
}

/** Can this application be forwarded to the next stage right now? */
function contractor_can_forward(array $app, int $openQueries): bool {
    if ($openQueries > 0) return false;
    if (in_array($app['status'] ?? '', ['Approved','Rejected','Query Raised'], true)) return false;
    return contractor_next_stage($app['stage'] ?? '') !== null;
}
