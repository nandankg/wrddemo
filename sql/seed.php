<?php
/**
 * Seed data for the WRD Jharkhand demo. Called by setup.php with an open PDO.
 */
declare(strict_types=1);

function seed_demo(PDO $pdo): void {
    $pw = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

    // ---- Divisions (each with a designated bank account → division-wise revenue) ----
    $divisions = [
        ['Ranchi Water Ways Division','रांची जल मार्ग प्रमंडल','Ranchi Circle','WRD-RNC-0001','SBI Treasury, Ranchi',23.3441,85.3096],
        ['Dhanbad Irrigation Division','धनबाद सिंचाई प्रमंडल','Dhanbad Circle','WRD-DHN-0002','Bank of India, Dhanbad',23.7957,86.4304],
        ['Bokaro Reservoir Division','बोकारो जलाशय प्रमंडल','Hazaribagh Circle','WRD-BOK-0003','PNB, Bokaro',23.6693,86.1511],
        ['Hazaribagh Canal Division','हजारीबाग नहर प्रमंडल','Hazaribagh Circle','WRD-HZB-0004','SBI, Hazaribagh',23.9925,85.3637],
        ['Jamshedpur (East Singhbhum) Division','जमशेदपुर प्रमंडल','Kolhan Circle','WRD-JSR-0005','Canara Bank, Jamshedpur',22.8046,86.2029],
        ['Palamu Irrigation Division','पलामू सिंचाई प्रमंडल','Palamu Circle','WRD-PLM-0006','SBI, Daltonganj',24.0333,84.0667],
    ];
    $ins = $pdo->prepare('INSERT INTO divisions (name,name_hi,circle,bank_account,bank_name,lat,lng) VALUES (?,?,?,?,?,?,?)');
    foreach ($divisions as $d) $ins->execute($d);

    // ---- Users (one per role) + role-switcher friendly ----
    $users = [
        ['secretary','Anjali Verma, IAS','अंजलि वर्मा','SECRETARY','Secretary, Water Resources Dept.',null],
        ['eic','R. K. Mahto','आर. के. महतो','EIC','Engineer-in-Chief',null],
        ['ce','S. P. Singh','एस. पी. सिंह','CE','Chief Engineer (Ranchi)',1],
        ['se','Manoj Tirkey','मनोज टिर्की','SE','Superintending Engineer',1],
        ['ee','Praveen Kumar','प्रवीण कुमार','EE','Executive Engineer, Ranchi',1],
        ['ae','Sunita Oraon','सुनीता उरांव','AE','Assistant Engineer',1],
        ['je','Amit Mahato','अमित महतो','JE','Junior Engineer',1],
        ['finance','D. N. Prasad','डी. एन. प्रसाद','FINANCE','Finance Officer',null],
        ['accounts','M. Ekka','एम. एक्का','ACCOUNTS','Divisional Accounts Officer',1],
        ['aso','Rekha Devi','रेखा देवी','ASO','Assistant Section Officer',null],
        ['admin','System Administrator','सिस्टम एडमिन','ADMIN','Portal Administrator',null],
        ['contractor','Narayan Constructions Pvt Ltd','नारायण कंस्ट्रक्शन्स','CONTRACTOR','Registered Contractor',null],
        ['consumer','Tata Steel Ltd (Industrial)','टाटा स्टील लि.','CONSUMER','Industrial Water Consumer',5],
    ];
    $ins = $pdo->prepare('INSERT INTO users (username,password_hash,name,name_hi,role,designation,division_id,email,phone) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($users as $u) {
        $ins->execute([$u[0],$pw,$u[1],$u[2],$u[3],$u[4],$u[5],$u[0].'@wrd.jharkhand.gov.in','9430'.rand(100000,999999)]);
    }
    $uid = []; foreach ($pdo->query('SELECT id,role FROM users') as $r) $uid[$r['role']] = (int)$r['id'];

    // ---- Schemes ----
    $schemes = [
        ['Major Irrigation Projects','वृहद सिंचाई परियोजना','Major','2700-101'],
        ['Medium Irrigation Projects','मध्यम सिंचाई परियोजना','Medium','2700-102'],
        ['Minor Irrigation Projects','लघु सिंचाई परियोजना','Minor','2702-103'],
        ['Lift Irrigation Scheme','उद्वह सिंचाई योजना','Lift','2702-104'],
        ['Watershed Development','जलछाजन विकास','Watershed','2702-105'],
        ['Multi-Purpose River Projects','बहुउद्देशीय नदी परियोजना','Multi-Purpose','2701-106'],
    ];
    $ins = $pdo->prepare('INSERT INTO schemes (name,name_hi,type,head_of_account) VALUES (?,?,?,?)');
    foreach ($schemes as $s) $ins->execute($s);

    // ---- Projects (geotagged across Jharkhand) ----
    $projects = [
        ['Subarnarekha Multipurpose Project','स्वर्णरेखा बहुउद्देशीय परियोजना',6,1,23.3601,85.3300,'On Track',72,68,185000000,125800000,'2023-04-01','2026-03-31'],
        ['Konar Canal Modernisation','कोनार नहर आधुनिकीकरण',1,4,23.9925,85.3637,'On Track',58,55,94000000,51700000,'2023-06-15','2025-12-31'],
        ['Tenughat Reservoir Augmentation','तेनुघाट जलाशय संवर्धन',6,3,23.7300,85.8100,'Delayed',41,49,128000000,62700000,'2022-11-01','2025-10-31'],
        ['Punasi Reservoir Project','पुनासी जलाशय परियोजना',1,2,24.1900,86.7100,'On Track',63,60,210000000,126000000,'2023-01-10','2026-06-30'],
        ['Mandal Dam (North Koel) Revival','मंडल बांध पुनरुद्धार',6,6,23.8000,84.2000,'Critical',28,35,340000000,119000000,'2022-08-01','2027-03-31'],
        ['Surangi Lift Irrigation','सुरंगी उद्वह सिंचाई',4,6,24.0500,84.0500,'On Track',81,77,46000000,35400000,'2023-03-01','2025-08-31'],
        ['Swarnrekha Canal Phase-II','स्वर्णरेखा नहर चरण-२',1,5,22.8046,86.2029,'On Track',55,52,76000000,39500000,'2023-05-01','2026-01-31'],
        ['Garga Reservoir Watershed','गरगा जलाशय जलछाजन',5,3,23.6693,86.1511,'On Track',67,64,38000000,24300000,'2023-02-01','2025-09-30'],
        ['Sone Canal Lining Works','सोन नहर लाइनिंग कार्य',2,6,24.1000,84.1500,'Delayed',44,40,52000000,20800000,'2022-12-01','2025-11-30'],
        ['Dhanbad Minor Irrigation Cluster','धनबाद लघु सिंचाई समूह',3,2,23.7957,86.4304,'On Track',70,66,29000000,19100000,'2023-07-01','2025-07-31'],
    ];
    $ins = $pdo->prepare('INSERT INTO projects (name,name_hi,scheme_id,division_id,lat,lng,status,physical_pct,financial_pct,sanctioned_amount,spent_amount,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($projects as $p) $ins->execute($p);

    // ---- Fund requisitions across every workflow stage ----
    $fy = '2025-26';
    $reqs = [
        // project, scheme, division, head, amount, justification, status, owner, allocated, ref, fundcode
        [1,6,1,'2701-106',25000000,'Procurement of gates and hoisting mechanism for main dam.','Released','ADMIN',25000000,'FRC/2025/0011','FC-2701-06','2025-05-12'],
        [4,1,2,'2700-101',18000000,'Canal lining work for left-bank distributary, Punasi command.','Approved by Finance','FINANCE',16500000,null,'FC-2700-04',null],
        [2,1,4,'2700-101',12000000,'Earthwork and structures for Konar canal RD 0-8 km.','Under Finance Review','FINANCE',null,null,null,null],
        [5,6,6,'2701-106',40000000,'Resumption of construction at Mandal Dam, Phase-III spillway.','Pending Review','SE',null,null,null,null],
        [3,6,3,'2701-106',15000000,'Desilting and embankment strengthening, Tenughat reservoir.','Pending Review','EE',null,null,null,null],
        [6,4,6,'2702-104',8000000,'Pump house electro-mechanical upgrade, Surangi LIS.','Released','ADMIN',8000000,'FRC/2025/0009','FC-2702-06','2025-04-28'],
        [7,1,5,'2700-101',11000000,'Cross-drainage works, Swarnrekha Canal Phase-II.','Rejected','EE',null,null,null,null],
        [8,5,3,'2702-105',6500000,'Check-dams and contour bunding, Garga watershed.','Draft','EE',null,null,null,null],
        [9,2,6,'2700-102',9500000,'CC lining, Sone canal minor 3R, length 4.2 km.','Under Finance Review','FINANCE',null,null,null,null],
        [10,3,2,'2702-103',5200000,'Renovation of 14 minor irrigation tanks, Dhanbad cluster.','Approved by Finance','FINANCE',5000000,null,'FC-2702-02',null],
    ];
    $ins = $pdo->prepare('INSERT INTO fund_requisitions (req_no,project_id,scheme_id,division_id,head_of_account,fy,amount_requested,justification,status,allocated_amount,release_ref,fund_code,release_date,created_by,current_owner_role) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $n = 1;
    foreach ($reqs as $r) {
        $reqno = sprintf('WRD/FR/%s/%04d', '2526', $n++);
        $ins->execute([$reqno,$r[0],$r[1],$r[2],$r[3],$fy,$r[4],$r[5],$r[6],$r[8],$r[9],$r[10],$r[11],$uid['EE'],$r[7]]);
    }

    // ---- Project progress updates (JE submits → AE verifies) ----
    $prog = [
        // project_id, physical, financial, note, status, submitted_by, verified_by
        [1, 75, 70, 'Spillway gates erection completed; RD 0-2 km lined.', 'Submitted', $uid['JE'], null],
        [3, 45, 52, 'Desilting 60% done; embankment pitching in progress.', 'Submitted', $uid['JE'], null],
        [2, 60, 57, 'Canal earthwork RD 0-8 km verified on site.', 'Verified', $uid['JE'], $uid['AE']],
        [5, 30, 36, 'Mobilisation resumed; access road restored.', 'Rejected', $uid['JE'], $uid['AE']],
    ];
    $ins = $pdo->prepare('INSERT INTO progress_updates (project_id,physical_pct,financial_pct,note,status,submitted_by,verified_by,created_at) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($prog as $i=>$g) {
        $ins->execute([$g[0],$g[1],$g[2],$g[3],$g[4],$g[5],$g[6], date('Y-m-d H:i:s', strtotime('-'.(3+$i*2).' days'))]);
    }

    // ---- Contractors (with risk scores; one blacklisted) ----
    $contractors = [
        ['WRD/REG/3/0451','Narayan Constructions Pvt Ltd','नारायण कंस्ट्रक्शन्स','I','AABCN1234K','20ABCDE1234F1Z5','Ranchi','Active',18,'2027-03-31','2021-05-10'],
        ['WRD/REG/3/0452','Jharkhand Infra Builders','झारखंड इंफ्रा बिल्डर्स','II','AACFJ5678L','20JHARK5678G1Z2','Dhanbad','Active',34,'2026-09-30','2020-08-22'],
        ['WRD/REG/3/0453','Koel Engineering Works','कोयल इंजीनियरिंग','III','AADCK9012M','20KOELE9012H1Z9','Palamu','Active',22,'2026-12-31','2022-01-15'],
        ['WRD/REG/3/0454','Subarnarekha Civil Co.','स्वर्णरेखा सिविल','I','AAECS3456N','20SUBAR3456J1Z7','Jamshedpur','Active',12,'2028-01-31','2019-11-03'],
        ['WRD/REG/3/0455','Damodar Valley Contractors','दामोदर वैली','II','AAFCD7890P','20DAMOD7890K1Z4','Bokaro','Renewal Due',45,'2025-07-31','2021-02-28'],
        ['WRD/REG/3/0456','Hilltop Project Pvt Ltd','हिलटॉप प्रोजेक्ट','IV','AAGCH2345Q','27HILLT2345L1Z1','Mumbai','Blacklisted',88,'2025-03-31','2020-06-19'],
        ['WRD/REG/3/0457','Ranchi Builders Syndicate','रांची बिल्डर्स','III','AAHCR6789R','20RANCH6789M1Z8','Ranchi','Active',29,'2027-06-30','2022-09-12'],
        ['WRD/REG/3/0458','Santhal Infra Solutions','संथाल इंफ्रा','II','AAICS0123S','20SANTH0123N1Z6','Dumka','Active',38,'2026-11-30','2021-12-01'],
    ];
    $ins = $pdo->prepare('INSERT INTO contractors (reg_no,name,name_hi,class,pan,gst,district,status,risk_score,valid_upto,registered_on,qr_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($contractors as $c) { $c[] = bin2hex(random_bytes(6)); $ins->execute($c); }
    // Link the demo contractor login to its firm (portal scoping, consumer-style).
    $pdo->prepare("UPDATE contractors SET login_user='contractor' WHERE reg_no=?")->execute(['WRD/REG/3/0451']);

    // ---- Contractor applications (processing inbox) ----
    $apps = [
        ['WRD/ACK/2526/1001',1,'Renewal','I','EIC','Pending Approval',45000,1,'2025-05-20'],
        ['WRD/ACK/2526/1002',5,'Renewal','II','EE','Under Process',30000,1,'2025-05-25'],
        ['WRD/ACK/2526/1003',null,'New','III','ASO','Document Verification',20000,1,'2025-06-01'],
        ['WRD/ACK/2526/1004',null,'New','IV','AE','Under Process',10000,0,'2025-06-02'],
    ];
    $ins = $pdo->prepare('INSERT INTO contractor_apps (ack_no,contractor_id,type,class,stage,status,fee,fee_paid,applied_on) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($apps as $a) $ins->execute($a);

    // ---- Industrial water allocations (workflow stages) ----
    $alloc = [
        ['WRD/IWA/2526/201','Tata Steel Ltd','River','Subarnarekha River',95.0,'Perennial',5,'East Singhbhum','Secretary','Approved','LIC/2526/0044','20SUBAR3456J1Z7',4750000,'2025-04-15'],
        ['WRD/IWA/2526/202','Bokaro Steel Plant (SAIL)','Reservoir','Tenughat Reservoir',120.0,'Perennial',3,'Bokaro','EIC','Under Review',null,'20DAMOD7890K1Z4',6000000,'2025-05-02'],
        ['WRD/IWA/2526/203','Hindalco Industries','River','Damodar River',60.0,'Seasonal',2,'Dhanbad','CE','Under Review',null,'20JHARK5678G1Z2',3000000,'2025-05-18'],
        ['WRD/IWA/2526/204','Usha Martin Ltd','Canal','Swarnrekha Canal',35.0,'Perennial',5,'Ranchi','SE','Under Review',null,'20RANCH6789M1Z8',1750000,'2025-05-28'],
        ['WRD/IWA/2526/205','Jindal Steel & Power','Reservoir','Konar Reservoir',80.0,'Perennial',4,'Hazaribagh','AE','New',', ','20KOELE9012H1Z9',4000000,'2025-06-03'],
        ['WRD/IWA/2526/206','Adhunik Power','River','Sone River',40.0,'Seasonal',6,'Palamu','EE','Under Review',null,'20SANTH0123N1Z6',2000000,'2025-06-05'],
    ];
    $ins = $pdo->prepare('INSERT INTO allocations (app_no,applicant,source,source_name,quantity_mld,season,division_id,district,stage,status,license_no,gst,annual_fee,applied_on) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($alloc as $a) $ins->execute($a);

    // ---- E-Tariff consumers ----
    $consumers = [
        ['WRD-CON-1001','Tata Steel Ltd','टाटा स्टील लि.','Industrial Units',5,'Subarnarekha River',95.0,'20SUBAR3456J1Z7','AAECS3456N','SO/2024/441','2027-03-31','consumer'],
        ['WRD-CON-1002','Bokaro Steel Plant (SAIL)','बोकारो स्टील','Public Sector Undertakings',3,'Tenughat Reservoir',120.0,'20DAMOD7890K1Z4','AAFCD7890P','SO/2024/512',  '2027-03-31',null],
        ['WRD-CON-1003','Ranchi Municipal Corporation','रांची नगर निगम','Municipal Bodies',1,'Subarnarekha River',45.0,'20RANCH6789M1Z8','AAHCR6789R','SO/2023/220','2026-12-31',null],
        ['WRD-CON-1004','Hindalco Industries','हिंडाल्को','Private Companies',2,'Damodar River',60.0,'20JHARK5678G1Z2','AACFJ5678L','SO/2024/308','2026-09-30',null],
        ['WRD-CON-1005','Heavy Engineering Corp','एचईसी','Public Sector Undertakings',1,'Subarnarekha River',30.0,'20HECRN1111A1Z3','AAACH1111A','SO/2023/118','2026-06-30',null],
        ['WRD-CON-1006','Usha Martin Ltd','उषा मार्टिन','Industrial Units',5,'Swarnrekha Canal',35.0,'20USHAM2222B1Z0','AABCU2222B','SO/2024/377','2027-01-31',null],
    ];
    $ins = $pdo->prepare('INSERT INTO consumers (consumer_id,name,name_hi,category,division_id,source,allocation_qty,gst,pan,sanction_ref,valid_upto,login_user) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($consumers as $c) $ins->execute($c);
    $conIds = []; foreach ($pdo->query('SELECT id,division_id FROM consumers ORDER BY id') as $r) $conIds[] = $r;

    // ---- Drawal entries + bills (various statuses incl. anomaly) ----
    // Helper to build a bill
    $billNo = 1;
    $insDraw = $pdo->prepare('INSERT INTO drawal_entries (consumer_id,period,prev_reading,curr_reading,consumption,excess,anomaly,entered_by,entered_on) VALUES (?,?,?,?,?,?,?,?,?)');
    $insBill = $pdo->prepare('INSERT INTO bills (bill_no,consumer_id,drawal_id,period,fixed_charge,variable_charge,excess_charge,penalty,interest,gst,total,status,stage,created_on) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insPay  = $pdo->prepare('INSERT INTO payments (txn_ref,bill_id,source_module,consumer_id,division_id,amount,channel,credited_account,status,paid_on) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $divAcct = []; foreach ($pdo->query('SELECT id,bank_account FROM divisions') as $r) $divAcct[(int)$r['id']] = $r['bank_account'];

    // Pre-built billing rows: [consumerIdx, period, prev, curr, excessUnits, status, paidChannel|null, daysAgo]
    $billRows = [
        [0,'May 2025',412000,498000,0,'Paid','JE-GRAS',24],
        [1,'May 2025',880000,1010000,8000,'Paid','Net Banking',20],
        [2,'May 2025',150000,196000,0,'Paid','UPI',18],
        [3,'May 2025',520000,585000,0,'Demand Raised',null,6],
        [0,'Jun 2025',498000,612000,14000,'Approved',null,2],         // EE approved, anomaly excess
        [5,'Jun 2025',300000,372000,0,'Pending Verification',null,1], // AE inbox
        [4,'Jun 2025',210000,252000,0,'Draft',null,0],                // JE draft
    ];
    foreach ($billRows as $b) {
        $con = $conIds[$b[0]];
        $consumption = $b[3] - $b[2];
        $excess = $b[4];
        $anomaly = $excess > 0 ? 1 : 0;
        $insDraw->execute([$con['id'],$b[1],$b[2],$b[3],$consumption,$excess,$anomaly,$uid['JE'],date('Y-m-d', strtotime("-{$b[7]} days"))]);
        $drawId = (int)$pdo->lastInsertId();

        // Tariff computation (demo rates)
        $fixed = 25000.00;
        $variable = round($consumption * 0.85, 2);          // ₹0.85 per unit
        $excessChg = round($excess * 2.10, 2);              // penal rate on excess
        $penalty = in_array($b[5], ['Demand Raised','Paid']) && $b[7] > 21 ? 1500.00 : 0.00;
        $interest = 0.00;
        $sub = $fixed + $variable + $excessChg + $penalty + $interest;
        $gst = round($sub * 0.18, 2);
        $total = round($sub + $gst, 2);

        $bno = sprintf('WRD/BILL/2526/%05d', $billNo++);
        $insBill->execute([$bno,$con['id'],$drawId,$b[1],$fixed,$variable,$excessChg,$penalty,$interest,$gst,$total,$b[5],$b[5],date('Y-m-d', strtotime("-{$b[7]} days"))]);
        $bid = (int)$pdo->lastInsertId();

        if ($b[5] === 'Paid' && $b[6]) {
            $txn = 'GRAS' . strtoupper(bin2hex(random_bytes(4)));
            $insPay->execute([$txn,$bid,'etariff',$con['id'],$con['division_id'],$total,$b[6],$divAcct[(int)$con['division_id']],'Success',date('Y-m-d H:i:s', strtotime("-{$b[7]} days"))]);
        }
    }

    // A few extra historical payments (contractor reg + allocation) for revenue dashboard depth
    $extra = [
        ['etariff',1,1,148000,'JE-GRAS',40],['etariff',2,3,96500,'UPI',35],
        ['contractor',null,1,45000,'E-GRAS',30],['allocation',null,5,475000,'JE-GRAS',55],
        ['etariff',4,2,210000,'Net Banking',28],['allocation',null,3,300000,'JE-GRAS',48],
    ];
    foreach ($extra as $e) {
        $txn = 'GRAS' . strtoupper(bin2hex(random_bytes(4)));
        $insPay->execute([$txn,null,$e[0],$e[1],$e[2],$e[3],$e[4],$divAcct[(int)$e[2]],'Success',date('Y-m-d H:i:s', strtotime("-{$e[5]} days"))]);
    }

    // ---- Grievances ----
    $gr = [
        ['Ramesh Mahto','9431001122','Water Supply',1,'Irregular canal water release in Tamar block affecting paddy.','In Progress'],
        ['Sita Kumari','9431003344','Billing',5,'Excess water bill received without meter reading.','Resolved'],
        ['Mohan Singh','9431005566','Project',6,'Delay in Mandal Dam rehabilitation works.','Escalated'],
        ['Anil Oraon','9431007788','Registration',1,'Contractor renewal certificate not generated after payment.','New'],
    ];
    $ins = $pdo->prepare('INSERT INTO grievances (ref_no,name,phone,category,division_id,description,status,sla_due,created_on) VALUES (?,?,?,?,?,?,?,?,?)');
    $g=1; foreach ($gr as $x) {
        $ref = sprintf('WRD/GRV/2526/%04d', $g++);
        $ins->execute([$ref,$x[0],$x[1],$x[2],$x[3],$x[4],$x[5],date('Y-m-d', strtotime('+7 days')),date('Y-m-d', strtotime('-'.rand(2,15).' days'))]);
    }

    // ---- RTI ----
    $rti = [
        ['Pankaj Sinha','Details of fund utilisation under Subarnarekha project 2024-25.','Replied',1],
        ['Asha Devi','List of empanelled contractors Class-I in Ranchi circle.','In Progress',1],
        ['Vikash Kumar','Status of water allocation licences issued to industries.','New',0],
    ];
    $ins = $pdo->prepare('INSERT INTO rti_applications (ref_no,applicant,subject,status,filed_on,fee_paid) VALUES (?,?,?,?,?,?)');
    $r=1; foreach ($rti as $x) {
        $ref = sprintf('WRD/RTI/2526/%04d', $r++);
        $ins->execute([$ref,$x[0],$x[1],$x[2],date('Y-m-d', strtotime('-'.rand(3,25).' days')),$x[3]]);
    }

    // ---- CMS content (bilingual) ----
    $content = [
        ['tender','Construction of Cross-Drainage Works, Swarnrekha Canal Phase-II','स्वर्णरेखा नहर चरण-२ हेतु निविदा','Open tender for CD works; estimated cost ₹11.0 Cr. Last date 30 June 2025.','सीडी कार्यों हेतु खुली निविदा; अनुमानित लागत ₹11.0 करोड़।','Civil Works','Published','2025-06-01'],
        ['tender','Supply & Installation of SCADA for Tenughat Reservoir','तेनुघाट जलाशय हेतु स्काडा','Two-bid e-tender for reservoir automation. EMD ₹2.0 Lakh.','जलाशय स्वचालन हेतु दो-बोली निविदा।','Electro-Mechanical','Published','2025-05-28'],
        ['notice','Public Notice: Annual Water Demand Returns due by 31 July 2025','सार्वजनिक सूचना: वार्षिक जल मांग','All industrial consumers must file annual water demand returns through the E-Tariff portal.','सभी औद्योगिक उपभोक्ता ई-टैरिफ पोर्टल पर वार्षिक रिटर्न दाखिल करें।','General','Published','2025-05-30'],
        ['news','WRD Jharkhand launches Integrated Digital Backbone','डब्ल्यूआरडी झारखंड का एकीकृत डिजिटल मंच','The Department unveils a unified, citizen-centric digital ecosystem across five platforms.','विभाग ने पाँच मंचों पर एकीकृत नागरिक-केंद्रित डिजिटल पारितंत्र का अनावरण किया।','Achievement','Published','2025-06-04'],
        ['order','Office Order: Revised Water Tariff Rates w.e.f. FY 2025-26','कार्यालय आदेश: संशोधित जल दरें','Revised category-wise water tariff rates notified for the financial year 2025-26.','वित्तीय वर्ष 2025-26 हेतु संशोधित श्रेणी-वार जल दरें अधिसूचित।','Policy','Published','2025-04-10'],
        ['scheme','Lift Irrigation Scheme — Apply for Command-Area Benefits','उद्वह सिंचाई योजना','Farmers in eligible command areas may apply for lift irrigation benefits online.','पात्र कमांड क्षेत्र के किसान ऑनलाइन आवेदन कर सकते हैं।','Citizen Service','Published','2025-05-12'],
    ];
    $ins = $pdo->prepare('INSERT INTO content (type,title,title_hi,body,body_hi,category,status,publish_date,author,is_new) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($content as $i=>$c) $ins->execute([$c[0],$c[1],$c[2],$c[3],$c[4],$c[5],$c[6],$c[7],'WRD CMS', $i<3?1:0]);

    // ---- Workflow log seed (for audit trail demos) ----
    $wl = $pdo->prepare('INSERT INTO workflow_log (entity_type,entity_id,action,from_role,to_role,actor,remarks,created_at) VALUES (?,?,?,?,?,?,?,?)');
    $wl->execute(['fund_requisition',1,'Created','EE','EE','Praveen Kumar (EE, Ranchi)','Fund requisition raised against Subarnarekha project.',date('Y-m-d H:i:s', strtotime('-20 days'))]);
    $wl->execute(['fund_requisition',1,'Forwarded','EE','SE','Praveen Kumar (EE, Ranchi)','Recommended for sanction.',date('Y-m-d H:i:s', strtotime('-19 days'))]);
    $wl->execute(['fund_requisition',1,'Forwarded to Finance','SE','FINANCE','Manoj Tirkey (SE)','Technically vetted.',date('Y-m-d H:i:s', strtotime('-16 days'))]);
    $wl->execute(['fund_requisition',1,'Approved by Finance','FINANCE','ADMIN','D. N. Prasad (Finance)','Allocated ₹2.50 Cr. Fund code FC-2701-06.',date('Y-m-d H:i:s', strtotime('-13 days'))]);
    $wl->execute(['fund_requisition',1,'Released','ADMIN','ADMIN','System Administrator','Released vide FRC/2025/0011.',date('Y-m-d H:i:s', strtotime('-10 days'))]);
}
