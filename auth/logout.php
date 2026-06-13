<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: ' . base_url('index.php'));
exit;
