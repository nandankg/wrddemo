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

/** Effective milestone status: an unfinished item past its planned date is Delayed. */
function ppms_milestone_status(string $status, ?string $actual, string $planned, string $today): string {
    if ($status === 'Done') return 'Done';
    if ($planned < $today)  return 'Delayed';
    return $status;
}

/** Weighted completion % from a project's milestones (only Done counts). */
function ppms_milestone_progress(array $milestones): int {
    $total = 0; $done = 0;
    foreach ($milestones as $m) {
        $w = (int)$m['weight'];
        $total += $w;
        if ($m['status'] === 'Done') $done += $w;
    }
    return $total > 0 ? (int)round($done / $total * 100) : 0;
}

/** Per-division BI aggregates as an indexed list sorted by division name. */
function ppms_bi_by_division(array $projects): array {
    $acc = [];
    foreach ($projects as $p) {
        $d = $p['divn'];
        if (!isset($acc[$d])) $acc[$d] = ['divn'=>$d,'count'=>0,'physSum'=>0,'finSum'=>0,'sanctioned'=>0.0,'spent'=>0.0];
        $acc[$d]['count']++;
        $acc[$d]['physSum']    += (int)$p['physical_pct'];
        $acc[$d]['finSum']     += (int)$p['financial_pct'];
        $acc[$d]['sanctioned'] += (float)$p['sanctioned_amount'];
        $acc[$d]['spent']      += (float)$p['spent_amount'];
    }
    ksort($acc);
    $out = [];
    foreach ($acc as $a) {
        $out[] = [
            'divn'        => $a['divn'],
            'count'       => $a['count'],
            'phys'        => $a['count'] ? (int)round($a['physSum']/$a['count']) : 0,
            'fin'         => $a['count'] ? (int)round($a['finSum']/$a['count']) : 0,
            'sanctioned'  => $a['sanctioned'],
            'spent'       => $a['spent'],
            'utilisation' => $a['sanctioned'] > 0 ? (int)round($a['spent']/$a['sanctioned']*100) : 0,
        ];
    }
    return $out;
}

/** Shape already-fetched rows into {columns, rows} for a report type (drives preview + every export). */
function ppms_report_dataset(string $type, array $rows): array {
    $maps = [
        'project' => [
            'columns' => ['Project','Scheme','Division','Status','Physical %','Financial %','Sanctioned (₹)','Spent (₹)'],
            'keys'    => ['name','scheme','divn','status','physical_pct','financial_pct','sanctioned_amount','spent_amount'],
        ],
        'division' => [
            'columns' => ['Division','Projects','Avg Physical %','Avg Financial %','Sanctioned (₹)','Spent (₹)','Utilisation %'],
            'keys'    => ['divn','count','phys','fin','sanctioned','spent','utilisation'],
        ],
        'scheme' => [
            'columns' => ['Scheme','Projects','Avg Physical %','Sanctioned (₹)','Spent (₹)'],
            'keys'    => ['scheme','count','phys','sanctioned','spent'],
        ],
        'requisition' => [
            'columns' => ['Req No','Project','Division','Amount (₹)','Status','Allocated (₹)'],
            'keys'    => ['req_no','proj','divn','amount_requested','status','allocated_amount'],
        ],
    ];
    $m = $maps[$type] ?? $maps['project'];
    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($m['keys'] as $k) $line[] = $r[$k] ?? '';
        $out[] = $line;
    }
    return ['columns' => $m['columns'], 'rows' => $out];
}

/** Next scheduled-report run date from a base date; unknown frequency defaults to monthly. */
function ppms_next_run(string $frequency, string $from): string {
    $map = ['Daily'=>'+1 day','Weekly'=>'+1 week','Monthly'=>'+1 month','Quarterly'=>'+3 months'];
    $add = $map[$frequency] ?? '+1 month';
    return date('Y-m-d', strtotime($add, strtotime($from)));
}

/** Simulated 6-digit OTP (demo only; shown on-screen, never sent over a real channel). */
function ppms_otp_generate(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/** Write a simulated notification (SMS / OTP / EMAIL). DB side-effect; not unit-tested. */
function ppms_notify(PDO $pdo, string $channel, string $to, string $message, ?string $entity = null): void {
    $pdo->prepare('INSERT INTO notifications (channel,to_label,message,entity,status) VALUES (?,?,?,?,?)')
        ->execute([$channel, $to, $message, $entity, 'Sent']);
}

/** Unread notification count (for the header bell). DB read; not unit-tested. */
function ppms_unread_count(PDO $pdo): int {
    try {
        return (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read=0')->fetchColumn();
    } catch (Throwable $e) {
        return 0;   // table may not exist yet on a fresh checkout
    }
}

/** Require a logged-in user; bounce to the PPMS login (not the generic one) if not. */
function ppms_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/ppms/login.php')); exit;
    }
}
