<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();
foreach (['ga_asset_rooms', 'ga_assets', 'ga_asset_category_l1'] as $t) {
    echo $t . ': ';
    echo Illuminate\Support\Facades\Schema::hasTable($t) ? implode(',', Illuminate\Support\Facades\Schema::getColumnListing($t)) : 'MISSING';
    echo PHP_EOL;
}
