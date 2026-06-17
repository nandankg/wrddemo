<?php
declare(strict_types=1);

/**
 * Registry of the five INDEPENDENT WRD products.
 * Pure data — no DB, no session. Each product is self-contained.
 * `home`/nav `url` values are app-root-relative (pass through base_url() at render).
 */
function wrd_apps(): array {
    return [
        'ppms' => [
            'key' => 'ppms', 'short' => 'PPMS',
            'name' => 'Project Progress Monitoring', 'name_hi' => 'परियोजना प्रगति निगरानी',
            'accent' => '#0e7c86', 'icon' => '📊',
            'tagline' => 'Physical & financial tracking, fund flow, GIS & MIS.',
            'tagline_hi' => 'भौतिक एवं वित्तीय प्रगति, निधि प्रवाह, जीआईएस एवं एमआईएस।',
            'home' => 'app/ppms/index.php',
            'roles' => ['JE','AE','EE','SE','EIC','FINANCE','SECRETARY'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Command Centre','url'=>'app/ppms/index.php','icon'=>'▤'],
                ['key'=>'projects','label'=>'Projects & Progress','url'=>'app/ppms/projects.php','icon'=>'📍'],
                ['key'=>'milestones','label'=>'Milestones','url'=>'app/ppms/milestones.php','icon'=>'🏁'],
                ['key'=>'requisitions','label'=>'Fund Requisition','url'=>'app/ppms/requisitions.php','icon'=>'₹','roles'=>['EE','SE','EIC','FINANCE','SECRETARY']],
                ['key'=>'bi','label'=>'BI Dashboard','url'=>'app/ppms/bi.php','icon'=>'📈'],
                ['key'=>'reports','label'=>'Report Builder','url'=>'app/ppms/reports.php','icon'=>'▦'],
                ['key'=>'scheduled','label'=>'Scheduled Reports','url'=>'app/ppms/scheduled.php','icon'=>'🗓'],
                ['key'=>'notifications','label'=>'Notifications','url'=>'app/ppms/notifications.php','icon'=>'🔔'],
            ],
        ],
        'contractor' => [
            'key' => 'contractor', 'short' => 'Contractor Reg.',
            'name' => 'Contractor Registration & Empanelment', 'name_hi' => 'ठेकेदार पंजीकरण एवं सूचीयन',
            'accent' => '#2563eb', 'icon' => '⚒️',
            'tagline' => 'Online empanelment, verification, e-certificate with QR.',
            'tagline_hi' => 'ऑनलाइन सूचीयन, सत्यापन, क्यूआर सहित ई-प्रमाणपत्र।',
            'home' => 'app/contractor/index.php',
            'roles' => ['CONTRACTOR','ASO','AE','EE','EIC'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Registry Desk','url'=>'app/contractor/index.php','icon'=>'▤'],
                ['key'=>'applications','label'=>'Applications','url'=>'app/contractor/applications.php','icon'=>'📋','roles'=>['ASO','AE','EE','EIC']],
                ['key'=>'registry','label'=>'Registered Contractors','url'=>'app/contractor/registry.php','icon'=>'📒','roles'=>['ASO','AE','EE','EIC']],
                ['key'=>'verify','label'=>'Verify Certificate','url'=>'app/contractor/verify.php','icon'=>'✔','roles'=>['ASO','AE','EE','EIC']],
            ],
        ],
        'allocation' => [
            'key' => 'allocation', 'short' => 'Allocation',
            'name' => 'Industrial Water Allocation', 'name_hi' => 'औद्योगिक जल आवंटन',
            'accent' => '#0891b2', 'icon' => '💧',
            'tagline' => 'Apply, technical scrutiny, approval & licence issuance.',
            'tagline_hi' => 'आवेदन, तकनीकी जाँच, अनुमोदन एवं लाइसेंस जारी।',
            'home' => 'app/allocation/index.php',
            'roles' => ['CONSUMER','AE','EE','SE','CE','EIC','SECRETARY'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Allocation Desk','url'=>'app/allocation/index.php','icon'=>'▤'],
                ['key'=>'map','label'=>'Source Map (GIS)','url'=>'app/allocation/map.php','icon'=>'🗺️'],
                ['key'=>'applications','label'=>'Applications','url'=>'app/allocation/applications.php','icon'=>'📋','roles'=>['AE','EE','SE','CE','EIC','SECRETARY']],
                ['key'=>'renewals','label'=>'Renewals','url'=>'app/allocation/renewals.php','icon'=>'🔁'],
                ['key'=>'licences','label'=>'Licences','url'=>'app/allocation/licences.php','icon'=>'📜','roles'=>['AE','EE','SE','CE','EIC','SECRETARY']],
                ['key'=>'inspections','label'=>'Inspections','url'=>'app/allocation/inspections.php','icon'=>'🔎','roles'=>['AE','EE','SE','CE','EIC','SECRETARY']],
                ['key'=>'analytics','label'=>'Water Analytics','url'=>'app/allocation/analytics.php','icon'=>'📈','roles'=>['AE','EE','SE','CE','EIC','SECRETARY']],
            ],
        ],
        'etariff' => [
            'key' => 'etariff', 'short' => 'E-Tariff',
            'name' => 'Water E-Tariff & Billing', 'name_hi' => 'जल ई-टैरिफ एवं बिलिंग',
            'accent' => '#059669', 'icon' => '🧾',
            'tagline' => 'Drawal, slab billing, online payment & revenue MIS.',
            'tagline_hi' => 'जल आहरण, स्लैब बिलिंग, ऑनलाइन भुगतान एवं राजस्व एमआईएस।',
            'home' => 'app/etariff/index.php',
            'roles' => ['CONSUMER','JE','AE','EE','ACCOUNTS','SECRETARY'],
            'nav' => [
                ['key'=>'dashboard','label'=>'Revenue & Billing','url'=>'app/etariff/index.php','icon'=>'▤'],
                ['key'=>'bills','label'=>'Bills & Drawal','url'=>'app/etariff/bills.php','icon'=>'🧾'],
            ],
        ],
        'website' => [
            'key' => 'website', 'short' => 'Website + CMS',
            'name' => 'Departmental Website + CMS', 'name_hi' => 'विभागीय वेबसाइट एवं सीएमएस',
            'accent' => '#4f46e5', 'icon' => '🏛️',
            'tagline' => 'Bilingual public site, notices, RTI, grievance & admin CMS.',
            'tagline_hi' => 'द्विभाषी सार्वजनिक वेबसाइट, सूचनाएँ, आरटीआई, शिकायत एवं सीएमएस।',
            'home' => 'public/home.php',
            'roles' => ['CITIZEN','EDITOR','ADMIN'],
            'nav' => [
                ['key'=>'dashboard','label'=>'CMS Admin','url'=>'app/cms/index.php','icon'=>'✎'],
            ],
        ],
    ];
}

/** One product by key, or null. */
function wrd_app(string $key): ?array {
    return wrd_apps()[$key] ?? null;
}
