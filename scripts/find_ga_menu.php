<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();

$rows = DB::table('menu')->where('is_active', 1)->get(['id', 'menu', 'submenu']);
foreach ($rows as $row) {
    $sub = json_decode($row->submenu ?? '[]', true);
    if (!is_array($sub)) {
        continue;
    }
    foreach ($sub as $group) {
        $name = $group['nama_inden_menu'] ?? $group['name'] ?? '';
        $label = strtolower($name);
        if (str_contains($label, 'general') || str_contains($label, 'affair') || str_contains($label, 'asset') || str_contains($label, 'aset')) {
            echo "menu id={$row->id} root={$row->menu}\n";
            echo json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n";
        }
        if (is_array($group['sub_menu'] ?? null)) {
            foreach ($group['sub_menu'] as $item) {
                if (is_string($item) && (stripos($item, 'asset') !== false || stripos($item, 'aset') !== false)) {
                    echo "menu id={$row->id} item under {$name}: {$item}\n";
                }
            }
        }
    }
}
