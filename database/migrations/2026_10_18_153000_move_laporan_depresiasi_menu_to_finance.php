<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FROM_ROOT = 'General Affair';

    private const FROM_GROUP = 'Assets';

    private const TO_ROOT = 'Finance';

    private const TO_GROUP = 'Aset';

    private const MENU_ITEM = 'Laporan Depresiasi';

    private const TO_PARENT = 'Finance/Aset';

    public function up(): void
    {
        $this->removeFromMenu(self::FROM_ROOT, self::FROM_GROUP);
        $this->addToMenu(self::TO_ROOT, self::TO_GROUP);
        $this->retargetAccess();
    }

    public function down(): void
    {
        $this->removeFromMenu(self::TO_ROOT, self::TO_GROUP);
        $this->addToMenu(self::FROM_ROOT, self::FROM_GROUP);

        foreach (['akses_menu', 'template_akses'] as $table) {
            $this->rewriteParent($table, self::TO_PARENT, self::FROM_ROOT . '/' . self::FROM_GROUP);
        }
    }

    private function addToMenu(string $root, string $group): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        DB::table('menu')
            ->where('is_active', 1)
            ->whereRaw('LOWER(menu) = ?', [strtolower($root)])
            ->orderBy('id')
            ->each(function ($row) use ($group) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                $groupIndex = null;
                foreach ($submenu as $idx => $item) {
                    $label = strtolower(trim(str_replace('_', ' ', (string) ($item['nama_inden_menu'] ?? $item['name'] ?? ''))));
                    if ($label === strtolower($group)) {
                        $groupIndex = $idx;
                        break;
                    }
                }

                if ($groupIndex === null) {
                    $submenu[] = [
                        'nama_inden_menu' => $group,
                        'sub_menu' => [self::MENU_ITEM],
                    ];
                } else {
                    $items = is_array($submenu[$groupIndex]['sub_menu'] ?? null) ? $submenu[$groupIndex]['sub_menu'] : [];
                    if (!in_array(self::MENU_ITEM, $items, true)) {
                        $items[] = self::MENU_ITEM;
                    }
                    $submenu[$groupIndex]['sub_menu'] = $items;
                }

                DB::table('menu')->where('id', $row->id)->update([
                    'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    private function removeFromMenu(string $root, string $group): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        DB::table('menu')
            ->where('is_active', 1)
            ->whereRaw('LOWER(menu) = ?', [strtolower($root)])
            ->orderBy('id')
            ->each(function ($row) use ($group) {
                $submenu = json_decode($row->submenu ?? '[]', true);
                if (!is_array($submenu)) {
                    return;
                }

                $changed = false;
                foreach ($submenu as $idx => $item) {
                    $label = strtolower(trim(str_replace('_', ' ', (string) ($item['nama_inden_menu'] ?? $item['name'] ?? ''))));
                    if ($label !== strtolower($group)) {
                        continue;
                    }
                    $items = is_array($item['sub_menu'] ?? null) ? $item['sub_menu'] : [];
                    $filtered = array_values(array_filter($items, fn ($name) => $name !== self::MENU_ITEM));
                    if (count($filtered) !== count($items)) {
                        $submenu[$idx]['sub_menu'] = $filtered;
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('menu')->where('id', $row->id)->update([
                        'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    private function retargetAccess(): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table) {
                $access = json_decode($row->akses ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                $changed = false;
                $hasItem = false;
                foreach ($access as $idx => $item) {
                    if (!is_array($item) || ($item['name'] ?? null) !== self::MENU_ITEM) {
                        continue;
                    }
                    $hasItem = true;
                    if (($item['parent'] ?? null) !== self::TO_PARENT) {
                        $access[$idx]['parent'] = self::TO_PARENT;
                        $changed = true;
                    }
                }

                if (!$hasItem) {
                    $reference = collect($access)->first(function ($item) {
                        return is_array($item)
                            && ($item['name'] ?? null) === 'Daftar Aset'
                            && ($item['parent'] ?? null) === self::TO_PARENT;
                    });
                    if (!$reference) {
                        $reference = collect($access)->first(function ($item) {
                            return is_array($item) && ($item['parent'] ?? null) === self::TO_PARENT;
                        });
                    }
                    if ($reference) {
                        $clone = $reference;
                        $clone['name'] = self::MENU_ITEM;
                        $clone['parent'] = self::TO_PARENT;
                        $access[] = $clone;
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table($table)->where('id', $row->id)->update([
                        'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
        }
    }

    private function rewriteParent(string $table, string $fromParent, string $toParent): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $fromParent, $toParent) {
            $access = json_decode($row->akses ?? '[]', true);
            if (!is_array($access)) {
                return;
            }

            $changed = false;
            foreach ($access as $idx => $item) {
                if (!is_array($item) || ($item['name'] ?? null) !== self::MENU_ITEM) {
                    continue;
                }
                if (($item['parent'] ?? null) === $fromParent) {
                    $access[$idx]['parent'] = $toParent;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table($table)->where('id', $row->id)->update([
                    'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }
};
