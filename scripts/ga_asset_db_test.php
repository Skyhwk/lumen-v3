<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();
echo 'db count=' . Illuminate\Support\Facades\DB::table('ga_assets')->count() . PHP_EOL;
echo 'eloquent count=' . App\Models\GaAsset::count() . PHP_EOL;
