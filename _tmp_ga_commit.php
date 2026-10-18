<?php
ini_set('memory_limit', '2048M');
set_time_limit(0);
require __DIR__ . '/bootstrap/app.php';
$app->boot();
$path = 'C:\\Users\\FREEE\\Downloads\\GA - Asset Total (1).xlsx';
$svc = app(App\Services\GaAsset\GaAssetExcelImportService::class);
$t0 = microtime(true);
$result = $svc->importFromPath($path, 'GA - Asset Total (1).xlsx', true, null, 'Import CLI Commit');
$stats = $result['stats'];
$withCost = DB::table('ga_assets')->whereNotNull('acquisition_cost')->where('acquisition_cost','>',0)->count();
$withLife = DB::table('ga_assets')->whereNotNull('useful_life_years')->where('useful_life_years','>',0)->count();
$withPurchase = DB::table('ga_assets')->whereNotNull('purchase_date')->count();
$depRows = DB::table('ga_asset_depreciation_yearly')->count();
echo json_encode([
  'stats' => [
    'rows_total' => $stats['rows_total'] ?? 0,
    'rows_imported' => $stats['rows_imported'] ?? 0,
    'rows_updated' => $stats['rows_updated'] ?? 0,
    'rows_skipped' => $stats['rows_skipped'] ?? 0,
    'rows_failed' => $stats['rows_failed'] ?? 0,
    'depreciation' => $stats['depreciation'] ?? null,
    'depreciation_error' => $stats['depreciation_error'] ?? null,
  ],
  'after' => [
    'with_cost' => $withCost,
    'with_life' => $withLife,
    'with_purchase_date' => $withPurchase,
    'dep_snapshots' => $depRows,
  ],
  'seconds' => round(microtime(true)-$t0, 2),
], JSON_PRETTY_PRINT) . PHP_EOL;
