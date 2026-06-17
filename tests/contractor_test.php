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
    assert_eq('applications.php?id=1', contractor_pending_actions('ASO', $apps)[0]['url']);
    assert_eq('applications.php?id=2', contractor_pending_actions('EIC', $apps)[0]['url']);
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
