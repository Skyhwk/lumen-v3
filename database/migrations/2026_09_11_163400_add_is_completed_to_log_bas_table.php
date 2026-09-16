<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsCompletedToLogBasTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('log_bas')) {
            return;
        }

        Schema::table('log_bas', function (Blueprint $table) {
            if (!Schema::hasColumn('log_bas', 'is_completed')) {
                $table->tinyInteger('is_completed')->default(0)->after('no_sampel');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('log_bas')) {
            return;
        }

        Schema::table('log_bas', function (Blueprint $table) {
            if (Schema::hasColumn('log_bas', 'is_completed')) {
                $table->dropColumn('is_completed');
            }
        });
    }
}
