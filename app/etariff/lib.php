<?php
declare(strict_types=1);

/**
 * E-Tariff pure logic — no DB, no rendering. Callers pass already-fetched rows.
 */

/**
 * Compute a water bill with slab-based variable charges.
 * Slabs (units): 0–500,000 @ ₹0.75 ; 500,001–1,000,000 @ ₹0.90 ; above @ ₹1.10.
 * Category sets the fixed charge. Excess drawal is penalised at ₹2.10/unit. GST 18%.
 */
function etariff_compute_bill(string $category, float $consumption, float $excess): array {
    $fixedByCat = [
        'Industrial Units'              => 25000.0,
        'Public Sector Undertakings'    => 25000.0,
        'Private Companies'             => 25000.0,
        'Municipal Bodies'             => 15000.0,
    ];
    $fixed = $fixedByCat[$category] ?? 20000.0;

    $rem = max(0.0, $consumption); $variable = 0.0;
    $s1 = min($rem, 500000.0); $variable += $s1 * 0.75; $rem -= $s1;
    if ($rem > 0) { $s2 = min($rem, 500000.0); $variable += $s2 * 0.90; $rem -= $s2; }
    if ($rem > 0) { $variable += $rem * 1.10; }
    $variable = round($variable, 2);

    $excessChg = round(max(0.0, $excess) * 2.10, 2);
    $penalty = 0.0; $interest = 0.0;
    $sub = $fixed + $variable + $excessChg + $penalty + $interest;
    $gst = round($sub * 0.18, 2);
    $total = round($sub + $gst, 2);
    return compact('fixed','variable','excessChg','penalty','interest','gst','total');
}

/** Map a role to its dashboard archetype. */
function etariff_role_view(string $role): string {
    switch ($role) {
        case 'CONSUMER':                return 'consumer';
        case 'JE': case 'AE': case 'EE': return 'billing';
        case 'ACCOUNTS': case 'SECRETARY': case 'EIC': default: return 'revenue';
    }
}

/** Bill KPIs. $bills: rows with status, total. */
function etariff_bill_kpis(array $bills): array {
    $c = ['draft'=>0,'pending'=>0,'approved'=>0,'demand_raised'=>0,'paid'=>0];
    $outstanding = 0.0; $collected = 0.0;
    foreach ($bills as $b) {
        switch ($b['status']) {
            case 'Draft':                 $c['draft']++; break;
            case 'Pending Verification':  $c['pending']++; break;
            case 'Approved':              $c['approved']++; break;
            case 'Demand Raised':         $c['demand_raised']++; $outstanding += (float)$b['total']; break;
            case 'Paid':                  $c['paid']++; $collected += (float)$b['total']; break;
        }
    }
    return $c + ['outstanding'=>$outstanding, 'collected'=>$collected];
}

/**
 * Pending actions for a role. $bills: rows with id, bill_no, status, total, cname.
 * Returns rows: ['label','meta','status','url'].
 */
function etariff_pending_actions(string $role, array $bills): array {
    $map = [
        'JE'       => 'Draft',
        'AE'       => 'Pending Verification',
        'EE'       => 'Approved',
        'CONSUMER' => 'Demand Raised',
    ];
    $want = $map[$role] ?? null;
    if ($want === null) return [];
    $verb = ['JE'=>'Submit bill','AE'=>'Verify bill','EE'=>'Raise demand','CONSUMER'=>'Pay bill'][$role];
    $out = [];
    foreach ($bills as $b) if ($b['status'] === $want)
        $out[] = ['label'=>$verb.' '.$b['bill_no'].' · '.$b['cname'], 'meta'=>(string)$b['total'], 'status'=>$b['status'], 'url'=>'bills.php?id='.$b['id']];
    return $out;
}

/** Require a logged-in user; bounce to the E-Tariff login if not. */
function etariff_require_login(): void {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        header('Location: ' . base_url('app/etariff/login.php')); exit;
    }
}
