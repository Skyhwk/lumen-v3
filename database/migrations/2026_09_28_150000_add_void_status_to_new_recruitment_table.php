<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddVoidStatusToNewRecruitmentTable extends Migration
{
    private const STATUS_VALUES = [
        'assessment',
        'screening',
        'approved',
        'interview_hrd',
        'profile_completion',
        'interview_user',
        'management_decision',
        'internal_sallary_offer',
        'salary_offer',
        'hired',
        'rejected',
        'finance_review',
        'training',
        'void',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('new_recruitment') || !Schema::hasColumn('new_recruitment', 'status')) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM new_recruitment WHERE Field = 'status'");
        $type = (string) ($column->Type ?? '');

        if (stripos($type, "'void'") !== false) {
            return;
        }

        if (stripos($type, 'enum(') === false) {
            return;
        }

        $this->alterStatusEnum(self::STATUS_VALUES, (string) ($column->Default ?? 'assessment'));
    }

    public function down(): void
    {
        if (!Schema::hasTable('new_recruitment') || !Schema::hasColumn('new_recruitment', 'status')) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM new_recruitment WHERE Field = 'status'");
        $type = (string) ($column->Type ?? '');

        if (stripos($type, "'void'") === false) {
            return;
        }

        DB::table('new_recruitment')
            ->where('status', 'void')
            ->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);

        $valuesWithoutVoid = array_values(array_filter(
            self::STATUS_VALUES,
            function ($value) {
                return $value !== 'void';
            }
        ));

        $this->alterStatusEnum($valuesWithoutVoid, (string) ($column->Default ?? 'assessment'));
    }

    private function alterStatusEnum(array $values, string $default): void
    {
        $escaped = array_map(function ($value) {
            return str_replace("'", "\\'", $value);
        }, $values);

        $enum = implode("','", $escaped);
        $default = str_replace("'", "\\'", $default);

        DB::statement("ALTER TABLE new_recruitment MODIFY status ENUM('{$enum}') NOT NULL DEFAULT '{$default}'");
    }
}
