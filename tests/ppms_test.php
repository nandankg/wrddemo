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

it('ppms_milestone_status flags overdue non-done items as Delayed', function () {
    assert_eq('Done',       ppms_milestone_status('Done', '2026-05-01', '2026-04-01', '2026-06-14'));
    assert_eq('Delayed',    ppms_milestone_status('In-Progress', null, '2026-04-01', '2026-06-14'));
    assert_eq('Delayed',    ppms_milestone_status('Pending', null, '2026-01-01', '2026-06-14'));
    assert_eq('In-Progress',ppms_milestone_status('In-Progress', null, '2026-12-01', '2026-06-14'));
    assert_eq('Pending',    ppms_milestone_status('Pending', null, '2026-12-31', '2026-06-14'));
    assert_eq('In-Progress', ppms_milestone_status('In-Progress', null, '2026-06-14', '2026-06-14')); // due today ≠ delayed
});

it('ppms_milestone_progress is weighted by Done milestones', function () {
    $ms = [
      ['weight'=>2,'status'=>'Done'],
      ['weight'=>2,'status'=>'In-Progress'],
      ['weight'=>1,'status'=>'Pending'],
    ];
    assert_eq(40, ppms_milestone_progress($ms));   // 2 / 5 = 40%
    assert_eq(0,  ppms_milestone_progress([]));     // no divide-by-zero
});

it('ppms_bi_by_division aggregates per division, sorted by name', function () {
    $projects = [
      ['divn'=>'B','physical_pct'=>80,'financial_pct'=>70,'sanctioned_amount'=>'200','spent_amount'=>'100'],
      ['divn'=>'A','physical_pct'=>60,'financial_pct'=>50,'sanctioned_amount'=>'100','spent_amount'=>'50'],
      ['divn'=>'A','physical_pct'=>40,'financial_pct'=>30,'sanctioned_amount'=>'100','spent_amount'=>'30'],
    ];
    $by = ppms_bi_by_division($projects);
    assert_eq('A', $by[0]['divn']);
    assert_eq(2,   $by[0]['count']);
    assert_eq(50,  $by[0]['phys']);          // (60+40)/2
    assert_eq(40,  $by[0]['fin']);           // (50+30)/2
    assert_eq(200.0, $by[0]['sanctioned']);
    assert_eq(80.0,  $by[0]['spent']);
    assert_eq(40,  $by[0]['utilisation']);   // 80/200
    assert_eq('B', $by[1]['divn']);
    assert_eq(50,  $by[1]['utilisation']);   // 100/200
});

it('ppms_report_dataset projects rows to columns by type', function () {
    $rows = [['name'=>'P1','scheme'=>'S1','divn'=>'D1','status'=>'On Track',
              'physical_pct'=>60,'financial_pct'=>55,'sanctioned_amount'=>'100','spent_amount'=>'50']];
    $ds = ppms_report_dataset('project', $rows);
    assert_eq(8, count($ds['columns']));
    assert_eq('Project', $ds['columns'][0]);
    assert_eq(['P1','S1','D1','On Track',60,55,'100','50'], $ds['rows'][0]);
    // unknown type falls back to the project layout
    assert_eq($ds['columns'], ppms_report_dataset('weird', $rows)['columns']);
});

it('ppms_next_run advances by frequency, defaulting to monthly', function () {
    assert_eq('2026-06-15', ppms_next_run('Daily',     '2026-06-14'));
    assert_eq('2026-06-21', ppms_next_run('Weekly',    '2026-06-14'));
    assert_eq('2026-07-14', ppms_next_run('Monthly',   '2026-06-14'));
    assert_eq('2026-09-14', ppms_next_run('Quarterly', '2026-06-14'));
    assert_eq('2026-07-14', ppms_next_run('???',       '2026-06-14'));
});

it('ppms_otp_generate returns a 6-digit numeric string', function () {
    $otp = ppms_otp_generate();
    assert_eq(6, strlen($otp));
    assert_true(ctype_digit($otp));
});
