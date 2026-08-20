<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIsActiveToNewRecruitmentTable extends Migration
{
    public function up()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
        });

        if (Schema::hasColumn('new_recruitment', 'is_active')) {
            DB::table('new_recruitment')
                ->where('status', 'void')
                ->update(['is_active' => false]);
        }
    }

    public function down()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            if (Schema::hasColumn('new_recruitment', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
}
