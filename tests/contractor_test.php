<?php
require_once __DIR__ . '/../app/contractor/lib.php';

it('contractor_role_view maps roles to contractor/registry', function () {
    assert_eq('contractor', contractor_role_view('CONTRACTOR'));
    assert_eq('registry',   contractor_role_view('ASO'));
    assert_eq('registry',   contractor_role_view('AE'));
    assert_eq('registry',   contractor_role_view('EE'));
    assert_eq('registry',   contractor_role_view('EIC'));
    assert_eq('registry',   contractor_role_view('SOMETHING'));
});

it('contractor_next_stage walks ASO -> AE -> EE -> EIC -> null', function () {
    assert_eq('AE',  contractor_next_stage('ASO'));
    assert_eq('EE',  contractor_next_stage('AE'));
    assert_eq('EIC', contractor_next_stage('EE'));
    assert_eq(null,  contractor_next_stage('EIC'));
    assert_eq(null,  contractor_next_stage('UNKNOWN'));
});

it('contractor_fee returns class fee with a default', function () {
    assert_eq(45000.0, contractor_fee('I'));
    assert_eq(30000.0, contractor_fee('II'));
    assert_eq(20000.0, contractor_fee('III'));
    assert_eq(10000.0, contractor_fee('IV'));
    assert_eq(10000.0, contractor_fee('X'));
});

it('contractor_kpis counts in-process apps and contractor statuses', function () {
    $apps = [
      ['status'=>'Document Verification'],
      ['status'=>'Under Process'],
      ['status'=>'Approved'],
      ['status'=>'Rejected'],
    ];
    $contractors = [
      ['status'=>'Active'],['status'=>'Active'],['status'=>'Blacklisted'],['status'=>'Pending'],
    ];
    $k = contractor_kpis($apps, $contractors);
    assert_eq(2, $k['in_process']);   // DocVerif + UnderProcess
    assert_eq(1, $k['approved']);
    assert_eq(2, $k['active']);
    assert_eq(1, $k['blacklisted']);
    assert_eq(4, $k['total_apps']);
});

it('contractor_pending_actions returns stage work for the acting role', function () {
    $apps = [
      ['id'=>1,'ack_no'=>'A1','stage'=>'ASO','status'=>'Document Verification','cname'=>'X'],
      ['id'=>2,'ack_no'=>'A2','stage'=>'EIC','status'=>'Pending Approval','cname'=>'Y'],
      ['id'=>3,'ack_no'=>'A3','stage'=>'AE','status'=>'Approved','cname'=>'Z'],
    ];
    assert_eq('scrutiny.php?app_id=1', contractor_pending_actions('ASO', $apps)[0]['url']);
    assert_eq('scrutiny.php?app_id=2', contractor_pending_actions('EIC', $apps)[0]['url']);
    assert_eq(0, count(contractor_pending_actions('AE', $apps)));        // id3 is Approved
    assert_eq(0, count(contractor_pending_actions('CONTRACTOR', $apps)));
});

it('contractor_eligibility recommends the highest class the credentials qualify for', function () {
    // Class I: >=10 yrs, >=10 projects, >=5 Cr turnover
    assert_eq('I',   contractor_eligibility(14, 28, 85000000)['class']);
    // Class II: >=7 yrs, >=6 projects, >=3 Cr
    assert_eq('II',  contractor_eligibility(8, 11, 31000000)['class']);
    // Class III: >=4 yrs, >=3 projects, >=1.5 Cr
    assert_eq('III', contractor_eligibility(5, 6, 16000000)['class']);
    // Falls back to Class IV (entry level)
    assert_eq('IV',  contractor_eligibility(2, 1, 4000000)['class']);
    // High experience but low turnover cannot reach Class I
    assert_eq('III', contractor_eligibility(20, 30, 16000000)['class']);
    // Reason is a non-empty string
    assert_true(contractor_eligibility(14, 28, 85000000)['reason'] !== '');
});

