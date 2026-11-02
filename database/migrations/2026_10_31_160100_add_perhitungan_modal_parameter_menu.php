<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU = 'Perhitungan Modal Parameter';

    private const REFERENCE = 'Harga Parameter';

    private const GROUP = 'Master Harga';

    public function up(): void
    {
        $this->insertMenuItem();
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

                foreach ($submenu as &$group) {
                    if (is_array($group['sub_menu'] ?? null)) {
                        $group['sub_menu'] = array_values(array_filter(
                            $group['sub_menu'],
                            fn ($item) => $item !== self::MENU
                        ));
                    }
                }
                unset($group);

                DB::table('menu')->where('id', $row->id)->update([
                    'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
                ]);
            });
        }

        $this->removeAccess('akses_menu', 'akses');
        $this->removeAccess('template_akses', 'akses');
    }

    private function insertMenuItem(): void
    {
        if (!Schema::hasTable('menu') || !Schema::hasColumn('menu', 'submenu')) {
            return;
        }

        DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $changed = false;
            foreach ($submenu as &$group) {
                $items = $group['sub_menu'] ?? null;
                if (!is_array($items)) {
                    continue;
                }

                if (in_array(self::MENU, $items, true)) {
                    return;
                }

                $groupLabel = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? ''))));

                if (in_array(self::REFERENCE, $items, true)) {
                    $index = array_search(self::REFERENCE, $items, true);
                    array_splice($items, $index + 1, 0, [self::MENU]);
                    $group['sub_menu'] = array_values($items);
                    $changed = true;
                    break;
                }

                if ($groupLabel === strtolower(self::GROUP) && !in_array(self::MENU, $items, true)) {
                    $items[] = self::MENU;
                    $group['sub_menu'] = array_values($items);
                    $changed = true;
                    break;
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

            if (collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::MENU)) {
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
