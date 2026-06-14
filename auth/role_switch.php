<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$role = strtoupper($_GET['role'] ?? '');
$allowed = ['SECRETARY','EIC','CE','SE','EE','AE','JE','FINANCE','ADMIN','ASO','ACCOUNTS','CITIZEN','EDITOR','CONSUMER','CONTRACTOR'];

// Return target: explicit ?to=, else the referring page, else the launcher.
// Same-origin only — never redirect off-site (no open redirect).
function rs_internal(string $url): bool {
    $h = parse_url($url, PHP_URL_HOST);
    return $h === null || $h === '' || $h === ($_SERVER['HTTP_HOST'] ?? '');
}
$to = base_url('index.php');
if (!empty($_GET['to']) && rs_internal($_GET['to']))                 $to = $_GET['to'];
elseif (!empty($_SERVER['HTTP_REFERER']) && rs_internal($_SERVER['HTTP_REFERER'])) $to = $_SERVER['HTTP_REFERER'];

if (in_array($role, $allowed, true) && switch_to_role($role)) {
    flash('Switched to ' . $role . ' view.');
    header('Location: ' . $to);
} else {
    header('Location: ' . base_url('index.php'));
}
exit;
