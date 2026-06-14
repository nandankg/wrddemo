<?php
require_once __DIR__ . '/../app/ppms/lib.php';

$projects = [
  ['status'=>'On Track','sanctioned_amount'=>'100','spent_amount'=>'50','physical_pct'=>60],
  ['status'=>'Delayed','sanctioned_amount'=>'100','spent_amount'=>'25','physical_pct'=>40],
  ['status'=>'Critical','sanctioned_amount'=>'200','spent_amount'=>'25','physical_pct'=>20],
];

it('ppms_kpis aggregates sanctioned/spent/utilisation/at-risk', function () use ($projects) {
    $k = ppms_kpis($projects);
    assert_eq(400.0, $k['sanctioned']);
    assert_eq(100.0, $k['spent']);
    assert_eq(25, $k['utilisation']);           // 100/400 = 25%
    assert_eq(3,  $k['count']);
    assert_eq(2,  $k['at_risk']);               // Delayed + Critical
    assert_eq(40, $k['avg_physical']);          // (60+40+20)/3
    assert_eq(1,  $k['by_status']['On Track']);
});

it('ppms_kpis is safe on an empty project set (no divide-by-zero)', function () {
    $k = ppms_kpis([]);
    assert_eq(0.0, $k['sanctioned']);
    assert_eq(0,   $k['utilisation']);
    assert_eq(0,   $k['count']);
});

it('ppms_fund_kpis counts queue stages and released amount', function () {
    $reqs = [
      ['status'=>'Approved by Finance','amount_requested'=>'10','allocated_amount'=>'9'],
      ['status'=>'Under Finance Review','amount_requested'=>'5','allocated_amount'=>null],
      ['status'=>'Released','amount_requested'=>'20','allocated_amount'=>'20'],
      ['status'=>'Released','amount_requested'=>'8','allocated_amount'=>'8'],
    ];
    $f = ppms_fund_kpis($reqs);
    assert_eq(1,    $f['pending_release']);      // Approved by Finance
    assert_eq(1,    $f['under_finance']);
    assert_eq(28.0, $f['released_amount']);      // 20 + 8
});

it('ppms_role_view maps each role to its dashboard archetype', function () {
    assert_eq('field',     ppms_role_view('JE'));
    assert_eq('field',     ppms_role_view('AE'));
    assert_eq('division',  ppms_role_view('EE'));
    assert_eq('finance',   ppms_role_view('FINANCE'));
    assert_eq('oversight', ppms_role_view('SE'));
    assert_eq('oversight', ppms_role_view('EIC'));
    assert_eq('oversight', ppms_role_view('SECRETARY'));
    assert_eq('oversight', ppms_role_view('SOMETHING_ELSE'));
});

it('ppms_valid_pct accepts 0..100 ints only', function () {
    assert_true(ppms_valid_pct(0));
    assert_true(ppms_valid_pct(100));
    assert_true(ppms_valid_pct(55));
    assert_true(ppms_valid_pct(-1) === false);
    assert_true(ppms_valid_pct(101) === false);
});

it('ppms_pending_actions returns review items for EE and verify items for AE', function () {
    $reqs = [
      ['id'=>7,'req_no'=>'R7','status'=>'Pending Review','amount_requested'=>'100'],
      ['id'=>8,'req_no'=>'R8','status'=>'Under Finance Review','amount_requested'=>'200'],
    ];
    $progress = [
      ['id'=>3,'project_name'=>'Dam X','status'=>'Submitted'],
    ];
    $ee = ppms_pending_actions('EE', $reqs, $progress);
    assert_eq(1, count($ee));
    assert_eq('requisitions.php?id=7', $ee[0]['url']);

    $ae = ppms_pending_actions('AE', $reqs, $progress);
    assert_eq(1, count($ae));
    assert_eq('projects.php?id=3', $ae[0]['url']);

    $fin = ppms_pending_actions('FINANCE', $reqs, $progress);
    assert_eq(1, count($fin));
    assert_eq('requisitions.php?id=8', $fin[0]['url']);
});
