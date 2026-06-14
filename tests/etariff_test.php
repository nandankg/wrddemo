<?php
require_once __DIR__ . '/../app/etariff/lib.php';

it('etariff_compute_bill applies slab variable + category fixed + gst', function () {
    // Industrial, 600,000 units, no excess:
    // variable = 500000*0.75 + 100000*0.90 = 375000 + 90000 = 465000
    // fixed = 25000 ; sub = 490000 ; gst = 88200 ; total = 578200
    $b = etariff_compute_bill('Industrial Units', 600000, 0);
    assert_eq(25000.0, $b['fixed']);
    assert_eq(465000.0, $b['variable']);
    assert_eq(0.0,    $b['excessChg']);
    assert_eq(88200.0, $b['gst']);
    assert_eq(578200.0, $b['total']);
});

it('etariff_compute_bill uses the third slab above 1,000,000 units', function () {
    // 1,200,000 units: 500000*0.75 + 500000*0.90 + 200000*1.10
    //   = 375000 + 450000 + 220000 = 1,045,000
    $b = etariff_compute_bill('Industrial Units', 1200000, 0);
    assert_eq(1045000.0, $b['variable']);
});

it('etariff_compute_bill charges excess and lower municipal fixed', function () {
    // Municipal fixed = 15000 ; 100,000 units variable = 75000 ; excess 8000 @2.10 = 16800
    // sub = 15000 + 75000 + 16800 = 106800 ; gst = 19224 ; total = 126024
    $b = etariff_compute_bill('Municipal Bodies', 100000, 8000);
    assert_eq(15000.0, $b['fixed']);
    assert_eq(75000.0, $b['variable']);
    assert_eq(16800.0, $b['excessChg']);
    assert_eq(126024.0, $b['total']);
});

it('etariff_role_view maps roles to consumer/billing/revenue', function () {
    assert_eq('consumer', etariff_role_view('CONSUMER'));
    assert_eq('billing',  etariff_role_view('JE'));
    assert_eq('billing',  etariff_role_view('AE'));
    assert_eq('billing',  etariff_role_view('EE'));
    assert_eq('revenue',  etariff_role_view('ACCOUNTS'));
    assert_eq('revenue',  etariff_role_view('SECRETARY'));
    assert_eq('revenue',  etariff_role_view('ANYTHING_ELSE'));
});

it('etariff_bill_kpis counts statuses and sums outstanding/collected', function () {
    $bills = [
      ['status'=>'Draft','total'=>'100'],
      ['status'=>'Pending Verification','total'=>'200'],
      ['status'=>'Approved','total'=>'300'],
      ['status'=>'Demand Raised','total'=>'400'],
      ['status'=>'Demand Raised','total'=>'50'],
      ['status'=>'Paid','total'=>'1000'],
    ];
    $k = etariff_bill_kpis($bills);
    assert_eq(1, $k['draft']);
    assert_eq(1, $k['pending']);
    assert_eq(1, $k['approved']);
    assert_eq(2, $k['demand_raised']);
    assert_eq(1, $k['paid']);
    assert_eq(450.0,  $k['outstanding']);   // 400 + 50
    assert_eq(1000.0, $k['collected']);     // Paid totals
});

it('etariff_pending_actions returns stage work per role', function () {
    $bills = [
      ['id'=>1,'bill_no'=>'B1','status'=>'Draft','total'=>'100','cname'=>'Tata'],
      ['id'=>2,'bill_no'=>'B2','status'=>'Pending Verification','total'=>'200','cname'=>'SAIL'],
      ['id'=>3,'bill_no'=>'B3','status'=>'Approved','total'=>'300','cname'=>'Usha'],
      ['id'=>4,'bill_no'=>'B4','status'=>'Demand Raised','total'=>'400','cname'=>'Tata'],
    ];
    assert_eq('bills.php?id=1', etariff_pending_actions('JE', $bills)[0]['url']);
    assert_eq('bills.php?id=2', etariff_pending_actions('AE', $bills)[0]['url']);
    assert_eq('bills.php?id=3', etariff_pending_actions('EE', $bills)[0]['url']);
    assert_eq('bills.php?id=4', etariff_pending_actions('CONSUMER', $bills)[0]['url']);
});
