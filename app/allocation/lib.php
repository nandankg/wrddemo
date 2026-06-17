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

/* ===========================================================================
 * Phase 1 — GIS / AI assist / analytics pure logic (no DB, no rendering).
 * A water source row: name, name_hi, type, district, lat, lng,
 *   total_capacity_mld, allocated_mld, season, status.
 * =========================================================================== */

/** Utilisation % of a source (allocated / total), 0..100+, safe on zero capacity. */
function allocation_utilisation(array $src): float {
    $total = (float)($src['total_capacity_mld'] ?? 0);
    if ($total <= 0) return 0.0;
    return round((float)($src['allocated_mld'] ?? 0) / $total * 100, 1);
}

/** Unallocated headroom (MLD) of a source, never negative. */
function allocation_headroom(array $src): float {
    return max(0.0, (float)($src['total_capacity_mld'] ?? 0) - (float)($src['allocated_mld'] ?? 0));
}

/**
 * Visual tier for a utilisation %, shared by the GIS map and analytics.
 * <70 Available (green) · 70-90 Moderate (amber) · >90 Critical (red).
 */
function allocation_util_tier(float $pct): array {
    if ($pct > 90) return ['key'=>'critical','color'=>'#dc2626','label'=>'Critical'];
    if ($pct >= 70) return ['key'=>'moderate','color'=>'#d97706','label'=>'Moderate'];
    return ['key'=>'available','color'=>'#059669','label'=>'Available'];
}

/** Great-circle distance in km between two lat/lng points (haversine). */
function allocation_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371.0;
    $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 1);
}

/**
 * Recommend the best water source for a request.
 * Ranks Active sources that can meet the quantity, preferring same-district /
 * nearest (when a $ref location is given) and lower utilisation. Always returns
 * a candidate (the roomiest) even if none fully meets the quantity.
 *
 * @param array      $sources rows as above
 * @param array|null $ref     ['lat'=>float,'lng'=>float] applicant location, optional
 * @return array|null augmented source: + headroom, utilisation_pct, distance_km, meets_quantity, reason
 */
function allocation_recommend_source(string $district, float $quantity, array $sources, ?array $ref = null): ?array {
    $cands = [];
    foreach ($sources as $s) {
        if (($s['status'] ?? 'Active') !== 'Active') continue;
        $head = allocation_headroom($s);
        $util = allocation_utilisation($s);
        $dist = ($ref && isset($s['lat'], $s['lng']) && $ref['lat'] !== null)
            ? allocation_distance_km((float)$ref['lat'], (float)$ref['lng'], (float)$s['lat'], (float)$s['lng'])
            : null;
        $cands[] = $s + [
            'headroom' => $head, 'utilisation_pct' => $util, 'distance_km' => $dist,
            'meets_quantity' => $head >= $quantity,
            '_same_district' => strcasecmp((string)($s['district'] ?? ''), $district) === 0,
        ];
    }
    if (!$cands) return null;

    usort($cands, function ($a, $b) {
        // 1. sources that can satisfy the demand come first
        if ($a['meets_quantity'] !== $b['meets_quantity']) return $b['meets_quantity'] <=> $a['meets_quantity'];
        // 2. nearest when distance known, else same-district, else roomiest
        if ($a['distance_km'] !== null && $b['distance_km'] !== null && $a['distance_km'] !== $b['distance_km'])
            return $a['distance_km'] <=> $b['distance_km'];
        if ($a['_same_district'] !== $b['_same_district']) return $b['_same_district'] <=> $a['_same_district'];
        // 3. lower utilisation (more sustainable headroom)
        return $a['utilisation_pct'] <=> $b['utilisation_pct'];
    });

    $best = $cands[0];
    $bits = [round($best['utilisation_pct']).'% utilised'];
    if ($best['distance_km'] !== null) $bits[] = $best['distance_km'].' km away';
    $bits[] = strtolower((string)($best['season'] ?? 'perennial'));
    $best['reason'] = ucfirst(implode(' · ', $bits))
        . ($best['meets_quantity'] ? '' : ' — limited headroom, technical review advised');
    unset($best['_same_district']);
    return $best;
}

/**
 * Risk grade for an application against a chosen source.
 * @param array      $app    ['quantity_mld'=>float,'season'=>str,'docs_total'=>int,'docs_present'=>int]
 * @param array|null $source chosen source row (optional)
 * @return array ['level'=>'LOW|MEDIUM|HIGH','score'=>int,'reasons'=>string[]]
 */
