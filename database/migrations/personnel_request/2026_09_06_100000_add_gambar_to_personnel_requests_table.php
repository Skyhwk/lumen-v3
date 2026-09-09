<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGambarToPersonnelRequestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel_requests', 'gambar')) {
                $table->string('gambar', 500)->nullable()->after('requirement');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            if (Schema::hasColumn('personnel_requests', 'gambar')) {
                $table->dropColumn('gambar');
            }
        });
    }
}
