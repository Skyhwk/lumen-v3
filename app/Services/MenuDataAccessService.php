<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuDataAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MenuDataAccessService
{
    /**
     * Grade aktif untuk menu (sudah dinormalisasi).
     *
     * @return list<string>
     */
    public static function allowedGrades(string $menuKey): array
    {
        return MenuDataAccess::activeGradesForMenu($menuKey);
    }

    /**
     * Apakah menu punya konfigurasi grade aktif.
     */
    public static function hasConfiguredGrades(string $menuKey): bool
    {
        return self::allowedGrades($menuKey) !== [];
    }

    /**
     * Katalog menu yang bisa dikonfigurasi (dari master menu).
     * menu_key = slug nama leaf menu.
     *
     * @param  bool  $excludeConfigured  Sembunyikan menu yang sudah punya row aktif di menu_data_access
     * @param  string|null  $includeMenuKey  Tetap sertakan key ini (untuk mode edit)
     * @return list<array{menu_key: string, label: string}>
     */
    public static function selectableMenus(
        bool $excludeConfigured = false,
        ?string $includeMenuKey = null
    ): array {
        $items = [];

        $rows = Menu::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(menu) != ?', ['email'])
            ->get(['menu', 'submenu']);

        foreach ($rows as $row) {
            $submenus = is_array($row->submenu) ? $row->submenu : [];
            foreach ($submenus as $submenu) {
                $submenu = (array) $submenu;
                $children = $submenu['sub_menu'] ?? [];
                if (!is_array($children)) {
                    continue;
                }
                foreach ($children as $leafName) {
                    $label = trim((string) $leafName);
                    if ($label === '') {
                        continue;
                    }
                    $key = Str::slug($label);
                    if ($key === '') {
                        continue;
                    }
                    $items[$key] = [
                        'menu_key' => $key,
                        'label' => $label,
                    ];
                }
            }
        }

        ksort($items);
        $list = array_values($items);

        if (!$excludeConfigured) {
            return $list;
        }

        $configuredKeys = MenuDataAccess::query()
            ->where('is_active', true)
            ->pluck('menu_key')
            ->map(fn ($key) => MenuDataAccess::normalizeMenuKey($key))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $keepKey = MenuDataAccess::normalizeMenuKey($includeMenuKey);
        $configuredLookup = array_fill_keys($configuredKeys, true);

        return array_values(array_filter(
            $list,
            static function (array $item) use ($configuredLookup, $keepKey) {
                $key = $item['menu_key'];
                if ($keepKey !== '' && $key === $keepKey) {
                    return true;
                }

                return !isset($configuredLookup[$key]);
            }
        ));
    }

    /**
     * Map menu_key => label dari katalog.
     *
     * @return array<string, string>
     */
    public static function menuLabelMap(): array
    {
        $map = [];
        foreach (self::selectableMenus() as $item) {
            $map[$item['menu_key']] = $item['label'];
        }

        return $map;
    }

    public static function resolveMenuLabel(string $menuKey): string
    {
        $key = MenuDataAccess::normalizeMenuKey($menuKey);
        $map = self::menuLabelMap();

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Fallback: ubah slug jadi label sederhana
        return Str::title(str_replace('-', ' ', $key));
    }

    /**
     * Filter query: hanya data yang relasi karyawan-nya punya grade
     * yang terdaftar di menu_data_access untuk menu_key tersebut.
     *
     * Jika belum ada konfigurasi → query dikosongkan.
     */
    public static function applyKaryawanGradeFilter(Builder $query, string $menuKey): Builder
    {
        $allowedGrades = self::allowedGrades($menuKey);

        if ($allowedGrades === []) {
            return $query->whereRaw('1 = 0');
        }

        $placeholders = implode(',', array_fill(0, count($allowedGrades), '?'));

        return $query->whereHas('karyawan', function (Builder $q) use ($allowedGrades, $placeholders) {
            $q->whereRaw(
                'UPPER(TRIM(`grade`)) IN (' . $placeholders . ')',
                $allowedGrades
            );
        });
    }
}
