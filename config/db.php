<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Friendly guidance instead of a raw stack trace.
        http_response_code(500);
        echo '<div style="font-family:system-ui;max-width:640px;margin:80px auto;padding:32px;'
           . 'border:1px solid #e2e8f0;border-radius:16px;background:#fff">'
           . '<h2 style="color:#0B4F6C">Database not ready</h2>'
           . '<p>The demo database <code>' . DB_NAME . '</code> is not available yet.</p>'
           . '<ol><li>Start <b>Apache</b> and <b>MySQL</b> in the XAMPP Control Panel.</li>'
           . '<li>Run the one-click installer: <a href="' . base_url('setup.php') . '">'
           . base_url('setup.php') . '</a></li></ol>'
           . '<p style="color:#64748b;font-size:13px">Details: ' . htmlspecialchars($e->getMessage()) . '</p>'
           . '</div>';
        exit;
    }
    return $pdo;
}
