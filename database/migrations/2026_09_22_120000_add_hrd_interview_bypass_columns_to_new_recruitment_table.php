<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHrdInterviewBypassColumnsToNewRecruitmentTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'alasan_bypass')) {
                $table->text('alasan_bypass')->nullable()->after('approved_interview_hrd_at');
            }
            if (!Schema::hasColumn('new_recruitment', 'bypass_by')) {
                $table->string('bypass_by', 255)->nullable()->after('alasan_bypass');
            }
            if (!Schema::hasColumn('new_recruitment', 'bypass_at')) {
                $table->dateTime('bypass_at')->nullable()->after('bypass_by');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            foreach (['bypass_at', 'bypass_by', 'alasan_bypass'] as $column) {
                if (Schema::hasColumn('new_recruitment', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