it('contractor_doc_verify is deterministic and well-formed', function () {
    $docs = ['PAN Card','Balance Sheet','GST Certificate','CA Certificate','Cancelled Cheque','Affidavit'];
    foreach ($docs as $d) {
        $a = contractor_doc_verify($d, 0);
        $b = contractor_doc_verify($d, 0);
        assert_eq($a, $b, "deterministic for $d");                 // same input -> same output
        assert_true(in_array($a['status'], ['Verified','Issue'], true), "status valid for $d");
        if ($a['status'] === 'Verified') assert_eq(null, $a['issue'], "verified has no issue for $d");
        else assert_true(is_string($a['issue']) && $a['issue'] !== '', "issue text present for $d");
    }
    // Different seeds can change the outcome but never the shape
    assert_true(in_array(contractor_doc_verify('Balance Sheet', 6)['status'], ['Verified','Issue'], true));
});

it('contractor_public_stats blends a production baseline with live seed counts', function () {
    $contractors = [
        ['status'=>'Active'],['status'=>'Active'],['status'=>'Blacklisted'],['status'=>'Renewal Due'],
    ];
    $apps = [
        ['applied_on'=>'2026-04-10'],['applied_on'=>'2026-01-02'],['applied_on'=>'2025-12-31'],
    ];
    $s = contractor_public_stats($contractors, $apps, '2026-06-17');
    assert_eq(12536, $s['registered']);   // 12532 + 4
    assert_eq(9842,  $s['active']);       // 9840 + 2 Active
    assert_eq(2142,  $s['apps_year']);    // 2140 + 2 in 2026
    assert_eq(7,     $s['avg_days']);
});

it('contractor_score is deterministic and weights experience/financial/compliance', function () {
    // Class I, meets threshold exactly, clean docs, low risk.
    $c = ['experience_yrs'=>10,'completed_projects'=>10,'turnover'=>50000000,
          'class'=>'I','status'=>'Active','risk_score'=>10];
    $s = contractor_score($c, []);
    assert_eq(100, $s['experience']);   // 10yr + 10proj caps the bar
    assert_eq(90,  $s['financial']);    // ratio 1.0 -> 40 + 50
    assert_eq(90,  $s['compliance']);   // 100 - risk 10 - 0 issues
    assert_eq(94,  $s['overall']);      // 35 + 27 + 31.5 -> 94
    assert_eq('A', $s['band']);

    // Doc issues lower compliance; experience & financial clamp at 100.
    $c3 = ['experience_yrs'=>14,'completed_projects'=>28,'turnover'=>85000000,
           'class'=>'I','status'=>'Active','risk_score'=>10];
    $docs = [['status'=>'Verified'],['status'=>'Issue'],['status'=>'Issue']];
    $s3 = contractor_score($c3, $docs);
    assert_eq(100, $s3['experience']);
    assert_eq(100, $s3['financial']);   // ratio 1.7 capped at 1.2 -> 40 + 60
    assert_eq(60,  $s3['compliance']);  // 100 - 10 - 15*2
    assert_eq(86,  $s3['overall']);
    assert_eq('A', $s3['band']);

    // Blacklisted zeroes compliance regardless of docs.
    $c2 = ['experience_yrs'=>0,'completed_projects'=>0,'turnover'=>0,
           'class'=>'IV','status'=>'Blacklisted','risk_score'=>40];
    $s2 = contractor_score($c2, []);
    assert_eq(0,   $s2['experience']);
    assert_eq(40,  $s2['financial']);   // ratio 0 -> 40
    assert_eq(0,   $s2['compliance']);  // blacklisted
    assert_eq(12,  $s2['overall']);     // 0 + 12 + 0
    assert_eq('C', $s2['band']);
});

