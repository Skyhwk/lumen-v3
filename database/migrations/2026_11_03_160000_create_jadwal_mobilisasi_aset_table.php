<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_GROUP = 'Preparasi Sampling';
    private const MENU_ITEM = 'Monitoring Alat';
    private const MENU_PARENT = 'Sampling/Preparasi Sampling';
    private const REFERENCE = 'Jadwal Tim';

    public function up(): void
    {
        if (!Schema::hasTable('jadwal_mobilisasi_aset')) {
            Schema::create('jadwal_mobilisasi_aset', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_mobilisasi')->index();
                $table->unsignedBigInteger('id_aset')->index();
                $table->string('nama_alat')->nullable();
                $table->unsignedInteger('kategori_aset_id')->nullable();
                $table->unsignedInteger('sub_kategori_aset_id')->nullable();
                $table->string('created_by', 150)->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['id_mobilisasi', 'is_active']);
            });
        }

        $this->addMenu();
        $this->copyAccess('akses_menu', 'akses');
        $this->copyAccess('template_akses', 'akses');
    }

    public function down(): void
    {
        $this->removeAccess('akses_menu', 'akses');
        $this->removeAccess('template_akses', 'akses');
        $this->removeMenu();
        Schema::dropIfExists('jadwal_mobilisasi_aset');
    }

    private function addMenu(): void
    {
        if (!Schema::hasTable('menu') || !Schema::hasColumn('menu', 'submenu')) {
            return;
        }

        DB::table('menu')->where('is_active', 1)->where('menu', 'Sampling')->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $changed = false;
            foreach ($submenu as &$group) {
                $label = trim((string) ($group['nama_inden_menu'] ?? ''));
                if (strcasecmp($label, self::MENU_GROUP) !== 0) {
                    continue;
                }

                $items = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
                if (!in_array(self::MENU_ITEM, $items, true)) {
                    $items[] = self::MENU_ITEM;
                    $group['sub_menu'] = array_values($items);
                    $changed = true;
                }
                unset($group);

                if ($changed) {
                    DB::table('menu')->where('id', $row->id)->update([
                        'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
                    ]);
                }
                return;
            }
            unset($group);

            $insertAt = 1;
            foreach ($submenu as $index => $group) {
                if (strcasecmp(trim((string) ($group['nama_inden_menu'] ?? '')), 'Jadwal') === 0) {
                    $insertAt = $index + 1;
                    break;
                }
            }

            array_splice($submenu, $insertAt, 0, [[
                'nama_inden_menu' => self::MENU_GROUP,
                'sub_menu' => [self::MENU_ITEM],
            ]]);

            DB::table('menu')->where('id', $row->id)->update([
                'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    private function removeMenu(): void
    {
        if (!Schema::hasTable('menu') || !Schema::hasColumn('menu', 'submenu')) {
            return;
        }

        DB::table('menu')->where('menu', 'Sampling')->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $submenu = array_values(array_filter($submenu, function ($group) {
                return strcasecmp(trim((string) ($group['nama_inden_menu'] ?? '')), self::MENU_GROUP) !== 0;
            }));

            DB::table('menu')->where('id', $row->id)->update([
                'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    private function copyAccess(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access) || collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::MENU_ITEM && ($item['parent'] ?? null) === self::MENU_PARENT)) {
                return;
            }

            $reference = collect($access)->first(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::REFERENCE);
            if (!$reference) {
                return;
            }

            $reference['name'] = self::MENU_ITEM;
            $reference['parent'] = self::MENU_PARENT;
            $access[] = $reference;
            DB::table($table)->where('id', $row->id)->update([
                $column => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    private function removeAccess(string $table, string $column): void
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
                    fn ($item) => !is_array($item)
                        || ($item['name'] ?? null) !== self::MENU_ITEM
                        || ($item['parent'] ?? null) !== self::MENU_PARENT
                )), JSON_UNESCAPED_UNICODE),
            ]);
        });
    }
};
