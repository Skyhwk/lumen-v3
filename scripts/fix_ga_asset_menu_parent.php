<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$app->boot();

$parent = 'General Affair/Assets';
$names = ['Import Excel', 'Daftar Aset'];

foreach (['akses_menu', 'template_akses'] as $table) {
    DB::table($table)->orderBy('id')->each(function ($row) use ($table, $parent, $names) {
        $access = json_decode($row->akses ?? '[]', true);
        if (!is_array($access)) {
            return;
        }
        $changed = false;
        foreach ($access as &$item) {
            if (!is_array($item) || !in_array($item['name'] ?? '', $names, true)) {
                continue;
            }
            if (($item['parent'] ?? '') !== $parent) {
                $item['parent'] = $parent;
                $changed = true;
            }
        }
        unset($item);
        if ($changed) {
            DB::table($table)->where('id', $row->id)->update([
                'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
            ]);
        }
    });
}
echo "done\n";
