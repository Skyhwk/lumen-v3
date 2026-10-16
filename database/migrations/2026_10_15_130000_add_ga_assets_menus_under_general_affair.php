<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROOT_MENU = 'General Affair';

    private const GROUP_NAME = 'Assets';

    private const MENU_ITEMS = [
        'Import Excel',
        'Daftar Aset',
    ];

    private const ACCESS_REFERENCE = 'Kategori Asset';

    public function up(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $this->appendAssetsGroup();

        foreach (self::MENU_ITEMS as $menuLabel) {
            $this->copyAccessFromReference(self::ACCESS_REFERENCE, $menuLabel);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $this->removeAssetsGroup();

        foreach (self::MENU_ITEMS as $menuLabel) {
            $this->removeAccess($menuLabel);
        }
    }

    private function appendAssetsGroup(): void
    {
        DB::table('menu')
            ->where('is_active', 1)
            ->whereRaw('LOWER(menu) = ?', [strtolower(self::ROOT_MENU)])
            ->orderBy('id')
            ->each(function ($row) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                $groupIndex = null;
                foreach ($submenu as $idx => $group) {
                    $label = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''))));
                    if ($label === strtolower(self::GROUP_NAME)) {
                        $groupIndex = $idx;
                        break;
                    }
                }

                if ($groupIndex === null) {
                    $submenu[] = [
                        'nama_inden_menu' => self::GROUP_NAME,
                        'sub_menu' => self::MENU_ITEMS,
                    ];
                } else {
                    $group = $submenu[$groupIndex];
                    $items = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
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

    private function removeAssetsGroup(): void
    {
        DB::table('menu')
            ->where('is_active', 1)
            ->whereRaw('LOWER(menu) = ?', [strtolower(self::ROOT_MENU)])
            ->orderBy('id')
            ->each(function ($row) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                $changed = false;
                foreach ($submenu as $idx => $group) {
                    $label = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''))));
                    if ($label !== strtolower(self::GROUP_NAME)) {
                        continue;
                    }

                    $items = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
                    $filtered = array_values(array_filter($items, fn ($item) => !in_array($item, self::MENU_ITEMS, true)));
                    if (count($filtered) === 0) {
                        unset($submenu[$idx]);
                    } else {
                        $submenu[$idx]['sub_menu'] = $filtered;
                    }
                    $changed = true;
                }

                if ($changed) {
                    DB::table('menu')->where('id', $row->id)->update([
                        'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    private function copyAccessFromReference(string $referenceMenu, string $newMenu): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $referenceMenu, $newMenu) {
                $access = json_decode($row->akses ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                if (collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === $newMenu)) {
                    return;
                }

                $reference = collect($access)->first(fn ($item) => is_array($item) && ($item['name'] ?? null) === $referenceMenu);
                if (!$reference) {
                    return;
                }

                $clone = $reference;
                $clone['name'] = $newMenu;
                $clone['parent'] = self::ROOT_MENU . '/' . self::GROUP_NAME;

                $access[] = $clone;

                DB::table($table)->where('id', $row->id)->update([
                    'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }

    private function removeAccess(string $menuName): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $menuName) {
                $access = json_decode($row->akses ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                $filtered = array_values(array_filter(
                    $access,
                    fn ($item) => !is_array($item) || ($item['name'] ?? null) !== $menuName
                ));

                DB::table($table)->where('id', $row->id)->update([
                    'akses' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }
};
