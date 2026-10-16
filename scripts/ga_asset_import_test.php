<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();

$path = $argv[1] ?? 'd:\\ISL\\SHARE\\dedi\\GA - Asset Total (1).xlsx';
$commit = isset($argv[2]) && $argv[2] === 'commit';

$service = app(App\Services\GaAsset\GaAssetExcelImportService::class);
$result = $service->importFromPath($path, basename($path), $commit, 1, 'CLI Test');

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
