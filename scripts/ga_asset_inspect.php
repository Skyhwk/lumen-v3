<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();

foreach (['ga_asset_locations', 'ga_asset_rooms', 'master_kategori_aset', 'master_sub_kategori_aset'] as $t) {
    echo "=== $t ===\n";
    echo implode(',', Illuminate\Support\Facades\Schema::getColumnListing($t)) . "\n";
}
$row = Illuminate\Support\Facades\DB::table('ga_assets')->first();
echo "sample asset:\n" . json_encode($row, JSON_PRETTY_PRINT) . "\n";
$loc = Illuminate\Support\Facades\DB::table('ga_asset_locations')->limit(5)->get();
echo "locations sample:\n" . json_encode($loc, JSON_PRETTY_PRINT) . "\n";
$c = Illuminate\Support\Facades\DB::select('SHOW CREATE TABLE ga_asset_rooms')[0];
$arr = (array) $c;
echo "rooms create:\n" . end($arr) . "\n";
$c = Illuminate\Support\Facades\DB::select('SHOW CREATE TABLE ga_assets')[0];
$arr = (array) $c;
echo end($arr) . "\n";
