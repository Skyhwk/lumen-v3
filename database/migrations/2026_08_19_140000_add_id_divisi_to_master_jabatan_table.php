<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdDivisiToMasterJabatanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('master_jabatan') && !Schema::hasColumn('master_jabatan', 'id_divisi')) {
            Schema::table('master_jabatan', function (Blueprint $table) {
                $table->unsignedInteger('id_divisi')->nullable()->after('nama_jabatan');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('master_jabatan') && Schema::hasColumn('master_jabatan', 'id_divisi')) {
            Schema::table('master_jabatan', function (Blueprint $table) {
                $table->dropColumn('id_divisi');
            });
        }
    }
}
