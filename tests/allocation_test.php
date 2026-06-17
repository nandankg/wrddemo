<?php
require_once __DIR__ . '/../app/allocation/lib.php';

it('allocation_role_view splits applicant vs officer', function () {
    assert_eq('applicant', allocation_role_view('CONSUMER'));
    foreach (['AE','EE','SE','CE','EIC','SECRETARY'] as $r) assert_eq('officer', allocation_role_view($r));
    assert_eq('officer', allocation_role_view('SOMETHING'));
});

it('allocation_next_stage walks AE->EE->SE->CE->EIC->SECRETARY->null', function () {
    assert_eq('EE',        allocation_next_stage('AE'));
    assert_eq('SE',        allocation_next_stage('EE'));
    assert_eq('CE',        allocation_next_stage('SE'));
    assert_eq('EIC',       allocation_next_stage('CE'));
    assert_eq('SECRETARY', allocation_next_stage('EIC'));
    assert_eq(null,        allocation_next_stage('SECRETARY'));
    assert_eq(null,        allocation_next_stage('UNKNOWN'));
});

it('allocation_annual_fee is MLD * 50000 rounded to paise', function () {
    assert_eq(4750000.0, allocation_annual_fee(95.0));
    assert_eq(2000000.0, allocation_annual_fee(40.0));
});

it('allocation_kpis counts process/licensed/hold/total', function () {
    $rows = [
      ['status'=>'New'],
      ['status'=>'Under Review'],
      ['status'=>'On Hold'],
      ['status'=>'Approved'],
      ['status'=>'Rejected'],
    ];
    $k = allocation_kpis($rows);
    assert_eq(3, $k['in_process']);
    assert_eq(1, $k['licensed']);
    assert_eq(1, $k['on_hold']);
    assert_eq(5, $k['total']);
});

it('allocation_pending_actions returns this officer stage work, none for applicant', function () {
    $rows = [
      ['id'=>1,'app_no'=>'A1','applicant'=>'Tata','stage'=>'AE','status'=>'New'],
      ['id'=>2,'app_no'=>'A2','applicant'=>'SAIL','stage'=>'SECRETARY','status'=>'Under Review'],
      ['id'=>3,'app_no'=>'A3','applicant'=>'Usha','stage'=>'EE','status'=>'Approved'],
    ];
    assert_eq('applications.php?id=1', allocation_pending_actions('AE', $rows)[0]['url']);
    assert_eq('applications.php?id=2', allocation_pending_actions('SECRETARY', $rows)[0]['url']);
    assert_eq(0, count(allocation_pending_actions('EE', $rows)));
    assert_eq(0, count(allocation_pending_actions('CONSUMER', $rows)));
});

/* ---- Phase 1: GIS / AI assist pure logic ---- */

it('allocation_utilisation and headroom derive from capacity', function () {
    $s = ['total_capacity_mld'=>800, 'allocated_mld'=>640];
    assert_eq(80.0, allocation_utilisation($s));
    assert_eq(160.0, allocation_headroom($s));
    assert_eq(0.0, allocation_utilisation(['total_capacity_mld'=>0,'allocated_mld'=>5])); // no divide-by-zero
    assert_eq(0.0, allocation_headroom(['total_capacity_mld'=>10,'allocated_mld'=>50]));  // never negative
});

it('allocation_util_tier bands available/moderate/critical', function () {
    assert_eq('available', allocation_util_tier(40)['key']);
    assert_eq('moderate',  allocation_util_tier(70)['key']);
    assert_eq('moderate',  allocation_util_tier(90)['key']);
    assert_eq('critical',  allocation_util_tier(90.1)['key']);
});

it('allocation_distance_km is ~0 for identical points and positive otherwise', function () {
    assert_eq(0.0, allocation_distance_km(23.4, 85.5, 23.4, 85.5));
    assert_true(allocation_distance_km(23.4, 85.5, 22.8, 86.2) > 50.0);
});

it('allocation_recommend_source prefers a source that meets the demand', function () {
    $sources = [
        ['name'=>'Tight Dam','district'=>'X','status'=>'Active','season'=>'Perennial','total_capacity_mld'=>100,'allocated_mld'=>95,'lat'=>23.0,'lng'=>85.0],
        ['name'=>'Big Reservoir','district'=>'Y','status'=>'Active','season'=>'Perennial','total_capacity_mld'=>800,'allocated_mld'=>200,'lat'=>23.7,'lng'=>85.8],
    ];
    $rec = allocation_recommend_source('Z', 50.0, $sources);
    assert_eq('Big Reservoir', $rec['name']);
    assert_true($rec['meets_quantity']);
});

