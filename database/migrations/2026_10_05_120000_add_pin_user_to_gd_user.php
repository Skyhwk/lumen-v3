<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPinUserToGdUser extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('gd_user')) {
            return;
        }

        if (!Schema::hasColumn('gd_user', 'pin_user')) {
            Schema::table('gd_user', function (Blueprint $table) {
                $table->string('pin_user', 255)->nullable()->after('password');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('gd_user') && Schema::hasColumn('gd_user', 'pin_user')) {
            Schema::table('gd_user', function (Blueprint $table) {
                $table->dropColumn('pin_user');
            });
        }
    }
}
