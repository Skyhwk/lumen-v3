<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendHrApprovalStepChain extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('hr_approval_step')) {
            return;
        }

        Schema::table('hr_approval_step', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_approval_step', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(1)->after('step');
            }
            if (!Schema::hasColumn('hr_approval_step', 'expected_karyawan_id')) {
                $table->unsignedInteger('expected_karyawan_id')->nullable()->after('sort_order');
                $table->index(['expected_karyawan_id', 'state'], 'hr_approval_step_expected_state');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('hr_approval_step')) {
            return;
        }

        Schema::table('hr_approval_step', function (Blueprint $table) {
            if (Schema::hasColumn('hr_approval_step', 'expected_karyawan_id')) {
                $table->dropIndex('hr_approval_step_expected_state');
                $table->dropColumn('expected_karyawan_id');
            }
            if (Schema::hasColumn('hr_approval_step', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
}