it('contractor_app_breakdown buckets apps by review state', function () {
    $apps = [
      ['stage'=>'ASO','status'=>'Document Verification'],
      ['stage'=>'AE','status'=>'Under Process'],
      ['stage'=>'EE','status'=>'Under Process'],
      ['stage'=>'EIC','status'=>'Under Process'],
      ['stage'=>'AE','status'=>'Query Raised'],
      ['stage'=>'EIC','status'=>'Approved'],
      ['stage'=>'ASO','status'=>'Rejected'],
    ];
    $b = contractor_app_breakdown($apps);
    assert_eq(1, $b['new']);
    assert_eq(2, $b['verifying']);
    assert_eq(1, $b['approval_pending']);
    assert_eq(1, $b['query']);
    assert_eq(1, $b['approved']);
    assert_eq(1, $b['rejected']);
});

it('contractor_can_forward blocks on open queries and terminal states', function () {
    assert_true(contractor_can_forward(['stage'=>'ASO','status'=>'Under Process'], 0));
    assert_eq(false, contractor_can_forward(['stage'=>'ASO','status'=>'Under Process'], 1));
    assert_eq(false, contractor_can_forward(['stage'=>'ASO','status'=>'Query Raised'], 0));
    assert_eq(false, contractor_can_forward(['stage'=>'EIC','status'=>'Approved'], 0));
    assert_eq(false, contractor_can_forward(['stage'=>'ASO','status'=>'Rejected'], 0));
});

it('contractor_effective_status applies Blacklisted > Expired > stored', function () {
    $t = '2026-06-27';
    assert_eq('Active',      contractor_effective_status(['status'=>'Active','valid_upto'=>'2027-03-31'], $t));
    assert_eq('Expired',     contractor_effective_status(['status'=>'Active','valid_upto'=>'2025-01-01'], $t));
    assert_eq('Suspended',   contractor_effective_status(['status'=>'Suspended','valid_upto'=>'2028-01-01'], $t));
    assert_eq('Blacklisted', contractor_effective_status(['status'=>'Blacklisted','valid_upto'=>'2024-01-01'], $t)); // outranks Expired
});

it('contractor_filter matches district/class/category/effective-status', function () {
    $t = '2026-06-27';
    $cs = [
      ['id'=>1,'class'=>'I','district'=>'Ranchi','category'=>'Civil','status'=>'Active','valid_upto'=>'2027-03-31'],
      ['id'=>2,'class'=>'I','district'=>'Ranchi','category'=>'Civil','status'=>'Active','valid_upto'=>'2025-01-01'], // -> Expired
      ['id'=>3,'class'=>'II','district'=>'Dhanbad','category'=>'Mechanical','status'=>'Suspended','valid_upto'=>'2028-01-01'],
      ['id'=>4,'class'=>'I','district'=>'Bokaro','category'=>'Civil','status'=>'Blacklisted','valid_upto'=>'2024-01-01'],
    ];
    assert_eq(1, count(contractor_filter($cs, ['status'=>'Active'], $t)));     // only id1
    assert_eq(1, count(contractor_filter($cs, ['status'=>'Expired'], $t)));    // only id2
    assert_eq(2, count(contractor_filter($cs, ['district'=>'Ranchi'], $t)));   // id1,id2
    assert_eq(3, count(contractor_filter($cs, ['class'=>'I'], $t)));           // id1,id2,id4
    assert_eq(3, count(contractor_filter($cs, ['category'=>'Civil'], $t)));    // id1,id2,id4
    assert_eq(1, count(contractor_filter($cs, ['district'=>'Ranchi','status'=>'Active'], $t)));
    assert_eq(4, count(contractor_filter($cs, [], $t)));                       // no filters
});

