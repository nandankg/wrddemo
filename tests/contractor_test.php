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