it('allocation_recommend_source skips restricted sources and computes distance from ref', function () {
    $sources = [
        ['name'=>'Restricted','district'=>'X','status'=>'Restricted','season'=>'Perennial','total_capacity_mld'=>900,'allocated_mld'=>10,'lat'=>23.0,'lng'=>85.0],
        ['name'=>'Near Active','district'=>'X','status'=>'Active','season'=>'Perennial','total_capacity_mld'=>300,'allocated_mld'=>50,'lat'=>23.41,'lng'=>85.53],
    ];
    $rec = allocation_recommend_source('X', 20.0, $sources, ['lat'=>23.41,'lng'=>85.53]);
    assert_eq('Near Active', $rec['name']);
    assert_eq(0.0, $rec['distance_km']);
});

it('allocation_risk grades documents, headroom and season', function () {
    $src = ['total_capacity_mld'=>800,'allocated_mld'=>200,'status'=>'Active','season'=>'Perennial'];
    assert_eq('LOW', allocation_risk(['quantity_mld'=>50,'season'=>'Perennial','docs_total'=>4,'docs_present'=>4], $src)['level']);
    // demand exceeds headroom -> HIGH
    $tight = ['total_capacity_mld'=>100,'allocated_mld'=>95,'status'=>'Active','season'=>'Perennial'];
    assert_eq('HIGH', allocation_risk(['quantity_mld'=>50,'docs_total'=>4,'docs_present'=>4], $tight)['level']);
    // missing docs alone -> MEDIUM
    assert_eq('MEDIUM', allocation_risk(['quantity_mld'=>10,'docs_total'=>4,'docs_present'=>3], $src)['level']);
});

it('allocation_exec_summary names applicant, source and risk verdict', function () {
    $src = ['name'=>'Tenughat Reservoir','total_capacity_mld'=>820,'allocated_mld'=>640,'status'=>'Active','season'=>'Perennial'];
    $sum = allocation_exec_summary(['applicant'=>'ABC Steel','quantity_mld'=>50,'season'=>'Perennial','docs_total'=>4,'docs_present'=>4], $src);
    assert_true(str_contains($sum, 'ABC Steel'));
    assert_true(str_contains($sum, 'Tenughat Reservoir'));
    assert_true(str_contains($sum, 'recommended for approval'));
});

/* ---- Phase 2: payment / QR licence / renewal pure logic ---- */

it('allocation_challan_no is a stable padded JE-GRASS reference', function () {
    assert_eq('JEGRAS/2526/0044', allocation_challan_no(44));
    assert_eq('JEGRAS/2526/0007', allocation_challan_no(7));
});

it('allocation_fee_paid reads fee_status safely', function () {
    assert_true(allocation_fee_paid(['fee_status'=>'Paid']));
    assert_eq(false, allocation_fee_paid(['fee_status'=>'Unpaid']));
    assert_eq(false, allocation_fee_paid([]));   // missing key defaults to unpaid
});

it('allocation_signature_id is deterministic, 12 hex uppercase chars', function () {
    $a = allocation_signature_id('LIC/2526/0044','tok123');
    assert_eq($a, allocation_signature_id('LIC/2526/0044','tok123'));   // stable
    assert_true($a !== allocation_signature_id('LIC/2526/0044','tok124')); // token-sensitive
    assert_eq(1, preg_match('/^[0-9A-F]{12}$/', $a));
});

it('allocation_days_to_expiry counts whole days and goes negative when expired', function () {
    assert_eq(10, allocation_days_to_expiry('2026-01-11','2026-01-01'));
    assert_eq(0,  allocation_days_to_expiry('2026-01-01','2026-01-01'));
    assert_true(allocation_days_to_expiry('2025-12-20','2026-01-01') < 0);
});

it('allocation_is_due_renewal flags inside the 90-day window or expired', function () {
    assert_true(allocation_is_due_renewal('2026-03-01','2026-01-01'));   // ~59 days
    assert_true(allocation_is_due_renewal('2025-12-01','2026-01-01'));   // expired
    assert_eq(false, allocation_is_due_renewal('2027-01-01','2026-01-01')); // far off
    assert_eq(false, allocation_is_due_renewal('','2026-01-01'));        // no validity
});

it('allocation_abs_url falls back to a root-relative path without a host', function () {
    // In the CLI test harness there is no HTTP_HOST and no base_url(), so it stays relative.
    assert_eq('/app/allocation/licence_verify.php?token=x', allocation_abs_url('app/allocation/licence_verify.php?token=x'));
});
