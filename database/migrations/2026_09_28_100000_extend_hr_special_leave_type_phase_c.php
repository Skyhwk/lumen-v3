<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendHrSpecialLeaveTypePhaseC extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('hr_special_leave_type')) {
            return;
        }

        Schema::table('hr_special_leave_type', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_special_leave_type', 'duration_unit')) {
                $table->string('duration_unit', 16)->default('weekday')->after('duration');
            }
            if (!Schema::hasColumn('hr_special_leave_type', 'max_uses')) {
                $table->unsignedSmallInteger('max_uses')->nullable()->after('duration_unit');
            }
            if (!Schema::hasColumn('hr_special_leave_type', 'requires_attachment')) {
                $table->boolean('requires_attachment')->default(false)->after('max_uses');
            }
            if (!Schema::hasColumn('hr_special_leave_type', 'code')) {
                $table->string('code', 64)->nullable()->after('name');
                $table->unique('code');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('hr_special_leave_type')) {
            return;
        }

        Schema::table('hr_special_leave_type', function (Blueprint $table) {
            if (Schema::hasColumn('hr_special_leave_type', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('hr_special_leave_type', 'requires_attachment')) {
                $table->dropColumn('requires_attachment');
            }
            if (Schema::hasColumn('hr_special_leave_type', 'max_uses')) {
                $table->dropColumn('max_uses');
            }
            if (Schema::hasColumn('hr_special_leave_type', 'duration_unit')) {
                $table->dropColumn('duration_unit');
            }
        });
    }
}
