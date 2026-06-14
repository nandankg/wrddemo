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
