<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REQUEST_GROUP_LABELS = ['Permohonan', 'permohonan'];
    private const PAYROLL_GROUP_LABELS = ['Payroll', 'payroll'];

    private const REQUEST_MENU = 'Penyesuaian Gaji';
    private const HRD_MENU = 'Permohonan Penyesuaian Gaji';

    public function up(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $this->appendMenuToGroups(self::REQUEST_GROUP_LABELS, self::REQUEST_MENU);
        $this->appendMenuToGroups(self::PAYROLL_GROUP_LABELS, self::HRD_MENU);
        $this->copyAccessFromReference('Permohonan Cuti', self::REQUEST_MENU);
        $this->copyAccessFromReference('Master Sallary', self::HRD_MENU);
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $this->removeMenuFromGroups(self::REQUEST_GROUP_LABELS, self::REQUEST_MENU);
        $this->removeMenuFromGroups(self::PAYROLL_GROUP_LABELS, self::HRD_MENU);
        $this->removeAccess(self::REQUEST_MENU);
        $this->removeAccess(self::HRD_MENU);
    }

    private function appendMenuToGroups(array $groupLabels, string $menuLabel): void
    {
        DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) use ($groupLabels, $menuLabel) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $changed = false;
            foreach ($submenu as &$group) {
                $label = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''))));
                if (!in_array($label, array_map('strtolower', $groupLabels), true)) {
                    continue;
                }

                $group['sub_menu'] = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
                if (!in_array($menuLabel, $group['sub_menu'], true)) {
                    $group['sub_menu'][] = $menuLabel;
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

    private function removeMenuFromGroups(array $groupLabels, string $menuLabel): void
    {
        DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) use ($groupLabels, $menuLabel) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $changed = false;
            foreach ($submenu as &$group) {
                $label = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''))));
                if (!in_array($label, array_map('strtolower', $groupLabels), true)) {
                    continue;
                }

                if (!is_array($group['sub_menu'] ?? null)) {
                    continue;
                }

                $filtered = array_values(array_filter($group['sub_menu'], fn ($item) => $item !== $menuLabel));
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

    private function copyAccessFromReference(string $referenceMenu, string $newMenu): void
    {
        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $column = $table === 'template_akses' ? 'akses' : 'akses';
            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column, $referenceMenu, $newMenu) {
                $access = json_decode($row->{$column} ?? '[]', true);
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
                $access[] = $clone;

                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
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
