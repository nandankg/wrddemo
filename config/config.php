<?php
/**
 * WRD Jharkhand — Integrated Software Solution (DEMO)
 * Global configuration.
 *
 * NOTE: This is a presentation/demo build on PHP + MariaDB (XAMPP).
 * The production solution per RFP is delivered on React 18 + TypeScript
 * with Node/Django/Laravel + PostgreSQL 16. This demo proves the
 * workflows, UX and integrations functionally.
 */

declare(strict_types=1);

session_start();

// ---- Database (default XAMPP credentials) ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'wrd_demo');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Application ----
define('APP_NAME', 'WRD Jharkhand — Integrated Digital Backbone');
define('APP_NAME_HI', 'जल संसाधन विभाग, झारखंड — एकीकृत डिजिटल मंच');

// Base URL — auto-detect so it works whether served from /WRD or root.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
// Resolve to the app root (the folder that contains index.php)
$root = preg_replace('#/(app|auth|api|public|includes|deck)(/.*)?$#', '', $scriptDir);
if ($root === '' || $root === false) { $root = '/'; }
define('BASE_URL', rtrim($root, '/'));

function base_url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Demo password for all seeded accounts
define('DEMO_PASSWORD', 'demo123');

date_default_timezone_set('Asia/Kolkata');
