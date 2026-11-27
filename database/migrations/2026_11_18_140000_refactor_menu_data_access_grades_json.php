<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menu_data_access')) {
            return;
        }

        // Sudah ter-refactor
        if (Schema::hasColumn('menu_data_access', 'grades') && !Schema::hasColumn('menu_data_access', 'grade')) {
            $this->ensureMenuKeyUnique();
            return;
        }

        if (!Schema::hasColumn('menu_data_access', 'grades')) {
            Schema::table('menu_data_access', function (Blueprint $table) {
                $table->json('grades')->nullable()->after('menu_key');
            });
        }

        if (Schema::hasColumn('menu_data_access', 'grade')) {
            $groups = DB::table('menu_data_access')
                ->orderBy('id')
                ->get()
                ->groupBy(function ($row) {
                    return strtolower(str_replace('_', '-', trim((string) $row->menu_key)));
                });

            foreach ($groups as $menuKey => $rows) {
                $grades = [];
                $keep = null;

                foreach ($rows as $row) {
                    $grade = strtoupper(trim((string) ($row->grade ?? '')));
                    if ($grade !== '' && !in_array($grade, $grades, true)) {
                        $grades[] = $grade;
                    }

                    if ($keep === null) {
                        $keep = $row;
                        continue;
                    }

                    // Prioritas row aktif
                    if ((int) $row->is_active === 1 && (int) $keep->is_active !== 1) {
                        $keep = $row;
                    }
                }

                if ($keep === null) {
                    continue;
                }

                DB::table('menu_data_access')->where('id', $keep->id)->update([
                    'menu_key' => $menuKey,
                    'grades' => json_encode(array_values($grades)),
                    'is_active' => (int) ($keep->is_active ?? 1) === 1 ? 1 : 0,
                ]);

                DB::table('menu_data_access')
                    ->whereIn('id', $rows->pluck('id')->all())
                    ->where('id', '!=', $keep->id)
                    ->delete();
            }

            $this->dropIndexIfExists('menu_data_access', 'menu_data_access_menu_grade_uq');
            $this->dropIndexIfExists('menu_data_access', 'menu_data_access_grade_idx');

            Schema::table('menu_data_access', function (Blueprint $table) {
                $table->dropColumn('grade');
            });
        }

        $this->ensureMenuKeyUnique();
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu_data_access') || !Schema::hasColumn('menu_data_access', 'grades')) {
            return;
        }

        if (!Schema::hasColumn('menu_data_access', 'grade')) {
            Schema::table('menu_data_access', function (Blueprint $table) {
                $table->string('grade', 100)->nullable()->after('menu_key');
            });
        }

        $rows = DB::table('menu_data_access')->get();
        foreach ($rows as $row) {
            $grades = json_decode($row->grades ?? '[]', true);
            if (!is_array($grades) || $grades === []) {
                DB::table('menu_data_access')->where('id', $row->id)->update(['grade' => '']);
                continue;
            }

            $first = true;
            foreach ($grades as $grade) {
                $normalized = strtoupper(trim((string) $grade));
                if ($normalized === '') {
                    continue;
                }
                if ($first) {
                    DB::table('menu_data_access')->where('id', $row->id)->update(['grade' => $normalized]);
                    $first = false;
                    continue;
                }
                DB::table('menu_data_access')->insert([
                    'menu_key' => $row->menu_key,
                    'grade' => $normalized,
                    'grades' => null,
                    'is_active' => $row->is_active,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                    'deleted_by' => $row->deleted_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ]);
            }
        }

        $this->dropIndexIfExists('menu_data_access', 'menu_data_access_menu_key_uq');

        Schema::table('menu_data_access', function (Blueprint $table) {
            $table->dropColumn('grades');
        });

        $this->dropIndexIfExists('menu_data_access', 'menu_data_access_menu_grade_uq');
        Schema::table('menu_data_access', function (Blueprint $table) {
            $table->unique(['menu_key', 'grade'], 'menu_data_access_menu_grade_uq');
        });
    }

    private function ensureMenuKeyUnique(): void
    {
        $exists = collect(DB::select('SHOW INDEX FROM `menu_data_access` WHERE Key_name = ?', [
            'menu_data_access_menu_key_uq',
        ]))->isNotEmpty();

        if ($exists) {
            return;
        }

        Schema::table('menu_data_access', function (Blueprint $table) {
            $table->unique('menu_key', 'menu_data_access_menu_key_uq');
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [
            $indexName,
        ]))->isNotEmpty();

        if (!$exists) {
            return;
        }

        // Unique index juga bisa di-drop via DROP INDEX
        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }
};