function allocation_risk(array $app, ?array $source = null): array {
    $score = 0; $reasons = [];
    $qty = (float)($app['quantity_mld'] ?? 0);

    $docsTotal   = (int)($app['docs_total']   ?? 4);
    $docsPresent = (int)($app['docs_present'] ?? 4);
    if ($docsPresent < $docsTotal) { $score += 2; $reasons[] = ($docsTotal - $docsPresent).' clearance document(s) missing'; }
    else                            { $reasons[] = 'All '.$docsTotal.' clearance documents present'; }

    if ($source) {
        $head = allocation_headroom($source);
        $utilAfter = (float)($source['total_capacity_mld'] ?? 0) > 0
            ? ((float)($source['allocated_mld'] ?? 0) + $qty) / (float)$source['total_capacity_mld'] * 100 : 0;
        if ($head < $qty)        { $score += 3; $reasons[] = 'Requested quantity exceeds available headroom'; }
        elseif ($utilAfter > 90) { $score += 2; $reasons[] = 'Source would exceed 90% utilisation after allocation'; }
        else                     { $reasons[] = 'Adequate headroom at the selected source'; }

        if (($source['status'] ?? 'Active') !== 'Active') { $score += 2; $reasons[] = 'Source is under restriction'; }
        if (($app['season'] ?? 'Perennial') === 'Perennial' && ($source['season'] ?? 'Perennial') === 'Seasonal') {
            $score += 1; $reasons[] = 'Perennial demand on a seasonal source';
        }
    }

    $level = $score >= 3 ? 'HIGH' : ($score >= 1 ? 'MEDIUM' : 'LOW');
    return ['level'=>$level, 'score'=>$score, 'reasons'=>$reasons];
}

/**
 * Deterministic executive summary paragraph for an application.
 * @param array      $app    ['applicant'=>str,'quantity_mld'=>float,'season'=>str]
 * @param array|null $source chosen source row (optional)
 * @param array|null $risk   result of allocation_risk() (optional; computed if null)
 */
function allocation_exec_summary(array $app, ?array $source = null, ?array $risk = null): string {
    $risk ??= allocation_risk($app, $source);
    $who    = trim((string)($app['applicant'] ?? 'The applicant'));
    $qty    = (float)($app['quantity_mld'] ?? 0);
    $season = strtolower((string)($app['season'] ?? 'perennial'));
    $srcName = $source['name'] ?? ($app['source_name'] ?? 'the requested source');

    $s = sprintf('%s has requested %s MLD of water on a %s basis from %s.', $who, rtrim(rtrim(number_format($qty,1),'0'),'.'), $season, $srcName);
    if ($source) {
        $util = allocation_utilisation($source);
        $s .= sprintf(' The source is currently %s%% utilised with %s MLD of headroom available.',
            rtrim(rtrim(number_format($util,1),'0'),'.'), rtrim(rtrim(number_format(allocation_headroom($source),1),'0'),'.'));
    }
    $docMsg = (int)($app['docs_present'] ?? 4) >= (int)($app['docs_total'] ?? 4)
        ? 'All statutory technical and environmental clearances are on record.'
        : 'Some statutory clearances are pending and must be obtained before sanction.';
    $s .= ' ' . $docMsg;
    $s .= $risk['level'] === 'LOW'
        ? ' Risk is assessed as LOW; the application is recommended for approval.'
        : ($risk['level'] === 'MEDIUM'
            ? ' Risk is assessed as MEDIUM; approval is recommended subject to technical scrutiny.'
            : ' Risk is assessed as HIGH; the application requires detailed technical review before any sanction.');
    return $s;
}

/* ===========================================================================
 * Phase 2 — payment / QR licence / renewal / inspection pure logic.
 * =========================================================================== */

/** Deterministic JE-GRASS challan number for an allocation id. */
function allocation_challan_no(int $id): string {
    return sprintf('JEGRAS/2526/%04d', $id);
}

/** Has the licence fee been paid? Safe on missing key. */
function allocation_fee_paid(array $a): bool {
    return ($a['fee_status'] ?? 'Unpaid') === 'Paid';
}

/**
 * Absolute URL (scheme://host/base/path) for off-device use such as a scannable QR.
 * Falls back to a relative URL when no host is known (CLI/tests).
 */
function allocation_abs_url(string $appRelative): string {
    $rel = function_exists('base_url') ? base_url($appRelative) : '/' . ltrim($appRelative, '/');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') return $rel;
    if (preg_match('#^https?://#', $rel)) return $rel;   // already absolute
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($rel, '/');
}

/** Short, stable simulated digital-signature id from licence no + token. */
function allocation_signature_id(string $licenceNo, string $token): string {
    return strtoupper(substr(hash('sha256', $licenceNo . '|' . $token), 0, 12));
}

/** Whole days from $today to $validUpto (negative if expired). */
function allocation_days_to_expiry(string $validUpto, string $today): int {
    $a = strtotime($validUpto); $b = strtotime($today);
    if ($a === false || $b === false) return PHP_INT_MAX;
    return (int)floor(($a - $b) / 86400);
}

/** Is the licence within the renewal window (or already expired)? */
function allocation_is_due_renewal(string $validUpto, string $today, int $window = 90): bool {
    if (trim($validUpto) === '') return false;
    return allocation_days_to_expiry($validUpto, $today) <= $window;
}
