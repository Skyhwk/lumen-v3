<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserAssessmentConfigToPersonnelRequestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel_requests', 'use_user_assessment')) {
                $table->boolean('use_user_assessment')->default(false)->after('max_salary');
            }
            if (!Schema::hasColumn('personnel_requests', 'user_assessment_question_count')) {
                $table->unsignedInteger('user_assessment_question_count')->nullable()->after('use_user_assessment');
            }
            if (!Schema::hasColumn('personnel_requests', 'user_assessment_has_time_limit')) {
                $table->boolean('user_assessment_has_time_limit')->default(false)->after('user_assessment_question_count');
            }
            if (!Schema::hasColumn('personnel_requests', 'user_assessment_duration_minutes')) {
                $table->unsignedInteger('user_assessment_duration_minutes')->nullable()->after('user_assessment_has_time_limit');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            foreach ([
                'user_assessment_duration_minutes',
                'user_assessment_has_time_limit',
                'user_assessment_question_count',
                'use_user_assessment',
            ] as $column) {
                if (Schema::hasColumn('personnel_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
