<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiMatchingReasonToNewRecruitmentTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'ai_matching_reason')) {
                $table->text('ai_matching_reason')->nullable()->after('nilai_kecocokan');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (Schema::hasColumn('new_recruitment', 'ai_matching_reason')) {
                $table->dropColumn('ai_matching_reason');
            }
        });
    }
}
