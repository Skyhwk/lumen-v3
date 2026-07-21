<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

use Illuminate\Http\Request;
use App\Http\Controllers\api\LimsStpsController;

try {
    $req = new Request([
        'periode_awal' => '2026-01-01',
        'periode_akhir' => '2026-12-31'
    ]);
    
    $controller = new LimsStpsController($req);
    $response = $controller->index($req);
    echo "SUCCESS!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
