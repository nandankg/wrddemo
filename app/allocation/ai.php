<?php
/**
 * Allocation AI assist — JSON endpoint.
 * Same-origin fetch target for the apply form: returns the source recommendation,
 * risk grade, and executive summary from the unit-tested pure functions in lib.php.
 * Deterministic and offline — no external API.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/lib.php';
allocation_require_login();
header('Content-Type: application/json');

$pdo = db();
$quantity = (float)($_GET['quantity'] ?? 0);
$sourceId = (int)($_GET['source_id'] ?? 0);
$divId    = (int)($_GET['division_id'] ?? 0);
$district = trim((string)($_GET['district'] ?? ''));
$season   = ($_GET['season'] ?? 'Perennial') === 'Seasonal' ? 'Seasonal' : 'Perennial';
$applicant= trim((string)($_GET['applicant'] ?? 'The applicant'));
$docsPresent = (int)($_GET['docs_present'] ?? 4);

$sources = $pdo->query("SELECT * FROM water_sources")->fetchAll();

// Applicant reference location (for distance) = its division centroid, if any.
$ref = null;
if ($divId) {
    $d = $pdo->prepare("SELECT lat,lng FROM divisions WHERE id=?"); $d->execute([$divId]);
    if ($row = $d->fetch()) $ref = ['lat'=>$row['lat'], 'lng'=>$row['lng']];
}

$rec = allocation_recommend_source($district, $quantity, $sources, $ref);

// Chosen source: explicit pick, else the recommendation.
$chosen = null;
if ($sourceId) {
    foreach ($sources as $s) if ((int)$s['id'] === $sourceId) { $chosen = $s; break; }
}
$chosen ??= $rec;

$app = ['applicant'=>$applicant, 'quantity_mld'=>$quantity, 'season'=>$season,
        'docs_total'=>4, 'docs_present'=>$docsPresent];
$risk = allocation_risk($app, $chosen);
$summary = allocation_exec_summary($app, $chosen, $risk);

echo json_encode([
    'recommendation' => $rec ? [
        'id' => (int)$rec['id'], 'name' => $rec['name'], 'type' => $rec['type'],
        'utilisation_pct' => $rec['utilisation_pct'], 'distance_km' => $rec['distance_km'],
        'headroom' => $rec['headroom'], 'meets_quantity' => $rec['meets_quantity'], 'reason' => $rec['reason'],
    ] : null,
    'risk' => ['level'=>$risk['level'], 'reasons'=>$risk['reasons']],
    'summary' => $summary,
], JSON_UNESCAPED_UNICODE);
