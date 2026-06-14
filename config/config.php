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

// ---- Database ----
// Deployment override: if config/config.local.php exists (gitignored), it can
// define any of the DB_* constants below for this environment (e.g. Hostinger).
// A Git pull / re-upload never touches that file, so credentials survive redeploys.
$__local = __DIR__ . '/config.local.php';
if (is_file($__local)) require $__local;

// Defaults (local XAMPP) — only applied if not already set by config.local.php.
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'wrd_demo');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

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
