<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();
DB::table('akses_menu')->orderBy('id')->each(function ($row) {
    $access = json_decode($row->akses ?? '[]', true);
    if (!is_array($access)) return;
    foreach ($access as $item) {
        if (is_array($item) && ($item['name'] ?? '') === 'Kategori Asset') {
            echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
});