it('contractor_empanelment_matrix counts active/suspended/expired per class', function () {
    $t = '2026-06-27';
    $cs = [
      ['class'=>'I','status'=>'Active','valid_upto'=>'2027-03-31'],
      ['class'=>'I','status'=>'Active','valid_upto'=>'2025-01-01'],   // Expired
      ['class'=>'I','status'=>'Blacklisted','valid_upto'=>'2024-01-01'], // none of the 3
      ['class'=>'II','status'=>'Suspended','valid_upto'=>'2028-01-01'],
    ];
    $m = contractor_empanelment_matrix($cs, $t);
    assert_eq(1, $m['I']['active']);
    assert_eq(1, $m['I']['expired']);
    assert_eq(0, $m['I']['suspended']);
    assert_eq(1, $m['II']['suspended']);
    assert_eq(0, $m['III']['active']);
});

it('contractor_revenue_kpis splits total/new/renewal and current-FY (paid only)', function () {
    $apps = [
      ['contractor_id'=>1,'type'=>'New','fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-05-10'],
      ['contractor_id'=>1,'type'=>'Renewal','fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-06-01'],
      ['contractor_id'=>3,'type'=>'New','fee'=>30000,'fee_paid'=>0,'applied_on'=>'2026-06-02'], // unpaid -> excluded
      ['contractor_id'=>3,'type'=>'New','fee'=>30000,'fee_paid'=>1,'applied_on'=>'2026-02-15'], // before FY -> not in fy
      ['contractor_id'=>4,'type'=>'New','fee'=>10000,'fee_paid'=>1,'applied_on'=>'2024-12-01'],
    ];
    $k = contractor_revenue_kpis($apps, '2026-06-27'); // FY start 2026-04-01
    assert_eq(130000.0, $k['total']);
    assert_eq(45000.0,  $k['renewal']);
    assert_eq(85000.0,  $k['new']);
    assert_eq(90000.0,  $k['fy']);
});

it('contractor_monthly_collection buckets the trailing 12 months', function () {
    $apps = [
      ['fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-05-10'],
      ['fee'=>45000,'fee_paid'=>1,'applied_on'=>'2026-06-01'],
      ['fee'=>30000,'fee_paid'=>0,'applied_on'=>'2026-06-02'], // unpaid
      ['fee'=>30000,'fee_paid'=>1,'applied_on'=>'2026-02-15'],
      ['fee'=>10000,'fee_paid'=>1,'applied_on'=>'2024-12-01'], // out of window
    ];
    $mc = contractor_monthly_collection($apps, '2026-06-27');
    $keys = array_keys($mc);
    assert_eq(12, count($mc));
    assert_eq('2025-07', $keys[0]);
    assert_eq('2026-06', $keys[11]);
    assert_eq(45000.0, $mc['2026-05']);
    assert_eq(45000.0, $mc['2026-06']);
    assert_eq(30000.0, $mc['2026-02']);
    assert_eq(120000.0, array_sum($mc));
});

it('contractor_district_rollup aggregates count and paid revenue per district', function () {
    $cs = [
      ['id'=>1,'district'=>'Ranchi'],['id'=>2,'district'=>'Ranchi'],
      ['id'=>3,'district'=>'Dhanbad'],['id'=>4,'district'=>'Bokaro'],
    ];
    $apps = [
      ['contractor_id'=>1,'fee'=>45000,'fee_paid'=>1],
      ['contractor_id'=>1,'fee'=>45000,'fee_paid'=>1],
      ['contractor_id'=>3,'fee'=>30000,'fee_paid'=>1],
      ['contractor_id'=>3,'fee'=>30000,'fee_paid'=>0], // unpaid
      ['contractor_id'=>4,'fee'=>10000,'fee_paid'=>1],
    ];
    $r = contractor_district_rollup($cs, $apps);
    assert_eq(2, $r['Ranchi']['count']);
    assert_eq(90000.0, $r['Ranchi']['revenue']);
    assert_eq(1, $r['Dhanbad']['count']);
    assert_eq(30000.0, $r['Dhanbad']['revenue']);
    assert_eq(10000.0, $r['Bokaro']['revenue']);
});
