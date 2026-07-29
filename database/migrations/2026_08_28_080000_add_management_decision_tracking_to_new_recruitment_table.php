<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManagementDecisionTrackingToNewRecruitmentTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'is_keep')) {
                $table->boolean('is_keep')->default(false);
            }
            if (!Schema::hasColumn('new_recruitment', 'rejected_decision')) {
                $table->boolean('rejected_decision')->default(false);
            }
            if (!Schema::hasColumn('new_recruitment', 'rejected_decision_reason')) {
                $table->text('rejected_decision_reason')->nullable();
            }
            if (!Schema::hasColumn('new_recruitment', 'rejected_salary')) {
                $table->boolean('rejected_salary')->default(false);
            }
            if (!Schema::hasColumn('new_recruitment', 'rejected_salary_reason')) {
                $table->text('rejected_salary_reason')->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            foreach ([
                'is_keep',
                'rejected_decision',
                'rejected_decision_reason',
                'rejected_salary',
                'rejected_salary_reason',
            ] as $column) {
                if (Schema::hasColumn('new_recruitment', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
