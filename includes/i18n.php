<?php
declare(strict_types=1);

/** Language handling: ?lang=hi|en persisted in session. */
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','hi'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    // redirect to drop the query param cleanly
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    $qs  = $_GET; unset($qs['lang']);
    if ($qs) $url .= '?' . http_build_query($qs);
    header('Location: ' . $url); exit;
}
$LANG = $_SESSION['lang'] ?? 'en';
function lang(): string { return $_SESSION['lang'] ?? 'en'; }
function is_hi(): bool { return lang() === 'hi'; }

/** Pick the right column value bilingually. */
function bi(?string $en, ?string $hi): string {
    $v = is_hi() ? ($hi ?: $en) : $en;
    return htmlspecialchars((string)$v, ENT_QUOTES);
}

$STRINGS = [
    'portal_name'   => ['en'=>'Water Resources Department', 'hi'=>'जल संसाधन विभाग'],
    'govt'          => ['en'=>'Government of Jharkhand', 'hi'=>'झारखंड सरकार'],
    'tagline'       => ['en'=>'Integrated Digital Backbone', 'hi'=>'एकीकृत डिजिटल मंच'],
    'home'          => ['en'=>'Home', 'hi'=>'मुख्य पृष्ठ'],
    'about'         => ['en'=>'About', 'hi'=>'विभाग'],
    'schemes'       => ['en'=>'Schemes & Projects', 'hi'=>'योजनाएँ एवं परियोजनाएँ'],
    'tenders'       => ['en'=>'Tenders & Notices', 'hi'=>'निविदाएँ एवं सूचनाएँ'],
    'services'      => ['en'=>'Citizen Services', 'hi'=>'नागरिक सेवाएँ'],
    'rti'           => ['en'=>'RTI Online', 'hi'=>'आरटीआई ऑनलाइन'],
    'grievance'     => ['en'=>'Grievance', 'hi'=>'शिकायत'],
    'login'         => ['en'=>'Login', 'hi'=>'लॉगिन'],
    'logout'        => ['en'=>'Logout', 'hi'=>'लॉगआउट'],
    'dashboard'     => ['en'=>'Dashboard', 'hi'=>'डैशबोर्ड'],
    'apply_alloc'   => ['en'=>'Apply for Water Allocation', 'hi'=>'जल आवंटन हेतु आवेदन'],
    'pay_bill'      => ['en'=>'Pay Water Bill', 'hi'=>'जल बिल भुगतान'],
    'contractor_reg'=> ['en'=>'Contractor Registration', 'hi'=>'ठेकेदार पंजीकरण'],
    'quick_services'=> ['en'=>'Quick Services', 'hi'=>'त्वरित सेवाएँ'],
    'latest'        => ['en'=>'Latest Updates', 'hi'=>'नवीनतम अपडेट'],
    'whats_new'     => ['en'=>"What's New", 'hi'=>'नया क्या है'],
    'command_centre'=> ['en'=>'Executive Command Centre', 'hi'=>'कार्यकारी कमांड सेंटर'],
    'fund_req'      => ['en'=>'Fund Requisition', 'hi'=>'निधि माँग'],
    'reports'       => ['en'=>'Reports & MIS', 'hi'=>'रिपोर्ट एवं एमआईएस'],
    'allocation'    => ['en'=>'Water Allocation', 'hi'=>'जल आवंटन'],
    'etariff'       => ['en'=>'E-Tariff & Billing', 'hi'=>'ई-टैरिफ एवं बिलिंग'],
    'cms'           => ['en'=>'Website CMS', 'hi'=>'वेबसाइट सीएमएस'],
    'revenue'       => ['en'=>'Revenue', 'hi'=>'राजस्व'],
    'view_all'      => ['en'=>'View all', 'hi'=>'सभी देखें'],
    'search'        => ['en'=>'Search', 'hi'=>'खोजें'],
    'accessibility' => ['en'=>'Accessibility', 'hi'=>'सुगम्यता'],
    'skip_content'  => ['en'=>'Skip to main content', 'hi'=>'मुख्य सामग्री पर जाएँ'],
];
function t(string $key): string {
    global $STRINGS;
    $s = $STRINGS[$key] ?? null;
    if (!$s) return $key;
    return htmlspecialchars($s[lang()] ?? $s['en'], ENT_QUOTES);
}
