<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTicketSkK3Menu extends Migration
{
    private const MENU = 'Ticket SK K3';
    private const PARENT = 'Request';
    private const REFERENCE = 'Ticket Programming';
    private const PATH = '/request/ticket-sk-k3';
    private const SALES_DEPT = 9;
    private const K3_DEPT = 19;
    private const IT_DEPT = 7;

    public function up()
    {
        $this->insertMenuItem();
        $this->grantAccess('akses_menu', 'akses');
        $this->grantAccess('template_akses', 'akses');
    }

    public function down()
    {
        $this->removeMenuItem();
        $this->removeAccess('akses_menu', 'akses');
        $this->removeAccess('template_akses', 'akses');
    }

    private function insertMenuItem()
    {
        if (!Schema::hasTable('menu') || !Schema::hasColumn('menu', 'submenu')) {
            return;
        }

        DB::table('menu')->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $exists = collect($submenu)->contains(function ($item) {
                return is_array($item) && ($item['nama_inden_menu'] ?? $item['name'] ?? null) === self::MENU;
            });
            if ($exists) {
                return;
            }

            $parentName = strtolower(trim((string) ($row->menu ?? '')));
            $isRequestMenu = $parentName === strtolower(self::PARENT) || (int) $row->id === 105;
            if (!$isRequestMenu) {
                return;
            }

            $insertIndex = null;
            foreach ($submenu as $index => $item) {
                $label = $item['nama_inden_menu'] ?? $item['name'] ?? '';
                if ($label === self::REFERENCE) {
                    $insertIndex = $index + 1;
                    break;
                }
            }

            $newItem = [
                'nama_inden_menu' => self::MENU,
                'sub_menu' => null,
            ];

            if ($insertIndex === null) {
                $submenu[] = $newItem;
            } else {
                array_splice($submenu, $insertIndex, 0, [$newItem]);
            }

            DB::table('menu')->where('id', $row->id)->update([
                'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    private function removeMenuItem()
    {
        if (!Schema::hasTable('menu') || !Schema::hasColumn('menu', 'submenu')) {
            return;
        }

        DB::table('menu')->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $filtered = array_values(array_filter($submenu, function ($item) {
                return !is_array($item) || ($item['nama_inden_menu'] ?? $item['name'] ?? null) !== self::MENU;
            }));

            if (count($filtered) !== count($submenu)) {
                DB::table('menu')->where('id', $row->id)->update([
                    'submenu' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    private function grantAccess(string $table, string $column)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $targetUserIds = [];
        if ($table === 'akses_menu' && Schema::hasTable('master_karyawan')) {
            $targetUserIds = DB::table('master_karyawan')
                ->whereIn('id_department', [self::SALES_DEPT, self::K3_DEPT, self::IT_DEPT])
                ->where('is_active', 1)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column, $targetUserIds) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access)) {
                return;
            }

            if (collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::MENU)) {
                return;
            }

            $shouldGrant = $table !== 'akses_menu' || in_array((int) ($row->user_id ?? 0), $targetUserIds, true);
            $reference = collect($access)->first(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::REFERENCE);

            if ($table === 'template_akses') {
                $shouldGrant = (bool) $reference || collect($access)->contains(function ($item) {
                    return is_array($item) && (($item['parent'] ?? null) === self::PARENT);
                });
            }

            if (!$shouldGrant && !$reference) {
                return;
            }

            if (!$shouldGrant) {
                return;
            }

            $newItem = $reference ? $reference : [
                'name' => self::MENU,
                'parent' => self::PARENT,
                'path' => self::PATH,
                'access' => ['view', 'create', 'update', 'delete', 'approve', 'reject'],
            ];
            $newItem['name'] = self::MENU;
            $newItem['parent'] = $newItem['parent'] ?? self::PARENT;
            $newItem['path'] = self::PATH;
            if (!isset($newItem['access']) || !is_array($newItem['access'])) {
                $newItem['access'] = ['view', 'create', 'update', 'delete', 'approve', 'reject'];
            }

            $access[] = $newItem;

            DB::table($table)->where('id', $row->id)->update([
                $column => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    private function removeAccess(string $table, string $column)
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
}
