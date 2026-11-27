<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PARENT = 'Programmer';
    private const MENU = 'Blok LHP';
    private const REFERENCE = 'Pengaturan Akses Data';

    public function up(): void
    {
        if (Schema::hasTable('menu') && Schema::hasColumn('menu', 'submenu')) {
            DB::table('menu')
                ->where('is_active', 1)
                ->where('menu', self::PARENT)
                ->orderBy('id')
                ->each(function ($row) {
                    $submenu = json_decode($row->submenu ?? '[]', true);
                    if (!is_array($submenu)) {
                        $submenu = [];
                    }

                    $exists = collect($submenu)->contains(function ($item) {
                        $label = trim((string) ($item['nama_inden_menu'] ?? $item['name'] ?? ''));

                        return strcasecmp($label, self::MENU) === 0;
                    });

                    if ($exists) {
                        return;
                    }

                    $submenu[] = [
                        'sub_menu' => null,
                        'nama_inden_menu' => self::MENU,
                    ];

                    DB::table('menu')->where('id', $row->id)->update([
                        'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
                    ]);
                });
        }

        $this->copyAccess('akses_menu', 'akses');
        $this->copyAccess('template_akses', 'akses');
    }

    public function down(): void
    {
        if (Schema::hasTable('menu') && Schema::hasColumn('menu', 'submenu')) {
            DB::table('menu')
                ->where('menu', self::PARENT)
                ->orderBy('id')
                ->each(function ($row) {
                    $submenu = json_decode($row->submenu ?? '[]', true);
                    if (!is_array($submenu)) {
                        return;
                    }

                    $filtered = array_values(array_filter($submenu, function ($item) {
                        $label = trim((string) ($item['nama_inden_menu'] ?? $item['name'] ?? ''));

                        return strcasecmp($label, self::MENU) !== 0;
                    }));

                    if (count($filtered) !== count($submenu)) {
                        DB::table('menu')->where('id', $row->id)->update([
                            'submenu' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                });
        }

        $this->removeAccess('akses_menu', 'akses');
        $this->removeAccess('template_akses', 'akses');
    }

    private function copyAccess(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access)) {
                return;
            }

            $alreadyHas = collect($access)->contains(
                fn ($item) => is_array($item) && strcasecmp((string) ($item['name'] ?? ''), self::MENU) === 0
            );
            if ($alreadyHas) {
                return;
            }

            $reference = collect($access)->first(
                fn ($item) => is_array($item) && strcasecmp((string) ($item['name'] ?? ''), self::REFERENCE) === 0
            );

            if (!$reference) {
                $reference = collect($access)->first(
                    fn ($item) => is_array($item) && strcasecmp((string) ($item['parent'] ?? ''), self::PARENT) === 0
                );
            }

            if (!$reference) {
                return;
            }

            $reference['name'] = self::MENU;
            $reference['parent'] = self::PARENT;
            $access[] = $reference;

            DB::table($table)->where('id', $row->id)->update([
                $column => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    private function removeAccess(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access)) {
                return;
            }

            $filtered = array_values(array_filter(
                $access,
                fn ($item) => !(is_array($item) && strcasecmp((string) ($item['name'] ?? ''), self::MENU) === 0)
            ));

            if (count($filtered) !== count($access)) {
                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }
};
