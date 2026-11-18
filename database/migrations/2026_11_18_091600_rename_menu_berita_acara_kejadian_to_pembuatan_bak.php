<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const OLD_MENU = 'Berita Acara Kejadian';
    private const NEW_MENU = 'Pembuatan BAK';

    private const REQUEST_GROUP_LABELS = ['Request', 'request', 'Permohonan', 'permohonan'];

    private const ACCESS_REFERENCE_MENUS = [
        self::OLD_MENU,
        'Permohonan Cuti',
        'Biaya Operasional',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $this->renameMenuLabels([self::OLD_MENU => self::NEW_MENU]);
        $this->ensureMenuUnderRequest();
        $this->ensureAccessForUsers();
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $this->renameMenuLabels([self::NEW_MENU => self::OLD_MENU]);
        $this->removeMenuFromRequestGroups(self::NEW_MENU);
        $this->removeAccessEntry(self::NEW_MENU);
    }

    private function renameMenuLabels(array $renames): void
    {
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

        $this->updateAccessTables($renames);
    }

    private function updateAccessTables(array $renames): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $renames) {
                $column = 'akses';
                $access = json_decode($row->{$column} ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                $changed = false;

                foreach ($access as &$item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $name = $item['name'] ?? null;
                    if ($name === 'Kebijakan Privasi') {
                        continue;
                    }

                    $itemChanged = false;

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
                        $parentForPath = (string) ($item['parent'] ?? '');
                        $item['path'] = $this->buildMenuPath($parentForPath, (string) $item['name']);
                        $changed = true;
                    }
                }
                unset($item);

                if ($changed) {
                    DB::table($table)->where('id', $row->id)->update([
                        $column => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
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

    private function ensureMenuUnderRequest(): void
    {
        DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $changed = false;

            foreach ($submenu as &$group) {
                $label = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''))));
                if (!in_array($label, array_map('strtolower', self::REQUEST_GROUP_LABELS), true)) {
                    continue;
                }

                $group['sub_menu'] = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
                $items = $group['sub_menu'];

                $hasNew = in_array(self::NEW_MENU, $items, true);
                $hasOld = in_array(self::OLD_MENU, $items, true);

                if (!$hasNew && !$hasOld) {
                    $group['sub_menu'][] = self::NEW_MENU;
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

    private function removeMenuFromRequestGroups(string $menuLabel): void
    {
        DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) use ($menuLabel) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $changed = false;

            foreach ($submenu as &$group) {
                $label = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''))));
                if (!in_array($label, array_map('strtolower', self::REQUEST_GROUP_LABELS), true)) {
                    continue;
                }

                if (!is_array($group['sub_menu'] ?? null)) {
                    continue;
                }

                $filtered = array_values(array_filter(
                    $group['sub_menu'],
                    fn ($item) => $item !== $menuLabel
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

    private function ensureAccessForUsers(): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $column = 'akses';
            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
                $access = json_decode($row->{$column} ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                if (collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::NEW_MENU)) {
                    return;
                }

                $reference = null;
                foreach (self::ACCESS_REFERENCE_MENUS as $referenceMenu) {
                    $reference = collect($access)->first(
                        fn ($item) => is_array($item) && ($item['name'] ?? null) === $referenceMenu
                    );
                    if ($reference) {
                        break;
                    }
                }

                if (!$reference) {
                    return;
                }

                $clone = $reference;
                $clone['name'] = self::NEW_MENU;
                $parentForPath = (string) ($clone['parent'] ?? 'request');
                $clone['path'] = $this->buildMenuPath($parentForPath, self::NEW_MENU);

                $access[] = $clone;

                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }

    private function removeAccessEntry(string $menuName): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $column = 'akses';
            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column, $menuName) {
                $access = json_decode($row->{$column} ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                $filtered = array_values(array_filter(
                    $access,
                    fn ($item) => !is_array($item) || ($item['name'] ?? null) !== $menuName
                ));

                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }
};