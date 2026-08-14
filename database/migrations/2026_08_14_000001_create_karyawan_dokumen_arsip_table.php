<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKaryawanDokumenArsipTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('karyawan_dokumen_arsip')) {
            return;
        }

        Schema::create('karyawan_dokumen_arsip', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id');
            $table->string('jenis_dokumen', 100);
            $table->string('nama_file', 255);
            $table->text('path_file');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('ukuran_file')->nullable();
            $table->string('sumber', 50)->default('upload_manual');
            $table->text('catatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index('karyawan_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('karyawan_dokumen_arsip');
    }
}
