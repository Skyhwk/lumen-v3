<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU = 'Demo Customer';
    private const REFERENCE = 'Registrasi Customer';
    private const GROUP = 'Customers';

    public function up(): void
    {
        if (Schema::hasTable('menu') && Schema::hasColumn('menu', 'submenu')) {
            DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                $changed = false;
                foreach ($submenu as &$group) {
                    $label = trim((string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''));
                    $items = $group['sub_menu'] ?? null;
                    if (strcasecmp($label, self::GROUP) !== 0 || !is_array($items) || !in_array(self::REFERENCE, $items, true)) {
                        continue;
                    }

                    $items = array_values(array_filter($items, fn ($item) => $item !== self::MENU));
                    $index = array_search(self::REFERENCE, $items, true);
                    array_splice($items, $index + 1, 0, [self::MENU]);
                    $group['sub_menu'] = array_values($items);
                    $changed = true;
                }
                unset($group);

                if ($changed) {
                    DB::table('menu')->where('id', $row->id)->update([
                        'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
        }

        $this->copyAccess('akses_menu', 'akses');
        $this->copyAccess('template_akses', 'akses');
    }

    public function down(): void
    {
        if (Schema::hasTable('menu') && Schema::hasColumn('menu', 'submenu')) {
            DB::table('menu')->orderBy('id')->each(function ($row) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                $changed = false;
                foreach ($submenu as &$group) {
                    if (!is_array($group['sub_menu'] ?? null)) {
                        continue;
                    }

                    $filtered = array_values(array_filter(
                        $group['sub_menu'],
                        fn ($item) => $item !== self::MENU
                    ));
                    if (count($filtered) !== count($group['sub_menu'])) {
                        $group['sub_menu'] = $filtered;
                        $changed = true;
                    }
                }
                unset($group);

                if ($changed) {
                    DB::table('menu')->where('id', $row->id)->update([
                        'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
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
            if (!is_array($access) || collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::MENU)) {
                return;
            }

            $reference = collect($access)->first(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::REFERENCE);
            if (!$reference) {
                return;
            }

            $reference['name'] = self::MENU;
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

            DB::table($table)->where('id', $row->id)->update([
                $column => json_encode(array_values(array_filter(
                    $access,
                    fn ($item) => !is_array($item) || ($item['name'] ?? null) !== self::MENU
                )), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }
};
