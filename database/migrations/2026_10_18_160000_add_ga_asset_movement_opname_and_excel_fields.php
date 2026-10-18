<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_ITEMS = ['Rekap Jumlah', 'Stock Opname'];

    public function up(): void
    {
        if (Schema::hasTable('ga_assets')) {
            Schema::table('ga_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('ga_assets', 'accurate_sync_flag')) {
                    $table->string('accurate_sync_flag', 8)->nullable();
                }
                if (!Schema::hasColumn('ga_assets', 'condition_text')) {
                    $table->string('condition_text', 191)->nullable();
                }
                if (!Schema::hasColumn('ga_assets', 'damage_received_date')) {
                    $table->date('damage_received_date')->nullable();
                }
            });
        }

        if (!Schema::hasTable('ga_asset_movements')) {
            Schema::create('ga_asset_movements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asset_id');
                $table->string('movement_kind', 16);
                $table->unsignedBigInteger('from_branch_id')->nullable();
                $table->unsignedBigInteger('to_branch_id')->nullable();
                $table->unsignedBigInteger('from_location_id')->nullable();
                $table->unsignedBigInteger('to_location_id')->nullable();
                $table->unsignedBigInteger('from_room_id')->nullable();
                $table->unsignedBigInteger('to_room_id')->nullable();
                $table->string('note', 500)->nullable();
                $table->string('source', 16)->default('manual');
                $table->string('created_by', 191)->nullable();
                $table->dateTime('moved_at');
                $table->timestamps();
                $table->index(['asset_id', 'moved_at']);
            });
        }

        if (!Schema::hasTable('ga_asset_opnames')) {
            Schema::create('ga_asset_opnames', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('branch_id');
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->string('status', 16)->default('open');
                $table->string('opened_by', 191)->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->timestamps();
                $table->unique(['branch_id', 'period_year', 'period_month'], 'ga_asset_opname_period_unique');
            });
        }

        if (!Schema::hasTable('ga_asset_opname_lines')) {
            Schema::create('ga_asset_opname_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('opname_id');
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('asset_id')->nullable();
                $table->string('scanned_code', 80);
                $table->string('result', 16);
                $table->string('scanned_by', 191)->nullable();
                $table->dateTime('scanned_at');
                $table->timestamps();
                $table->unique(['opname_id', 'scanned_code'], 'ga_asset_opname_scan_unique');
                $table->index(['opname_id', 'result']);
            });
        }

        $this->appendFinanceMenus();
    }

    public function down(): void
    {
        $this->removeFinanceMenus();
        Schema::dropIfExists('ga_asset_opname_lines');
        Schema::dropIfExists('ga_asset_opnames');
        Schema::dropIfExists('ga_asset_movements');

        if (Schema::hasTable('ga_assets')) {
            Schema::table('ga_assets', function (Blueprint $table) {
                foreach (['damage_received_date', 'condition_text', 'accurate_sync_flag'] as $column) {
                    if (Schema::hasColumn('ga_assets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
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
                foreach (self::MENU_ITEMS as $menuItem) {
                    $exists = collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === $menuItem);
                    if ($exists) {
                        continue;
                    }
                    $reference = collect($access)->first(function ($item) {
                        return is_array($item)
                            && ($item['name'] ?? null) === 'Daftar Aset'
                            && ($item['parent'] ?? null) === 'Finance/Aset';
                    });
                    if (!$reference) {
                        continue;
                    }
                    $clone = $reference;
                    $clone['name'] = $menuItem;
                    $clone['parent'] = 'Finance/Aset';
                    $access[] = $clone;
                    $changed = true;
                }
                if ($changed) {
                    DB::table($table)->where('id', $row->id)->update([
                        'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
        }
    }

    private function removeFinanceMenus(): void
    {
        if (Schema::hasTable('menu')) {
            DB::table('menu')->whereRaw('LOWER(menu) = ?', ['finance'])->orderBy('id')->each(function ($row) {
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

        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->orderBy('id')->each(function ($row) use ($table) {
                $access = json_decode($row->akses ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }
                $filtered = array_values(array_filter(
                    $access,
                    fn ($item) => !is_array($item) || !in_array($item['name'] ?? null, self::MENU_ITEMS, true)
                ));
                if (count($filtered) === count($access)) {
                    return;
                }
                DB::table($table)->where('id', $row->id)->update([
                    'akses' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }
};
