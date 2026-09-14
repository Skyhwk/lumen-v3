<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RenameKebijakanMenuLabelsToKetetapan extends Migration
{
    private const MENU_RENAMES = [
        'Request Kebijakan' => 'Request Ketetapan',
        'Kebijakan Perusahaan' => 'Ketetapan Perusahaan',
        'Request Kebijakan Approval' => 'Persetujuan Request Ketetapan',
        'Drafting Kebijakan' => 'Drafting Ketetapan',
    ];

    private const MENU_RENAMES_REVERSE = [
        'Request Ketetapan' => 'Request Kebijakan',
        'Ketetapan Perusahaan' => 'Kebijakan Perusahaan',
        'Persetujuan Request Ketetapan' => 'Request Kebijakan Approval',
        'Drafting Ketetapan' => 'Drafting Kebijakan',
    ];

    public function up(): void
    {
        $this->updateMenuTable(self::MENU_RENAMES);
        $this->updateAccessTables(self::MENU_RENAMES);
    }

    public function down(): void
    {
        $this->updateMenuTable(self::MENU_RENAMES_REVERSE);
        $this->updateAccessTables(self::MENU_RENAMES_REVERSE);
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
}
