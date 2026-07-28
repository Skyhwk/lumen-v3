<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTanggalDisusunToLimsDocumentsTable extends Migration
{
    public function up()
    {
        Schema::table('lims_documents', function (Blueprint $table) {
            $table->date('tanggal_disusun')->nullable()->after('jabatan_penyusun');
        });
    }

    public function down()
    {
        Schema::table('lims_documents', function (Blueprint $table) {
            $table->dropColumn('tanggal_disusun');
        });
    }
}
