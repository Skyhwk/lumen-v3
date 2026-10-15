<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();
$row = DB::table('menu')->where('id', 118)->first();
echo json_encode(json_decode($row->submenu), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
