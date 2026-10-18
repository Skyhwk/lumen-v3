<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROOT_MENU = 'Finance';

    private const GROUP_NAME = 'Aset';

    private const MENU_ITEM = 'Laporan Depresiasi';

    public function up(): void
    {
        if (Schema::hasTable('ga_assets')) {
            Schema::table('ga_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('ga_assets', 'acquisition_cost')) {
                    $table->decimal('acquisition_cost', 18, 2)->nullable();
                }
                if (!Schema::hasColumn('ga_assets', 'useful_life_years')) {
                    $table->unsignedSmallInteger('useful_life_years')->nullable();
                }
            });
        }

        if (!Schema::hasTable('ga_asset_useful_life_rules')) {
            Schema::create('ga_asset_useful_life_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('source', 16);
                $table->string('category_name', 191);
                $table->string('sub_category_name', 191)->default('');
                $table->unsignedSmallInteger('useful_life_years');
                $table->timestamps();
                $table->unique(['source', 'category_name', 'sub_category_name'], 'ga_asset_life_rule_unique');
            });
        }

        if (!Schema::hasTable('ga_asset_depreciation_yearly')) {
            Schema::create('ga_asset_depreciation_yearly', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asset_id');
                $table->unsignedSmallInteger('fiscal_year');
                $table->decimal('acquisition_cost', 18, 2);
                $table->unsignedSmallInteger('useful_life_years');
                $table->string('useful_life_source', 16);
                $table->decimal('annual_depreciation', 18, 2);
                $table->decimal('accumulated_depreciation', 18, 2);
                $table->decimal('book_value', 18, 2);
                $table->timestamps();
                $table->unique(['asset_id', 'fiscal_year'], 'ga_asset_depr_year_unique');
                $table->index('fiscal_year');
            });
        }

        $this->seedUsefulLifeRules();
        $this->appendMenuItem();
    }

    public function down(): void
    {
        $this->removeMenuItem();
        Schema::dropIfExists('ga_asset_depreciation_yearly');
        Schema::dropIfExists('ga_asset_useful_life_rules');

        if (Schema::hasTable('ga_assets')) {
            Schema::table('ga_assets', function (Blueprint $table) {
                if (Schema::hasColumn('ga_assets', 'useful_life_years')) {
                    $table->dropColumn('useful_life_years');
                }
                if (Schema::hasColumn('ga_assets', 'acquisition_cost')) {
                    $table->dropColumn('acquisition_cost');
                }
            });
        }
    }

    private function seedUsefulLifeRules(): void
    {
        if (!Schema::hasTable('ga_asset_useful_life_rules')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [
            ['source' => 'category', 'category_name' => 'KENDARAAN', 'sub_category_name' => '', 'useful_life_years' => 7],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'SAMPLING', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'LAB', 'useful_life_years' => 7],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'KANTOR', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'NETWORK', 'useful_life_years' => 4],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'LAINNYA', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'FURNITURE', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'HOUSEKEEPING', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'KANTOR', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'LAB', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'SAMPLING', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'LAINNYA', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT ANGKUTAN DARAT BERMOTOR', 'sub_category_name' => 'KENDARAAN', 'useful_life_years' => 7],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT UKUR', 'sub_category_name' => 'INSTRUMENT', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT KANTOR', 'sub_category_name' => 'FURNITURE', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT RUMAH TANGGA', 'sub_category_name' => 'FURNITURE', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT LABORATORIUM LINGKUNGAN HIDUP', 'sub_category_name' => 'LAB', 'useful_life_years' => 7],
            ['source' => 'kemenkeu', 'category_name' => 'KOMPUTER UNIT', 'sub_category_name' => 'NETWORK', 'useful_life_years' => 4],
            ['source' => 'kemenkeu', 'category_name' => 'PERALATAN KOMPUTER', 'sub_category_name' => 'NETWORK', 'useful_life_years' => 4],
        ];

        foreach ($rows as $row) {
            DB::table('ga_asset_useful_life_rules')->updateOrInsert(
                [
                    'source' => $row['source'],
                    'category_name' => $row['category_name'],
                    'sub_category_name' => $row['sub_category_name'],
                ],
                [
                    'useful_life_years' => $row['useful_life_years'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function appendMenuItem(): void
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

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

        foreach (['Daftar Aset', 'Kategori Asset'] as $referenceMenu) {
            $this->copyAccessFromReference($referenceMenu);
        }
    }

    private function copyAccessFromReference(string $referenceMenu): bool
    {
        $copied = false;

        foreach (['akses_menu', 'template_akses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $referenceMenu, &$copied) {
                $access = json_decode($row->akses ?? '[]', true);
                if (!is_array($access)) {
                    return;
                }

                if (collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === self::MENU_ITEM)) {
                    $copied = true;
                    return;
                }

                $reference = collect($access)->first(fn ($item) => is_array($item) && ($item['name'] ?? null) === $referenceMenu);
                if (!$reference) {
                    return;
                }

                $clone = $reference;
                $clone['name'] = self::MENU_ITEM;
                $clone['parent'] = self::ROOT_MENU . '/' . self::GROUP_NAME;
                $access[] = $clone;
                $copied = true;

                DB::table($table)->where('id', $row->id)->update([
                    'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
                ]);
            });
        }

        return $copied;
    }

    private function removeMenuItem(): void
    {
        if (Schema::hasTable('menu')) {
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
                        $filtered = array_values(array_filter($items, fn ($item) => $item !== self::MENU_ITEM));
                        $submenu[$idx]['sub_menu'] = $filtered;
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('menu')->where('id', $row->id)->update([
                            'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
                        ]);
                    }
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
                    fn ($item) => !is_array($item) || ($item['name'] ?? null) !== self::MENU_ITEM
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
