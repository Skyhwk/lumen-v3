<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIsRejectedKandidatToNewRecruitmentTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('new_recruitment', 'is_rejected_hrd')
            && !Schema::hasColumn('new_recruitment', 'is_rejected_kandidat')) {
            DB::statement('ALTER TABLE new_recruitment CHANGE is_rejected_hrd is_rejected_kandidat TINYINT(1) NOT NULL DEFAULT 0');
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'is_rejected_kandidat')) {
                $table->boolean('is_rejected_kandidat')->default(false)->after('is_approved_interview_hrd');
            }
        });

        if (!Schema::hasColumn('new_recruitment', 'is_rejected_kandidat')) {
            return;
        }

        DB::table('new_recruitment')
            ->where('status', 'rejected')
            ->where(function ($query) {
                $query->whereNotNull('rejected_by')
                    ->where('rejected_by', '!=', '');
            })
            ->update(['is_rejected_kandidat' => true]);

        if (Schema::hasColumn('new_recruitment', 'reject_interview_user_at')) {
            DB::table('new_recruitment')
                ->whereNotNull('reject_interview_user_at')
                ->update(['is_rejected_kandidat' => true]);
        }

        DB::table('new_recruitment')
            ->where('meta_history', 'like', '%hrd_final_decision_rejected%')
            ->update(['is_rejected_kandidat' => true]);
    }

    public function down()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            if (Schema::hasColumn('new_recruitment', 'is_rejected_kandidat')) {
                $table->dropColumn('is_rejected_kandidat');
            }
        });
    }
}
