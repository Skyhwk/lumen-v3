<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEvaluationTargetToAssessmentInternalSessions extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('assessment_internal_sessions')) {
            return;
        }

        Schema::table('assessment_internal_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('assessment_internal_sessions', 'evaluation_target_karyawan_id')) {
                $table->unsignedBigInteger('evaluation_target_karyawan_id')->nullable()->after('question_category_id');
            }
            if (!Schema::hasColumn('assessment_internal_sessions', 'evaluation_target_name')) {
                $table->string('evaluation_target_name', 150)->nullable()->after('evaluation_target_karyawan_id');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('assessment_internal_sessions')) {
            return;
        }

        Schema::table('assessment_internal_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_internal_sessions', 'evaluation_target_name')) {
                $table->dropColumn('evaluation_target_name');
            }
            if (Schema::hasColumn('assessment_internal_sessions', 'evaluation_target_karyawan_id')) {
                $table->dropColumn('evaluation_target_karyawan_id');
            }
        });
    }
}
