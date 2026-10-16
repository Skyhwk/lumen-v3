<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private $names = [
        'Master Lokasi Aset',
        'Master Ruang Aset',
        'Master Nomor CS',
    ];

    public function up(): void
    {
        $this->ensureMenu();
        $this->copyAccess('akses_menu', 'akses');
        $this->copyAccess('template_akses', 'akses');
    }

    public function down(): void
    {
    }

    private function ensureMenu(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $row = DB::table('menu')->where('menu', 'General Affair')->where('is_active', 1)->first();
        if (!$row) {
            return;
        }

        $submenu = json_decode($row->submenu ?? '[]', true);
        if (!is_array($submenu)) {
            $submenu = [];
        }

        $existing = collect($submenu)->map(function ($item) {
            return is_array($item) ? ($item['nama_inden_menu'] ?? '') : '';
        })->all();

        foreach ($this->names as $name) {
            if (!in_array($name, $existing, true)) {
                $submenu[] = [
                    'sub_menu' => null,
                    'nama_inden_menu' => $name,
                ];
            }
        }

        DB::table('menu')->where('id', $row->id)->update([
            'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function copyAccess(string $table, string $column): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
            $items = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($items)) {
                return;
            }

            $source = collect($items)->first(function ($item) {
                return is_array($item) && ($item['name'] ?? '') === 'Daftar Mobil' && ($item['parent'] ?? '') === 'General Affair';
            });

            if (!$source) {
                return;
            }

            $changed = false;
            foreach ($this->names as $name) {
                $exists = collect($items)->contains(function ($item) use ($name) {
                    return is_array($item) && ($item['name'] ?? '') === $name && ($item['parent'] ?? '') === 'General Affair';
                });

                if ($exists) {
                    continue;
                }

                $copy = $source;
                $copy['name'] = $name;
                $copy['parent'] = 'General Affair';
                $items[] = $copy;
                $changed = true;
            }

            if ($changed) {
                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode(array_values($items), JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }
};
