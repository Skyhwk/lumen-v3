<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('laporan_summary')) {
            Schema::create('laporan_summary', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('no_dokumen', 9);
                $table->unsignedBigInteger('karyawan_id');
                $table->string('jenis', 16);
                $table->date('tanggal');
                $table->longText('isi')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->unique('no_dokumen', 'uq_laporan_summary_no_dokumen');
                $table->unique(['karyawan_id', 'jenis', 'tanggal'], 'uq_laporan_summary_pemilik_periode');
            });
        }

        if (!Schema::hasTable('laporan_summary_sumber')) {
            Schema::create('laporan_summary_sumber', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('summary_id');
                $table->string('sumber_tipe', 32);
                $table->unsignedBigInteger('sumber_id');
                $table->unsignedBigInteger('karyawan_id')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->index('summary_id', 'idx_laporan_summary_sumber_summary');
                $table->unique(['summary_id', 'sumber_tipe', 'sumber_id'], 'uq_laporan_summary_sumber');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_summary_sumber');
        Schema::dropIfExists('laporan_summary');
    }
};
