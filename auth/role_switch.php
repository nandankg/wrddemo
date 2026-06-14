<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$role = strtoupper($_GET['role'] ?? '');
$allowed = ['SECRETARY','EIC','CE','SE','EE','AE','JE','FINANCE','ADMIN','ASO','ACCOUNTS','CITIZEN','EDITOR','CONSUMER','CONTRACTOR'];

// Return target: explicit ?to=, else the referring page, else the launcher.
$to = $_GET['to'] ?? ($_SERVER['HTTP_REFERER'] ?? base_url('index.php'));

if (in_array($role, $allowed, true) && switch_to_role($role)) {
    flash('Switched to ' . $role . ' view.');
    header('Location: ' . $to);
} else {
    header('Location: ' . base_url('index.php'));
}
exit;
