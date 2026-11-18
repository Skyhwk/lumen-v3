<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RenameRekapBakMenuToLaporanBak extends Migration
{
    private const MENU_RENAMES = [
        'Rekap BAK' => 'Laporan BAK',
    ];

    private const MENU_RENAMES_REVERSE = [
        'Laporan BAK' => 'Rekap BAK',
    ];

    public function up(): void
    {
        $this->updateMenuTable(self::MENU_RENAMES);
        $this->updateAccessTables(self::MENU_RENAMES);
        $this->updateMenuDataAccess('rekap-bak', 'laporan-bak');
    }

    public function down(): void
    {
        $this->updateMenuTable(self::MENU_RENAMES_REVERSE);
        $this->updateAccessTables(self::MENU_RENAMES_REVERSE);
        $this->updateMenuDataAccess('laporan-bak', 'rekap-bak');
    }

    private function updateMenuTable(array $renames): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        DB::table('menu')->orderBy('id')->each(function ($row) use ($renames) {
            $submenu = json_decode($row->submenu ?? '[]', true);

            if (!is_array($submenu)) {
                return;
            }

            $changed = false;

            foreach ($submenu as &$group) {
                if (!is_array($group)) {
                    continue;
                }

                if (!empty($group['nama_inden_menu']) && isset($renames[$group['nama_inden_menu']])) {
                    $group['nama_inden_menu'] = $renames[$group['nama_inden_menu']];
                    $changed = true;
                }

                if (!isset($group['sub_menu']) || !is_array($group['sub_menu'])) {
                    continue;
                }

                foreach ($group['sub_menu'] as &$item) {
                    if (is_string($item) && isset($renames[$item])) {
                        $item = $renames[$item];
                        $changed = true;
                    }
                }
                unset($item);
            }
            unset($group);

            if ($changed) {
                DB::table('menu')->where('id', $row->id)->update([
                    'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    private function updateAccessTables(array $renames): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $renames) {
                $access = json_decode($row->akses ?? '[]', true);

                if (!is_array($access)) {
                    return;
                }

                $changed = false;

                foreach ($access as &$item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $itemChanged = false;
                    $name = $item['name'] ?? null;

                    if ($name && isset($renames[$name])) {
                        $item['name'] = $renames[$name];
                        $itemChanged = true;
                    }

                    $parent = $item['parent'] ?? null;
                    if (is_string($parent) && $parent !== '') {
                        $updatedParent = $this->renameParentPath($parent, $renames);
                        if ($updatedParent !== $parent) {
                            $item['parent'] = $updatedParent;
                            $itemChanged = true;
                        }
                    }

                    if ($itemChanged && !empty($item['name'])) {
                        $item['path'] = $this->buildMenuPath(
                            (string) ($item['parent'] ?? ''),
                            (string) $item['name']
                        );
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
    }

    private function updateMenuDataAccess(string $fromKey, string $toKey): void
    {
        if (!Schema::hasTable('menu_data_access')) {
            return;
        }

        $fromKey = Str::slug($fromKey);
        $toKey = Str::slug($toKey);

        if ($fromKey === '' || $toKey === '' || $fromKey === $toKey) {
            return;
        }

        DB::table('menu_data_access')
            ->where('menu_key', $fromKey)
            ->update(['menu_key' => $toKey]);

        // legacy underscore form
        $fromLegacy = str_replace('-', '_', $fromKey);
        if ($fromLegacy !== $fromKey) {
            DB::table('menu_data_access')
                ->where('menu_key', $fromLegacy)
                ->update(['menu_key' => $toKey]);
        }
    }

    private function renameParentPath(string $parent, array $renames): string
    {
        $parts = array_map('trim', explode('/', $parent));
        $updated = false;

        foreach ($parts as &$part) {
            if (isset($renames[$part])) {
                $part = $renames[$part];
                $updated = true;
            }
        }
        unset($part);

        return $updated ? implode('/', $parts) : $parent;
    }

    private function buildMenuPath(string $parent, string $name): string
    {
        $segments = [];

        foreach (explode('/', $parent) as $part) {
            $slug = Str::slug(str_replace('_', ' ', trim($part)));
            if ($slug !== '') {
                $segments[] = $slug;
            }
        }

        $nameSlug = Str::slug(str_replace('_', ' ', trim($name)));
        if ($nameSlug !== '') {
            $segments[] = $nameSlug;
        }

        return '/' . implode('/', $segments);
    }
}
