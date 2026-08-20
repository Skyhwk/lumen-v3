<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImpersonatorUserIdToUserTokenTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('user_token')) {
            return;
        }

        Schema::table('user_token', function (Blueprint $table) {
            if (!Schema::hasColumn('user_token', 'impersonator_user_id')) {
                $table->unsignedBigInteger('impersonator_user_id')->nullable()->after('is_impersonate');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('user_token')) {
            return;
        }

        Schema::table('user_token', function (Blueprint $table) {
            if (Schema::hasColumn('user_token', 'impersonator_user_id')) {
                $table->dropColumn('impersonator_user_id');
            }
        });
    }
}
