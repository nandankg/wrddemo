<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$role = strtoupper($_GET['role'] ?? '');
$allowed = ['SECRETARY','EIC','CE','SE','EE','AE','JE','FINANCE','ADMIN','ASO','CONSUMER','CONTRACTOR'];
if (in_array($role, $allowed, true) && switch_to_role($role)) {
    flash('Switched to ' . $role . ' view.');
    header('Location: ' . base_url('app/dashboard.php'));
} else {
    header('Location: ' . base_url('auth/login.php'));
}
exit;
