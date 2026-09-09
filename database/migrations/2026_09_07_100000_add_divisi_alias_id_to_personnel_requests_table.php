<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDivisiAliasIdToPersonnelRequestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('personnel_requests') || Schema::hasColumn('personnel_requests', 'divisi_alias_id')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('divisi_alias_id')->nullable()->after('divisi_alias');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('personnel_requests') || !Schema::hasColumn('personnel_requests', 'divisi_alias_id')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->dropColumn('divisi_alias_id');
        });
    }
}
