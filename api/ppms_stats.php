<?php
/** Public read-only live feed consumed by the website (Component E ← PPMS). */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$pdo=db();
echo json_encode([
  'as_of' => date('c'),
  'source' => 'PPMS (Component A) — secure read-only API',
  'metrics' => [
    'active_projects' => (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'on_track'        => (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status='On Track'")->fetchColumn(),
    'sanctioned_outlay'=> (float)$pdo->query('SELECT COALESCE(SUM(sanctioned_amount),0) FROM projects')->fetchColumn(),
    'revenue_collected'=> (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Success'")->fetchColumn(),
    'active_contractors'=> (int)$pdo->query("SELECT COUNT(*) FROM contractors WHERE status='Active'")->fetchColumn(),
    'water_consumers'  => (int)$pdo->query('SELECT COUNT(*) FROM consumers')->fetchColumn(),
  ],
  'projects' => $pdo->query("SELECT name,status,physical_pct,financial_pct,lat,lng FROM projects")->fetchAll(),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
