<?php
/**
 * One-click installer for the WRD Jharkhand demo.
 * Visit  http://localhost/WRD/setup.php  once after starting MySQL.
 * Creates the database, all tables, and rich seed data.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$steps = [];
function ok(string $m) { global $steps; $steps[] = ['ok', $m]; }
function info(string $m){ global $steps; $steps[] = ['info', $m]; }

try {
    // 1. Connect directly to the pre-created database.
    //    On shared hosting (e.g. Hostinger) the DB must already exist (created
    //    in the control panel) — the MySQL user cannot CREATE DATABASE. On XAMPP
    //    this still works because the database is created by the panel/this app.
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    ok('Connected to database <code>' . DB_NAME . '</code>.');

    // 2. Drop existing demo tables (clean reset).
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['progress_updates','workflow_log','payments','bills','drawal_entries','consumers','allocations',
              'contractor_apps','contractors','fund_requisitions','projects','schemes',
              'divisions','content','grievances','rti_applications','users'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    ok('Previous demo tables cleared.');

    // 3. Schema --------------------------------------------------------------
    $pdo->exec(<<<SQL
    CREATE TABLE divisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120), name_hi VARCHAR(160),
        circle VARCHAR(120), bank_account VARCHAR(40), bank_name VARCHAR(120),
        lat DECIMAL(9,6), lng DECIMAL(9,6)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(60) UNIQUE, password_hash VARCHAR(255),
        name VARCHAR(120), name_hi VARCHAR(160),
        role VARCHAR(30), designation VARCHAR(120),
        division_id INT NULL, email VARCHAR(120), phone VARCHAR(20)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE schemes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(140), name_hi VARCHAR(180), type VARCHAR(60), head_of_account VARCHAR(60)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(180), name_hi VARCHAR(220),
        scheme_id INT, division_id INT,
        lat DECIMAL(9,6), lng DECIMAL(9,6),
        status VARCHAR(30), physical_pct INT, financial_pct INT,
        sanctioned_amount DECIMAL(16,2), spent_amount DECIMAL(16,2),
        start_date DATE, end_date DATE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE fund_requisitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        req_no VARCHAR(40) UNIQUE,
        project_id INT, scheme_id INT, division_id INT,
        head_of_account VARCHAR(60), fy VARCHAR(12),
        amount_requested DECIMAL(16,2), justification TEXT,
        status VARCHAR(40), -- Draft, Pending Review, Under Finance Review, Approved by Finance, Rejected, Released
        allocated_amount DECIMAL(16,2) NULL, release_ref VARCHAR(60) NULL,
        fund_code VARCHAR(40) NULL, release_date DATE NULL,
        created_by INT, current_owner_role VARCHAR(30),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE progress_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT,
        physical_pct INT, financial_pct INT,
        note VARCHAR(255),
        status VARCHAR(20), -- Submitted, Verified, Rejected
        submitted_by INT, verified_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE contractors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reg_no VARCHAR(40) UNIQUE, name VARCHAR(160), name_hi VARCHAR(200),
        class VARCHAR(10), pan VARCHAR(15), gst VARCHAR(20),
        district VARCHAR(80), status VARCHAR(30), risk_score INT,
        valid_upto DATE, registered_on DATE, qr_token VARCHAR(40),
        login_user VARCHAR(60) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE contractor_apps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ack_no VARCHAR(40) UNIQUE, contractor_id INT, type VARCHAR(20),
        class VARCHAR(10), stage VARCHAR(20), status VARCHAR(30),
        fee DECIMAL(12,2), fee_paid TINYINT DEFAULT 0, applied_on DATE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_no VARCHAR(40) UNIQUE, applicant VARCHAR(180),
        source VARCHAR(60), source_name VARCHAR(120),
        quantity_mld DECIMAL(10,2), season VARCHAR(20),
        division_id INT, district VARCHAR(80),
        stage VARCHAR(30), status VARCHAR(30),
        license_no VARCHAR(40) NULL, gst VARCHAR(20),
        annual_fee DECIMAL(14,2), applied_on DATE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE consumers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consumer_id VARCHAR(30) UNIQUE, name VARCHAR(180), name_hi VARCHAR(220),
        category VARCHAR(40), division_id INT,
        source VARCHAR(60), allocation_qty DECIMAL(10,2),
        gst VARCHAR(20), pan VARCHAR(15),
        sanction_ref VARCHAR(40), valid_upto DATE, login_user VARCHAR(60)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE drawal_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consumer_id INT, period VARCHAR(20),
        prev_reading DECIMAL(12,2), curr_reading DECIMAL(12,2),
        consumption DECIMAL(12,2), excess DECIMAL(12,2),
        anomaly TINYINT DEFAULT 0, entered_by INT, entered_on DATE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_no VARCHAR(40) UNIQUE, consumer_id INT, drawal_id INT NULL,
        period VARCHAR(20),
        fixed_charge DECIMAL(14,2), variable_charge DECIMAL(14,2),
        excess_charge DECIMAL(14,2), penalty DECIMAL(14,2), interest DECIMAL(14,2),
        gst DECIMAL(14,2), total DECIMAL(14,2),
        status VARCHAR(30), -- Draft(JE), Pending Verification(AE), Approved(EE), Demand Raised, Paid
        stage VARCHAR(20), created_on DATE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        txn_ref VARCHAR(40) UNIQUE, bill_id INT NULL, source_module VARCHAR(20),
        consumer_id INT NULL, division_id INT,
        amount DECIMAL(14,2), channel VARCHAR(30),
        credited_account VARCHAR(40), status VARCHAR(20),
        paid_on DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE workflow_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(30), entity_id INT,
        action VARCHAR(60), from_role VARCHAR(30), to_role VARCHAR(30),
        actor VARCHAR(120), remarks TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20), -- notice, tender, news, scheme, order
        title VARCHAR(240), title_hi VARCHAR(300),
        body TEXT, body_hi TEXT,
        category VARCHAR(60), status VARCHAR(20),
        publish_date DATE, author VARCHAR(120), is_new TINYINT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE grievances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ref_no VARCHAR(30) UNIQUE, name VARCHAR(160), phone VARCHAR(20),
        category VARCHAR(60), division_id INT, description TEXT,
        status VARCHAR(30), sla_due DATE, created_on DATE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);

    $pdo->exec(<<<SQL
    CREATE TABLE rti_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ref_no VARCHAR(30) UNIQUE, applicant VARCHAR(160),
        subject VARCHAR(240), status VARCHAR(30), filed_on DATE, fee_paid TINYINT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
    ok('All 17 tables created (utf8mb4 / Hindi-ready).');

    // 4. Seed ----------------------------------------------------------------
    require __DIR__ . '/sql/seed.php';   // populates using $pdo
    seed_demo($pdo);
    ok('Seed data inserted: divisions, schemes, geotagged projects, fund requisitions, consumers, bills, payments, contractors, allocations, grievances, RTI, and bilingual CMS content.');

} catch (Throwable $e) {
    info('ERROR: ' . htmlspecialchars($e->getMessage()));
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>WRD Demo — Setup</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Mukta:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Mukta',sans-serif}h1,h2{font-family:'Fraunces',serif}</style>
</head>
<body class="bg-slate-100 min-h-screen">
<div class="max-w-3xl mx-auto py-14 px-6">
  <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
    <div class="bg-gradient-to-r from-[#06314a] to-[#0E7C86] px-8 py-7 text-white">
      <h1 class="text-2xl font-semibold">WRD Jharkhand — Demo Installer</h1>
      <p class="text-cyan-100 text-sm mt-1">Integrated Software Solution · Presentation Build</p>
    </div>
    <div class="p-8 space-y-3">
      <?php foreach ($steps as [$type,$msg]): ?>
        <div class="flex items-start gap-3 text-sm <?= $type==='info'?'text-rose-700':'text-slate-700' ?>">
          <span class="mt-0.5"><?= $type==='info' ? '⚠️' : '✅' ?></span>
          <span><?= $msg ?></span>
        </div>
      <?php endforeach; ?>

      <div class="mt-6 pt-6 border-t border-slate-200">
        <a href="<?= base_url('index.php') ?>" class="inline-block bg-[#0E7C86] hover:bg-[#0a5d65] text-white font-semibold px-6 py-3 rounded-xl shadow">
          Launch the Portal →
        </a>
        <a href="<?= base_url('auth/login.php') ?>" class="inline-block ml-3 text-[#06314a] font-semibold px-6 py-3 rounded-xl border border-slate-300 hover:bg-slate-50">
          Officer / Citizen Login
        </a>
      </div>
      <p class="text-xs text-slate-500 mt-4">All demo accounts use password <code class="bg-slate-100 px-1.5 py-0.5 rounded">demo123</code>. Use the quick role-switcher on the login page to jump between JE / AE / EE / Secretary etc.</p>
    </div>
  </div>
</div>
</body></html>
