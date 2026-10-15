<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();
$tables = Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE 'ga_%'");
foreach ($tables as $t) {
    $arr = (array) $t;
    echo reset($arr) . PHP_EOL;
}
