<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_ITEMS = [
        'Laporan Total Aset Akhir',
        'Aturan Umur Manfaat',
    ];

    public function up(): void
    {
        $this->appendFinanceMenus();

        foreach (self::MENU_ITEMS as $menuItem) {
            $this->copyAccess($menuItem);
        }
    }

    public function down(): void
    {
        $this->removeFinanceMenus();

        foreach (self::MENU_ITEMS as $menuItem) {
            $this->removeAccess($menuItem);
        }
    }

    private function appendFinanceMenus(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        DB::table('menu')
            ->where('is_active', 1)
            ->whereRaw('LOWER(menu) = ?', ['finance'])
            ->orderBy('id')
            ->each(function ($row) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                $groupIndex = null;
                foreach ($submenu as $idx => $group) {
                    $label = strtolower(trim((string) ($group['nama_inden_menu'] ?? $group['name'] ?? '')));
                    if ($label === 'aset') {
                        $groupIndex = $idx;
                        break;
                    }
                }

                if ($groupIndex === null) {
                    $submenu[] = ['nama_inden_menu' => 'Aset', 'sub_menu' => self::MENU_ITEMS];
                } else {
                    $items = is_array($submenu[$groupIndex]['sub_menu'] ?? null) ? $submenu[$groupIndex]['sub_menu'] : [];
                    foreach (self::MENU_ITEMS as $item) {
                        if (!in_array($item, $items, true)) {
                            $items[] = $item;
                        }
                    }
                    $submenu[$groupIndex]['sub_menu'] = $items;
                }

                DB::table('menu')->where('id', $row->id)->update([
                    'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    private function removeFinanceMenus(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        DB::table('menu')
            ->where('is_active', 1)
            ->whereRaw('LOWER(menu) = ?', ['finance'])
            ->orderBy('id')
            ->each(function ($row) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                foreach ($submenu as $idx => $group) {
                    $label = strtolower(trim((string) ($group['nama_inden_menu'] ?? '')));
                    if ($label !== 'aset') {
                        continue;
                    }
                    $items = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
                    $submenu[$idx]['sub_menu'] = array_values(array_filter(
                        $items,
                        fn ($name) => !in_array($name, self::MENU_ITEMS, true)
                    ));
                }

                DB::table('menu')->where('id', $row->id)->update([
                    'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    private function copyAccess(string $menuItem): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $menuItem) {
                $access = json_decode($row->akses ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                if (collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === $menuItem)) {
                    return;
                }

                $reference = collect($access)->first(function ($item) {
                    return is_array($item)
                        && ($item['name'] ?? null) === 'Daftar Aset'
                        && ($item['parent'] ?? null) === 'Finance/Aset';
                });

                if (!$reference) {
                    return;
                }

                $clone = $reference;
                $clone['name'] = $menuItem;
                $clone['parent'] = 'Finance/Aset';
                $access[] = $clone;

                DB::table($table)->where('id', $row->id)->update([
                    'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }

    private function removeAccess(string $menuItem): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $menuItem) {
                $access = json_decode($row->akses ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                $filtered = array_values(array_filter(
                    $access,
                    fn ($item) => !is_array($item) || ($item['name'] ?? null) !== $menuItem
                ));

                DB::table($table)->where('id', $row->id)->update([
                    'akses' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }
};
